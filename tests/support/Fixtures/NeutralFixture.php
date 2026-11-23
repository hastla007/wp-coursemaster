<?php
/**
 * Neutral reference fixture `cc-fixture-neutral-v1` (R0-05 §2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Support\Fixtures;

use CourseCaptain\Access\ContentProtection;
use CourseCaptain\Access\GrantService;
use CourseCaptain\Access\GroupService;
use CourseCaptain\Access\TrialLimits;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Activity\MediaLibrary;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Import\ImportService;
use CourseCaptain\Import\PackageExporter;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Roles;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Storage\Database;

/**
 * Synthetic, rights-free content with stable keys (`cc-fx-` source IDs,
 * `cc_fx_` logins, `fixture.test` addresses). The correct answer is always
 * key `a` in the source; delivery shuffles. PKG-v2 differs only in
 * Q-A1-007 (text addition "(korrigiert)"). The loader is idempotent and
 * never depends on test order (D-03). Times are relative to T0; the loader
 * uses "now" as T0 (e.g. G-7 expires at T0 − 1 d).
 */
final class NeutralFixture {

	public const SYSTEM  = 'cc-fx-neutral-v1';
	public const T0      = '2030-01-15 10:00:00';
	public const MED_005 = 'https://videos.example.test/cc/alpha-07.mp4';

	/**
	 * Package records.
	 *
	 * @param int $version 1 or 2.
	 * @return array<string, list<array<string, mixed>>>
	 */
	public static function records( int $version = 1 ): array {
		$questions = array();
		$plan      = array(
			// Module => [single, multi, open] key ranges per R0-05 §2.3.
			'A1' => array( range( 1, 16 ), range( 17, 21 ), range( 22, 24 ) ),
			'A2' => array( range( 2, 10 ), array_merge( array( 1 ), range( 11, 15 ) ), array( 16 ) ),
			'A3' => array( range( 1, 5 ), range( 6, 9 ), array( 10 ) ),
			'B1' => array( range( 1, 6 ), range( 7, 9 ), range( 10, 12 ) ),
			'B2' => array( range( 1, 4 ), range( 5, 7 ), array( 8 ) ),
		);
		$pools     = array();
		foreach ( $plan as $module => $types ) {
			foreach ( array( 'single_choice', 'multi_choice', 'open_text' ) as $t => $type ) {
				foreach ( $types[ $t ] as $n ) {
					$key  = sprintf( '%s-%03d', $module, $n );
					$text = sprintf( 'Beispielfrage %s: Welche Aussage trifft auf Konzept %d zu?', $key, $n );
					if ( 2 === $version && 'A1-007' === $key ) {
						$text .= ' (korrigiert)';
					}
					$questions[ $key ]  = PackageExporter::sealed(
						array(
							'source_id' => 'cc-fx-q-' . strtolower( $key ),
							'revision'  => array(
								'question_type' => $type,
								'text_html'     => $text,
								'settings'      => array(),
							),
							'answers'   => self::answers( $key, $type ),
							'tags'      => 'A1' === $module ? array(
								array(
									'type'  => 'topic',
									'key'   => $n <= 12 ? 'T-A1-a' : 'T-A1-b',
									'label' => $n <= 12 ? 'T-A1-a' : 'T-A1-b',
								),
							) : array(),
						)
					);
					$pools[ $module ][] = $key;
				}
			}
		}
		foreach ( $pools as $module => $keys ) {
			sort( $keys );
			$pools[ $module ] = $keys;
		}

		$lessons = array();
		$lesson  = static function ( string $id, string $type, array $settings = array() ) use ( &$lessons ): void {
			$lessons[ $id ] = PackageExporter::sealed(
				array(
					'source_id'     => 'cc-fx-' . strtolower( $id ),
					'title'         => 'Lektion ' . $id,
					'body_html'     => '<p>Platzhaltertext für ' . $id . '.</p>',
					'activity_type' => $type,
					'settings'      => $settings,
				)
			);
		};
		foreach ( array( 'L-A-01', 'L-A-02', 'L-A-03', 'L-A-08', 'L-A-09', 'L-B-01', 'L-B-02' ) as $id ) {
			$lesson( $id, 'lesson' );
		}
		// MED-002/MED-003 internal video with subtitles; MED-005 foreign host (§2.6).
		$lesson(
			'L-A-04',
			'video',
			array(
				'provider'         => 'url',
				'url'              => 'https://media.fixture.test/cc/alpha-04.mp4',
				'subtitles'        => 'vtt',
				'subtitle_url'     => 'https://media.fixture.test/cc/alpha-04.vtt',
				'duration_seconds' => 2,
			)
		);
		$lesson( 'L-A-05', 'video', array( 'duration_seconds' => 2 ) );
		$lesson( 'L-A-06', 'video', array( 'placeholder' => true ) );
		$lesson(
			'L-A-07',
			'video',
			array(
				'provider'         => 'url',
				'url'              => self::MED_005,
				'duration_seconds' => 2,
			)
		);

		$module  = static function ( string $id, array $lesson_ids, array $question_keys ): array {
			return PackageExporter::sealed(
				array(
					'source_id'     => 'cc-fx-' . strtolower( $id ),
					'title'         => 'Modul ' . $id,
					'summary'       => '',
					'lessons'       => array_map(
						static fn( string $l, int $i ): array => array(
							'lesson' => 'cc-fx-' . strtolower( $l ),
							'sort'   => $i,
						),
						$lesson_ids,
						array_keys( $lesson_ids )
					),
					'question_pool' => array_map(
						static fn( string $q, int $i ): array => array(
							'question' => 'cc-fx-q-' . strtolower( $q ),
							'sort'     => $i + 1,
						),
						$question_keys,
						array_keys( $question_keys )
					),
				)
			);
		};
		$modules = array(
			'M-A1'  => $module( 'M-A1', array(), $pools['A1'] ),
			'M-A2'  => $module( 'M-A2', array(), $pools['A2'] ),
			'M-A3'  => $module( 'M-A3', array(), $pools['A3'] ),
			'LM-A1' => $module( 'LM-A1', array( 'L-A-01', 'L-A-02', 'L-A-03', 'L-A-04' ), array() ),
			'LM-A2' => $module( 'LM-A2', array( 'L-A-05', 'L-A-06', 'L-A-07' ), array() ),
			'LM-A3' => $module( 'LM-A3', array( 'L-A-08', 'L-A-09' ), array() ),
			'M-B1'  => $module( 'M-B1', array( 'L-B-01', 'L-B-02' ), $pools['B1'] ),
			'M-B2'  => $module( 'M-B2', array(), $pools['B2'] ),
		);
		$course  = static function ( string $id, string $slug, string $title, array $module_ids ): array {
			return PackageExporter::sealed(
				array(
					'source_id'        => 'cc-fx-course-' . $id,
					'title'            => $title,
					'slug'             => $slug,
					'summary'          => '',
					'description_html' => '<p>' . $title . '</p>',
					'modules'          => array_map(
						static fn( string $m, int $i ): array => array(
							'module'   => 'cc-fx-' . strtolower( $m ),
							'sort'     => ( $i + 1 ) * 10,
							'required' => true,
						),
						$module_ids,
						array_keys( $module_ids )
					),
				)
			);
		};
		return array(
			'questions' => array_values( $questions ),
			'lessons'   => array_values( $lessons ),
			'modules'   => array_values( $modules ),
			'courses'   => array(
				$course( 'a', 'cc-a', 'Kurs Alpha', array( 'M-A1', 'M-A2', 'M-A3', 'LM-A1', 'LM-A2', 'LM-A3' ) ),
				$course( 'b', 'cc-b', 'Kurs Beta', array( 'M-B1', 'M-B2' ) ),
			),
		);
	}

