<?php
/**
 * Testkonten und Szenario-Zustände für die Nutzertests (R0-26 §1.1–§1.5, §3.3).
 *
 * Alle Konten heißen `cc_fx_ux_…`, alle Adressen enden auf `@example.test`
 * (E-207, R0-26 §4.1). Passwörter werden je Lauf neu erzeugt und nur einmal
 * ausgegeben. Nur für Test-Sites.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tools\Usability;

use CourseCaptain\Access\GrantService;
use CourseCaptain\Access\GroupService;
use CourseCaptain\Activity\ActivityProgress;
use CourseCaptain\Assignment\AssignmentService;
use CourseCaptain\Certificate\CertificateService;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Learning\SmartTrainer;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Question\Randomizer;
use CourseCaptain\Storage\Database;

/**
 * Konten und vorbereitete Zustände.
 */
final class Scenarios {

	public const DOMAIN = 'example.test';

	/**
	 * Ausgegebene Zugangsdaten.
	 *
	 * @var list<array{login: string, password: string, role: string, purpose: string}>
	 */
	public array $credentials = array();

	/**
	 * Prüfbare Ergebnisse des Laufs (Selbstkontrolle).
	 *
	 * @var list<string>
	 */
	public array $checks = array();

	/**
	 * Konto anlegen oder aktualisieren; neues Passwort.
	 *
	 * @param string $name    Kurzname (ohne Präfix).
	 * @param string $role    WordPress-Rolle.
	 * @param string $purpose Zweck (für die Zugangsliste).
	 * @param string $display Anzeigename.
	 * @return int
	 * @throws \RuntimeException Wenn WordPress das Konto ablehnt.
	 */
	public function user( string $name, string $role, string $purpose, string $display = '' ): int {
		$login    = 'cc_fx_ux_' . $name;
		$password = wp_generate_password( 16, false );
		$found    = get_user_by( 'login', $login );
		$data     = array(
			'user_login'   => $login,
			'user_email'   => $login . '@' . self::DOMAIN,
			'user_pass'    => $password,
			'role'         => $role,
			'display_name' => '' === $display ? $login : $display,
			'locale'       => 'de_DE',
		);
		if ( $found instanceof \WP_User ) {
			$data['ID'] = $found->ID;
			$id         = wp_update_user( $data );
		} else {
			$id = wp_insert_user( $data );
		}
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( esc_html( $login . ': ' . $id->get_error_message() ) );
		}
		$this->credentials[] = array(
			'login'    => $login,
			'password' => $password,
			'role'     => $role,
			'purpose'  => $purpose,
		);
		PolicyService::flush();
		return (int) $id;
	}

	/**
	 * Manueller Zugang (M-14), optional nur für ein Modul.
	 *
	 * @param int      $admin  Admin.
	 * @param int      $user   Lernende Person.
	 * @param int      $course Kurs.
	 * @param int|null $module Modul.
	 * @return int
	 */
	public function grant( int $admin, int $user, int $course, ?int $module = null ): int {
		$options = null === $module ? array() : array( 'module_id' => $module );
		$id      = GrantService::current()->grant_manual( Actor::user( $admin ), $user, $course, wp_generate_uuid4(), 'Nutzertest R1-29 (Testdaten)', $options );
		PolicyService::flush();
		return $id;
	}

	/**
	 * Kursteam-Zuordnung.
	 *
	 * @param int    $user   Konto.
	 * @param int    $course Kurs.
	 * @param string $role   trainer|manager.
	 * @return void
	 */
	public function staff( int $user, int $course, string $role ): void {
		Database::current()->insert(
			'ccap_course_staff',
			array(
				'course_id' => $course,
				'user_id'   => $user,
				'role'      => $role,
				'group_id'  => 0,
			),
			array( 'created_at', 'updated_at' )
		);
		PolicyService::flush();
	}

	/**
	 * Teilweise bearbeiteter Kurs (LE-T10): Lektionen 1–4 abgeschlossen, Lektion 5 geöffnet.
	 *
	 * @param int                  $user Lernende Person.
	 * @param array<string, mixed> $map  IDs RK-1.
	 * @return void
	 */
	public function partial( int $user, array $map ): void {
		$progress = ActivityProgress::current();
		$actor    = Actor::user( $user );
		$course   = (int) $map['course'];
		foreach ( array( 'l01', 'l02', 'l03', 'l04', 'l05' ) as $key ) {
			$lesson = (int) $map['lessons'][ $key ];
			switch ( $map['lesson_type'][ $key ] ) {
				case 'video_internal':
				case 'video_external':
					$progress->interact( $actor, $course, $lesson, 'position', array( 'position_seconds' => Builder::VIDEO_SECONDS ) );
					break;
				case 'file':
					$progress->interact( $actor, $course, $lesson, 'download' );
					break;
				default:
					$progress->interact( $actor, $course, $lesson, 'l05' === $key ? 'view' : 'confirm', array( 'seconds' => 20 ) );
			}
		}
		$done = array();
		foreach ( array( 'l01', 'l02', 'l03', 'l04', 'l05' ) as $key ) {
			$state  = $progress->state( $user, (int) $map['lessons'][ $key ] );
			$done[] = $key . '=' . ( null === $state ? 'none' : (string) $state['status'] );
		}
		$this->checks[] = 'teilweise (RK-1): ' . implode( ', ', $done );
	}

	/**
	 * Prüfungsversuch starten und Antworten setzen. Die Zeit läuft echt; mit
	 * `$shift` wird der Versuch danach um so viele Sekunden zurückdatiert
	 * (Startzeit und Frist), damit die Policy mit der echten Uhr rechnet.
	 *
	 * @param int      $user     Lernende Person.
	 * @param int      $rule     Regel.
	 * @param int|null $variant  Variante.
	 * @param int      $answered Anzahl beantworteter Positionen.
	 * @param int      $flagged  Anzahl markierter Positionen.
	 * @param int      $shift    Sekunden zurückdatieren (0 = nicht).
	 * @param bool     $correct  Richtig (true) oder gemischt (false) beantworten.
	 * @return int Sitzung.
	 */
	public function exam_attempt( int $user, int $rule, ?int $variant, int $answered, int $flagged, int $shift = 0, bool $correct = false ): int {
		$sessions = ExamSessions::current();
		$actor    = Actor::user( $user );
		$session  = $sessions->start( $actor, $rule, $variant, wp_generate_uuid4(), new Randomizer( 'cc-fx-rk-v1:' . $user . ':' . $rule ) );
		$db       = Database::current();
		$rows     = $db->rows( $db->prepare( 'SELECT position, revision_id FROM `' . $db->table( 'ccap_exam_session_question' ) . '` WHERE session_id = %d ORDER BY position', $session ) );
		$repo     = QuestionRepository::current();
		$given    = 0;
		foreach ( $rows as $i => $row ) {
			if ( $given >= $answered ) {
				break;
			}
			$revision = (array) $repo->revision( (int) $row['revision_id'] );
			$answer   = self::answer( $revision, $correct || 0 !== $i % 3 );
			if ( null === $answer ) {
				continue;
			}
			if ( QuestionTypes::OPEN_TEXT === $revision['question_type'] ) {
				$sessions->reveal( $actor, $session, (int) $row['position'] );
			}
			$sessions->save_answer( $actor, $session, (int) $row['position'], $answer );
			++$given;
		}
		for ( $i = 0; $i < $flagged && isset( $rows[ $i * 2 ] ); $i++ ) {
			$sessions->flag( $actor, $session, (int) $rows[ $i * 2 ]['position'], true );
		}
		if ( $shift > 0 ) {
			$table = $db->table( 'ccap_exam_session' );
			$db->execute( $db->prepare( "UPDATE `{$table}` SET started_at = started_at - INTERVAL %d SECOND, deadline_at = deadline_at - INTERVAL %d SECOND WHERE id = %d", $shift, $shift, $session ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
			$sq = $db->table( 'ccap_exam_session_question' );
			$db->execute( $db->prepare( "UPDATE `{$sq}` SET answered_at = answered_at - INTERVAL %d SECOND WHERE session_id = %d AND answered_at IS NOT NULL", $shift, $session ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
			wp_cache_flush();
		}
		return $session;
	}

	/**
	 * Abgelaufener Versuch (E-75: `evaluated`, `end_reason = expired`).
	 *
	 * @param int $user Lernende Person.
	 * @param int $rule Regel mit Zeitlimit.
	 * @param int $time Zeitlimit in Sekunden.
	 * @return int Sitzung.
	 */
	public function expired_attempt( int $user, int $rule, int $time ): int {
		$session = $this->exam_attempt( $user, $rule, null, 6, 1, $time + 600 );
		ExamSessions::current()->expire_due();
		$db             = Database::current();
		$row            = (array) $db->row( $db->prepare( 'SELECT status, end_reason FROM `' . $db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $session ) );
		$this->checks[] = 'abgelaufen (RK-2): Sitzung ' . $session . ' status=' . ( $row['status'] ?? '?' ) . ' end_reason=' . ( $row['end_reason'] ?? '?' );
		return $session;
	}

	/**
	 * Bestandener Versuch mit Nachweis (LE-T08b).
	 *
	 * @param int $admin Admin (stellt aus).
	 * @param int $user  Lernende Person.
	 * @param int $rule  Regel.
	 * @return int Sitzung.
	 */
	public function passed_attempt( int $admin, int $user, int $rule ): int {
		$session = $this->exam_attempt( $user, $rule, null, 999, 0, 0, true );
		$result  = ExamSessions::current()->submit( Actor::user( $user ), $session );
		$passed  = ! empty( $result['passed'] ) || ! empty( $result['result']['passed'] );
		$cert    = '';
		try {
			$issued = CertificateService::current()->issue( Actor::user( $admin ), CertificateService::EVIDENCE_EXAM, $session, 'Nutzertest R1-29: vorbereiteter Nachweis (Testdaten)' );
			$cert   = (string) $issued['certificate_uid'];
		} catch ( \Throwable $e ) {
			$cert = 'nicht ausgestellt (' . $e->getMessage() . ')';
		}
		$this->checks[] = 'bestanden (RK-2): Sitzung ' . $session . ' passed=' . ( $passed ? 'ja' : 'nein' ) . ' Nachweis=' . $cert;
		return $session;
	}

	/**
	 * Lernhistorie mit fälligen Wiederholungen (LE-T07, Muster R0-05 PAR-06).
	 *
	 * @param int   $user      Lernende Person.
	 * @param array $questions Fragen (zugänglich).
	 * @phpstan-param list<int> $questions
	 * @return void
	 */
	public function due_history( int $user, array $questions ): void {
		$db   = Database::current();
		$base = time() - 3 * DAY_IN_SECONDS;
		foreach ( array_slice( $questions, 0, 8 ) as $n => $question ) {
			SmartTrainer::ensure_row( $db, $user, $question );
			$right = $n < 6;
			for ( $k = 0; $k < ( $right ? 3 : 1 ); $k++ ) {
				SmartTrainer::set_now( $base + $n * 60 + $k * 900 );
				$db->transaction(
					static function ( Database $tx ) use ( $user, $question, $right ): void {
						SmartTrainer::apply( $tx, $user, $question, $right );
					}
				);
			}
		}
		SmartTrainer::set_now( null );
		$due            = (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_question_progress' ) . "` WHERE user_id = %d AND due_at IS NOT NULL AND due_at <= %s AND learn_status IN ('secure','mastered')", $user, gmdate( 'Y-m-d H:i:s' ) ) );
		$this->checks[] = 'faellig (RK-2): ' . $due . ' Fragen fällig';
	}

	/**
	 * Eingereichte Abgabe (optional mit PDF).
	 *
	 * @param int         $user   Lernende Person.
	 * @param int         $course Kurs.
	 * @param int         $lesson Aufgabe.
	 * @param string|null $text   Text.
	 * @param string|null $pdf    PDF-Vorlage (wird kopiert).
	 * @return string Abgabe-UID.
	 */
	public function submission( int $user, int $course, int $lesson, ?string $text, ?string $pdf ): string {
		$service = AssignmentService::current();
		$actor   = Actor::user( $user );
		$draft   = $service->save_draft( $actor, $course, $lesson, $text );
		if ( null !== $pdf ) {
			$tmp = wp_tempnam( 'cc-fx-abgabe' );
			copy( $pdf, $tmp );
			$service->add_file( $actor, $draft['submission_uid'], $tmp, 'auswertung.pdf', 'application/pdf', wp_generate_uuid4() );
		}
		$service->submit( $actor, $draft['submission_uid'] );
		return $draft['submission_uid'];
	}

	/**
	 * Abgabe zurückgeben (LE-T12).
	 *
	 * @param int    $grader Bewertende Person.
	 * @param string $uid    Abgabe.
	 * @return void
	 */
	public function return_submission( int $grader, string $uid ): void {
		$out            = AssignmentService::current()->grade( Actor::user( $grader ), $uid, 'return', null, 'Bitte ergänze bei jedem Ziel einen Termin (das „T“ in SMART) und reiche die Aufgabe erneut ein.', '', wp_generate_uuid4() );
		$this->checks[] = 'rueckgabe (RK-1): status=' . (string) ( $out['status'] ?? '?' );
	}

	/**
	 * Zeitausgleich 150 % für einen Kurs (UX-075, E-122).
	 *
	 * @param int $admin  Admin.
	 * @param int $user   Lernende Person.
	 * @param int $course Kurs.
	 * @return void
	 */
	public function accommodation( int $admin, int $user, int $course ): void {
		Database::current()->insert(
			'ccap_accommodation',
			array(
				'user_id'         => $user,
				'scope_type'      => 'course',
				'scope_id'        => $course,
				'time_factor_pct' => 150,
				'unlimited'       => 0,
				'created_by'      => $admin,
			),
			array( 'created_at' )
		);
	}

	/**
	 * Gruppe mit Plätzen, Besitzerin und beigetretenen Mitgliedern (Flow 3).
	 *
	 * @param int   $admin   Admin.
	 * @param int   $owner   Besitzerin.
	 * @param int   $course  Kurs.
	 * @param int   $seats   Plätze.
	 * @param array $members Konten, die über eine Einladung beitreten.
	 * @phpstan-param list<int> $members
	 * @return int Gruppe.
	 */
	public function group( int $admin, int $owner, int $course, int $seats, array $members ): int {
		$service = GroupService::current();
		$group   = $service->create( Actor::user( $admin ), $owner, 'Team Einarbeitung (Testgruppe)', $seats, array( array( 'course_id' => $course ) ), wp_generate_uuid4() );
		foreach ( $members as $member ) {
			$user   = get_userdata( $member );
			$invite = false === $user ? null : $service->invite( Actor::user( $admin ), $group, (string) $user->user_email );
			if ( null !== $invite ) {
				$service->claim( Actor::user( $member ), (string) $invite['token'] );
			}
		}
		PolicyService::flush();
		$this->checks[] = 'gruppe (RK-3): belegt ' . $service->occupied( $group ) . ' von ' . $seats;
		return $group;
	}

	/**
	 * Störfall AD-T05: eine Zustellung der Hintergrundwarteschlange auf `failed` setzen.
	 *
	 * @return int Betroffene Zustellung (0 = keine vorhanden).
	 */
	public static function queue_failure(): int {
		$db    = Database::current();
		$table = $db->table( 'ccap_outbox_delivery' );
		$id    = (int) $db->scalar( "SELECT id FROM `{$table}` ORDER BY id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
		if ( $id > 0 ) {
			$db->execute( $db->prepare( "UPDATE `{$table}` SET status = 'failed', attempts = 5, last_error_class = 'internal', last_error = %s, locked_until = NULL, lock_owner = NULL WHERE id = %d", 'Störfall Nutzertest R1-29: Empfänger nicht erreichbar (simuliert)', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
		}
		return $id;
	}

	/**
	 * Richtige (oder bewusst falsche) Antwort für eine Revision.
	 *
	 * @param array<string, mixed> $revision Revision.
	 * @param bool                 $correct  Richtig.
	 * @return mixed Antwort oder null, wenn der Typ nicht beantwortbar ist.
	 */
	public static function answer( array $revision, bool $correct ) {
		$settings = is_array( $revision['settings'] ?? null ) ? $revision['settings'] : (array) json_decode( (string) ( $revision['settings'] ?? '' ), true );
		$answers  = (array) ( $revision['answers'] ?? array() );
		switch ( (string) $revision['question_type'] ) {
			case QuestionTypes::SINGLE:
			case QuestionTypes::TRUE_FALSE:
			case QuestionTypes::MULTI:
				$keys = array();
				foreach ( $answers as $a ) {
					if ( (bool) ( $a['correct'] ?? false ) === $correct ) {
						$keys[] = (string) $a['key'];
					}
				}
				if ( ! $correct || QuestionTypes::MULTI !== $revision['question_type'] ) {
					$keys = array_slice( $keys, 0, 1 );
				}
				return array() === $keys ? null : $keys;
			case QuestionTypes::OPEN_TEXT:
				return array( 'known' => $correct );
			case QuestionTypes::NUMERIC:
				return $correct ? (string) $settings['target'] : '99999';
			case QuestionTypes::MATCHING:
				$out = array();
				foreach ( array_keys( (array) ( $settings['pairs'] ?? array() ) ) as $i ) {
					$out[ 'L' . ( $i + 1 ) ] = 'R' . ( $correct ? $i + 1 : ( ( $i + 1 ) % count( (array) $settings['pairs'] ) ) + 1 );
				}
				return $out;
			case QuestionTypes::ORDERING:
				$items = array_map( static fn( int $i ): string => 'I' . ( $i + 1 ), array_keys( (array) ( $settings['items'] ?? array() ) ) );
				return $correct ? $items : array_reverse( $items );
			case QuestionTypes::CLOZE:
				$out = array();
				foreach ( (array) ( $settings['gaps'] ?? array() ) as $gap ) {
					$out[ (string) $gap['id'] ] = $correct ? (string) $gap['accepted'][0] : 'falsch';
				}
				return $out;
		}
		return null;
	}
}
