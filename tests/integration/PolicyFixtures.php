<?php
/**
 * RBAC fixtures of R0-07 §5.1 (subset without endpoints).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Policy\MetaCaps;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Policy\Roles;
use CourseCaptain\Storage\Database;

/**
 * Builds the fixture world on the current site. IDs are exposed as public
 * properties named after R0-05/R0-07 (U-ADM → $adm, C-A → $course_a …).
 */
final class PolicyFixtures {

	// phpcs:disable Squiz.Commenting.VariableComment.Missing -- Fixture IDs, named after R0-07 §5.1.
	public int $adm;
	public int $au2;
	public int $tut;
	public int $trb;
	public int $mgr;
	public int $sup;
	public int $l1;
	public int $l2;
	public int $l3;
	public int $x;
	public int $blk;
	public int $norole;
	public int $go;
	public int $editor;
	public int $course_a;
	public int $course_b;
	public int $course_c;
	public int $course_d;
	public int $module_a1;
	public int $module_a2;
	public int $module_b1;
	public int $lesson_a1;
	public int $question_a;
	public int $question_shared;
	public int $question_own_au2;
	public int $rule_a;
	public int $revision_a;
	public int $session_l1;
	public int $session_l1_submitted;
	public int $session_tut;
	public int $session_b;
	public int $group_1;
	public int $grant_l1;
	public int $grant_x;
	public int $certificate_l1;
	public int $import_batch;
	public string $file_a;
	public string $file_c;
	// phpcs:enable

	/**
	 * Database access.
	 *
	 * @var Database
	 */
	private Database $db;

