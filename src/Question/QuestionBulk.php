<?php
/**
 * Tags and bulk edit of the question bank (UI-F8, R0-11 A-07 "Tags,
 * Sammelbearbeitung", R0-06 E-10).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Question;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Tags belong to a revision (`ccap_question_tag`) and are part of the
 * content hash, so a tag change follows the revision model of
 * {@see QuestionRepository::save()}: a draft revision is updated in
 * place, a published revision is never changed (the change becomes a new
 * revision, which can be published right away, because tags change
 * nothing a learner sees). A question with unpublished changes keeps them
 * as draft: the tag change joins the draft, nothing else is published.
 * Rights and validity of the revision are carried over.
 *
 * Pool changes and "deprecate" work on the question identity and create no
 * revision: "deprecate" archives the question (never a hard delete,
 * INV-C-108; the revision status `deprecated` is reserved for replaced
 * revisions). Every change is checked per question (M-07, M-06 for the
 * module) and audited.
 */
final class QuestionBulk {

	/** Tag types (R0-06 §2 `ccap_question_tag.tag_type`; `smart_category` as read by the smart trainer). */
	public const TYPES = array( 'topic', 'subtopic', 'competency', 'smart_category', 'scope' );

	/** Bulk actions. */
	public const ACTIONS = array( 'tag_add', 'tag_remove', 'pool_add', 'pool_move', 'deprecate' );

	/** Most questions per bulk run. */
	public const MAX = 200;

	/**
	 * Constructor.
	 *
	 * @param Database           $db        Database access.
	 * @param QuestionRepository $questions Question bank.
	 * @param PolicyService      $policy    Policy.
	 */
	public function __construct( private Database $db, private QuestionRepository $questions, private PolicyService $policy ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( Database::current(), QuestionRepository::current(), PolicyService::instance() );
	}

	/**
	 * One tag from text: `type: label` with a known type, otherwise the
	 * whole text is a `topic` label. The key is the sanitized label.
	 *
	 * @param string $text Text.
	 * @return array{type: string, key: string, label: string}|null Null for empty text.
	 */
	public static function parse_tag( string $text ): ?array {
		$text = trim( sanitize_text_field( $text ) );
		if ( '' === $text ) {
			return null;
		}
		$type  = 'topic';
		$label = $text;
		$colon = strpos( $text, ':' );
		if ( false !== $colon ) {
			$prefix = strtolower( trim( substr( $text, 0, $colon ) ) );
			if ( in_array( $prefix, self::TYPES, true ) ) {
				$type  = $prefix;
				$label = trim( substr( $text, $colon + 1 ) );
			}
		}
		$label = mb_substr( $label, 0, 255 );
		$key   = substr( sanitize_title( $label ), 0, 191 );
		if ( '' === $key ) {
			return null;
		}
		return array(
			'type'  => $type,
			'key'   => $key,
			'label' => $label,
		);
	}

	/**
	 * Tags from a text field (one per line or separated by commas).
	 *
	 * @param string $text Text.
	 * @return list<array{type: string, key: string, label: string}>
	 */
	public static function parse_tags( string $text ): array {
		$out   = array();
		$lines = preg_split( '/[\r\n,]+/', $text );
		foreach ( false === $lines ? array() : $lines as $line ) {
			$tag = self::parse_tag( (string) $line );
			if ( null !== $tag && ! isset( $out[ $tag['type'] . ':' . $tag['key'] ] ) ) {
				$out[ $tag['type'] . ':' . $tag['key'] ] = $tag;
			}
		}
		return array_values( $out );
	}

	/**
	 * Tags as text for the editor (one per line, `type: label`; topics without prefix).
	 *
	 * @param array<int, array<string, mixed>> $tags Tags.
	 * @return string
	 */
	public static function format_tags( array $tags ): string {
		$lines = array();
		foreach ( $tags as $tag ) {
			$label   = '' !== (string) ( $tag['label'] ?? '' ) ? (string) $tag['label'] : (string) ( $tag['key'] ?? '' );
			$type    = (string) ( $tag['type'] ?? 'topic' );
			$lines[] = 'topic' === $type ? $label : $type . ': ' . $label;
		}
		return implode( "\n", $lines );
	}

