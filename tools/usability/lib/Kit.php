<?php
/**
 * Autoren-Kit und Prüfskript für `cc-fx-rk-v1` (R0-26 §1.1 „Lieferform“, §6):
 * Drehbücher (Teilnehmenden- und Moderationsfassung), Soll-Zustand
 * `rk<n>-soll.json`, Fragen-Teilpaket `rk<n>-q40.zip` und `check-rk`.
 * Nur für Test-Sites.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tools\Usability;

use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Import\PackageExporter;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;

/**
 * Kit-Erzeugung und Soll-Ist-Vergleich.
 */
final class Kit {

	/**
	 * Die 10 manuell anzulegenden Fragen (AU-T04: 3, AU-T05: 7) je Kurs (E-165).
	 *
	 * @param array<string, mixed> $def Kursdefinition.
	 * @param string               $key Kurs-Schlüssel.
	 * @return array{t04: list<array{int, int}>, t05: list<array{int, int}>}
	 */
	public static function manual( array $def, string $key ): array {
		$t04  = array();
		$t05  = array();
		$used = array();
		foreach ( array( 's', 'm', 'tf' ) as $type ) {
			foreach ( (array) $def['modules'] as $m => $module ) {
				foreach ( (array) $module['questions'] as $n => $q ) {
					if ( $type === $q[0] && ! isset( $q['img'] ) && empty( $q['inline'] ) && ! isset( $used[ "$m-$n" ] ) ) {
						$t04[]           = array( $m, $n );
						$used[ "$m-$n" ] = true;
						break 2;
					}
				}
			}
		}
		$new = array(
			'rk1' => 'ma',
			'rk2' => 'n',
		);
		foreach ( (array) $def['modules'] as $m => $module ) {
			foreach ( (array) $module['questions'] as $n => $q ) {
				$is_new = isset( $new[ $key ] ) && $new[ $key ] === $q[0] && ! self::has_type( $t05, $def, $new[ $key ] );
				$is_img = isset( $q['img'] ) && ! self::has_image( $t05, $def ) && ( '' !== (string) $def['images'][ $q['img'] ]['alt'] || 'rk3' === $key );
				if ( ( $is_new || $is_img ) && ! isset( $used[ "$m-$n" ] ) ) {
					$t05[]           = array( $m, $n );
					$used[ "$m-$n" ] = true;
				}
			}
		}
		foreach ( (array) $def['modules'] as $m => $module ) {
			foreach ( (array) $module['questions'] as $n => $q ) {
				if ( count( $t05 ) >= 7 ) {
					break 2;
				}
				if ( in_array( $q[0], array( 's', 'm', 'tf' ), true ) && ! isset( $q['img'] ) && empty( $q['inline'] ) && ! isset( $used[ "$m-$n" ] ) ) {
					$t05[]           = array( $m, $n );
					$used[ "$m-$n" ] = true;
				}
			}
		}
		return array(
			't04' => $t04,
			't05' => $t05,
		);
	}

