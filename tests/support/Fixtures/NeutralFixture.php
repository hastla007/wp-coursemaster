<?php
/**
 * Neutral reference fixture `cc-fixture-neutral-v1` (R0-05 §2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Support\Fixtures;

use CourseCaptain\Import\ImportService;
use CourseCaptain\Import\PackageExporter;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
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

	public const SYSTEM = 'cc-fx-neutral-v1';
	public const T0     = '2030-01-15 10:00:00';

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
		$lesson( 'L-A-04', 'video', array( 'duration_seconds' => 2 ) );
		$lesson( 'L-A-05', 'video', array( 'duration_seconds' => 2 ) );
		$lesson( 'L-A-06', 'video', array( 'placeholder' => true ) );
		$lesson( 'L-A-07', 'video', array( 'duration_seconds' => 2 ) );

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
	 * Idempotent loader: users, PKG-v1, C-C/C-D, rules, grants.
	 *
	 * @param string $dir Scratch directory for the package.
	 * @return array{users: array<string, int>, courses: array<string, int>}
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
		) as $login => $role ) {
			$name            = 'cc_fx_' . $login;
			$found           = get_user_by( 'login', $name );
			$id              = $found instanceof \WP_User ? (int) $found->ID : (int) wp_insert_user(
				array(
					'user_login' => $name,
					'user_email' => $name . '@fixture.test',
					'user_pass'  => wp_generate_password( 24 ),
					'role'       => $role,
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
			$db->insert(
				'ccap_access_rule',
				array(
					'course_id'   => $courses['C-A'],
					'scope'       => 'view',
					'access_type' => 'free_limited',
					'limit_type'  => 'first_items',
					'limit_value' => 3,
				)
			);
		}
		PolicyService::flush();
		return array(
			'users'   => $users,
			'courses' => $courses,
		);
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
