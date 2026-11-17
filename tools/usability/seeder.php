<?php
/**
 * WP-CLI-Befehl `wp ccap-ux …` für die Nutzertest-Umgebung (R1-29, R0-26 §3.3, §6).
 *
 * NUR FÜR TEST-SITES. Der Befehl verweigert die Arbeit, wenn die Konstante
 * `CCAP_UX_TESTSITE` in der wp-config.php fehlt (setzt `setup-site.sh`).
 * Er legt Konten mit Passwörtern an, erzeugt Lernstände und Störfälle und
 * darf nie auf einer Produktiv-Site laufen.
 *
 * Laden: `wp --require=tools/usability/seeder.php ccap-ux <befehl>` (dieses
 * WP-CLI hat kein `eval`/`eval-file`/`db`; `--require` ist ein globaler
 * Parameter von WP-CLI selbst).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tools\Usability;

use CourseCaptain\Access\ContentGate;
use CourseCaptain\Admin\SetupState;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Import\ImportService;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

require_once __DIR__ . '/isolate-autoload.php';
require_once __DIR__ . '/lib/Media.php';
require_once __DIR__ . '/lib/Builder.php';
require_once __DIR__ . '/lib/Scenarios.php';
require_once __DIR__ . '/lib/Kit.php';

/**
 * Nutzertest-Umgebung für Course Captain (nur Test-Sites).
 */
final class Command {

	public const OPTION = 'ccap_ux_seed';

	/**
	 * Legt Konten und den Startzustand eines Profils an. Passwörter werden
	 * neu erzeugt und nur einmal ausgegeben.
	 *
	 * ## OPTIONS
	 *
	 * [--profil=<profil>]
	 * : referenz (Lernende, Trainer, Gruppenverwaltung, AU-T13), autor (leere Site, Setup abgeschlossen) oder admin (Setup offen, nur RK-3).
	 * ---
	 * default: referenz
	 * options:
	 *   - referenz
	 *   - autor
	 *   - admin
	 * ---
	 *
	 * [--lernende=<anzahl>]
	 * : Anzahl frischer Lernendenkonten cc_fx_ux_p01 … (Profil referenz).
	 * ---
	 * default: 9
	 * ---
	 *
	 * [--kit=<verzeichnis>]
	 * : Autoren-Kit (Drehbücher, Soll-Dateien, Fragenpakete, Medien) hierhin schreiben (Profil referenz).
	 *
	 * @param array<int, string>    $args  Argumente.
	 * @param array<string, string> $assoc Optionen.
	 * @return void
	 */
	public function seed( array $args, array $assoc ): void {
		unset( $args );
		self::guard();
		if ( false !== get_option( self::OPTION ) ) {
			\WP_CLI::error( 'Diese Site ist schon befüllt. Zurücksetzen mit tools/usability/setup-site.sh (baut die Site neu auf).' );
		}
		$profil  = (string) ( $assoc['profil'] ?? 'referenz' );
		$content = require __DIR__ . '/content/rk-v1.php';
		$scen    = new Scenarios();
		$admin   = $scen->user( 'admin', 'administrator', 'Moderation, Störfälle, Admin-Aufgaben', 'Moderation (Admin)' );
		$started = microtime( true );
		$state   = array(
			'profil'  => $profil,
			'version' => 1,
			'courses' => array(),
		);
		$notes   = array();
		update_option( 'users_can_register', 0 );
		update_option( 'timezone_string', 'Europe/Berlin' );
		update_option( 'WPLANG', 'de_DE' );
		wp_set_current_user( $admin );

		if ( 'autor' === $profil ) {
			$scen->user( 'autor', 'ccap_course_manager', 'Autorensitzung AU-T01…T12 (Start ohne Kurs)', 'Autorin Test' );
			self::complete_setup( $admin );
		} elseif ( 'admin' === $profil ) {
			$builder = new Builder( $admin, $content );
			$builder->media();
			$state['courses']['rk3'] = $builder->course( 'rk3' );
			$scen->user( 'gv_kandidatin', 'subscriber', 'AD-T06: künftige Gruppenbesitzerin', 'Gruppenbesitzerin Kandidatin' );
			$notes = $builder->notes;
		} else {
			$state = $this->referenz( $scen, $admin, $content, max( 1, (int) ( $assoc['lernende'] ?? 9 ) ) ) + $state;
			$notes = $state['notes'];
			unset( $state['notes'] );
		}
		update_option( self::OPTION, $state, false );
		if ( 'referenz' === $profil && ! empty( $assoc['kit'] ) ) {
			$this->kit( array(), array( 'ziel' => (string) $assoc['kit'] ) );
		}
		self::print_result( $profil, $scen, $notes, microtime( true ) - $started );
	}