	/**
	 * Soll-Zustand (maschinenlesbare Pflichtmerkmale, R0-26 §1.1).
	 *
	 * @param array<string, mixed> $def Kursdefinition.
	 * @return array<string, mixed>
	 */
	public static function soll( array $def ): array {
		$modules = array();
		$images  = 0;
		$types   = array();
		foreach ( (array) $def['modules'] as $module ) {
			$lesson_types = array();
			foreach ( (array) $module['lessons'] as $lesson ) {
				$lesson_types[] = self::activity_type( (string) $lesson[2] );
			}
			$counts = array();
			foreach ( (array) $module['questions'] as $q ) {
				$type            = Builder::TYPES[ $q[0] ];
				$counts[ $type ] = ( $counts[ $type ] ?? 0 ) + 1;
				$types[ $type ]  = ( $types[ $type ] ?? 0 ) + 1;
				$images         += isset( $q['img'] ) ? 1 : 0;
			}
			ksort( $counts );
			$modules[] = array(
				'title'          => $module['title'],
				'lessons'        => count( (array) $module['lessons'] ),
				'activity_types' => $lesson_types,
				'questions'      => count( (array) $module['questions'] ),
				'question_types' => $counts,
			);
		}
		ksort( $types );
		$exam = (array) $def['exam'];
		$a    = (array) $def['assignment'];
		return array(
			'package'                  => 'cc-fx-rk-v1',
			'id'                       => $def['id'],
			'title'                    => $def['title'],
			'status'                   => 'publish',
			'modules'                  => $modules,
			'lessons_total'            => array_sum( array_column( $modules, 'lessons' ) ),
			'questions'                => array_sum( array_column( $modules, 'questions' ) ),
			'question_types'           => $types,
			'images'                   => array(
				'count'                   => $images,
				'alt_or_decorative_ratio' => 1.0,
			),
			'internal_videos_with_vtt' => count( array_filter( array_merge( ...array_column( $modules, 'activity_types' ) ), static fn( string $t ): bool => 'video' === $t ) ) > 0,
			'exam'                     => array(
				'version'      => $exam['version'],
				'blueprint'    => $exam['blueprint'] ?? null,
				'fixed'        => isset( $exam['fixed'] ) ? array_sum( (array) $exam['fixed'] ) : null,
				'time_limit'   => $exam['time_limit'],
				'pass'         => $exam['pass'][0] . '/' . $exam['pass'][1],
				'parts'        => is_array( $exam['parts'] ) ? $exam['parts'][0] . '/' . $exam['parts'][1] : null,
				'max_attempts' => $exam['attempts'],
				'timer_mode'   => $exam['timer_mode'],
			),
			'assignment'               => array(
				'submission_type' => $a['submission_type'],
				'max_points'      => $a['max_points'],
				'passing_points'  => $a['passing_points'],
				'completion'      => $a['completion'],
				'allowlist'       => $a['file_rules']['allowlist'] ?? null,
			),
			'access'                   => $def['access'],
		);
	}