	/**
	 * Build everything.
	 */
	public function __construct() {
		$this->db = Database::current();
		self::register_post_types();

		$this->adm    = self::user( 'administrator' );
		$this->au2    = self::user( Roles::AUTHOR );
		$this->tut    = self::user( Roles::TRAINER );
		$this->trb    = self::user( Roles::TRAINER );
		$this->mgr    = self::user( Roles::COURSE_MANAGER );
		$this->sup    = self::user( Roles::SUPPORT );
		$this->l1     = self::user( 'subscriber' );
		$this->l2     = self::user( 'subscriber' );
		$this->l3     = self::user( 'subscriber' );
		$this->x      = self::user( 'subscriber' );
		$this->blk    = self::user( 'subscriber' );
		$this->go     = self::user( 'subscriber' );
		$this->editor = self::user( 'editor' );
		$this->norole = self::user( 'subscriber' );
		( new \WP_User( $this->norole ) )->set_role( '' );

		$this->course_a  = self::post( Relations::COURSE_TYPE, 'publish', $this->adm );
		$this->course_b  = self::post( Relations::COURSE_TYPE, 'publish', $this->au2 );
		$this->course_c  = self::post( Relations::COURSE_TYPE, 'draft', $this->adm );
		$this->course_d  = self::post( Relations::COURSE_TYPE, 'ccap_archived', $this->adm );
		$this->module_a1 = self::post( Relations::MODULE_TYPE, 'publish', $this->adm );
		$this->module_a2 = self::post( Relations::MODULE_TYPE, 'publish', $this->adm );
		$this->module_b1 = self::post( Relations::MODULE_TYPE, 'publish', $this->au2 );
		$this->lesson_a1 = self::post( Relations::LESSON_TYPE, 'publish', $this->adm, $this->module_a1 );
		foreach ( array( array( $this->course_a, $this->module_a1 ), array( $this->course_a, $this->module_a2 ), array( $this->course_b, $this->module_b1 ) ) as $pair ) {
			$this->db->insert(
				'ccap_course_module',
				array(
					'course_id' => $pair[0],
					'module_id' => $pair[1],
				)
			);
		}

		$this->question_a       = $this->question( $this->adm, array( $this->module_a1 ) );
		$this->question_shared  = $this->question( $this->adm, array( $this->module_a1, $this->module_b1 ) );
		$this->question_own_au2 = $this->question( $this->au2, array() );

		$this->staff( $this->tut, $this->course_a, 'trainer', 0 );

		$this->group_1 = $this->db->insert(
			'ccap_group',
			array(
				'uid'           => $this->db->uuid(),
				'owner_user_id' => $this->go,
				'name'          => 'GRP-1',
				'seats'         => 5,
			),
			array( 'created_at', 'updated_at' )
		);
		$this->staff( $this->trb, $this->course_a, 'trainer', $this->group_1 );
		$this->seat( $this->group_1, $this->l3, 'active' );

		$this->grant_l1 = $this->grant( $this->l1, $this->course_a );
		$this->grant( $this->l3, $this->course_a );
		$this->grant( $this->l2, $this->course_a, array( 'status' => 'revoked' ) );
		$this->grant_x = $this->grant( $this->x, $this->course_b, array( 'valid_until' => $this->db->now( -3600 ) ) );
		$this->grant( $this->blk, $this->course_a );
		$this->grant( $this->tut, $this->course_a );
		$this->lock( $this->blk );

		$this->rule_a     = $this->db->insert(
			'ccap_exam_rule',
			array(
				'uid'          => $this->db->uuid(),
				'course_id'    => $this->course_a,
				'version'      => '1',
				'status'       => 'published',
				'passing_rule' => '{}',
			),
			array( 'created_at', 'updated_at' )
		);
		$rule_b           = $this->db->insert(
			'ccap_exam_rule',
			array(
				'uid'          => $this->db->uuid(),
				'course_id'    => $this->course_b,
				'version'      => '1',
				'status'       => 'published',
				'passing_rule' => '{}',
			),
			array( 'created_at', 'updated_at' )
		);
		$this->revision_a = $this->db->insert(
			'ccap_course_revision',
			array(
				'course_id'      => $this->course_a,
				'revision_no'    => 1,
				'structure'      => '{}',
				'structure_hash' => 'sha256:' . str_repeat( '0', 64 ),
				'published_at'   => $this->db->now(),
			)
		);

		$this->session_l1           = $this->session( $this->l1, $this->course_a, $this->rule_a, 'in_progress' );
		$this->session_l1_submitted = $this->session( $this->l1, $this->course_a, $this->rule_a, 'submitted' );
		$this->session_tut          = $this->session( $this->tut, $this->course_a, $this->rule_a, 'submitted' );
		$this->session_b            = $this->session( $this->x, $this->course_b, $rule_b, 'submitted' );

		$this->certificate_l1 = $this->db->insert(
			'ccap_certificate',
			array(
				'certificate_uid'    => $this->db->uuid(),
				'user_id'            => $this->l1,
				'course_id'          => $this->course_a,
				'course_revision_id' => $this->revision_a,
				'evidence_type'      => 'exam_session',
				'evidence_id'        => $this->session_l1_submitted,
				'template_version'   => '1',
				'snapshot'           => '{}',
				'snapshot_hash'      => 'sha256:' . str_repeat( '1', 64 ),
				'verify_token_hash'  => hash( 'sha256', 'VT-1' ),
				'issued_at'          => $this->db->now(),
			),
			array( 'created_at' )
		);

		$this->file_a = $this->media( 'course', $this->course_a );
		$this->file_c = $this->media( 'course', $this->course_c );

		$this->import_batch = $this->db->insert(
			'ccap_import_batch',
			array(
				'package_id'     => 'pkg-1',
				'schema_version' => '1',
				'status'         => 'previewed',
				'manifest'       => '{}',
				'created_by'     => $this->adm,
			),
			array( 'created_at' )
		);
	}

	/**
	 * Minimal registration of the own post types (R1-04 registers them fully).
	 */
	public static function register_post_types(): void {
		foreach ( Relations::POST_TYPES as $type ) {
			if ( ! post_type_exists( $type ) ) {
				register_post_type(
					$type,
					array(
						'public'       => true,
						'map_meta_cap' => true,
						'capabilities' => MetaCaps::post_type_capabilities(),
					)
				);
			}
		}
	}

	/**
	 * New user with a role.
	 *
	 * @param string $role Role.
	 * @return int
	 */
	private static function user( string $role ): int {
		return (int) wp_insert_user(
			array(
				'user_login' => 'cc_fx_' . wp_generate_password( 8, false ),
				'user_pass'  => wp_generate_password(),
				'user_email' => wp_generate_password( 8, false ) . '@example.org',
				'role'       => $role,
			)
		);
	}

	/**
	 * New post.
	 *
	 * @param string $type   Post type.
	 * @param string $status Post status.
	 * @param int    $author Author.
	 * @param int    $parent_id Parent.
	 * @return int
	 */
	private static function post( string $type, string $status, int $author, int $parent_id = 0 ): int {
		return (int) wp_insert_post(
			array(
				'post_type'   => $type,
				'post_status' => $status,
				'post_author' => $author,
				'post_parent' => $parent_id,
				'post_title'  => $type . ' fixture',
			)
		);
	}

