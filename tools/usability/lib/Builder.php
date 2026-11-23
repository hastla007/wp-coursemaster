<?php
/**
 * Baut die Referenzkurse aus `content/rk-v1.php` über die Plugin-Dienste auf
 * (R0-26 §1.1 „Referenzstand für Lernendentests“). Nur für Test-Sites.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tools\Usability;

use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Activity\MediaLibrary;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Storage\Database;

/**
 * Referenzkurse, Fragen, Prüfungen, Aufgaben und Zugangsregeln.
 */
final class Builder {

	public const VIDEO_SECONDS = 30;

	/**
	 * Medien je Schlüssel.
	 *
	 * @var array<string, array{attachment: int, url: string, media_uid: string, file: string}>
	 */
	private array $media = array();

	/**
	 * Hinweise für die Ausgabe.
	 *
	 * @var list<string>
	 */
	public array $notes = array();

	/**
	 * Konstruktor.
	 *
	 * @param int                  $admin   Admin-Konto (Autor der Inhalte).
	 * @param array<string, mixed> $content Paketinhalt.
	 */
	public function __construct( private int $admin, private array $content ) {
	}

	/**
	 * Ablage der Paketmedien.
	 *
	 * @return array{dir: string, url: string}
	 */
	public static function upload_dir(): array {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( (string) $uploads['basedir'] ) . 'cc-fx-rk-v1';
		wp_mkdir_p( $dir );
		return array(
			'dir' => $dir,
			'url' => set_url_scheme( trailingslashit( (string) $uploads['baseurl'] ) . 'cc-fx-rk-v1', 'https' ),
		);
	}

	/**
	 * Alle Medien erzeugen und als Anhang bzw. Kursmedium registrieren.
	 *
	 * @return void
	 */
	public function media(): void {
		$target = self::upload_dir();
		$admin  = Actor::user( $this->admin );
		foreach ( (array) $this->content['courses'] as $key => $course ) {
			foreach ( (array) $course['images'] as $image_key => $image ) {
				$file = $target['dir'] . '/' . $image_key . '.png';
				Media::image( (string) $image['kind'], $file );
				$this->attach( $image_key, $file, 'image/png', (string) $image['alt'], null );
			}
			$video = $target['dir'] . '/' . $key . '-video.mp4';
			if ( Media::video( self::VIDEO_SECONDS, (string) $course['title'], $video ) ) {
				$this->attach( $key . '-video', $video, 'video/mp4', '', $admin );
			} else {
				$this->notes[] = 'Kein ffmpeg: ' . $key . ' nutzt einen Video-Platzhalter. Eigenes MP4 nachreichen (siehe Vorbereitung §3).';
			}
			$vtt = $target['dir'] . '/' . $key . '-video.vtt';
			Media::vtt( self::cues( $course ), self::VIDEO_SECONDS, $vtt );
			$this->attach( $key . '-vtt', $vtt, 'text/vtt', '', null );
			foreach ( (array) $course['modules'] as $module ) {
				foreach ( (array) $module['lessons'] as $lesson ) {
					if ( 'file' !== $lesson[2] ) {
						continue;
					}
					$file = $target['dir'] . '/' . $key . '-' . $lesson[0] . '.pdf';
					Media::pdf( (string) $lesson[1], self::lines( (string) $lesson[3] ), $file );
					$this->attach( $key . '-' . $lesson[0], $file, 'application/pdf', '', $admin );
				}
			}
		}
		// Upload files for prepared submissions (R0-26 §1.1 „vorbereitete Abgaben“).
		Media::pdf( 'Umsatzauswertung (Beispielabgabe gut)', array( 'Summe der Monatsumsätze: 25.200 Euro', 'Mittelwert: 2.100 Euro', 'Diagramm: Säulendiagramm mit Titel „Monatsumsatz“ und beschrifteten Achsen', 'Textbeschreibung: Der Umsatz steigt von 1.200 Euro im Januar auf 3.000 Euro im Dezember.' ), $target['dir'] . '/abgabe-gut.pdf' );
		Media::pdf( 'Umsatzauswertung (Beispielabgabe mit Lücken)', array( 'Summe der Monatsumsätze: 25.200 Euro', 'Mittelwert: 2.100 Euro', 'Diagramm: Säulendiagramm ohne Titel, Achsen nicht beschriftet' ), $target['dir'] . '/abgabe-datei.pdf' );
		Media::pdf( 'Umsatzauswertung (unvollständige Beispielabgabe)', array( 'Summe der Monatsumsätze: 25.200 Euro', 'Mittelwert und Diagramm fehlen noch.' ), $target['dir'] . '/abgabe-unvollstaendig.pdf' );
	}