	/**
	 * Ist-Werte eines Kurses in der Struktur des Solls.
	 *
	 * @param int $course Kurs.
	 * @return array<string, mixed>
	 */
	public static function ist( int $course ): array {
		$db      = Database::current();
		$modules = array();
		$types   = array();
		$images  = 0;
		$alt_ok  = 0;
		$videos  = 0;
		$vtt     = 0;
		$mods    = $db->rows( $db->prepare( 'SELECT module_id FROM `' . $db->table( 'ccap_course_module' ) . '` WHERE course_id = %d ORDER BY sort_order', $course ) );
		$ids     = array();
		$a       = null;
		foreach ( $mods as $row ) {
			$module       = (int) $row['module_id'];
			$ids[]        = $module;
			$lessons      = get_posts(
				array(
					'post_type'   => 'ccap_lesson',
					'post_parent' => $module,
					'post_status' => 'any',
					'orderby'     => 'menu_order',
					'order'       => 'ASC',
					'numberposts' => -1,
				)
			);
			$lesson_types = array();
			foreach ( $lessons as $lesson ) {
				$def            = ActivityService::definition( (int) $lesson->ID );
				$lesson_types[] = $def['type'];
				if ( 'video' === $def['type'] && 'media' === ( $def['settings']['provider'] ?? '' ) ) {
					++$videos;
					$vtt += 'vtt' === ( $def['settings']['subtitles'] ?? '' ) ? 1 : 0;
				}
				if ( 'assignment' === $def['type'] ) {
					$a = $def;
				}
			}
			$counts = array();
			$rows   = $db->rows( $db->prepare( 'SELECT r.question_type, r.question_text FROM `' . $db->table( 'ccap_module_question' ) . '` p JOIN `' . $db->table( 'ccap_question' ) . '` q ON q.id = p.question_id JOIN `' . $db->table( 'ccap_question_revision' ) . "` r ON r.id = q.current_revision_id WHERE p.module_id = %d AND q.status = 'published'", $module ) );
			foreach ( $rows as $q ) {
				$type            = (string) $q['question_type'];
				$counts[ $type ] = ( $counts[ $type ] ?? 0 ) + 1;
				$types[ $type ]  = ( $types[ $type ] ?? 0 ) + 1;
				if ( preg_match_all( '/<img\b[^>]*>/i', (string) $q['question_text'], $m ) ) {
					foreach ( $m[0] as $tag ) {
						++$images;
						$has_alt = 1 === preg_match( '/\salt="([^"]*)"/i', $tag, $alt );
						$alt_ok += $has_alt && ( '' !== trim( $alt[1] ) || 1 === preg_match( '/role="presentation"/i', $tag ) ) ? 1 : 0;
					}
				}
			}
			ksort( $counts );
			$modules[] = array(
				'title'          => get_the_title( $module ),
				'lessons'        => count( $lessons ),
				'activity_types' => $lesson_types,
				'questions'      => count( $rows ),
				'question_types' => $counts,
			);
		}
		ksort( $types );
		$rule = $db->row( $db->prepare( 'SELECT id, version, time_limit_seconds, passing_rule, settings FROM `' . $db->table( 'ccap_exam_rule' ) . "` WHERE course_id = %d AND status = 'published' ORDER BY id DESC LIMIT 1", $course ) );
		$exam = null;
		if ( null !== $rule ) {
			$passing   = (array) json_decode( (string) $rule['passing_rule'], true );
			$settings  = (array) json_decode( (string) $rule['settings'], true );
			$resolved  = (array) ( $settings['resolved'] ?? array() );
			$blueprint = array();
			foreach ( $db->rows( $db->prepare( 'SELECT module_id, question_count FROM `' . $db->table( 'ccap_exam_blueprint' ) . '` WHERE rule_id = %d', (int) $rule['id'] ) ) as $b ) {
				$blueprint[ (int) $b['module_id'] ] = (int) $b['question_count'];
			}
			$ordered = array();
			foreach ( $ids as $module ) {
				if ( isset( $blueprint[ $module ] ) ) {
					$ordered[] = $blueprint[ $module ];
				}
			}
			$parts = array_values( array_unique( array_map( static fn( array $p ): string => $p['min_ratio']['num'] . '/' . $p['min_ratio']['den'], (array) ( $passing['parts'] ?? array() ) ) ) );
			$fixed = (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_exam_variant_question' ) . '` vq JOIN `' . $db->table( 'ccap_exam_variant' ) . "` v ON v.id = vq.variant_id WHERE v.course_id = %d AND v.status = 'published'", $course ) );
			$exam  = array(
				'version'      => $rule['version'],
				'blueprint'    => array() === $ordered ? null : $ordered,
				'fixed'        => 0 === $fixed ? null : $fixed,
				'time_limit'   => null === $rule['time_limit_seconds'] ? null : (int) $rule['time_limit_seconds'],
				'pass'         => $passing['min_ratio']['num'] . '/' . $passing['min_ratio']['den'],
				'parts'        => 1 === count( $parts ) ? $parts[0] : ( array() === $parts ? null : implode( ',', $parts ) ),
				'max_attempts' => $resolved['exams.max_attempts']['v'] ?? null,
				'timer_mode'   => $resolved['exams.timer_mode']['v'] ?? null,
			);
		}
		$rules  = $db->rows( $db->prepare( 'SELECT module_id, access_type, limit_value FROM `' . $db->table( 'ccap_access_rule' ) . '` WHERE course_id = %d', $course ) );
		$access = array( 'mode' => 'manual' );
		foreach ( $rules as $r ) {
			if ( 'free_limited' === $r['access_type'] ) {
				$access = array(
					'mode'        => 'free_limited',
					'first_items' => (int) $r['limit_value'],
				);
			}
			if ( 'drip' === $r['access_type'] ) {
				$access['drip_days'] = (int) $r['limit_value'];
			}
		}
		$groups = (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_group_course' ) . '` WHERE course_id = %d', $course ) );
		if ( $groups > 0 ) {
			$access['mode'] = 'group';
		}
		return array(
			'title'                    => get_the_title( $course ),
			'status'                   => get_post_status( $course ),
			'modules'                  => $modules,
			'lessons_total'            => array_sum( array_column( $modules, 'lessons' ) ),
			'questions'                => array_sum( array_column( $modules, 'questions' ) ),
			'question_types'           => $types,
			'images'                   => array(
				'count'                   => $images,
				'alt_or_decorative_ratio' => 0 === $images ? 1.0 : round( $alt_ok / $images, 2 ),
			),
			'internal_videos_with_vtt' => $videos > 0 && $videos === $vtt,
			'exam'                     => $exam,
			'assignment'               => null === $a ? null : array(
				'submission_type' => $a['settings']['submission_type'] ?? null,
				'max_points'      => $a['settings']['max_points'] ?? null,
				'passing_points'  => $a['settings']['passing_points'] ?? null,
				'completion'      => $a['rule']['type'] ?? null,
				'allowlist'       => $a['settings']['file_rules']['allowlist'] ?? null,
			),
			'access'                   => $access,
		);
	}

	/**
	 * Soll-Ist-Vergleich (`check-rk`): Liste [Merkmal, Soll, Ist, ok].
	 *
	 * @param array<string, mixed> $soll Soll.
	 * @param array<string, mixed> $ist  Ist.
	 * @return list<array{string, string, string, bool}>
	 */
	public static function compare( array $soll, array $ist ): array {
		$out   = array();
		$check = static function ( string $label, $want, $have ) use ( &$out ): void {
			$w     = is_scalar( $want ) || null === $want ? var_export( $want, true ) : (string) wp_json_encode( $want, JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Report output.
			$h     = is_scalar( $have ) || null === $have ? var_export( $have, true ) : (string) wp_json_encode( $have, JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Report output.
			$out[] = array( $label, $w, $h, $w === $h );
		};
		$check( 'Status', $soll['status'], $ist['status'] );
		$check( 'Anzahl Module', count( $soll['modules'] ), count( $ist['modules'] ) );
		$check( 'Lektionen gesamt', $soll['lessons_total'], $ist['lessons_total'] );
		foreach ( $soll['modules'] as $i => $module ) {
			$have = $ist['modules'][ $i ] ?? array();
			$check( 'Modul ' . ( $i + 1 ) . ' Lektionen/Activity-Typen', $module['activity_types'], $have['activity_types'] ?? null );
			$check( 'Modul ' . ( $i + 1 ) . ' Fragen je Typ', $module['question_types'], $have['question_types'] ?? null );
		}
		$check( 'Fragen gesamt', $soll['questions'], $ist['questions'] );
		$check( 'Bilder mit Alt-Text oder dekorativ (Anteil)', 1.0, $ist['images']['alt_or_decorative_ratio'] );
		$check( 'Bilder in Fragen (Anzahl)', $soll['images']['count'], $ist['images']['count'] );
		$check( 'Interne Videos mit WebVTT', $soll['internal_videos_with_vtt'], $ist['internal_videos_with_vtt'] );
		foreach ( array( 'blueprint', 'fixed', 'time_limit', 'pass', 'parts', 'max_attempts', 'timer_mode' ) as $field ) {
			$check( 'Prüfung ' . $field, $soll['exam'][ $field ], is_array( $ist['exam'] ) ? $ist['exam'][ $field ] : '(keine veröffentlichte Prüfung)' );
		}
		foreach ( array( 'submission_type', 'max_points', 'passing_points', 'completion', 'allowlist' ) as $field ) {
			$check( 'Aufgabe ' . $field, $soll['assignment'][ $field ], is_array( $ist['assignment'] ) ? $ist['assignment'][ $field ] : '(keine Aufgabe)' );
		}
		$check( 'Zugangsmodus', $soll['access']['mode'], $ist['access']['mode'] );
		if ( isset( $soll['access']['first_items'] ) ) {
			$check( 'Teilumfang erste n Lektionen', $soll['access']['first_items'], $ist['access']['first_items'] ?? null );
		}
		if ( isset( $soll['access']['drip_days'] ) ) {
			$check( 'Drip Modul 2 (Tage)', $soll['access']['drip_days'], $ist['access']['drip_days'] ?? null );
		}
		return $out;
	}

	/**
	 * Fragen-Teilpaket `rk<n>-q40` (Ersatzweg R0-26 §1.1: Kurs ohne Lektionen
	 * mit den 40 Importfragen; ein Import nur von Fragen ist im Paketformat
	 * v1 nicht vorgesehen). Enthält den eingebauten Alt-Text-Fehler (AU-T09).
	 *
	 * @param int                  $admin Admin.
	 * @param array<string, mixed> $map   IDs des Referenzkurses.
	 * @param array<string, mixed> $def   Kursdefinition.
	 * @param string               $file  Ziel.
	 * @return array{file: string, questions: int}
	 */
	public static function q40( int $admin, array $map, array $def, string $file ): array {
		$records = PackageExporter::records( Actor::user( $admin ), array( (int) $map['course'] ) );
		$manual  = self::manual( $def, (string) $map['key'] );
		$skip    = array();
		foreach ( array_merge( $manual['t04'], $manual['t05'] ) as $pos ) {
			$skip[] = sprintf( 'cc-fx-%s-m%d-q%02d', $map['key'], $pos[0] + 1, $pos[1] + 1 );
		}
		$db    = Database::current();
		$table = $db->table( 'ccap_question' );
		$drop  = array();
		foreach ( $skip as $ref ) {
			$uid = (string) $db->scalar( $db->prepare( "SELECT uid FROM `{$table}` WHERE reference_no = %s", $ref ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
			if ( '' !== $uid ) {
				$drop[ $uid ] = true;
			}
		}
		$broken    = false;
		$questions = array();
		foreach ( $records['questions'] as $record ) {
			if ( isset( $drop[ (string) $record['source_id'] ] ) ) {
				continue;
			}
			$text = (string) ( $record['revision']['text_html'] ?? $record['revision']['question_text'] ?? '' );
			if ( ! $broken && str_contains( $text, '<img' ) ) {
				// Eingebauter Fehler AU-T09: Bild ohne Alt-Text.
				$text = (string) preg_replace( '/\s(alt|role)="[^"]*"/i', '', $text );
				if ( isset( $record['revision']['text_html'] ) ) {
					$record['revision']['text_html'] = $text;
				} else {
					$record['revision']['question_text'] = $text;
				}
				$broken = true;
				unset( $record['content_hash'] );
				$record = PackageExporter::sealed( $record );
			}
			$questions[] = $record;
		}
		$keep = array_flip( array_map( static fn( array $q ): string => (string) $q['source_id'], $questions ) );
		foreach ( $records['modules'] as $i => $module ) {
			$module['lessons']       = array();
			$module['question_pool'] = array_values( array_filter( (array) $module['question_pool'], static fn( array $p ): bool => isset( $keep[ (string) $p['question'] ] ) ) );
			unset( $module['content_hash'] );
			$records['modules'][ $i ] = PackageExporter::sealed( $module );
		}
		$records['lessons']   = array();
		$records['questions'] = $questions;
		PackageExporter::write( $records, 'cc-fx-rk-v1', 'cc-fx-rk-v1 ' . $def['id'] . ' Fragen-Teilpaket (40 Fragen)', $file );
		return array(
			'file'      => $file,
			'questions' => count( $questions ),
		);
	}

	/**
	 * Drehbuch als Markdown (Teilnehmenden- oder Moderationsfassung).
	 *
	 * @param array<string, mixed> $def        Kursdefinition.
	 * @param string               $key        Kurs-Schlüssel.
	 * @param bool                 $moderation Moderationsfassung mit Lösungen und eingebauten Fehlern.
	 * @return string
	 */
	public static function drehbuch( array $def, string $key, bool $moderation ): string {
		$manual = self::manual( $def, $key );
		$n      = 0;
		$md     = '# Drehbuch ' . $def['id'] . ' · ' . $def['title'] . ( $moderation ? ' (Moderationsfassung – nicht an Teilnehmende geben)' : '' ) . "\n\n";
		$md    .= '> Synthetischer Referenzkurs aus dem Paket `cc-fx-rk-v1` (CC0, R0-26 §1.1). Alle Firmen, Personen und Adressen sind erfunden. Erklärungstexte zu Lösungen entfallen, weil die freie Version sie nicht ausliefert (E-20).' . "\n\n";
		$md    .= "## 1. Kurs (AU-T01)\n\n- **Titel:** " . $def['title'] . "\n- **Beschreibung:** " . $def['summary'] . "\n- **Lernziele:**\n";
		foreach ( (array) $def['objectives'] as $objective ) {
			$md .= '  - ' . $objective . "\n";
		}
		$md .= "\n## 2. Module und Lektionen (AU-T02, AU-T03)\n\n";
		if ( $moderation ) {
			$md .= "> Eingebauter Fehler AU-T02: In Modul 1 steht die letzte Lektion in diesem Drehbuch an erster Stelle. Die Person baut zuerst nach Drehbuch auf; danach reicht die Moderation die Karte AU-T02b: „Die Lektion an Position 1 von Modul 1 gehört ans Ende des Moduls. Verschiebe sie.“ Verschieben per Maus **oder** Tastatur (A11Y-008). Soll = Reihenfolge im Soll-Zustand.\n\n";
		}
		$medien = array(
			'video_internal' => 'Video (intern) mit Untertiteln: Datei `' . $key . '-video.mp4`, Untertitel `' . $key . '-video.vtt`',
			'video_external' => 'Video (extern): Adresse vom Moderationsblatt',
			'file'           => 'Datei (PDF) aus dem Medienordner',
			'quiz'           => 'Text mit Inline-Quiz (3 Fragen, siehe Abschnitt 3)',
			'assignment'     => 'Aufgabe (siehe Abschnitt 5)',
			'lesson_confirm' => 'Text, Abschluss durch „gelesen bestätigen“',
			'lesson'         => 'Text',
		);
		foreach ( (array) $def['modules'] as $m => $module ) {
			$md     .= '### Modul ' . ( $m + 1 ) . ': ' . $module['title'] . "\n\n";
			$lessons = (array) $module['lessons'];
			if ( 0 === $m ) {
				array_unshift( $lessons, array_pop( $lessons ) );
			}
			foreach ( $lessons as $l => $lesson ) {
				$md .= ( $l + 1 ) . '. **' . $lesson[1] . '** – ' . $medien[ $lesson[2] ] . ( $moderation && 0 === $m && 0 === $l ? ' **[eingebauter Fehler AU-T02, Soll: Position ' . count( $lessons ) . ']**' : '' );
				if ( 'file' === $lesson[2] ) {
					$md .= ' `' . $key . '-' . $lesson[0] . '.pdf`';
				}
				$md .= "\n\n   " . trim( wp_strip_all_tags( str_replace( array( '</p>', '</li>' ), array( "</p>\n\n   ", "</li>\n   " ), (string) $lesson[3] ) ) ) . "\n\n";
			}
		}
		$md .= "## 3. Fragen\n\nAU-T04 (drei Fragen) und AU-T05 (sieben Fragen) legen Sie selbst an. Die übrigen 40 Fragen kommen aus der Datei `" . $key . "-q40.zip` (AU-T06).\n\n";
		foreach ( array(
			't04' => 'AU-T04 – drei Fragen',
			't05' => 'AU-T05 – sieben Fragen',
		) as $part => $label ) {
			$md .= '### ' . $label . "\n\n";
			foreach ( $manual[ $part ] as $pos ) {
				$q   = $def['modules'][ $pos[0] ]['questions'][ $pos[1] ];
				$md .= ( ++$n ) . '. ' . self::question_md( $q, $def, $key, $moderation ) . "\n";
			}
			$md .= "\n";
		}
		$exam = (array) $def['exam'];
		$md  .= "## 4. Prüfung (AU-T07)\n\n- **Bezeichnung:** " . $exam['version'] . "\n";
		if ( isset( $exam['blueprint'] ) ) {
			$total = array_sum( (array) $exam['blueprint'] );
			$md   .= '- **Fragen:** ' . $total . ' zufällig aus den Modulen: ';
			$parts = array();
			foreach ( (array) $exam['blueprint'] as $i => $count ) {
				$shown   = ( 'rk2' === $key && 3 === $i ) ? 13 : $count;
				$parts[] = 'Modul ' . ( $i + 1 ) . ': ' . $shown . ( $moderation && $shown !== $count ? ' **[eingebauter Fehler AU-T09, Soll ' . $count . ']**' : '' );
			}
			$md .= implode( ', ', $parts ) . "\n";
		} else {
			$md .= '- **Fragen:** ' . array_sum( (array) $exam['fixed'] ) . " feste Fragen (die ersten Fragen jedes Moduls ohne Karteikarten)\n";
		}
		$md    .= '- **Zeit:** ' . ( null === $exam['time_limit'] ? 'ohne Zeitlimit' : ( (int) $exam['time_limit'] / 60 ) . ' Minuten (' . ( 'strict' === $exam['timer_mode'] ? 'feste Frist' : 'Übungsmodus' ) . ')' ) . "\n";
		$md    .= '- **Bestehen:** ab ' . round( 100 * $exam['pass'][0] / $exam['pass'][1] ) . ' %' . ( is_array( $exam['parts'] ) ? ' gesamt und je Modul mindestens ' . round( 100 * $exam['parts'][0] / $exam['parts'][1] ) . ' %' : '' ) . "\n";
		$md    .= '- **Versuche:** ' . ( null === $exam['attempts'] ? 'unbegrenzt' : $exam['attempts'] ) . "\n- **Ergebnis:** nach der Abgabe\n- **Nachweis:** " . $def['certificate'] . "\n\n";
		$a      = (array) $def['assignment'];
		$md    .= "## 5. Aufgabe (AU-T12)\n\n- **Aufgabenstellung:** " . $a['instructions'] . "\n- **Abgabeart:** " . array(
			'text'          => 'Text',
			'file'          => 'Datei-Upload',
			'text_and_file' => 'Text und Datei',
		)[ $a['submission_type'] ] . ( isset( $a['file_rules'] ) ? ' (erlaubt: ' . implode( ', ', $a['file_rules']['allowlist'] ) . ')' : '' ) . "\n- **Punkte:** höchstens " . $a['max_points'] . ( null === $a['passing_points'] ? ', ohne Bestehensgrenze' : ', bestanden ab ' . $a['passing_points'] ) . "\n- **Abschluss:** " . array(
			'graded'    => 'wenn bewertet',
			'passed'    => 'wenn bestanden',
			'submitted' => 'wenn eingereicht',
		)[ $a['completion'] ] . "\n- **Bewertungshinweis (nur für Bewertende):** " . $a['rubric_text'] . "\n\n";
		$access = (array) $def['access'];
		$md    .= "## 6. Zugang (AU-T08, AU-T11)\n\n- " . array(
			'free_limited' => 'Kostenlose Selbstanmeldung; ohne Anmeldung sind die ersten ' . ( $access['first_items'] ?? 3 ) . ' Lektionen frei',
			'manual'       => 'Manuelle Freischaltung durch das Kursteam (AU-T11: Testnutzer nur für Modul 1 und 2 freischalten, E-27)',
			'group'        => 'Gruppe mit ' . ( $access['seats'] ?? 5 ) . ' Plätzen; Modul 2 wird ' . ( $access['drip_days'] ?? 1 ) . ' Tag nach dem Zugangsbeginn freigeschaltet',
		)[ $access['mode'] ] . "\n";
		if ( $moderation ) {
			$md .= "\n## 7. Eingebaute Fehler und Soll (nur Moderation)\n\n- AU-T09 Alt-Text: " . ( 'rk3' === $key ? 'Die Bildfrage in AU-T05 nennt im Teilnehmenden-Drehbuch **keinen** Alt-Text.' : 'Eine Bildfrage im Paket `' . $key . '-q40.zip` hat **keinen** Alt-Text.' ) . "\n";
			$md .= '- AU-T09 Blueprint: ' . ( 'rk2' === $key ? 'Modul 4 verlangt 13 Fragen, der Pool hat 12. Soll: 7.' : 'nur RK-2.' ) . "\n- Soll-Datei: `" . $key . '-soll.json`, Prüfung nach der Sitzung: `wp ccap-ux check-rk ' . $key . " --kurs=<ID>`.\n";
		}
		return $md;
	}

	/**
	 * Eine Frage als Markdown.
	 *
	 * @param array<int|string, mixed> $q          Frage.
	 * @param array<string, mixed>     $def        Kursdefinition.
	 * @param string                   $key        Kurs-Schlüssel.
	 * @param bool                     $moderation Lösungen zeigen.
	 * @return string
	 */
	private static function question_md( array $q, array $def, string $key, bool $moderation ): string {
		$labels = array(
			's'  => 'Eine richtige Antwort',
			'm'  => 'Mehrere richtige Antworten',
			'tf' => 'Wahr/Falsch',
			'o'  => 'Freitext mit Musterantwort',
			'f'  => 'Karteikarte',
			'ma' => 'Zuordnung',
			'or' => 'Reihenfolge',
			'cl' => 'Lückentext',
			'n'  => 'Zahl mit erlaubter Abweichung',
		);
		$md     = '**' . $labels[ $q[0] ] . ':** ' . $q[1] . "\n";
		if ( isset( $q['img'] ) ) {
			$alt = (string) $def['images'][ $q['img'] ]['alt'];
			$md .= '   - Bild: `' . $q['img'] . '.png`' . ( 'rk3' === $key && ! $moderation ? '' : ( '' === $alt ? ' (dekorativ)' : ' – Alt-Text: „' . $alt . '“' ) ) . "\n";
		}
		switch ( $q[0] ) {
			case 's':
			case 'm':
				foreach ( (array) $q[2] as $right ) {
					$md .= '   - [x] ' . $right . "\n";
				}
				foreach ( (array) $q[3] as $wrong ) {
					$md .= '   - [ ] ' . $wrong . "\n";
				}
				break;
			case 'tf':
				$md .= '   - Richtig ist: ' . ( $q[2] ? 'Wahr' : 'Falsch' ) . "\n";
				break;
			case 'ma':
				foreach ( (array) $q[2] as $left => $right ) {
					$md .= '   - ' . $left . ' → ' . $right . "\n";
				}
				break;
			case 'n':
				$md .= '   - Sollwert ' . $q[2] . ( '' !== $q[5] ? ' ' . $q[5] : '' ) . ', erlaubte Abweichung ' . $q[4] . ( 'relative' === $q[3] ? ' %' : '' ) . "\n";
				break;
		}
		return $md;
	}

	/**
	 * Activity-Typ einer Lektionsart.
	 *
	 * @param string $kind Lektionsart aus dem Inhalt.
	 * @return string
	 */
	private static function activity_type( string $kind ): string {
		return array(
			'lesson'         => 'lesson',
			'lesson_confirm' => 'lesson',
			'video_internal' => 'video',
			'video_external' => 'video',
			'file'           => 'file',
			'quiz'           => 'quiz',
			'assignment'     => 'assignment',
		)[ $kind ];
	}

	/**
	 * Enthält die Auswahl schon einen Typ?
	 *
	 * @param list<array{int, int}> $picked Auswahl.
	 * @param array<string, mixed>  $def  Kursdefinition.
	 * @param string                $type Typkürzel.
	 * @return bool
	 */
	private static function has_type( array $picked, array $def, string $type ): bool {
		foreach ( $picked as $pos ) {
			if ( $type === $def['modules'][ $pos[0] ]['questions'][ $pos[1] ][0] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Enthält die Auswahl schon eine Bildfrage?
	 *
	 * @param list<array{int, int}> $picked Auswahl.
	 * @param array<string, mixed>  $def  Kursdefinition.
	 * @return bool
	 */
	private static function has_image( array $picked, array $def ): bool {
		foreach ( $picked as $pos ) {
			if ( isset( $def['modules'][ $pos[0] ]['questions'][ $pos[1] ]['img'] ) ) {
				return true;
			}
		}
		return false;
	}
}