	/**
	 * Referenzstand für Lernende, Trainer, Gruppenverwaltung und AU-T13.
	 *
	 * @param Scenarios            $scen     Konten/Zustände.
	 * @param int                  $admin    Admin.
	 * @param array<string, mixed> $content  Paket.
	 * @param int                  $learners Anzahl frischer Konten.
	 * @return array<string, mixed>
	 */
	private function referenz( Scenarios $scen, int $admin, array $content, int $learners ): array {
		$builder = new Builder( $admin, $content );
		$builder->media();
		$maps = array();
		foreach ( array( 'rk1', 'rk2', 'rk3' ) as $key ) {
			$maps[ $key ] = $builder->course( $key );
		}
		$rk1 = $maps['rk1'];
		$rk2 = $maps['rk2'];
		$rk3 = $maps['rk3'];

		$manager = $scen->user( 'kursmanager', 'ccap_course_manager', 'AU-T10/T11/T13 im Referenzstand (bewertet, schreibt ein)', 'Kursmanagerin Test' );
		$trainer = $scen->user( 'trainer', 'ccap_trainer', 'Trainer aller drei Referenzkurse (Bewertungswarteschlange)', 'Trainer Test' );
		foreach ( $maps as $map ) {
			$scen->staff( $trainer, (int) $map['course'], 'trainer' );
		}
		unset( $manager );

		// Frische Lernendenkonten (R0-26 §1.3): RK-2 manuell freigeschaltet, RK-3 für Drip/Sperrgründe.
		for ( $i = 1; $i <= $learners; $i++ ) {
			$user = $scen->user( sprintf( 'p%02d', $i ), 'subscriber', 'Frisches Lernendenkonto P-LE-' . sprintf( '%02d', $i ) . ' (RK-1 ohne Zugang für LE-T01)', sprintf( 'Testperson %02d', $i ) );
			$scen->grant( $admin, $user, (int) $rk2['course'] );
			$scen->grant( $admin, $user, (int) $rk3['course'] );
		}

		$u = $scen->user( 'teilweise', 'subscriber', 'LE-T10: RK-1 begonnen, Lektionen 1–4 fertig, Lektion 5 offen', 'Lernende teilweise' );
		$scen->grant( $admin, $u, (int) $rk1['course'] );
		$scen->partial( $u, $rk1 );

		$u = $scen->user( 'pruefung', 'subscriber', 'Laufende Prüfung: RK-1-Abschlussquiz (ohne Zeitlimit) mit 10 Antworten und 2 Markierungen; RK-2 zeitnah per Szenario', 'Lernende mit laufender Prüfung' );
		$scen->grant( $admin, $u, (int) $rk1['course'] );
		$scen->grant( $admin, $u, (int) $rk2['course'] );
		$session          = $scen->exam_attempt( $u, (int) $rk1['rule'], null, 10, 2 );
		$scen->checks[]   = 'pruefung (RK-1): laufende Sitzung ' . $session;
		$running_rk1_user = $u;
		unset( $running_rk1_user );

		$u = $scen->user( 'abgelaufen', 'subscriber', 'UX-032: RK-2-Prüfung abgelaufen (automatisch ausgewertet)', 'Lernende Prüfung abgelaufen' );
		$scen->grant( $admin, $u, (int) $rk2['course'] );
		$scen->expired_attempt( $u, (int) $rk2['rule'], (int) $content['courses']['rk2']['exam']['time_limit'] );

		$u = $scen->user( 'ablauf', 'subscriber', 'UX-032 live: vor der Aufgabe `wp ccap-ux scenario ablauf` ausführen (1 min Restzeit)', 'Lernende kurz vor Ablauf' );
		$scen->grant( $admin, $u, (int) $rk2['course'] );

		$u = $scen->user( 'bestanden', 'subscriber', 'LE-T08b: RK-2 bestanden, Nachweis ausgestellt', 'Lernende bestanden' );
		$scen->grant( $admin, $u, (int) $rk2['course'] );
		$scen->passed_attempt( $admin, $u, (int) $rk2['rule'] );

		$u = $scen->user( 'faellig', 'subscriber', 'LE-T07: Lernhistorie RK-2 mit fälligen Wiederholungen', 'Lernende mit fälligen Fragen' );
		$scen->grant( $admin, $u, (int) $rk2['course'] );
		$scen->due_history( $u, array_values( array_filter( (array) $rk2['questions'][0], static fn( int $q ): bool => in_array( Builder::type_of( $q ), array( 'single_choice', 'multi_choice', 'true_false' ), true ) ) ) );

		$u = $scen->user( 'ausgleich', 'subscriber', 'UX-075: Zeitausgleich 150 % für RK-2', 'Lernende mit Zeitausgleich' );
		$scen->grant( $admin, $u, (int) $rk2['course'] );
		$scen->accommodation( $admin, $u, (int) $rk2['course'] );

		$pdf = Builder::upload_dir()['dir'] . '/abgabe-';
		$u   = $scen->user( 'rueckgabe', 'subscriber', 'LE-T12: RK-1-Aufgabe zurückgegeben mit Kommentar', 'Lernende mit Rückgabe' );
		$scen->grant( $admin, $u, (int) $rk1['course'] );
		$uid = $scen->submission( $u, (int) $rk1['course'], (int) $rk1['assignment'], self::text( 'rueckgabe' ), null );
		$scen->return_submission( $trainer, $uid );

		// Drei Abgaben je Kurs warten auf Bewertung (AU-T13): gut, unvollständig, mit Datei.
		$waiting = 0;
		ContentGate::set_now( time() + 2 * DAY_IN_SECONDS ); // RK-3-Aufgabe liegt in Modul 2 (Drip Tag 1).
		foreach ( array( 'gut', 'unvollstaendig', 'datei' ) as $n => $kind ) {
			$u = $scen->user( 'abgabe' . ( $n + 1 ), 'subscriber', 'AU-T13: eingereichte Abgabe „' . $kind . '“ in RK-1, RK-2 und RK-3', 'Abgabe ' . ( $n + 1 ) );
			foreach ( $maps as $key => $map ) {
				$scen->grant( $admin, $u, (int) $map['course'] );
				$type = (string) $content['courses'][ $key ]['assignment']['submission_type'];
				$text = 'file' === $type ? null : self::text( $kind );
				$file = 'text' === $type ? null : $pdf . $kind . '.pdf';
				$scen->submission( $u, (int) $map['course'], (int) $map['assignment'], $text, $file );
				++$waiting;
			}
		}
		ContentGate::set_now( null );
		$scen->checks[] = 'Abgaben eingereicht und unbewertet: ' . $waiting;

		$owner = $scen->user( 'gv', 'subscriber', 'GV-T01…T06: Besitzerin der RK-3-Gruppe (5 Plätze, 2 belegt)', 'Gruppenbesitzerin Test' );
		$team1 = $scen->user( 'team1', 'subscriber', 'Mitglied der RK-3-Gruppe', 'Teammitglied 1' );
		$team2 = $scen->user( 'team2', 'subscriber', 'Mitglied der RK-3-Gruppe', 'Teammitglied 2' );
		$scen->user( 'beitritt', 'subscriber', 'GV-T04/GV-T07: Konto der Moderation zum Beitreten', 'Beitritt Test' );
		$group         = $scen->group( $admin, $owner, (int) $rk3['course'], 5, array( $team1, $team2 ) );
		$maps['group'] = $group;

		// Katalogseite mit dem Block „Kurskatalog“ (Einstieg für LE-T01, sonst legt sie Setup-Schritt 2 an).
		$page            = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => 'kurse',
				'post_title'   => 'Kurse',
				'post_content' => '<!-- wp:course-captain/course-catalog /-->',
				'post_author'  => $admin,
			)
		);
		$maps['catalog'] = (int) $page;
		$scen->checks[]  = 'Katalogseite: ' . get_permalink( (int) $page );

		foreach ( array( 'rk1', 'rk2', 'rk3' ) as $key ) {
			$failed         = array_filter( Kit::compare( Kit::soll( (array) $content['courses'][ $key ] ), Kit::ist( (int) $maps[ $key ]['course'] ) ), static fn( array $r ): bool => ! $r[3] );
			$scen->checks[] = 'check-rk ' . $key . ': ' . ( array() === $failed ? 'grün' : count( $failed ) . ' Abweichung(en): ' . implode( '; ', array_map( static fn( array $r ): string => $r[0] . ' soll ' . $r[1] . ' ist ' . $r[2], $failed ) ) );
		}
		return array(
			'courses' => $maps,
			'notes'   => $builder->notes,
		);
	}

	/**
	 * Vergleicht einen Kurs mit dem Soll-Zustand (`check-rk`, R0-26 §1.1, S-06).
	 *
	 * ## OPTIONS
	 *
	 * <rk>
	 * : rk1, rk2 oder rk3 (Soll aus dem Paket) oder Pfad zu einer rk<n>-soll.json.
	 *
	 * [--kurs=<id-oder-slug>]
	 * : Zu prüfender Kurs (Standard: der Referenzkurs dieser Site).
	 *
	 * @subcommand check-rk
	 *
	 * @param array<int, string>    $args  Argumente.
	 * @param array<string, string> $assoc Optionen.
	 * @return void
	 */
	public function check_rk( array $args, array $assoc ): void {
		self::guard();
		$content = require __DIR__ . '/content/rk-v1.php';
		$ref     = (string) ( $args[0] ?? '' );
		if ( is_file( $ref ) ) {
			$soll = (array) json_decode( (string) file_get_contents( $ref ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
			$key  = strtolower( str_replace( '-', '', (string) ( $soll['id'] ?? '' ) ) );
		} elseif ( isset( $content['courses'][ $ref ] ) ) {
			$key  = $ref;
			$soll = Kit::soll( (array) $content['courses'][ $ref ] );
		} else {
			\WP_CLI::error( 'Unbekanntes Soll: ' . $ref );
			return;
		}
		$course = (string) ( $assoc['kurs'] ?? (string) $content['courses'][ $key ]['slug'] );
		$id     = ctype_digit( $course ) ? (int) $course : (int) ( get_page_by_path( $course, OBJECT, 'ccap_course' )->ID ?? 0 );
		if ( $id <= 0 || 'ccap_course' !== get_post_type( $id ) ) {
			\WP_CLI::error( 'Kurs nicht gefunden: ' . $course );
		}
		$rows = Kit::compare( $soll, Kit::ist( $id ) );
		$bad  = 0;
		foreach ( $rows as $row ) {
			\WP_CLI::line( ( $row[3] ? 'OK    ' : 'FEHLT ' ) . $row[0] . ' | Soll ' . $row[1] . ' | Ist ' . $row[2] );
			$bad += $row[3] ? 0 : 1;
		}
		if ( $bad > 0 ) {
			\WP_CLI::error( $bad . ' Pflichtmerkmal(e) nicht erfüllt (Kurs ' . $id . ').' );
		}
		\WP_CLI::success( 'check-rk ' . $key . ': alle ' . count( $rows ) . ' Pflichtmerkmale erfüllt (Kurs ' . $id . ').' );
	}

	/**
	 * Schreibt das Autoren-Kit: Drehbücher, Soll-Dateien, Fragenpakete und Medien.
	 *
	 * ## OPTIONS
	 *
	 * --ziel=<verzeichnis>
	 * : Zielverzeichnis (außerhalb des Webroots ablegen).
	 *
	 * @param array<int, string>    $args  Argumente.
	 * @param array<string, string> $assoc Optionen.
	 * @return void
	 */
	public function kit( array $args, array $assoc ): void {
		unset( $args );
		self::guard();
		$state = get_option( self::OPTION );
		if ( ! is_array( $state ) || 'referenz' !== ( $state['profil'] ?? '' ) ) {
			\WP_CLI::error( 'Das Kit entsteht aus dem Referenzstand (Profil referenz).' );
		}
		$dir = rtrim( (string) ( $assoc['ziel'] ?? '' ), '/' );
		if ( '' === $dir || ! wp_mkdir_p( $dir . '/medien' ) ) {
			\WP_CLI::error( 'Zielverzeichnis fehlt oder ist nicht beschreibbar.' );
		}
		$content = require __DIR__ . '/content/rk-v1.php';
		$admin   = (int) ( get_user_by( 'login', 'cc_fx_ux_admin' )->ID ?? 0 );
		foreach ( array( 'rk1', 'rk2', 'rk3' ) as $key ) {
			$def = (array) $content['courses'][ $key ];
			file_put_contents( $dir . '/drehbuch-' . $key . '.md', Kit::drehbuch( $def, $key, false ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI tool.
			file_put_contents( $dir . '/drehbuch-' . $key . '-moderation.md', Kit::drehbuch( $def, $key, true ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI tool.
			file_put_contents( $dir . '/' . $key . '-soll.json', (string) wp_json_encode( Kit::soll( $def ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI tool.
			$q40 = Kit::q40( $admin, (array) $state['courses'][ $key ], $def, $dir . '/' . $key . '-q40.zip' );
			\WP_CLI::line( $key . ': Drehbuch, Soll, Fragenpaket mit ' . $q40['questions'] . ' Fragen; Paketprüfung: ' . self::dry_run( $admin, $q40['file'] ) );
		}
		$media = Builder::upload_dir()['dir'];
		foreach ( (array) glob( $media . '/*' ) as $file ) {
			if ( is_string( $file ) ) {
				copy( $file, $dir . '/medien/' . basename( $file ) );
			}
		}
		\WP_CLI::success( 'Kit geschrieben: ' . $dir );
	}

	/**
	 * Szenarien und Störfälle während einer Sitzung (nur Test-Sites).
	 *
	 * ## OPTIONS
	 *
	 * <szenario>
	 * : laufende-pruefung | ablauf | sitzungen-beenden | warteschlangenfehler | einschreiben
	 *
	 * [--konto=<login>]
	 * : Betroffenes Konto (Standard je Szenario; nicht --user, das ist ein globaler WP-CLI-Parameter).
	 *
	 * [--kurs=<rk>]
	 * : rk1, rk2 oder rk3 (für einschreiben, Standard rk1).
	 *
	 * ## EXAMPLES
	 *
	 *     wp ccap-ux scenario laufende-pruefung --konto=cc_fx_ux_p03
	 *     wp ccap-ux scenario ablauf
	 *     wp ccap-ux scenario sitzungen-beenden --konto=cc_fx_ux_autor
	 *
	 * @param array<int, string>    $args  Argumente.
	 * @param array<string, string> $assoc Optionen.
	 * @return void
	 */
	public function scenario( array $args, array $assoc ): void {
		self::guard();
		$state   = get_option( self::OPTION );
		$name    = (string) ( $args[0] ?? '' );
		$content = require __DIR__ . '/content/rk-v1.php';
		$login   = (string) ( $assoc['konto'] ?? array(
			'laufende-pruefung' => 'cc_fx_ux_pruefung',
			'ablauf'            => 'cc_fx_ux_ablauf',
			'sitzungen-beenden' => 'cc_fx_ux_autor',
		)[ $name ] ?? '' );
		$user    = '' === $login ? null : get_user_by( 'login', $login );
		$scen    = new Scenarios();
		$rk2     = is_array( $state ) ? ( $state['courses']['rk2'] ?? null ) : null;
		switch ( $name ) {
			case 'laufende-pruefung':
			case 'ablauf':
				if ( ! $user instanceof \WP_User || ! is_array( $rk2 ) ) {
					\WP_CLI::error( 'Konto oder RK-2 fehlt (Profil referenz?).' );
					return;
				}
				$limit   = (int) $content['courses']['rk2']['exam']['time_limit'];
				$shift   = 'ablauf' === $name ? $limit - 60 : 0;
				$session = $scen->exam_attempt( (int) $user->ID, (int) $rk2['rule'], null, 'ablauf' === $name ? 5 : 10, 2, $shift );
				$view    = ExamSessions::current()->view( Actor::user( (int) $user->ID ), $session );
				\WP_CLI::success( 'RK-2-Versuch ' . $session . ' für ' . $login . ', Restzeit ' . (int) $view['remaining_seconds'] . ' s. Lernbereich: …/lernen/pruefung/' . $session );
				break;
			case 'sitzungen-beenden':
				if ( ! $user instanceof \WP_User ) {
					\WP_CLI::error( 'Konto fehlt.' );
					return;
				}
				\WP_Session_Tokens::get_instance( (int) $user->ID )->destroy_all();
				\WP_CLI::success( 'Alle Anmeldesitzungen von ' . $login . ' beendet (Störfall UX-012).' );
				break;
			case 'warteschlangenfehler':
				$id = Scenarios::queue_failure();
				0 === $id ? \WP_CLI::error( 'Keine Zustellung in der Warteschlange vorhanden.' ) : \WP_CLI::success( 'Zustellung ' . $id . ' steht auf „failed“ (Störfall AD-T05).' );
				break;
			case 'einschreiben':
				$key = (string) ( $assoc['kurs'] ?? 'rk1' );
				if ( ! $user instanceof \WP_User || ! is_array( $state ) || ! isset( $state['courses'][ $key ] ) ) {
					\WP_CLI::error( 'Konto oder Kurs fehlt.' );
					return;
				}
				$admin = (int) ( get_user_by( 'login', 'cc_fx_ux_admin' )->ID ?? 0 );
				$scen->grant( $admin, (int) $user->ID, (int) $state['courses'][ $key ]['course'] );
				\WP_CLI::success( $login . ' hat Zugang zu ' . $key . ' (Ersatz für die Selbstanmeldung, siehe Bereitschaft).' );
				break;
			default:
				\WP_CLI::error( 'Unbekanntes Szenario: ' . $name );
		}
	}

	/**
	 * Paket hochladen, prüfen und Dry-run ansehen, danach verwerfen (AU-T06-Voraussetzung, R1-15).
	 *
	 * @param int    $admin Admin.
	 * @param string $file  Paket.
	 * @return string
	 */
	private static function dry_run( int $admin, string $file ): string {
		$service = ImportService::current();
		$actor   = Actor::user( $admin );
		try {
			$batch  = $service->upload( $actor, $file );
			$result = $service->validate( $actor, $batch );
			if ( 'validated' !== $result['status'] ) {
				return 'ungültig ' . (string) wp_json_encode( array_slice( $result['errors'], 0, 3 ) );
			}
			$preview = $service->preview( $actor, $batch );
			$service->discard( $actor, $batch );
			return 'gültig, Dry-run ' . (string) $preview['status'] . ' ' . (string) wp_json_encode( $preview['plan'], JSON_UNESCAPED_UNICODE );
		} catch ( \Throwable $e ) {
			return 'Fehler ' . $e->getMessage();
		}
	}

	/**
	 * Abbruch außerhalb einer Test-Site.
	 *
	 * @return void
	 */
	private static function guard(): void {
		if ( ! defined( 'CCAP_UX_TESTSITE' ) || true !== CCAP_UX_TESTSITE ) {
			\WP_CLI::error( 'Nur für Test-Sites: CCAP_UX_TESTSITE ist nicht gesetzt (siehe tools/usability/setup-site.sh).' );
		}
		if ( ! class_exists( Database::class ) ) {
			\WP_CLI::error( 'Course Captain ist nicht aktiv.' );
		}
	}

	/**
	 * Setup als abgeschlossen markieren (Startzustand Flow 1, R0-26 §1.2).
	 *
	 * @param int $admin Admin.
	 * @return void
	 */
	private static function complete_setup( int $admin ): void {
		$state = SetupState::get();
		foreach ( SetupState::STEPS as $step ) {
			$state['steps'][ $step ] = array(
				'status' => 'saved',
				'at'     => gmdate( 'c' ),
				'by'     => $admin,
			);
		}
		$state['status']       = 'completed';
		$state['purpose']      = 'internal_training';
		$state['current_step'] = 'readiness';
		SetupState::write( $state, $admin, (int) $state['_rev'] );
	}

	/**
	 * Abgabetexte.
	 *
	 * @param string $kind Art.
	 * @return string
	 */
	private static function text( string $kind ): string {
		return array(
			'gut'            => 'Ziele: 1. Bis Freitag lese ich zwei Kapitel des Fachbuchs. 2. Bis Mittwoch schreibe ich das Angebot für Kunde A fertig. 3. Bis Sonntag gehe ich dreimal 30 Minuten spazieren. Matrix: A – Angebot fertigstellen; B – Fachbuch lesen, Spaziergänge; C – Rückruf Lieferant an Kollegin abgeben; D – Newsletter-Archiv sortieren streiche ich. Puffer: Zwischen allen Terminen plane ich 15 Minuten ein, am Freitagnachmittag bleibt eine Stunde frei.',
			'unvollstaendig' => 'Ich möchte nächste Woche mehr schaffen und weniger Stress haben. Wichtig sind das Angebot und das Lesen. Puffer plane ich ein, wenn Zeit ist. Den Rest überlege ich mir noch und ergänze ihn später gerne.',
			'datei'          => 'Meine Planung steht ausführlich in der angehängten Datei. Kurz: drei SMART-Ziele mit Termin, Aufgaben nach Eisenhower sortiert, täglich zwei Pufferblöcke von je 20 Minuten. Freitag 14 bis 15 Uhr ist die Wochenplanung fest eingetragen.',
			'rueckgabe'      => 'Ziele: Fachbuch lesen, Angebot schreiben, mehr Sport. Matrix: Angebot ist dringend und wichtig, Fachbuch wichtig, Rückruf delegiere ich, Archiv streiche ich. Puffer: jeden Tag eine halbe Stunde nach dem Mittagessen frei halten.',
		)[ $kind ];
	}

	/**
	 * Ergebnis einmalig ausgeben.
	 *
	 * @param string    $profil  Profil.
	 * @param Scenarios $scen    Konten/Prüfungen.
	 * @param array     $notes   Hinweise.
	 * @phpstan-param list<string> $notes
	 * @param float     $seconds Laufzeit.
	 * @return void
	 */
	private static function print_result( string $profil, Scenarios $scen, array $notes, float $seconds ): void {
		\WP_CLI::line( '' );
		\WP_CLI::line( '=== NUR TEST-SITE · Profil ' . $profil . ' · cc-fx-rk-v1 · ' . gmdate( 'Y-m-d H:i' ) . ' UTC · ' . number_format( $seconds, 1 ) . ' s ===' );
		\WP_CLI::line( 'Zugangsdaten (werden nur jetzt angezeigt und nirgends gespeichert):' );
		\WP_CLI::line( sprintf( '%-24s %-18s %-20s %s', 'Login', 'Passwort', 'Rolle', 'Zweck' ) );
		foreach ( $scen->credentials as $row ) {
			\WP_CLI::line( sprintf( '%-24s %-18s %-20s %s', $row['login'], $row['password'], $row['role'], $row['purpose'] ) );
		}
		\WP_CLI::line( '[Kontrolle] Plugin-Code aus: ' . dirname( (string) ( new \ReflectionClass( Database::class ) )->getFileName(), 2 ) );
		foreach ( $scen->checks as $check ) {
			\WP_CLI::line( '[Kontrolle] ' . $check );
		}
		foreach ( $notes as $note ) {
			\WP_CLI::warning( $note );
		}
	}
}

\WP_CLI::add_command( 'ccap-ux', Command::class );