	/**
	 * Medium eines Schlüssels.
	 *
	 * @param string $key Schlüssel.
	 * @return array{attachment: int, url: string, media_uid: string, file: string}|null
	 */
	public function medium( string $key ): ?array {
		return $this->media[ $key ] ?? null;
	}

	/**
	 * Einen Referenzkurs vollständig anlegen und veröffentlichen.
	 *
	 * @param string $key Kurs-Schlüssel (rk1|rk2|rk3).
	 * @return array<string, mixed> IDs (course, modules, lessons, questions, rule, variant, assignment).
	 */
	public function course( string $key ): array {
		$def       = (array) $this->content['courses'][ $key ];
		$admin     = Actor::user( $this->admin );
		$repo      = QuestionRepository::current();
		$structure = CourseStructure::current();
		$course    = $this->post( 'ccap_course', (string) $def['slug'], (string) $def['title'], self::description( $def ), 0, 0, (string) $def['summary'] );
		PolicyService::flush();
		$map = array(
			'key'       => $key,
			'course'    => $course,
			'modules'   => array(),
			'lessons'   => array(),
			'questions' => array(),
			'inline'    => array(),
		);
		foreach ( (array) $def['modules'] as $m => $module ) {
			$module_id          = $this->post( 'ccap_module', $def['slug'] . '-m' . ( $m + 1 ), (string) $module['title'], '', 0, $m );
			$map['modules'][]   = $module_id;
			$map['questions'][] = array();
			$structure->assign_module( $admin, $course, $module_id, ( $m + 1 ) * 10, true );
			foreach ( (array) $module['lessons'] as $l => $lesson ) {
				$lesson_id                        = $this->post( 'ccap_lesson', $def['slug'] . '-' . $lesson[0], (string) $lesson[1], (string) $lesson[3], $module_id, $l );
				$map['lessons'][ $lesson[0] ]     = $lesson_id;
				$map['lesson_type'][ $lesson[0] ] = (string) $lesson[2];
			}
			foreach ( (array) $module['questions'] as $q => $question ) {
				$data = $this->question_data( $question, $key, $m, $q );
				$id   = $repo->create( $admin, $data );
				$repo->publish( $admin, $id );
				$repo->add_to_pool( $admin, $module_id, $id );
				$map['questions'][ $m ][] = $id;
				if ( ! empty( $question['inline'] ) ) {
					$map['inline'][] = $id;
				}
			}
		}
		foreach ( (array) $def['modules'] as $module ) {
			foreach ( (array) $module['lessons'] as $lesson ) {
				$this->activity( $key, $def, $lesson, (int) $map['lessons'][ $lesson[0] ], $map );
			}
		}
		CoursePublisher::current()->publish( $admin, $course, 'minor', 'cc-fx-rk-v1 Referenzstand' );
		$map['rule']    = $this->exam( $def, $map );
		$map['variant'] = $map['variant'] ?? null;
		$this->access( $def, $map );
		$map['assignment'] = (int) $map['lessons'][ $def['assignment']['lesson'] ];
		return $map;
	}