	/**
	 * Question in module pools.
	 *
	 * @param int   $author  Author.
	 * @param array $modules Modules.
	 * @phpstan-param list<int> $modules
	 * @return int
	 */
	private function question( int $author, array $modules ): int {
		$id = $this->db->insert(
			'ccap_question',
			array(
				'uid'            => $this->db->uuid(),
				'author_user_id' => $author,
			),
			array( 'created_at', 'updated_at' )
		);
		foreach ( $modules as $module ) {
			$this->db->insert(
				'ccap_module_question',
				array(
					'module_id'   => $module,
					'question_id' => $id,
				)
			);
		}
		return $id;
	}

	/**
	 * Staff assignment.
	 *
	 * @param int    $user   User.
	 * @param int    $course Course.
	 * @param string $role   Role.
	 * @param int    $group  Group scope.
	 */
	public function staff( int $user, int $course, string $role, int $group ): void {
		$this->db->insert(
			'ccap_course_staff',
			array(
				'course_id' => $course,
				'user_id'   => $user,
				'role'      => $role,
				'group_id'  => $group,
			),
			array( 'created_at', 'updated_at' )
		);
	}

	/**
	 * Seat.
	 *
	 * @param int    $group  Group.
	 * @param int    $user   User.
	 * @param string $status Status.
	 * @param array  $extra  More columns.
	 * @return int
	 */
	public function seat( int $group, int $user, string $status, array $extra = array() ): int {
		return $this->db->insert(
			'ccap_seat',
			array(
				'group_id' => $group,
				'user_id'  => 0 === $user ? null : $user,
				'status'   => $status,
			) + $extra,
			array( 'created_at', 'updated_at' )
		);
	}

	/**
	 * Active manual grant.
	 *
	 * @param int                  $user   User.
	 * @param int                  $course Course.
	 * @param array<string, mixed> $extra  More columns.
	 * @return int
	 */
	public function grant( int $user, int $course, array $extra = array() ): int {
		return $this->db->insert(
			'ccap_grant',
			$extra + array(
				'uid'         => $this->db->uuid(),
				'user_id'     => $user,
				'course_id'   => $course,
				'status'      => 'active',
				'source_type' => 'manual',
				'source_id'   => $this->db->uuid(),
			),
			array( 'created_at', 'updated_at' )
		);
	}

	/**
	 * Learner lock.
	 *
	 * @param int $user User.
	 * @return int
	 */
	public function lock( int $user ): int {
		return $this->db->insert(
			'ccap_learner_lock',
			array(
				'user_id'     => $user,
				'reason_code' => 'test',
				'valid_from'  => $this->db->now( -60 ),
			),
			array( 'created_at', 'updated_at' )
		);
	}

	/**
	 * Exam session.
	 *
	 * @param int    $user   User.
	 * @param int    $course Course.
	 * @param int    $rule   Rule.
	 * @param string $status Status.
	 * @return int
	 */
	private function session( int $user, int $course, int $rule, string $status ): int {
		return $this->db->insert(
			'ccap_exam_session',
			array(
				'uid'           => $this->db->uuid(),
				'user_id'       => $user,
				'course_id'     => $course,
				'rule_id'       => $rule,
				'mode'          => 'random',
				'form_key'      => 'random:' . $rule . ':' . $this->db->uuid(),
				'status'        => $status,
				'start_token'   => $this->db->uuid(),
				'snapshot'      => '{}',
				'snapshot_hash' => 'sha256:' . str_repeat( '2', 64 ),
				'started_at'    => $this->db->now(),
				'deadline_at'   => $this->db->now( 3600 ),
			)
		);
	}

	/**
	 * Protected media assigned to an object.
	 *
	 * @param string $type Object type.
	 * @param int    $id   Object ID.
	 * @return string Media UID.
	 */
	private function media( string $type, int $id ): string {
		$uid   = $this->db->uuid();
		$media = $this->db->insert(
			'ccap_media',
			array(
				'uid'    => $uid,
				'sha256' => hash( 'sha256', $uid ),
			),
			array( 'created_at', 'updated_at' )
		);
		$this->db->insert(
			'ccap_content_media',
			array(
				'object_type' => $type,
				'object_id'   => $id,
				'media_id'    => $media,
				'role'        => 'attachment',
			)
		);
		return $uid;
	}
}