	/**
	 * Answers per type: single 4 options (a correct), multi 5 options
	 * (a,b[,c] correct; A2-015/A3-009 all correct), open text model answer.
	 *
	 * @param string $key  Question key.
	 * @param string $type Type.
	 * @return list<array{key: string, text_html: string, correct: bool}>
	 */
	private static function answers( string $key, string $type ): array {
		if ( 'open_text' === $type ) {
			return array(
				array(
					'key'       => $key . '-a',
					'text_html' => 'Musterantwort ' . $key,
					'correct'   => true,
				),
			);
		}
		$letters = 'single_choice' === $type ? array( 'a', 'b', 'c', 'd' ) : array( 'a', 'b', 'c', 'd', 'e' );
		$all     = in_array( $key, array( 'A2-015', 'A3-009' ), true );
		$right   = 'single_choice' === $type ? 1 : ( $all ? 5 : 2 + ( (int) substr( $key, -1 ) % 2 ) );
		$out     = array();
		foreach ( $letters as $i => $letter ) {
			$out[] = array(
				'key'       => $key . '-' . $letter,
				'text_html' => 'Antwort ' . strtoupper( $letter ) . ' zu ' . $key,
				'correct'   => $i < $right,
			);
		}
		return $out;
	}

	/**
	 * Write PKG-v1 / PKG-v2.
	 *
	 * @param int    $version Version.
	 * @param string $file    Target.
	 * @return string
	 */
	public static function package( int $version, string $file ): string {
		return PackageExporter::write( self::records( $version ), self::SYSTEM, 'cc-fixture-neutral-v1 PKG-v' . $version, $file );
	}