	/**
	 * Activity einer Lektion.
	 *
	 * @param string               $key    Kurs-Schlüssel.
	 * @param array<string, mixed> $def    Kursdefinition.
	 * @param array<int, mixed>    $lesson Lektionsdefinition.
	 * @param int                  $id     Lektion.
	 * @param array<string, mixed> $map    IDs (für Inline-Fragen).
	 * @return void
	 */
	private function activity( string $key, array $def, array $lesson, int $id, array $map ): void {
		$admin    = Actor::user( $this->admin );
		$service  = ActivityService::current();
		$type     = (string) $lesson[2];
		$settings = array();
		$rule     = array();
		switch ( $type ) {
			case 'lesson':
			case 'lesson_confirm':
				$type = 'lesson';
				$rule = array( 'type' => 'manual_confirm' );
				break;
			case 'video_internal':
				$type     = 'video';
				$video    = $this->medium( $key . '-video' );
				$vtt      = $this->medium( $key . '-vtt' );
				$settings = null === $video
					? array( 'placeholder' => true )
					: array(
						'provider'         => 'media',
						'media_uid'        => $video['media_uid'],
						'duration_seconds' => self::VIDEO_SECONDS,
					);
				if ( null !== $vtt ) {
					$settings += array(
						'subtitles'    => 'vtt',
						'subtitle_url' => $vtt['url'],
					);
				}
				$rule = array( 'type' => 'position_ratio' );
				break;
			case 'video_external':
				$type     = 'video';
				$external = (string) getenv( 'CCAP_UX_EXTERNAL_VIDEO_URL' );
				$fallback = $this->medium( $key . '-video' );
				if ( '' !== $external ) {
					$host     = (string) wp_parse_url( $external, PHP_URL_HOST );
					$provider = str_contains( $host, 'youtu' ) ? 'youtube' : ( str_contains( $host, 'vimeo' ) ? 'vimeo' : 'url' );
					$settings = array(
						'provider'         => $provider,
						'url'              => $external,
						'duration_seconds' => self::VIDEO_SECONDS,
					);
				} elseif ( null !== $fallback ) {
					$settings      = array(
						'provider'         => 'url',
						'url'              => $fallback['url'],
						'duration_seconds' => self::VIDEO_SECONDS,
					);
					$this->notes[] = $key . ': externes Video zeigt auf die eigene Site. Für E-45a einen Fremdhost über CCAP_UX_EXTERNAL_VIDEO_URL setzen.';
				} else {
					$settings = array( 'placeholder' => true );
				}
				$rule = array( 'type' => 'position_ratio' );
				break;
			case 'file':
				$file     = $this->medium( $key . '-' . $lesson[0] );
				$settings = array( 'media_uid' => null === $file ? '' : $file['media_uid'] );
				$rule     = array( 'type' => 'downloaded' );
				break;
			case 'quiz':
				$settings = array(
					'source' => 'inline',
					'scored' => true,
				);
				$rule     = array( 'type' => 'attempted' );
				$db       = Database::current();
				foreach ( (array) $map['inline'] as $i => $question ) {
					$db->insert(
						'ccap_activity_question',
						array(
							'activity_id' => $id,
							'question_id' => (int) $question,
							'sort_order'  => ( $i + 1 ) * 10,
						)
					);
				}
				break;
			case 'assignment':
				$a        = (array) $def['assignment'];
				$settings = array_filter(
					array(
						'submission_type' => $a['submission_type'],
						'max_points'      => $a['max_points'],
						'passing_points'  => $a['passing_points'],
						'instructions'    => $a['instructions'],
						'rubric_text'     => $a['rubric_text'],
						'text_limits'     => $a['text_limits'] ?? null,
						'file_rules'      => $a['file_rules'] ?? null,
					),
					static fn( $v ): bool => null !== $v
				);
				$rule     = array( 'type' => $a['completion'] );
				break;
		}
		$service->define( $admin, $id, $type, $settings, $rule );
	}