	/**
	 * Add and remove tags of one question.
	 *
	 * @param Actor                            $actor    Author (M-07).
	 * @param int                              $question Question.
	 * @param array<int, array<string, mixed>> $add      Tags to add (`type`, `key`, `label`).
	 * @param array<int, array<string, mixed>> $remove   Tags to remove (matched by type and key).
	 * @param bool                             $publish  Publish the new revision of a published question without other pending changes.
	 * @return string `unchanged`, `draft` (saved, not published) or `published`.
	 * @throws CourseException `ccap_forbidden`, `ccap_not_found`.
	 */
	public function change_tags( Actor $actor, int $question, array $add, array $remove, bool $publish ): string {
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::EDIT_QUESTION, array( $question ) ), 'Not allowed to edit this question.' );
		$identity = $this->db->row( $this->db->prepare( 'SELECT status, current_revision_id FROM `' . $this->db->table( 'ccap_question' ) . '` WHERE id = %d', $question ) );
		$latest   = (int) $this->db->scalar( $this->db->prepare( 'SELECT id FROM `' . $this->db->table( 'ccap_question_revision' ) . '` WHERE question_id = %d ORDER BY revision_no DESC LIMIT 1', $question ) );
		$revision = $latest > 0 ? $this->questions->revision( $latest ) : null;
		if ( null === $identity || null === $revision || 'archived' === $identity['status'] ) {
			throw new CourseException( 'ccap_not_found', 'question', 404 );
		}
		$before = array();
		foreach ( (array) $revision['tags'] as $tag ) {
			$before[ $tag['type'] . ':' . $tag['key'] ] = $tag;
		}
		$after = $before;
		foreach ( $remove as $tag ) {
			unset( $after[ (string) $tag['type'] . ':' . (string) $tag['key'] ] );
		}
		foreach ( $add as $tag ) {
			$after[ (string) $tag['type'] . ':' . (string) $tag['key'] ] = array(
				'type'  => (string) $tag['type'],
				'key'   => (string) $tag['key'],
				'label' => (string) ( $tag['label'] ?? '' ),
			);
		}
		if ( self::same( $before, $after ) ) {
			return 'unchanged';
		}
		$pending      = 'published' === $identity['status'] && (int) $identity['current_revision_id'] !== $latest;
		$data         = $this->data( $revision, $latest );
		$data['tags'] = array_values( $after );
		$saved        = $this->questions->save( $actor, $question, $data, 'editorial' );
		$result       = 'draft';
		if ( $publish && 'published' === $identity['status'] && ! $pending && array() === QuestionTypes::validate( (array) $this->questions->revision( $saved ) ) ) {
			$saved  = $this->questions->publish( $actor, $question, $saved );
			$result = 'published';
		}
		AuditLog::write(
			$this->db,
			$actor,
			'question_tags_changed',
			'question',
			(string) $question,
			null,
			array(
				'revision_id' => $saved,
				'added'       => array_values( array_diff( array_keys( $after ), array_keys( $before ) ) ),
				'removed'     => array_values( array_diff( array_keys( $before ), array_keys( $after ) ) ),
				'published'   => 'published' === $result,
			)
		);
		return $result;
	}

	/**
	 * Run a bulk action over selected questions; every question is checked
	 * on its own, failures do not stop the others.
	 *
	 * @param Actor                $actor  Author.
	 * @param string               $action One of {@see self::ACTIONS}.
	 * @param array<int, int>      $ids    Questions.
	 * @param array<string, mixed> $params `tag` (array tag), `module` (int), `publish` (bool), `confirm` (bool).
	 * @return array{changed: int, published: int, draft: int, unchanged: int, failed: int}
	 * @throws CourseException `ccap_nothing_selected`, `ccap_too_many`, `ccap_invalid_param`, `ccap_confirmation_required`, `ccap_forbidden`.
	 */
	public function apply( Actor $actor, string $action, array $ids, array $params ): array {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ), static fn( int $id ): bool => $id > 0 ) ) );
		if ( array() === $ids ) {
			throw new CourseException( 'ccap_nothing_selected', 'questions', 422 );
		}
		if ( count( $ids ) > self::MAX ) {
			throw new CourseException( 'ccap_too_many', 'questions', 422 );
		}
		if ( ! in_array( $action, self::ACTIONS, true ) ) {
			throw new CourseException( 'ccap_invalid_param', 'action', 422 );
		}
		$tag    = isset( $params['tag'] ) && is_array( $params['tag'] ) ? $params['tag'] : null;
		$module = (int) ( $params['module'] ?? 0 );
		if ( in_array( $action, array( 'tag_add', 'tag_remove' ), true ) && null === $tag ) {
			throw new CourseException( 'ccap_invalid_param', 'tag', 422 );
		}
		if ( in_array( $action, array( 'pool_add', 'pool_move' ), true ) ) {
			if ( $module <= 0 || null === get_post( $module ) || \CourseCaptain\Policy\Relations::MODULE_TYPE !== get_post_type( $module ) ) {
				throw new CourseException( 'ccap_invalid_param', 'module', 422 );
			}
			CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::EDIT_COURSE_ITEM, array( $module, 'module' ) ), 'Not allowed to edit this module.' );
		}
		if ( 'deprecate' === $action && empty( $params['confirm'] ) ) {
			throw new CourseException( 'ccap_confirmation_required', 'confirm', 422 );
		}
		$out = array(
			'changed'   => 0,
			'published' => 0,
			'draft'     => 0,
			'unchanged' => 0,
			'failed'    => 0,
		);
		foreach ( $ids as $question ) {
			try {
				switch ( $action ) {
					case 'tag_add':
					case 'tag_remove':
						$state = 'tag_add' === $action
							? $this->change_tags( $actor, $question, array( (array) $tag ), array(), ! empty( $params['publish'] ) )
							: $this->change_tags( $actor, $question, array(), array( (array) $tag ), ! empty( $params['publish'] ) );
						if ( 'unchanged' === $state ) {
							++$out['unchanged'];
						} elseif ( 'published' === $state ) {
							++$out['changed'];
							++$out['published'];
						} else {
							++$out['changed'];
							++$out['draft'];
						}
						break;
					case 'pool_add':
					case 'pool_move':
						if ( $this->pool( $actor, $question, $module, 'pool_move' === $action ) ) {
							++$out['changed'];
						} else {
							++$out['unchanged'];
						}
						break;
					case 'deprecate':
						$status = (string) $this->db->scalar( $this->db->prepare( 'SELECT status FROM `' . $this->db->table( 'ccap_question' ) . '` WHERE id = %d', $question ) );
						if ( '' === $status ) {
							throw new CourseException( 'ccap_not_found', 'question', 404 );
						}
						if ( 'archived' === $status ) {
							++$out['unchanged'];
							break;
						}
						$this->questions->archive( $actor, $question );
						AuditLog::write( $this->db, $actor, 'question_archived', 'question', (string) $question, null, array( 'bulk' => true ) );
						++$out['changed'];
						break;
				}
			} catch ( CourseException $e ) {
				unset( $e );
				++$out['failed'];
			}
		}
		if ( 'deprecate' === $action || str_starts_with( $action, 'pool_' ) ) {
			\CourseCaptain\Cache\CacheStore::bump_content();
		}
		return $out;
	}

	/**
	 * Add a question to a module pool; "move" also removes it from the other
	 * pools the author may edit.
	 *
	 * @param Actor $actor    Author.
	 * @param int   $question Question.
	 * @param int   $module   Target module.
	 * @param bool  $move     Remove from the other pools.
	 * @return bool Whether anything changed.
	 * @throws CourseException Without rights.
	 */
	private function pool( Actor $actor, int $question, int $module, bool $move ): bool {
		$table   = $this->db->table( 'ccap_module_question' );
		$current = array_map( 'intval', $this->db->wpdb()->get_col( $this->db->prepare( "SELECT module_id FROM `{$table}` WHERE question_id = %d", $question ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
		$changed = false;
		if ( ! in_array( $module, $current, true ) ) {
			$this->questions->add_to_pool( $actor, $module, $question );
			$changed = true;
		} else {
			CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::EDIT_QUESTION, array( $question ) ), 'Not allowed to edit this question.' );
		}
		$removed = array();
		if ( $move ) {
			foreach ( $current as $other ) {
				if ( $other !== $module && $this->policy->decide( $actor, PolicyService::EDIT_COURSE_ITEM, array( $other, 'module' ) )->allowed ) {
					$this->db->execute( $this->db->prepare( "DELETE FROM `{$table}` WHERE module_id = %d AND question_id = %d", $other, $question ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
					$removed[] = $other;
				}
			}
		}
		if ( $changed || array() !== $removed ) {
			AuditLog::write(
				$this->db,
				$actor,
				'question_pool_changed',
				'question',
				(string) $question,
				null,
				array(
					'module_id'    => $module,
					'removed_from' => $removed,
					'bulk'         => true,
				)
			);
			return true;
		}
		return false;
	}

	/**
	 * Revision data for a new save: content plus the rights and validity columns.
	 *
	 * @param array<string, mixed> $revision Revision as read by the repository.
	 * @param int                  $id       Revision ID.
	 * @return array<string, mixed>
	 */
	private function data( array $revision, int $id ): array {
		$extra = (array) $this->db->row( $this->db->prepare( 'SELECT image_required, rights_status, rights_owner, license_reference, valid_from, valid_to FROM `' . $this->db->table( 'ccap_question_revision' ) . '` WHERE id = %d', $id ) );
		return array(
			'question_type'      => (string) $revision['question_type'],
			'question_text'      => (string) $revision['question_text'],
			'settings'           => (array) $revision['settings'],
			'answers'            => (array) $revision['answers'],
			'feedback_correct'   => $revision['feedback_correct'],
			'feedback_incorrect' => $revision['feedback_incorrect'],
			'image_required'     => '1' === (string) ( $extra['image_required'] ?? '0' ),
			'rights_status'      => (string) ( $extra['rights_status'] ?? 'own' ),
			'rights_owner'       => $extra['rights_owner'] ?? null,
			'license_reference'  => $extra['license_reference'] ?? null,
			'valid_from'         => $extra['valid_from'] ?? null,
			'valid_to'           => $extra['valid_to'] ?? null,
		);
	}

	/**
	 * Same tag set (type, key, label).
	 *
	 * @param array<string, array<string, mixed>> $a Tags.
	 * @param array<string, array<string, mixed>> $b Tags.
	 * @return bool
	 */
	private static function same( array $a, array $b ): bool {
		$norm = static function ( array $tags ): array {
			$out = array();
			foreach ( $tags as $key => $tag ) {
				$out[ $key ] = (string) ( $tag['label'] ?? '' );
			}
			ksort( $out );
			return $out;
		};
		return $norm( $a ) === $norm( $b );
	}
}