	/**
	 * Idempotent loader (§2.2–§2.8): users and roles, PKG-v1, C-C (with the
	 * deliberately broken M-C1/ER-C) and C-D, exam rules ER-A/ER-B with the
	 * fixed variants EP-A-1…3/EP-B-1, media MED-001…004 and downloads
	 * DL-A-1…3, pages PAGE-1/PAGE-2, grants G-1/G-2/G-7/G-10, groups
	 * GRP-1/GRP-2, partial access AR-01. Rules that change the access of the
	 * base users (AR-02…AR-08, G-3) and the flashcard decks FC-A/FC-B are
	 * separate on-demand steps (`rule()`, `grant()`, `flashcards()`), so the
	 * loader result never depends on test order (D-03).
	 *
	 * @param string $dir Scratch directory for the package.
	 * @return array{users: array<string, int>, courses: array<string, int>, modules: array<string, int>, rules: array<string, int>, variants: array<string, int>, media: array<string, int>, pages: array<string, int>, groups: array<string, int>}
	 */
	public static function load( string $dir ): array {
		$users = array();
		foreach ( array(
			'admin'       => 'administrator',
			'editor'      => 'editor',
			'author'      => 'author',
			'lea'         => 'subscriber',
			'ole'         => 'subscriber',
			'free'        => 'subscriber',
			'newcomer'    => 'subscriber',
			'group_owner' => 'subscriber',
			'other'       => 'subscriber',
			'tutor'       => Roles::TRAINER,
			'manager'     => Roles::COURSE_MANAGER,
			'seat_1'      => 'subscriber',
			'seat_2'      => 'subscriber',
			'seat_3'      => 'subscriber',
			'seat_4'      => 'subscriber',
		) as $login => $role ) {
			$name            = 'cc_fx_' . $login;
			$found           = get_user_by( 'login', $name );
			$id              = $found instanceof \WP_User ? (int) $found->ID : (int) wp_insert_user(
				array(
					'user_login'      => $name,
					'user_email'      => $name . '@fixture.test',
					'user_pass'       => wp_generate_password( 24 ),
					'role'            => $role,
					// U-L4: registered T0 − 40 d (drip from registration, GM-083).
					'user_registered' => 'newcomer' === $login ? gmdate( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS ) : '',
				)
			);
			$users[ $login ] = $id;
		}
		$admin = Actor::user( $users['admin'] );
		PolicyService::flush();
		if ( null === self::object( 'course', 'cc-fx-course-a' ) ) {
			$service = ImportService::current();
			$batch   = $service->upload( $admin, self::package( 1, $dir . '/pkg-v1.zip' ) );
			$service->validate( $admin, $batch );
			$service->preview( $admin, $batch );
			$service->approve( $admin, $batch );
			$service->run( $admin, $batch );
		}
		$courses = array(
			'C-A' => (int) self::object( 'course', 'cc-fx-course-a' ),
			'C-B' => (int) self::object( 'course', 'cc-fx-course-b' ),
		);
		foreach ( array(
			'C-C' => array( 'cc-c', 'Kurs Gamma', 'draft' ),
			'C-D' => array( 'cc-d', 'Kurs Delta', 'ccap_archived' ),
		) as $id => $def ) {
			$existing       = get_page_by_path( $def[0], OBJECT, 'ccap_course' );
			$courses[ $id ] = $existing instanceof \WP_Post ? (int) $existing->ID : (int) wp_insert_post(
				array(
					'post_type'   => 'ccap_course',
					'post_name'   => $def[0],
					'post_title'  => $def[1],
					'post_status' => $def[2],
				)
			);
		}
		$modules = array();
		foreach ( array( 'M-A1', 'M-A2', 'M-A3', 'LM-A1', 'LM-A2', 'LM-A3', 'M-B1', 'M-B2' ) as $key ) {
			$modules[ $key ] = (int) self::object( 'module', 'cc-fx-' . strtolower( $key ) );
		}
		$db = Database::current();
		if ( '0' === (string) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_grant' ) . '` WHERE source_id = %s', 'cc-fx-g-1' ) ) ) {
			foreach ( array(
				'cc-fx-g-1' => array( 'lea', 'C-A', null ),
				'cc-fx-g-2' => array( 'ole', 'C-A', null ),
				'cc-fx-g-7' => array( 'other', 'C-B', gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ),
			) as $source => $g ) {
				$db->insert(
					'ccap_grant',
					array(
						'uid'         => $db->uuid(),
						'user_id'     => $users[ $g[0] ],
						'course_id'   => $courses[ $g[1] ],
						'status'      => 'active',
						'source_type' => 'manual',
						'source_id'   => $source,
						'valid_until' => $g[2],
					),
					array( 'created_at', 'updated_at' )
				);
			}
			// AR-01: question bank, the first 3 questions per module (E-332 kind).
			$db->insert(
				'ccap_access_rule',
				array(
					'course_id'   => $courses['C-A'],
					'scope'       => 'view',
					'access_type' => 'free_limited',
					'limit_type'  => TrialLimits::QUESTIONS,
					'limit_value' => 3,
				)
			);
		}
		// U-TUT: trainer of C-A only (GM-079, GM-154).
		$staff = $db->table( 'ccap_course_staff' );
		if ( null === $db->scalar( $db->prepare( "SELECT id FROM `{$staff}` WHERE course_id = %d AND user_id = %d AND role = 'trainer'", $courses['C-A'], $users['tutor'] ) ) ) {
			$db->insert(
				'ccap_course_staff',
				array(
					'course_id'   => $courses['C-A'],
					'user_id'     => $users['tutor'],
					'role'        => 'trainer',
					'group_id'    => 0,
					'assigned_by' => $users['admin'],
				),
				array( 'created_at', 'updated_at' )
			);
		}
		PolicyService::flush();
		$modules['M-C1'] = self::broken_course( $admin, $courses['C-C'] );
		$exams           = self::exams( $admin, $courses, $modules );
		$media           = self::media( $admin, $courses['C-A'] );
		// G-10: U-S1, C-A question bank, until T0 + 60 d.
		GrantService::current()->grant_manual(
			$admin,
			$users['seat_1'],
			$courses['C-A'],
			self::uuid( 'G-10' ),
			'Fixture',
			array(
				'scopes'      => 'view',
				'valid_until' => gmdate( 'Y-m-d H:i:s', time() + 60 * DAY_IN_SECONDS ),
			)
		);
		$groups = array(
			'GRP-1' => GroupService::current()->create(
				$admin,
				$users['group_owner'],
				'Gruppe Eins',
				3,
				array(
					array(
						'course_id' => $courses['C-A'],
						'scopes'    => 'view,exam',
					),
				),
				self::uuid( 'GRP-1' ),
				array(
					'valid_until' => gmdate( 'Y-m-d H:i:s', time() + 365 * DAY_IN_SECONDS ),
					'reason'      => 'Fixture',
				)
			),
			'GRP-2' => GroupService::current()->create(
				$admin,
				$users['admin'],
				'Gruppe Zwei',
				5,
				array( array( 'course_id' => $courses['C-B'] ) ),
				self::uuid( 'GRP-2' ),
				array(
					'valid_until' => gmdate( 'Y-m-d H:i:s', time() + 90 * DAY_IN_SECONDS ),
					'reason'      => 'Fixture',
				)
			),
		);
		PolicyService::flush();
		return array(
			'users'    => $users,
			'courses'  => $courses,
			'modules'  => $modules,
			'rules'    => $exams['rules'],
			'variants' => $exams['variants'],
			'media'    => $media,
			'pages'    => self::pages( $courses['C-A'] ),
			'groups'   => $groups,
		);
	}

	/**
	 * Deterministic UUID (v5 layout) of a fixture key; used as request key.
	 *
	 * @param string $key Fixture key, e.g. `G-10`.
	 * @return string
	 */
	public static function uuid( string $key ): string {
		$h = sha1( self::SYSTEM . ':' . $key );
		return sprintf( '%s-%s-5%s-%s-%s', substr( $h, 0, 8 ), substr( $h, 8, 4 ), substr( $h, 13, 3 ), dechex( 8 | ( hexdec( $h[16] ) & 3 ) ) . substr( $h, 17, 3 ), substr( $h, 20, 12 ) );
	}

	/**
	 * Question ID of a fixture key like `A1-007`.
	 *
	 * @param string $key Key.
	 * @return int
	 */
	public static function question( string $key ): int {
		return (int) self::object( 'question', 'cc-fx-q-' . strtolower( $key ) );
	}

	/**
	 * C-C: module M-C1 without questions and the invalid draft rule ER-C
	 * (blueprint 10 > pool 0, 600 s) for GM-144.
	 *
	 * @param Actor $admin  Admin.
	 * @param int   $course C-C.
	 * @return int M-C1.
	 */
	private static function broken_course( Actor $admin, int $course ): int {
		$found  = get_page_by_path( 'cc-fx-m-c1', OBJECT, 'ccap_module' );
		$module = $found instanceof \WP_Post ? (int) $found->ID : 0;
		if ( 0 === $module ) {
			$module = (int) wp_insert_post(
				array(
					'post_type'   => 'ccap_module',
					'post_name'   => 'cc-fx-m-c1',
					'post_title'  => 'Modul M-C1',
					'post_status' => 'publish',
				)
			);
			CourseStructure::current()->assign_module( $admin, $course, $module );
			$rules = ExamRules::current();
			$rule  = $rules->create(
				$admin,
				$course,
				'cc-fx-er-c',
				array(
					'min_ratio' => array(
						'num' => 1,
						'den' => 2,
					),
				),
				600
			);
			$rules->set_blueprint( $admin, $rule, $module, 10 );
		}
		return $module;
	}

	/**
	 * ER-A, EP-A-1…3 (C-A), ER-B, EP-B-1 (C-B) per §2.4; ER-C is a draft of C-C.
	 *
	 * ER-A: M-A1 7, M-A2 13, M-A3 10 = 30; 3 600 s; pass ≥ 23/30 and part P1
	 * (M-A1) ≥ 5/7 and part P2 (M-A2 + M-A3, a module group, E-331) ≥ 18/23.
	 * P1 uses the single-module form `section` (before E-331, still valid),
	 * P2 the group form `sections`. ER-B: M-B1 6, M-B2 4, no time limit,
	 * ≥ 7/10, `purpose=practice`.
	 *
	 * @param Actor              $admin   Admin.
	 * @param array<string, int> $courses Courses.
	 * @param array<string, int> $modules Modules.
	 * @return array{rules: array<string, int>, variants: array<string, int>}
	 */
	private static function exams( Actor $admin, array $courses, array $modules ): array {
		$db    = Database::current();
		$rules = ExamRules::current();
		$table = $db->table( 'ccap_exam_rule' );
		$out   = array();
		foreach ( array(
			'ER-A' => array(
				'C-A',
				array(
					'M-A1' => 7,
					'M-A2' => 13,
					'M-A3' => 10,
				),
				array( 23, 30 ),
				3600,
				array(),
				array( 'M-A1', 5, 7 ),
			),
			'ER-B' => array(
				'C-B',
				array(
					'M-B1' => 6,
					'M-B2' => 4,
				),
				array( 7, 10 ),
				null,
				array( 'purpose' => 'practice' ),
				null,
			),
		) as $key => $def ) {
			$version = 'cc-fx-' . strtolower( $key );
			$found   = $db->scalar( $db->prepare( "SELECT id FROM `{$table}` WHERE course_id = %d AND version = %s", $courses[ $def[0] ], $version ) );
			if ( null === $found ) {
				$passing = array(
					'min_ratio' => array(
						'num' => $def[2][0],
						'den' => $def[2][1],
					),
				);
				if ( null !== $def[5] ) {
					$passing['parts'] = array(
						array(
							'key'       => 'P1',
							'section'   => $modules[ $def[5][0] ],
							'min_ratio' => array(
								'num' => $def[5][1],
								'den' => $def[5][2],
							),
						),
						array(
							'key'       => 'P2',
							'sections'  => array( $modules['M-A2'], $modules['M-A3'] ),
							'min_ratio' => array(
								'num' => 18,
								'den' => 23,
							),
						),
					);
				}
				$found = $rules->create( $admin, $courses[ $def[0] ], $version, $passing, $def[3], $def[4] );
				foreach ( $def[1] as $module => $count ) {
					$rules->set_blueprint( $admin, (int) $found, $modules[ $module ], $count );
				}
				$rules->publish( $admin, (int) $found );
			}
			$out[ $key ] = (int) $found;
		}
		$range    = static fn( string $module, int $from, int $to ): array => array_map( static fn( int $n ): string => sprintf( '%s-%03d', $module, $n ), range( $from, $to ) );
		$variants = array(
			'EP-A-1' => array( 'C-A', 1, array_merge( $range( 'A1', 1, 7 ), $range( 'A2', 1, 13 ), $range( 'A3', 1, 10 ) ) ),
			'EP-A-2' => array( 'C-A', 2, array_merge( $range( 'A1', 8, 14 ), $range( 'A2', 4, 16 ), $range( 'A3', 1, 10 ) ) ),
			'EP-A-3' => array( 'C-A', 3, array_merge( $range( 'A1', 15, 21 ), array_reverse( $range( 'A2', 2, 14 ) ), array_reverse( $range( 'A3', 1, 10 ) ) ) ),
			'EP-B-1' => array( 'C-B', 1, array_merge( array( 'B1-010' ), $range( 'B1', 1, 5 ), $range( 'B2', 1, 4 ) ) ),
		);
		$vtable   = $db->table( 'ccap_exam_variant' );
		$ids      = array();
		foreach ( $variants as $key => $def ) {
			$found = $db->scalar( $db->prepare( "SELECT id FROM `{$vtable}` WHERE course_id = %d AND variant_no = %d", $courses[ $def[0] ], $def[1] ) );
			if ( null === $found ) {
				$found = $rules->create_variant( $admin, $courses[ $def[0] ], $def[1], array_map( array( self::class, 'question' ), $def[2] ) );
			}
			$ids[ $key ] = (int) $found;
		}
		return array(
			'rules'    => $out,
			'variants' => $ids,
		);
	}

	/**
	 * Synthetic CC0 media (§2.6) in the upload folder `cc-fixture-neutral-v1`:
	 * MED-001 PNG, MED-002 MP4 (built by `tools/fixtures/build-media.sh`),
	 * MED-003 WebVTT, MED-004 PDF; downloads DL-A-1…3 are MED-004 copies
	 * attached to C-A in this order. MED-005 is only a URL (lesson L-A-07).
	 *
	 * @param Actor $admin  Admin.
	 * @param int   $course C-A.
	 * @return array<string, int> Fixture key → media ID.
	 */
	private static function media( Actor $admin, int $course ): array {
		$upload = wp_upload_dir();
		$folder = $upload['basedir'] . '/cc-fixture-neutral-v1';
		wp_mkdir_p( $folder );
		$files   = self::media_files();
		$library = MediaLibrary::current();
		$out     = array();
		foreach ( array(
			'MED-001' => array( 'a1-003.png', 'image/png', 'A1-003', null, 'public' ),
			'MED-002' => array( 'alpha-04.mp4', 'video/mp4', 'alpha-04', null, 'protected' ),
			'MED-003' => array( 'alpha-04.vtt', 'text/vtt', 'alpha-04 Untertitel', null, 'protected' ),
			'MED-004' => array( 'fixture.pdf', 'application/pdf', 'Fixture-Dokument', null, 'protected' ),
			'DL-A-1'  => array( 'uebersicht.pdf', 'application/pdf', 'uebersicht', 'fixture.pdf', 'protected' ),
			'DL-A-2'  => array( 'arbeitsblatt.pdf', 'application/pdf', 'arbeitsblatt', 'fixture.pdf', 'protected' ),
			'DL-A-3'  => array( 'uebersicht-a.pdf', 'application/pdf', 'Übersicht "A"', 'fixture.pdf', 'protected' ),
		) as $key => $def ) {
			$path = $folder . '/' . $def[0];
			$data = $files[ $def[3] ?? $def[0] ];
			if ( ! is_file( $path ) || (string) file_get_contents( $path ) !== $data ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local fixture file.
				file_put_contents( $path, $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Local fixture file.
			}
			$name        = 'cc-fx-' . strtolower( $key );
			$found       = get_posts(
				array(
					'post_type'        => 'attachment',
					'post_status'      => 'inherit',
					'name'             => $name,
					'fields'           => 'ids',
					'numberposts'      => 1,
					'suppress_filters' => true,
				)
			);
			$attachment  = array() !== $found ? (int) $found[0] : (int) wp_insert_attachment(
				array(
					'post_title'     => $def[2],
					'post_name'      => $name,
					'post_mime_type' => $def[1],
					'post_status'    => 'inherit',
				),
				$path
			);
			$out[ $key ] = $library->register_attachment( $admin, $attachment, $def[4] );
		}
		foreach ( array( 'DL-A-1', 'DL-A-2', 'DL-A-3' ) as $i => $key ) {
			$library->attach( $admin, $out[ $key ], 'course', $course, 'download', $i + 1 );
		}
		return $out;
	}

	/**
	 * File contents of MED-001…004 (deterministic; the MP4 is a committed
	 * build artefact because PHP cannot encode H.264).
	 *
	 * @return array<string, string>
	 */
	public static function media_files(): array {
		$png = '';
		if ( function_exists( 'imagecreatetruecolor' ) ) {
			$image = imagecreatetruecolor( 64, 64 );
			if ( false !== $image ) {
				imagefill( $image, 0, 0, (int) imagecolorallocate( $image, 0x33, 0x66, 0x99 ) );
				imagestring( $image, 2, 14, 25, 'A1-003', (int) imagecolorallocate( $image, 0xff, 0xff, 0xff ) );
				ob_start();
				imagepng( $image );
				$png = (string) ob_get_clean();
			}
		}
		return array(
			'a1-003.png'   => $png,
			'alpha-04.mp4' => (string) file_get_contents( __DIR__ . '/media/alpha-04.mp4' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local fixture file.
			'alpha-04.vtt' => "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nBeispiel\n",
			'fixture.pdf'  => self::pdf( 'Fixture-Dokument' ),
		);
	}

	/**
	 * Minimal one-page PDF with a text line (valid xref offsets).
	 *
	 * @param string $text ASCII text.
	 * @return string
	 */
	private static function pdf( string $text ): string {
		$stream  = 'BT /F1 18 Tf 72 720 Td (' . $text . ') Tj ET';
		$objects = array(
			'<< /Type /Catalog /Pages 2 0 R >>',
			'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
			'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
			'<< /Length ' . strlen( $stream ) . " >>\nstream\n" . $stream . "\nendstream",
			'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
		);
		$out     = "%PDF-1.4\n";
		$offsets = array();
		foreach ( $objects as $i => $object ) {
			$offsets[] = strlen( $out );
			$out      .= ( $i + 1 ) . " 0 obj\n" . $object . "\nendobj\n";
		}
		$xref = strlen( $out );
		$out .= 'xref' . "\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";
		foreach ( $offsets as $offset ) {
			$out .= sprintf( "%010d 00000 n \n", $offset );
		}
		return $out . 'trailer' . "\n<< /Size " . ( count( $objects ) + 1 ) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
	}

	/**
	 * MED-BAD-1…6 (§2.6, GM-128) written to a scratch directory.
	 *
	 * @param string $dir Target directory.
	 * @return array<string, array{path: string, name: string, mime: string}>
	 */
	public static function bad_media( string $dir ): array {
		$png = self::media_files()['a1-003.png'];
		$out = array();
		foreach ( array(
			'MED-BAD-1' => array( 'x.php', "<?php echo 'fixture';\n", 'application/x-php' ),
			'MED-BAD-2' => array( 'x.php.png', $png, 'image/png' ),
			'MED-BAD-3' => array( 'x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'image/svg+xml' ),
			'MED-BAD-4' => array( '.htaccess', "Options +ExecCGI\n", 'text/plain' ),
			'MED-BAD-5' => array( 'x.png', "<?php echo 'fixture';\n", 'image/png' ),
			'MED-BAD-6' => array( 'y.png', '<html><body>fixture</body></html>', 'text/html' ),
		) as $key => $def ) {
			$path = $dir . '/' . $key . '-' . $def[0];
			file_put_contents( $path, $def[1] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Scratch file.
			$out[ $key ] = array(
				'path' => $path,
				'name' => $def[0],
				'mime' => $def[2],
			);
		}
		return $out;
	}

	/**
	 * PAGE-1 (fully protected for C-A) and PAGE-2 (open page with a block
	 * and a shortcode section protected for C-A).
	 *
	 * @param int $course C-A.
	 * @return array<string, int>
	 */
	private static function pages( int $course ): array {
		$out = array();
		foreach ( array(
			'PAGE-1' => array( 'cc-fx-page-1', 'Seite Eins', '<p>Geschützter Platzhaltertext Seite Eins.</p>', true ),
			'PAGE-2' => array( 'cc-fx-page-2', 'Seite Zwei', "<!-- wp:paragraph -->\n<p>Offener Abschnitt.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[" . ContentProtection::SHORTCODE . ' course="' . $course . '"]Geschützter Abschnitt Seite Zwei.[/' . ContentProtection::SHORTCODE . "]\n<!-- /wp:shortcode -->", false ),
		) as $key => $def ) {
			$found = get_page_by_path( $def[0], OBJECT, 'page' );
			$id    = $found instanceof \WP_Post ? (int) $found->ID : (int) wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_name'    => $def[0],
					'post_title'   => $def[1],
					'post_content' => $def[2],
					'post_status'  => 'publish',
				)
			);
			if ( $def[3] ) {
				update_post_meta( $id, ContentProtection::META, $course );
			}
			$out[ $key ] = $id;
		}
		return $out;
	}

	/**
	 * On-demand access rules of §2.8 (not part of `load()`: they change what
	 * U-L1…U-L4 may see). Partial access has one limit per kind (E-332,
	 * TrialLimits): AR-02 fixed variants 2, AR-03 lessons 2, AR-06
	 * flashcards 5 (flashcards are questions, kind `first_questions`),
	 * AR-07 files 1 — they apply at the same time with different numbers.
	 *
	 * @param string                            $key AR-02…AR-08.
	 * @param array<string, array<string, int>> $fx  Loaded fixture (`courses`, `modules`).
	 * @return int Rule ID.
	 */
	public static function rule( string $key, array $fx ): int {
		$course = (int) $fx['courses']['C-A'];
		$defs   = array(
			'AR-02' => array( 0, 'exam', 'free_limited', 2, TrialLimits::VARIANTS ),
			'AR-03' => array( 0, 'view', 'free_limited', 2, TrialLimits::LESSONS ),
			'AR-04' => array( (int) $fx['modules']['M-A2'], 'view', 'drip', 7, 'days_after_start' ),
			'AR-05' => array( (int) $fx['modules']['LM-A2'], 'view', 'drip', 30, 'days_after_start' ),
			'AR-06' => array( 0, 'view', 'free_limited', 5, TrialLimits::QUESTIONS ),
			'AR-07' => array( 0, 'download', 'free_limited', 1, TrialLimits::FILES ),
			'AR-08' => array( 0, 'view', 'free', null, null ),
		);
		$def    = $defs[ $key ];
		$db     = Database::current();
		$id     = $db->insert(
			'ccap_access_rule',
			array(
				'course_id'   => $course,
				'module_id'   => $def[0],
				'scope'       => $def[1],
				'access_type' => $def[2],
				'limit_type'  => $def[4],
				'limit_value' => $def[3],
			),
			array( 'created_at', 'updated_at' )
		);
		PolicyService::flush();
		return $id;
	}

	/**
	 * On-demand grants of §2.8: G-3 (U-L3, C-A `all`).
	 *
	 * @param string                            $key G-3.
	 * @param array<string, array<string, int>> $fx  Loaded fixture.
	 * @return int Grant ID.
	 */
	public static function grant( string $key, array $fx ): int {
		$id = GrantService::current()->grant_manual( Actor::user( (int) $fx['users']['admin'] ), (int) $fx['users']['free'], (int) $fx['courses']['C-A'], self::uuid( $key ), 'Fixture' );
		PolicyService::flush();
		return $id;
	}

	/**
	 * On-demand flashcard decks (§2.5): FC-A-01…08 (mode self-assessment)
	 * and FC-B-01…05 (mode streak, threshold 3) as flashcard lessons of the
	 * optional module LM-FC of C-A (optional, so the completion denominator
	 * of GM-009 stays 8).
	 *
	 * @param array<string, mixed> $fx Loaded fixture.
	 * @return array{FC-A: int, FC-B: int, cards: array<string, int>}
	 */
	public static function flashcards( array $fx ): array {
		$admin  = Actor::user( (int) $fx['users']['admin'] );
		$found  = get_page_by_path( 'cc-fx-lm-fc', OBJECT, 'ccap_module' );
		$module = $found instanceof \WP_Post ? (int) $found->ID : 0;
		$out    = array( 'cards' => array() );
		if ( 0 === $module ) {
			$module = (int) wp_insert_post(
				array(
					'post_type'   => 'ccap_module',
					'post_name'   => 'cc-fx-lm-fc',
					'post_title'  => 'Modul LM-FC',
					'post_status' => 'publish',
				)
			);
			CourseStructure::current()->assign_module( $admin, (int) $fx['courses']['C-A'], $module, 70, false );
		}
		$repo = QuestionRepository::current();
		$db   = Database::current();
		foreach ( array(
			'FC-A' => array( 8, array( 'mode' => 'self_assessment' ) ),
			'FC-B' => array(
				5,
				array(
					'mode'         => 'streak',
					'known_streak' => 3,
				),
			),
		) as $deck => $def ) {
			$slug   = 'cc-fx-' . strtolower( $deck );
			$exists = get_posts(
				array(
					'post_type'        => 'ccap_lesson',
					'post_status'      => 'publish',
					'name'             => $slug,
					'fields'           => 'ids',
					'numberposts'      => 1,
					'suppress_filters' => true,
				)
			);
			$lesson = array() !== $exists ? (int) $exists[0] : 0;
			if ( 0 === $lesson ) {
				$lesson = (int) wp_insert_post(
					array(
						'post_type'   => 'ccap_lesson',
						'post_name'   => $slug,
						'post_title'  => 'Karteikarten ' . $deck,
						'post_status' => 'publish',
						'post_parent' => $module,
						'menu_order'  => 'FC-A' === $deck ? 1 : 2,
					)
				);
				ActivityService::current()->define( $admin, $lesson, 'flashcard', $def[1], array( 'type' => 'all_known' ) );
				for ( $i = 1; $i <= $def[0]; $i++ ) {
					$key = sprintf( '%s-%02d', $deck, $i );
					$id  = $repo->create(
						$admin,
						array(
							'question_type' => QuestionTypes::FLASHCARD,
							'question_text' => 'Karte ' . $key . ': Begriff ' . $i,
							'settings'      => array( 'back' => 'Rückseite ' . $key ),
						)
					);
					$repo->publish( $admin, $id );
					$db->insert(
						'ccap_activity_question',
						array(
							'activity_id' => $lesson,
							'question_id' => $id,
							'sort_order'  => $i,
						)
					);
				}
			}
			$out[ $deck ] = $lesson;
			foreach ( $db->rows( $db->prepare( 'SELECT question_id, sort_order FROM `' . $db->table( 'ccap_activity_question' ) . '` WHERE activity_id = %d ORDER BY sort_order', $lesson ) ) as $row ) {
				$out['cards'][ sprintf( '%s-%02d', $deck, (int) $row['sort_order'] ) ] = (int) $row['question_id'];
			}
		}
		PolicyService::flush();
		return $out;
	}

	/**
	 * Object ID of a fixture source ID.
	 *
	 * @param string $type   Object type.
	 * @param string $source Source ID.
	 * @return int|null
	 */
	public static function object( string $type, string $source ): ?int {
		$db = Database::current();
		$id = $db->scalar( $db->prepare( 'SELECT object_id FROM `' . $db->table( 'ccap_source_map' ) . '` WHERE source_system = %s AND object_type = %s AND source_id = %s', self::SYSTEM, $type, $source ) );
		return null === $id ? null : (int) $id;
	}
}