	/**
	 * Prüfung nach Drehbuch (Blueprint oder feste Variante), veröffentlicht.
	 *
	 * @param array<string, mixed> $def Kursdefinition.
	 * @param array<string, mixed> $map IDs.
	 * @return int Regel-ID.
	 */
	private function exam( array $def, array &$map ): int {
		$exam     = (array) $def['exam'];
		$rules    = ExamRules::current();
		$admin    = Actor::user( $this->admin );
		$passing  = array(
			'min_ratio' => array(
				'num' => (int) $exam['pass'][0],
				'den' => (int) $exam['pass'][1],
			),
		);
		$sections = array();
		if ( is_array( $exam['parts'] ) ) {
			foreach ( $map['modules'] as $module ) {
				$sections[] = array(
					'section'   => (int) $module,
					'min_ratio' => array(
						'num' => (int) $exam['parts'][0],
						'den' => (int) $exam['parts'][1],
					),
				);
			}
			$passing['parts'] = $sections;
		}
		$overrides = array(
			'exams.max_attempts' => $exam['attempts'],
			'exams.timer_mode'   => $exam['timer_mode'],
			'exams.feedback'     => array(
				'visibility'                => $exam['feedback'],
				'show_score'                => true,
				'show_correct_answers'      => true,
				'show_correct_answers_when' => 'no_attempts_left_or_passed',
			),
		);
		if ( null === $exam['time_limit'] ) {
			$overrides['exams.time_limit_minutes'] = null;
		}
		$settings = array( 'overrides' => $overrides );
		if ( 'practice' === $exam['timer_mode'] ) {
			$settings['purpose'] = 'practice';
		}
		$rule = $rules->create( $admin, (int) $map['course'], (string) $exam['version'], $passing, null === $exam['time_limit'] ? null : (int) $exam['time_limit'], $settings );
		if ( isset( $exam['blueprint'] ) ) {
			foreach ( (array) $exam['blueprint'] as $i => $count ) {
				$rules->set_blueprint( $admin, $rule, (int) $map['modules'][ $i ], (int) $count );
			}
		} else {
			$questions = array();
			foreach ( (array) $exam['fixed'] as $i => $count ) {
				$pool      = array_values( array_filter( (array) $map['questions'][ $i ], static fn( int $q ): bool => 'flashcard' !== self::type_of( $q ) ) );
				$questions = array_merge( $questions, array_slice( $pool, 0, (int) $count ) );
			}
			$map['variant'] = $rules->create_variant( $admin, (int) $map['course'], 1, $questions );
		}
		$rules->publish( $admin, $rule );
		return $rule;
	}

	/**
	 * Zugangsregeln: Teilumfang (RK-1), Drip Modul 2 ab Tag n (RK-3).
	 *
	 * @param array<string, mixed> $def Kursdefinition.
	 * @param array<string, mixed> $map IDs.
	 * @return void
	 */
	private function access( array $def, array $map ): void {
		$db     = Database::current();
		$access = (array) $def['access'];
		if ( 'free_limited' === $access['mode'] ) {
			$db->insert(
				'ccap_access_rule',
				array(
					'course_id'   => (int) $map['course'],
					'scope'       => 'view',
					'access_type' => 'free_limited',
					'limit_type'  => 'first_items',
					'limit_value' => (int) $access['first_items'],
				)
			);
		}
		if ( isset( $access['drip_days'] ) ) {
			$db->insert(
				'ccap_access_rule',
				array(
					'course_id'   => (int) $map['course'],
					'module_id'   => (int) $map['modules'][1],
					'scope'       => 'view',
					'access_type' => 'drip',
					'limit_type'  => 'days',
					'limit_value' => (int) $access['drip_days'],
				)
			);
		}
		PolicyService::flush();
	}

	/**
	 * Revisionsdaten einer Frage.
	 *
	 * @param array<int|string, mixed> $q   Fragedefinition.
	 * @param string                   $key Kurs-Schlüssel.
	 * @param int                      $m   Modulindex.
	 * @param int                      $n   Fragenindex.
	 * @return array<string, mixed>
	 */
	public function question_data( array $q, string $key, int $m, int $n ): array {
		$text = '<p>' . esc_html( (string) $q[1] ) . '</p>';
		if ( isset( $q['img'] ) ) {
			$image = $this->content['courses'][ $key ]['images'][ $q['img'] ];
			$media = $this->medium( (string) $q['img'] );
			$src   = null === $media ? '' : $media['url'];
			$alt   = (string) $image['alt'];
			$text  = '<figure><img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '"' . ( '' === $alt ? ' role="presentation"' : '' ) . ' width="800" height="450"></figure>' . $text;
		}
		$data = array(
			'question_type' => self::TYPES[ $q[0] ],
			'question_text' => $text,
			'reference_no'  => sprintf( 'cc-fx-%s-m%d-q%02d', $key, $m + 1, $n + 1 ),
			'language'      => 'de_DE',
			'answers'       => array(),
			'settings'      => array(),
		);
		switch ( $q[0] ) {
			case 's':
			case 'm':
				$right   = (array) $q[2];
				$letters = range( 'A', 'Z' );
				$i       = 0;
				foreach ( array_merge( $right, (array) $q[3] ) as $j => $option ) {
					$data['answers'][] = array(
						'key'     => $letters[ $i++ ],
						'text'    => (string) $option,
						'correct' => $j < count( $right ),
					);
				}
				break;
			case 'tf':
				$data['answers'] = array(
					array(
						'key'     => 'T',
						'text'    => 'Wahr',
						'correct' => true === $q[2],
					),
					array(
						'key'     => 'F',
						'text'    => 'Falsch',
						'correct' => false === $q[2],
					),
				);
				break;
			case 'o':
				$data['answers'] = array(
					array(
						'key'     => 'MODEL',
						'text'    => (string) $q[2],
						'correct' => true,
					),
				);
				break;
			case 'f':
				$data['settings'] = array( 'back' => (string) $q[2] );
				break;
			case 'ma':
				foreach ( (array) $q[2] as $left => $right ) {
					$data['settings']['pairs'][] = array(
						'left'  => (string) $left,
						'right' => (string) $right,
					);
				}
				break;
			case 'or':
				foreach ( (array) $q[2] as $item ) {
					$data['settings']['items'][] = array( 'text' => (string) $item );
				}
				break;
			case 'cl':
				foreach ( (array) $q[2] as $gap => $accepted ) {
					$data['settings']['gaps'][] = array(
						'id'       => (string) $gap,
						'accepted' => array_values( array_map( 'strval', (array) $accepted ) ),
					);
				}
				break;
			case 'n':
				$data['settings'] = array(
					'target'         => (string) $q[2],
					'tolerance_type' => (string) $q[3],
					'tolerance'      => (string) $q[4],
					'unit'           => (string) $q[5],
				);
				break;
		}
		return $data;
	}

	public const TYPES = array(
		's'  => 'single_choice',
		'm'  => 'multi_choice',
		'tf' => 'true_false',
		'o'  => 'open_text',
		'f'  => 'flashcard',
		'ma' => 'matching',
		'or' => 'ordering',
		'cl' => 'cloze',
		'n'  => 'numeric_tolerance',
	);

	/**
	 * Fragetyp der aktuellen Revision.
	 *
	 * @param int $question Frage.
	 * @return string
	 */
	public static function type_of( int $question ): string {
		$db = Database::current();
		return (string) $db->scalar( $db->prepare( 'SELECT r.question_type FROM `' . $db->table( 'ccap_question' ) . '` q JOIN `' . $db->table( 'ccap_question_revision' ) . '` r ON r.id = q.current_revision_id WHERE q.id = %d', $question ) );
	}

	/**
	 * Beitrag anlegen (veröffentlicht, deterministischer Slug).
	 *
	 * @param string $type      Typ.
	 * @param string $slug      Slug.
	 * @param string $title     Titel.
	 * @param string $content   Inhalt.
	 * @param int    $parent_id Eltern.
	 * @param int    $order     Reihenfolge.
	 * @param string $excerpt   Kurzbeschreibung.
	 * @return int
	 * @throws \RuntimeException Wenn WordPress den Beitrag ablehnt.
	 */
	private function post( string $type, string $slug, string $title, string $content, int $parent_id, int $order, string $excerpt = '' ): int {
		$id = wp_insert_post(
			array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_name'    => $slug,
				'post_title'   => $title,
				'post_content' => $content,
				'post_excerpt' => $excerpt,
				'post_parent'  => $parent_id,
				'menu_order'   => $order,
				'post_author'  => $this->admin,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( esc_html( $id->get_error_message() ) );
		}
		return (int) $id;
	}

	/**
	 * Datei als Anhang; mit Actor zusätzlich als geschütztes Kursmedium.
	 *
	 * @param string     $key   Schlüssel.
	 * @param string     $file  Datei.
	 * @param string     $mime  MIME-Typ.
	 * @param string     $alt   Alt-Text (Bilder).
	 * @param Actor|null $actor Actor für die Registrierung als Kursmedium.
	 * @return void
	 * @throws \RuntimeException Wenn WordPress den Anhang ablehnt.
	 */
	private function attach( string $key, string $file, string $mime, string $alt, ?Actor $actor ): void {
		$target     = self::upload_dir();
		$attachment = wp_insert_attachment(
			array(
				'post_mime_type' => $mime,
				'post_title'     => $key,
				'post_status'    => 'inherit',
				'post_author'    => $this->admin,
			),
			$file,
			0,
			true
		);
		if ( is_wp_error( $attachment ) ) {
			throw new \RuntimeException( esc_html( $attachment->get_error_message() ) );
		}
		if ( '' !== $alt ) {
			update_post_meta( (int) $attachment, '_wp_attachment_image_alt', $alt );
		}
		$uid = '';
		if ( null !== $actor ) {
			$media = MediaLibrary::current()->register_attachment( $actor, (int) $attachment );
			$db    = Database::current();
			$uid   = (string) $db->scalar( $db->prepare( 'SELECT uid FROM `' . $db->table( 'ccap_media' ) . '` WHERE id = %d', $media ) );
		}
		$this->media[ $key ] = array(
			'attachment' => (int) $attachment,
			'url'        => $target['url'] . '/' . basename( $file ),
			'media_uid'  => $uid,
			'file'       => $file,
		);
	}

	/**
	 * Kursbeschreibung mit Lernzielen (es gibt kein eigenes Lernziel-Feld).
	 *
	 * @param array<string, mixed> $def Kursdefinition.
	 * @return string
	 */
	public static function description( array $def ): string {
		$html = '<p>' . esc_html( (string) $def['summary'] ) . '</p><h2>Lernziele</h2><ul>';
		foreach ( (array) $def['objectives'] as $objective ) {
			$html .= '<li>' . esc_html( (string) $objective ) . '</li>';
		}
		return $html . '</ul><p><small>Synthetischer Referenzkurs cc-fx-rk-v1 (CC0) für Nutzertests.</small></p>';
	}

	/**
	 * Untertitel aus dem Lektionstext des internen Videos.
	 *
	 * @param array<string, mixed> $course Kursdefinition.
	 * @return list<string>
	 */
	private static function cues( array $course ): array {
		foreach ( (array) $course['modules'] as $module ) {
			foreach ( (array) $module['lessons'] as $lesson ) {
				if ( 'video_internal' === $lesson[2] ) {
					return self::lines( (string) $lesson[3] );
				}
			}
		}
		return array( (string) $course['title'] );
	}

	/**
	 * Sätze eines HTML-Texts.
	 *
	 * @param string $html HTML.
	 * @return list<string>
	 */
	public static function lines( string $html ): array {
		$text  = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( str_replace( array( '</p>', '</li>' ), array( '</p> ', '</li>. ' ), $html ) ) ) );
		$parts = preg_split( '/(?<=[.!?])\s+/u', $text );
		return array_values( array_filter( array_map( 'trim', false === $parts ? array( $text ) : $parts ), static fn( string $s ): bool => '' !== $s && '.' !== $s ) );
	}
}
