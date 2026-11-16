<?php
/**
 * Basic certificate domain (R0-29 §2, R0-08 §7.2, R0-23 §5; R1-44).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Certificate;

use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Event\Outbox;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Settings\EffectiveSettings;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\StorageException;

defined( 'ABSPATH' ) || exit;

/**
 * The only writer of `ccap_certificate` (CR-1).
 *
 * - Issuance only from existing, evidence-eligible evidence: an evaluated,
 *   passed exam attempt with `evidence_eligible` (never `reduced` or
 *   practice) or a completed course. One certificate per evidence
 *   (`UNIQUE evidence_rev`); a second call returns the first (CERT-002).
 * - Automatic issuance (`certificates.auto_issue`) runs as internal outbox
 *   subscriber `certificates` on `course.completed`, `exam.passed` and
 *   `exam.reevaluated` (failed → passed); exams additionally need an
 *   exam-scope grant at submission (E-113). A course completion issues only
 *   when the learner holds no valid certificate of the course yet.
 * - Snapshot `format_version = 1` (R0-29 §2.4) with every printed value
 *   resolved; validated, canonical, hashed.
 * - Correction = new revision with `supersedes_id`, old row `corrected`,
 *   template, evidence, person and course unchanged (R0-23 §5.3).
 * - Revocation: reason category, all revisions, final (R0-23 §5.4).
 * - Verification token per revision, derived by HMAC and stored only as
 *   SHA-256 (`verify_token_hash`); public fields per R0-29 §2.5.
 */
final class CertificateService {

	public const SUBSCRIBER       = 'certificates';
	public const EVIDENCE_EXAM    = 'exam_session';
	public const EVIDENCE_COURSE  = 'course_completion';
	public const TEMPLATE_OPTION  = 'course_captain_certificate_templates';
	public const NAME_META        = 'ccap_certificate_name_public';
	public const REVOKE_REASONS   = array( 'fraud', 'invalid_assessment', 'issued_in_error', 'other' );
	public const CORRECTABLE      = array( 'holder_name', 'course_title', 'refresh_evidence' );
	public const IMMUTABLE        = array( 'user_id', 'course_id', 'course_uid', 'evidence_type', 'evidence_id', 'evidence_ref' );
	public const VERIFY_PER_MIN   = 30;
	public const RECERTIFY_DAYS   = 30;
	public const IDEMPOTENCY_DAYS = 7;

	/**
	 * Constructor.
	 *
	 * @param Database      $db     Database access.
	 * @param PolicyService $policy Policy.
	 */
	public function __construct( private Database $db, private PolicyService $policy ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( Database::current(), PolicyService::instance() );
	}

	/**
	 * Hooks: internal outbox subscriber (E-38: Free events stay internal).
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter(
			'course_captain_internal_subscribers',
			static function ( $subscribers ) {
				$subscribers                     = (array) $subscribers;
				$subscribers[ self::SUBSCRIBER ] = array( 'course.completed', 'exam.passed', 'exam.reevaluated' );
				return $subscribers;
			}
		);
		add_filter(
			'course_captain_internal_subscriber_handlers',
			static function ( $handlers ) {
				$handlers                     = (array) $handlers;
				$handlers[ self::SUBSCRIBER ] = array( self::class, 'handle_event' );
				return $handlers;
			}
		);
	}

	/**
	 * Outbox handler. Evidence that cannot be certified is not an error;
	 * storage errors are rethrown for the retry.
	 *
	 * @param array<string, mixed> $envelope Event envelope v1.
	 * @return void
	 */
	public static function handle_event( array $envelope ): void {
		$service = self::current();
		$type    = (string) ( $envelope['event_type'] ?? '' );
		$payload = (array) ( $envelope['payload'] ?? array() );
		$object  = (string) ( $envelope['object_id'] ?? '' );
		try {
			if ( 'course.completed' === $type && 1 === preg_match( '/^(\d+):(\d+):\d+$/', $object, $m ) ) {
				$progress = $service->db->scalar( $service->db->prepare( 'SELECT id FROM `' . $service->db->table( 'ccap_course_progress' ) . '` WHERE course_id = %d AND user_id = %d', (int) $m[1], (int) $m[2] ) );
				if ( null !== $progress ) {
					$service->auto_issue( self::EVIDENCE_COURSE, (int) $progress );
				}
				return;
			}
			$session = $service->db->scalar( $service->db->prepare( 'SELECT id FROM `' . $service->db->table( 'ccap_exam_session' ) . '` WHERE uid = %s', $object ) );
			if ( null === $session ) {
				return;
			}
			if ( 'exam.passed' === $type || ( 'exam.reevaluated' === $type && ! empty( $payload['passed'] ) && empty( $payload['passed_old'] ) ) ) {
				$service->auto_issue( self::EVIDENCE_EXAM, (int) $session );
			} elseif ( 'exam.reevaluated' === $type && empty( $payload['passed'] ) && ! empty( $payload['passed_old'] ) ) {
				$service->review_required( (int) $session );
			}
		} catch ( CourseException $e ) {
			// Not certifiable (e.g. evidence gone); nothing to retry.
			unset( $e );
		}
	}

	/**
	 * Automatic issuance (CF-02). Returns null when the setting is off or
	 * the evidence does not qualify.
	 *
	 * @param string $type        Evidence type.
	 * @param int    $evidence_id Evidence row.
	 * @return array{id: int, certificate_uid: string, created: bool}|null
	 */
	public function auto_issue( string $type, int $evidence_id ): ?array {
		$evidence = $this->evidence( $type, $evidence_id );
		if ( null === $evidence || ! $evidence['eligible'] ) {
			return null;
		}
		// Class E (R0-28 §1.4 #22): decided at the event with the course override of the published revision.
		if ( true !== EffectiveSettings::for_course( 'certificates.auto_issue', (int) $evidence['course_id'] ) ) {
			return null;
		}
		if ( self::EVIDENCE_EXAM === $type && ! $this->grant_at_submission( $evidence ) ) {
			return null;
		}
		if ( self::EVIDENCE_COURSE === $type ) {
			$existing = $this->existing( $type, $evidence_id );
			if ( null === $existing && $this->holds_valid_certificate( (int) $evidence['user_id'], (int) $evidence['course_id'] ) ) {
				return null;
			}
		}
		return $this->issue_evidence( $evidence, null, '' );
	}

	/**
	 * Manual issuance from existing evidence (CF-03, M-19: never the own,
	 * `ccap_manage_certificates`, reason mandatory). Never without evidence.
	 *
	 * @param Actor  $actor       Certificate manager.
	 * @param string $type        `exam_session` or `course_completion`.
	 * @param int    $evidence_id Evidence row.
	 * @param string $reason      Reason.
	 * @return array{id: int, certificate_uid: string, created: bool}
	 * @throws CourseException `ccap_forbidden`, `ccap_validation_failed`, `ccap_not_found`.
	 */
	public function issue( Actor $actor, string $type, int $evidence_id, string $reason ): array {
		if ( $actor->is_system() || ! user_can( $actor->user_id, 'ccap_manage_certificates' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to issue certificates.', 403 );
		}
		$reason = sanitize_textarea_field( $reason );
		if ( '' === trim( $reason ) ) {
			throw new CourseException( 'ccap_validation_failed', 'A reason is required.', 422 );
		}
		$evidence = $this->evidence( $type, $evidence_id );
		if ( null === $evidence ) {
			throw new CourseException( 'ccap_validation_failed', 'No evidence to certify.', 422 );
		}
		if ( (int) $evidence['user_id'] === $actor->user_id ) {
			throw new CourseException( 'ccap_forbidden', 'Never the own certificate.', 403 );
		}
		if ( ! $evidence['eligible'] ) {
			throw new CourseException( 'ccap_validation_failed', 'The evidence is not eligible for a certificate.', 422 );
		}
		return $this->issue_evidence( $evidence, $actor, $reason );
	}

	/**
	 * Correction (CF-09, R0-23 §5.3).
	 *
	 * @param Actor                $actor       Certificate manager (never the holder).
	 * @param string               $uid         Certificate UID.
	 * @param string               $reason      Reason (mandatory).
	 * @param array<string, mixed> $changes     `holder_name`, `course_title`, `refresh_evidence`.
	 * @param string               $request_key Idempotency key (UUID) of the admin request.
	 * @return array{id: int, certificate_uid: string, revision_no: int}
	 * @throws CourseException `ccap_not_found`, `ccap_forbidden`, `ccap_validation_failed`, `ccap_idempotency_conflict`.
	 */
	public function correct( Actor $actor, string $uid, string $reason, array $changes, string $request_key = '' ): array {
		$latest = $this->latest( $uid );
		if ( null === $latest ) {
			throw new CourseException( 'ccap_not_found', 'Unknown certificate.', 404 );
		}
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::MANAGE_CERTIFICATE, array( (int) $latest['id'] ) ), 'Not allowed to correct this certificate.' );
		$reason = sanitize_textarea_field( $reason );
		if ( '' === trim( $reason ) ) {
			throw new CourseException( 'ccap_validation_failed', 'A reason is required.', 422 );
		}
		foreach ( array_keys( $changes ) as $key ) {
			if ( in_array( $key, self::IMMUTABLE, true ) ) {
				throw new CourseException( 'ccap_validation_failed', 'Evidence, person and course cannot be corrected; revoke and issue anew.', 422 );
			}
			if ( ! in_array( $key, self::CORRECTABLE, true ) ) {
				throw new CourseException( 'ccap_validation_failed', 'Unknown correction field.', 422 );
			}
		}
		$request_hash = hash( 'sha256', (string) wp_json_encode( array( $uid, $reason, $changes ) ) );
		$out          = $this->db->transaction(
			function ( Database $db ) use ( $actor, $uid, $reason, $changes, $request_key, $request_hash ): array {
				if ( '' !== $request_key ) {
					$stored = $this->idempotent( $db, $actor, 'certificate_correct', $request_key, $request_hash );
					if ( null !== $stored ) {
						return $stored + array( 'replayed' => true );
					}
				}
				$table = $db->table( 'ccap_certificate' );
				$rows  = $db->rows( $db->prepare( "SELECT * FROM `{$table}` WHERE certificate_uid = %s ORDER BY revision_no DESC FOR UPDATE", $uid ) );
				$old   = $rows[0] ?? null;
				if ( null === $old || 'issued' !== $old['status'] ) {
					throw new CourseException( 'ccap_validation_failed', 'Only a valid certificate can be corrected.', 422 );
				}
				$snapshot = (array) json_decode( (string) $old['snapshot'], true );
				$revision = (int) $old['revision_no'] + 1;
				if ( isset( $changes['holder_name'] ) ) {
					$name = sanitize_text_field( (string) $changes['holder_name'] );
					if ( '' === $name ) {
						throw new CourseException( 'ccap_validation_failed', 'The name must not be empty.', 422 );
					}
					$snapshot['holder']['display_name'] = $name;
				}
				if ( isset( $changes['course_title'] ) ) {
					$title = sanitize_text_field( (string) $changes['course_title'] );
					if ( '' === $title ) {
						throw new CourseException( 'ccap_validation_failed', 'The title must not be empty.', 422 );
					}
					$snapshot['course']['title'] = $title;
				}
				if ( ! empty( $changes['refresh_evidence'] ) ) {
					$evidence = $this->evidence( (string) $old['evidence_type'], (int) $old['evidence_id'] );
					if ( null === $evidence || ! $evidence['eligible'] ) {
						throw new CourseException( 'ccap_validation_failed', 'The evidence no longer qualifies; revoke instead.', 422 );
					}
					$snapshot['evidence']    = self::evidence_snapshot( $evidence );
					$snapshot['valid_until'] = $evidence['valid_until'];
				}
				$snapshot['revision_no']   = $revision;
				$snapshot['revision_kind'] = 'correction';
				$snapshot['issued_at']     = gmdate( 'Y-m-d\TH:i:s\Z', $db->time() );
				$snapshot['issued_by']     = self::issued_by( $actor );
				$sealed                    = CertificateSnapshot::seal( $snapshot );
				$id                        = $db->insert(
					'ccap_certificate',
					array(
						'certificate_uid'    => $uid,
						'revision_no'        => $revision,
						'user_id'            => (int) $old['user_id'],
						'course_id'          => (int) $old['course_id'],
						'course_revision_id' => (int) $old['course_revision_id'],
						'evidence_type'      => (string) $old['evidence_type'],
						'evidence_id'        => (int) $old['evidence_id'],
						'status'             => 'issued',
						'supersedes_id'      => (int) $old['id'],
						'template_version'   => (string) $old['template_version'],
						'snapshot'           => $sealed['json'],
						'snapshot_hash'      => $sealed['hash'],
						'verify_token_hash'  => hash( 'sha256', self::token( $uid, $revision ) ),
						'issued_at'          => $db->now(),
						'issued_by'          => $actor->user_id,
						'created_at'         => $db->now(),
					)
				);
				$db->execute( $db->prepare( "UPDATE `{$table}` SET status = 'corrected' WHERE id = %d", (int) $old['id'] ) );
				$diff = array();
				foreach ( array( 'holder', 'course', 'evidence', 'valid_until' ) as $field ) {
					$before = json_decode( (string) $old['snapshot'], true )[ $field ] ?? null;
					if ( ( $sealed['snapshot'][ $field ] ?? null ) !== $before ) {
						$diff[] = $field;
					}
				}
				AuditLog::write(
					$db,
					$actor,
					'certificate_corrected',
					'certificate',
					$uid,
					$reason,
					array(
						'revision_no'            => $revision,
						'supersedes_revision_no' => (int) $old['revision_no'],
						'changed'                => $diff,
					),
					(int) $old['user_id']
				);
				Outbox::record(
					$db,
					'certificate.corrected',
					'certificate',
					$uid,
					$revision,
					array(
						'revision_no'            => $revision,
						'supersedes_revision_no' => (int) $old['revision_no'],
					),
					array(
						'user_id'      => (int) $old['user_id'],
						'course_uid'   => (string) ( $snapshot['course']['course_uid'] ?? '' ),
						'actor_type'   => 'user',
						'privacy_tags' => array( 'personal_ref', 'certificate' ),
					)
				);
				$result = array(
					'id'              => $id,
					'certificate_uid' => $uid,
					'revision_no'     => $revision,
				);
				if ( '' !== $request_key ) {
					$db->execute(
						$db->prepare(
							'UPDATE `' . $db->table( 'ccap_request_idempotency' ) . '` SET status_code = 200, response = %s, resource = %s WHERE user_id = %d AND scope = %s AND idem_key = %s',
							(string) wp_json_encode( $result ),
							$uid,
							$actor->user_id,
							'certificate_correct',
							$request_key
						)
					);
				}
				return $result;
			}
		);
		unset( $out['replayed'] );
		do_action( 'course_captain_policy_changed' );
		return array(
			'id'              => (int) $out['id'],
			'certificate_uid' => (string) $out['certificate_uid'],
			'revision_no'     => (int) $out['revision_no'],
		);
	}

	/**
	 * Revocation (CF-10, R0-23 §5.4): every revision, final.
	 *
	 * @param Actor  $actor    Certificate manager (never the holder).
	 * @param string $uid      Certificate UID.
	 * @param string $category One of {@see self::REVOKE_REASONS}.
	 * @param string $text     Free text (mandatory for `other`).
	 * @return bool False when it was already revoked.
	 * @throws CourseException `ccap_not_found`, `ccap_forbidden`, `ccap_validation_failed`.
	 */
	public function revoke( Actor $actor, string $uid, string $category, string $text = '' ): bool {
		$latest = $this->latest( $uid );
		if ( null === $latest ) {
			throw new CourseException( 'ccap_not_found', 'Unknown certificate.', 404 );
		}
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::MANAGE_CERTIFICATE, array( (int) $latest['id'] ) ), 'Not allowed to revoke this certificate.' );
		$text = sanitize_textarea_field( $text );
		if ( ! in_array( $category, self::REVOKE_REASONS, true ) || ( 'other' === $category && '' === trim( $text ) ) ) {
			throw new CourseException( 'ccap_validation_failed', 'A reason is required.', 422 );
		}
		$changed = $this->db->transaction(
			function ( Database $db ) use ( $actor, $uid, $category, $text ): bool {
				$table = $db->table( 'ccap_certificate' );
				$rows  = $db->rows( $db->prepare( "SELECT id, revision_no, status, user_id, snapshot FROM `{$table}` WHERE certificate_uid = %s ORDER BY revision_no DESC FOR UPDATE", $uid ) );
				if ( array() === $rows || 'revoked' === $rows[0]['status'] ) {
					return false;
				}
				$db->execute(
					$db->prepare(
						"UPDATE `{$table}` SET status = 'revoked', revoked_at = %s, revoked_by = %d, revoke_reason = %s WHERE certificate_uid = %s",
						$db->now(),
						$actor->user_id,
						(string) wp_json_encode(
							array(
								'category' => $category,
								'text'     => $text,
							)
						),
						$uid
					)
				);
				AuditLog::write(
					$db,
					$actor,
					'certificate_revoked',
					'certificate',
					$uid,
					'' === $text ? $category : $text,
					array(
						'category'  => $category,
						'revisions' => count( $rows ),
					),
					(int) $rows[0]['user_id']
				);
				Outbox::record(
					$db,
					'certificate.revoked',
					'certificate',
					$uid,
					(int) $rows[0]['revision_no'],
					array( 'reason' => $category ),
					array(
						'user_id'      => (int) $rows[0]['user_id'],
						'course_uid'   => (string) ( json_decode( (string) $rows[0]['snapshot'], true )['course']['course_uid'] ?? '' ),
						'actor_type'   => 'user',
						'privacy_tags' => array( 'personal_ref', 'certificate' ),
					)
				);
				return true;
			}
		);
		// Caches must show the revocation at once (CON-037).
		do_action( 'course_captain_policy_changed' );
		return $changed;
	}

	/**
	 * Public verification (R-42, L-16): fields per R0-29 §2.5 only.
	 *
	 * @param string $token Verification token.
	 * @return array<string, mixed>
	 * @throws CourseException `ccap_not_found` (unknown, no oracle).
	 */
	public function verify( string $token ): array {
		$row = 1 === preg_match( '/^[0-9a-f]{40}$/', $token ) ? $this->db->row( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_certificate' ) . '` WHERE verify_token_hash = %s', hash( 'sha256', $token ) ) ) : null;
		if ( null === $row ) {
			throw new CourseException( 'ccap_not_found', 'Unknown certificate.', 404 );
		}
		return $this->public_view( $row );
	}

	/**
	 * Partner read (R0-19 `verify_certificate_v1` semantics, CRT-029): token →
	 * public fields; UID → full data with M-18 (holder) or certificate
	 * management, otherwise `ccap_not_found`.
	 *
	 * @param Actor  $actor           Caller.
	 * @param string $uid_or_token    Certificate UID or token.
	 * @return array<string, mixed>
	 * @throws CourseException `ccap_not_found`.
	 */
	public function verify_reference( Actor $actor, string $uid_or_token ): array {
		if ( 1 !== preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uid_or_token ) ) {
			return $this->verify( $uid_or_token );
		}
		$latest = $this->latest( $uid_or_token );
		if ( null === $latest || ! $this->policy->decide( $actor, PolicyService::VIEW_CERTIFICATE, array( (int) $latest['id'] ) )->allowed ) {
			throw new CourseException( 'ccap_not_found', 'Unknown certificate.', 404 );
		}
		return array(
			'status'      => self::status_of( $latest ),
			'course_uid'  => (string) ( json_decode( (string) $latest['snapshot'], true )['course']['course_uid'] ?? '' ),
			'issued_at'   => self::rfc3339( (string) $latest['issued_at'] ),
			'revision_no' => (int) $latest['revision_no'],
			'snapshot'    => (array) json_decode( (string) $latest['snapshot'], true ),
		);
	}

	/**
	 * Public field policy for one revision.
	 *
	 * @param array<string, mixed> $row Certificate row.
	 * @return array<string, mixed>
	 */
	private function public_view( array $row ): array {
		if ( 'revoked' === $row['status'] ) {
			return array(
				'status'     => 'revoked',
				'revoked_on' => self::site_date( (string) $row['revoked_at'] ),
			);
		}
		if ( 'corrected' === $row['status'] ) {
			$newest = (string) $this->db->scalar( $this->db->prepare( 'SELECT issued_at FROM `' . $this->db->table( 'ccap_certificate' ) . '` WHERE certificate_uid = %s ORDER BY revision_no DESC LIMIT 1', (string) $row['certificate_uid'] ) );
			return array(
				'status'        => 'corrected',
				'superseded_on' => self::site_date( $newest ),
			);
		}
		$snapshot = (array) json_decode( (string) $row['snapshot'], true );
		$out      = array(
			'status'       => self::status_of( $row ),
			'issuer'       => (string) ( $snapshot['issuer']['name'] ?? '' ),
			'course_title' => (string) ( $snapshot['course']['title'] ?? '' ),
			'issued_on'    => self::site_date( (string) $row['issued_at'] ),
			'valid_until'  => $snapshot['valid_until'] ?? null,
			'objectives'   => array_values( array_map( static fn( $o ): string => (string) ( ( (array) $o )['title'] ?? '' ), (array) ( $snapshot['objectives'] ?? array() ) ) ),
			'display_id'   => (string) ( $snapshot['display_id'] ?? '' ),
			'check_code'   => CertificateSnapshot::check_code( (string) $row['snapshot_hash'] ),
		);
		if ( $this->name_public( (int) $row['user_id'], (string) $row['certificate_uid'] ) ) {
			$out['holder_name'] = (string) ( $snapshot['holder']['display_name'] ?? '' );
		}
		return $out;
	}

	/**
	 * Holder's list (R-40, without `pdf_url`).
	 *
	 * @param Actor $actor Learner.
	 * @return list<array<string, mixed>>
	 * @throws CourseException `ccap_account_locked`.
	 */
	public function mine( Actor $actor ): array {
		if ( ( new Relations( $this->db ) )->is_locked( $actor->user_id ) ) {
			throw new CourseException( 'ccap_account_locked', 'Account locked.', 403 );
		}
		$table = $this->db->table( 'ccap_certificate' );
		$rows  = $this->db->rows( $this->db->prepare( "SELECT c.* FROM `{$table}` c WHERE c.user_id = %d AND c.revision_no = (SELECT MAX(x.revision_no) FROM `{$table}` x WHERE x.certificate_uid = c.certificate_uid) ORDER BY c.issued_at DESC, c.id DESC", $actor->user_id ) );
		$out   = array();
		foreach ( $rows as $row ) {
			$snapshot = (array) json_decode( (string) $row['snapshot'], true );
			$out[]    = array(
				'uid'                => (string) $row['certificate_uid'],
				'course_uid'         => (string) ( $snapshot['course']['course_uid'] ?? '' ),
				'course_title'       => (string) ( $snapshot['course']['title'] ?? '' ),
				'revision_no'        => (int) $row['revision_no'],
				'status'             => self::status_of( $row ),
				'issued_at'          => self::rfc3339( (string) $row['issued_at'] ),
				'valid_until'        => $snapshot['valid_until'] ?? null,
				'recertify'          => self::recertify_hint( $snapshot['valid_until'] ?? null ),
				'holder_name_public' => $this->name_public( (int) $row['user_id'], (string) $row['certificate_uid'] ),
				'view_url'           => CertificatePages::view_url( (string) $row['certificate_uid'] ),
			);
		}
		return $out;
	}

	/**
	 * One certificate for its holder (M-18) or a certificate manager: the
	 * newest revision with its verification link.
	 *
	 * @param Actor  $actor Viewer.
	 * @param string $uid   Certificate UID.
	 * @return array{row: array<string, mixed>, snapshot: array<string, mixed>, status: string, verify_url: string}
	 * @throws CourseException `ccap_not_found`, `ccap_account_locked`.
	 */
	public function view( Actor $actor, string $uid ): array {
		$latest = $this->latest( $uid );
		if ( null === $latest ) {
			throw new CourseException( 'ccap_not_found', 'Unknown certificate.', 404 );
		}
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::VIEW_CERTIFICATE, array( (int) $latest['id'] ) ), 'Not allowed to view this certificate.' );
		return array(
			'row'        => $latest,
			'snapshot'   => (array) json_decode( (string) $latest['snapshot'], true ),
			'status'     => self::status_of( $latest ),
			'verify_url' => CertificatePages::verify_url( self::token( $uid, (int) $latest['revision_no'] ) ),
		);
	}

	/**
	 * Name opt-in per certificate (CF-08, Δ-9): immediate, all revisions.
	 *
	 * @param Actor  $actor  Holder.
	 * @param string $uid    Certificate UID.
	 * @param bool   $visible Show the name on the verification page.
	 * @return bool
	 * @throws CourseException `ccap_not_found`, `ccap_account_locked`, `ccap_forbidden` (policy `never`).
	 */
	public function set_name_public( Actor $actor, string $uid, bool $visible ): bool {
		$latest = $this->latest( $uid );
		if ( null === $latest || (int) $latest['user_id'] !== $actor->user_id ) {
			throw new CourseException( 'ccap_not_found', 'Unknown certificate.', 404 );
		}
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::VIEW_CERTIFICATE, array( (int) $latest['id'] ) ), 'Not allowed.' );
		if ( $visible && 'never' === Settings::get( 'certificates.holder_name_policy' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Names are never shown on verification pages.', 403 );
		}
		$list = array_values( array_filter( array_map( 'strval', (array) get_user_meta( $actor->user_id, 'ccap_certificate_name_public', true ) ) ) );
		$list = array_values( array_diff( $list, array( $uid ) ) );
		if ( $visible ) {
			$list[] = $uid;
		}
		if ( array() === $list ) {
			delete_user_meta( $actor->user_id, 'ccap_certificate_name_public' );
		} else {
			update_user_meta( $actor->user_id, 'ccap_certificate_name_public', $list );
		}
		do_action( 'course_captain_policy_changed' );
		return $visible;
	}

	/**
	 * Verification rate limit `verify` (30/min per client, fail-closed, SEC-034).
	 *
	 * @param string $client Client key (address).
	 * @return void
	 * @throws CourseException `ccap_rate_limited` (429) or `ccap_temporarily_unavailable` (503).
	 */
	public function rate_limit( string $client ): void {
		$now    = $this->db->time();
		$window = $now - $now % 60;
		$bucket = hash( 'sha256', 'verify:' . $client );
		$table  = $this->db->table( 'ccap_rate_limit' );
		try {
			$this->db->execute(
				$this->db->prepare(
					"INSERT INTO `{$table}` (bucket_hash, window_start, hits, expires_at) VALUES (%s, %d, 1, %s) ON DUPLICATE KEY UPDATE hits = IF(window_start = VALUES(window_start), hits + 1, 1), window_start = VALUES(window_start), expires_at = VALUES(expires_at)",
					$bucket,
					$window,
					gmdate( 'Y-m-d H:i:s', $window + 120 )
				)
			);
			$hits = $this->db->scalar( $this->db->prepare( "SELECT hits FROM `{$table}` WHERE bucket_hash = %s", $bucket ) );
		} catch ( StorageException $e ) {
			unset( $e );
			$hits = null;
		}
		if ( null === $hits ) {
			throw new CourseException( 'ccap_temporarily_unavailable', 'Verification is temporarily unavailable.', 503 );
		}
		if ( (int) $hits > self::VERIFY_PER_MIN ) {
			throw new CourseException( 'ccap_rate_limited', 'Too many verification requests.', 429 );
		}
	}

	/**
	 * Integrity check (CRT-019): UIDs whose stored snapshot no longer
	 * matches its hash.
	 *
	 * @return list<string>
	 */
	public function integrity_failures(): array {
		$bad = array();
		$id  = 0;
		do {
			$rows = $this->db->rows( $this->db->prepare( 'SELECT id, certificate_uid, snapshot, snapshot_hash FROM `' . $this->db->table( 'ccap_certificate' ) . '` WHERE id > %d ORDER BY id LIMIT 500', $id ) );
			$more = 500 === count( $rows );
			foreach ( $rows as $row ) {
				$id = (int) $row['id'];
				if ( ! CertificateSnapshot::intact( (string) $row['snapshot'], (string) $row['snapshot_hash'] ) ) {
					$bad[] = (string) $row['certificate_uid'];
				}
			}
		} while ( $more );
		return array_values( array_unique( $bad ) );
	}

	/**
	 * Personal data export items (CF-12): snapshot data, no hashes or tokens.
	 *
	 * @param int $user_id User.
	 * @return list<array{group_id: string, group_label: string, item_id: string, data: list<array{name: string, value: string}>}>
	 */
	public function export_items( int $user_id ): array {
		$out = array();
		foreach ( $this->db->rows( $this->db->prepare( 'SELECT id, certificate_uid, revision_no, status, issued_at, revoked_at, snapshot FROM `' . $this->db->table( 'ccap_certificate' ) . '` WHERE user_id = %d ORDER BY id LIMIT 5000', $user_id ) ) as $row ) {
			$snapshot = (array) json_decode( (string) $row['snapshot'], true );
			$values   = array(
				'certificate_uid' => (string) $row['certificate_uid'],
				'display_id'      => (string) ( $snapshot['display_id'] ?? '' ),
				'revision_no'     => (string) $row['revision_no'],
				'status'          => (string) $row['status'],
				'issued_at'       => (string) $row['issued_at'],
				'revoked_at'      => (string) ( $row['revoked_at'] ?? '' ),
				'holder'          => (string) ( $snapshot['holder']['display_name'] ?? '' ),
				'course'          => (string) ( $snapshot['course']['title'] ?? '' ),
				'issuer'          => (string) ( $snapshot['issuer']['name'] ?? '' ),
				'evidence'        => (string) ( $snapshot['evidence']['type'] ?? '' ),
				'points'          => null === ( $snapshot['evidence']['points'] ?? null ) ? '' : (string) $snapshot['evidence']['points'],
				'max_points'      => null === ( $snapshot['evidence']['max_points'] ?? null ) ? '' : (string) $snapshot['evidence']['max_points'],
				'evaluated_at'    => (string) ( $snapshot['evidence']['evaluated_at'] ?? '' ),
				'valid_until'     => (string) ( $snapshot['valid_until'] ?? '' ),
				'objectives'      => implode( ', ', array_map( static fn( $o ): string => (string) ( ( (array) $o )['title'] ?? '' ), (array) ( $snapshot['objectives'] ?? array() ) ) ),
			);
			$data     = array();
			foreach ( $values as $name => $value ) {
				$data[] = array(
					'name'  => $name,
					'value' => $value,
				);
			}
			$out[] = array(
				'group_id'    => 'cc-certificates',
				'group_label' => 'Certificates',
				'item_id'     => 'cc-certificates-' . (int) $row['id'],
				'data'        => $data,
			);
		}
		return $out;
	}

	/**
	 * Erasure (CF-12, RT-09/RT-10): without `keep_evidence_on_erasure`
	 * (not yet in the settings register) certificates are deleted; their
	 * tokens then answer `ccap_not_found`.
	 *
	 * @param Database $db      Database (in the eraser transaction).
	 * @param int      $user_id User.
	 * @return int Rows deleted.
	 */
	public static function erase( Database $db, int $user_id ): int {
		$table = $db->table( 'ccap_certificate' );
		$db->execute( $db->prepare( "UPDATE `{$table}` SET supersedes_id = NULL WHERE user_id = %d", $user_id ) );
		$count = $db->execute( $db->prepare( "DELETE FROM `{$table}` WHERE user_id = %d", $user_id ) );
		delete_user_meta( $user_id, 'ccap_certificate_name_public' );
		return $count;
	}

	/**
	 * Retention RT-09: certificates whose newest revision is older than
	 * `$days` are deleted with all revisions.
	 *
	 * @param Database $db     Database.
	 * @param string   $cutoff UTC datetime.
	 * @return int Rows deleted.
	 */
	public static function expire_retention( Database $db, string $cutoff ): int {
		$table = $db->table( 'ccap_certificate' );
		$uids  = $db->wpdb()->get_col( $db->prepare( "SELECT certificate_uid FROM `{$table}` GROUP BY certificate_uid HAVING MAX(issued_at) < %s LIMIT 500", $cutoff ) );
		$count = 0;
		foreach ( $uids as $uid ) {
			$db->execute( $db->prepare( "UPDATE `{$table}` SET supersedes_id = NULL WHERE certificate_uid = %s", (string) $uid ) );
			$count += $db->execute( $db->prepare( "DELETE FROM `{$table}` WHERE certificate_uid = %s", (string) $uid ) );
		}
		return $count;
	}

	/**
	 * Verification token of a revision (HMAC with the installation secret;
	 * only its SHA-256 is stored).
	 *
	 * @param string $uid      Certificate UID.
	 * @param int    $revision Revision.
	 * @return string 40 hex characters.
	 */
	public static function token( string $uid, int $revision ): string {
		return substr( hash_hmac( 'sha256', 'ccap-certificate-verify|' . $uid . '|' . $revision, ProtectedStorage::secret() ), 0, 40 );
	}

	/**
	 * Current base template (CF-04): every change of `certificates.base_template`
	 * is a new `version`; issued certificates keep theirs. With a course,
	 * its leaf overrides apply (class A, R0-28 §1.4 #23); a definition that
	 * already has a version keeps that version number.
	 *
	 * @param int $course_id Course (0 = site template).
	 * @return array{template_uid: string, version: int, kind: string, definition_hash: string, definition: array<string, mixed>}
	 */
	public static function template( int $course_id = 0 ): array {
		$settings   = (array) ( $course_id > 0 ? EffectiveSettings::for_course( 'certificates.base_template', $course_id ) : Settings::get( 'certificates.base_template' ) );
		$definition = array(
			'issuer_name'     => (string) ( $settings['issuer_name'] ?? '' ),
			'footer_text'     => (string) ( $settings['footer_text'] ?? '' ),
			'logo_id'         => (int) ( $settings['logo_id'] ?? 0 ),
			'signature_label' => (string) ( $settings['signature_label'] ?? '' ),
		);
		$hash       = 'sha256:' . hash( 'sha256', CertificateSnapshot::canonical_json( $definition ) );
		$state      = get_option( 'course_captain_certificate_templates' );
		$state      = is_array( $state ) && isset( $state['template_uid'], $state['versions'] ) && is_array( $state['versions'] ) ? $state : array(
			'template_uid' => wp_generate_uuid4(),
			'versions'     => array(),
		);
		$versions   = array_values( (array) $state['versions'] );
		$last       = end( $versions );
		foreach ( $versions as $known ) {
			if ( is_array( $known ) && ( $known['definition_hash'] ?? '' ) === $hash ) {
				$last = $known;
			}
		}
		if ( ! is_array( $last ) || ( $last['definition_hash'] ?? '' ) !== $hash ) {
			$versions[]        = array(
				'version'         => count( $versions ) + 1,
				'definition_hash' => $hash,
				'created_at'      => gmdate( 'Y-m-d H:i:s' ),
			);
			$state['versions'] = $versions;
			update_option( 'course_captain_certificate_templates', $state, false );
			$last = end( $versions );
		}
		return array(
			'template_uid'    => (string) $state['template_uid'],
			'version'         => (int) $last['version'],
			'kind'            => 'base',
			'definition_hash' => $hash,
			'definition'      => $definition,
		);
	}

	/**
	 * Derived display status: `valid`, `expired` (valid_until passed; not a
	 * state, R0-23 §5.1), `corrected`, `revoked`.
	 *
	 * @param array<string, mixed> $row Certificate row.
	 * @return string
	 */
	public static function status_of( array $row ): string {
		if ( 'issued' !== $row['status'] ) {
			return (string) $row['status'];
		}
		$until = json_decode( (string) $row['snapshot'], true )['valid_until'] ?? null;
		return null !== $until && (string) $until < gmdate( 'Y-m-d' ) ? 'expired' : 'valid';
	}

	/**
	 * Recertification hint from 30 days before expiry (CF-14).
	 *
	 * @param mixed $valid_until Date or null.
	 * @return bool
	 */
	public static function recertify_hint( $valid_until ): bool {
		return is_string( $valid_until ) && $valid_until <= gmdate( 'Y-m-d', time() + self::RECERTIFY_DAYS * DAY_IN_SECONDS );
	}

	/**
	 * Newest revision of a certificate.
	 *
	 * @param string $uid Certificate UID.
	 * @return array<string, mixed>|null
	 */
	public function latest( string $uid ): ?array {
		return $this->db->row( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_certificate' ) . '` WHERE certificate_uid = %s ORDER BY revision_no DESC LIMIT 1', $uid ) );
	}

	/**
	 * Issue for normalized evidence; idempotent per evidence.
	 *
	 * @param array<string, mixed> $evidence Evidence.
	 * @param Actor|null           $actor    Manager (null = system).
	 * @param string               $reason   Reason (manual).
	 * @return array{id: int, certificate_uid: string, created: bool}
	 * @throws StorageException On database errors other than a lost race.
	 */
	private function issue_evidence( array $evidence, ?Actor $actor, string $reason ): array {
		$type        = (string) $evidence['type'];
		$evidence_id = (int) $evidence['id'];
		$existing    = $this->existing( $type, $evidence_id );
		if ( null !== $existing ) {
			return $existing;
		}
		$template = self::template( (int) $evidence['course_id'] );
		try {
			return $this->db->transaction(
				function ( Database $db ) use ( $evidence, $actor, $reason, $type, $evidence_id, $template ): array {
					$locked = $db->row( $db->prepare( 'SELECT id, certificate_uid FROM `' . $db->table( 'ccap_certificate' ) . '` WHERE evidence_type = %s AND evidence_id = %d ORDER BY revision_no LIMIT 1 FOR UPDATE', $type, $evidence_id ) );
					if ( null !== $locked ) {
						return array(
							'id'              => (int) $locked['id'],
							'certificate_uid' => (string) $locked['certificate_uid'],
							'created'         => false,
						);
					}
					$uid      = $db->uuid();
					$snapshot = $this->build_snapshot( $evidence, $uid, $template, $actor );
					$sealed   = CertificateSnapshot::seal( $snapshot );
					$id       = $db->insert(
						'ccap_certificate',
						array(
							'certificate_uid'    => $uid,
							'revision_no'        => 1,
							'user_id'            => (int) $evidence['user_id'],
							'course_id'          => (int) $evidence['course_id'],
							'course_revision_id' => (int) $evidence['course_revision_id'],
							'evidence_type'      => $type,
							'evidence_id'        => $evidence_id,
							'status'             => 'issued',
							'template_version'   => (string) $template['version'],
							'snapshot'           => $sealed['json'],
							'snapshot_hash'      => $sealed['hash'],
							'verify_token_hash'  => hash( 'sha256', self::token( $uid, 1 ) ),
							'issued_at'          => $db->now(),
							'issued_by'          => null === $actor ? null : $actor->user_id,
							'created_at'         => $db->now(),
						)
					);
					if ( null !== $actor ) {
						AuditLog::write(
							$db,
							$actor,
							'certificate_issued',
							'certificate',
							$uid,
							$reason,
							array(
								'evidence_type' => $type,
								'evidence_ref'  => (string) $evidence['ref_uuid'],
							),
							(int) $evidence['user_id']
						);
					}
					Outbox::record(
						$db,
						'certificate.issued',
						'certificate',
						$uid,
						1,
						array(
							'certificate_uid' => $uid,
							'revision_no'     => 1,
							'evidence_type'   => $type,
							'evidence_ref'    => (string) $evidence['ref_uuid'],
							'valid_until'     => $evidence['valid_until'],
						),
						array(
							'user_id'      => (int) $evidence['user_id'],
							'course_uid'   => (string) $evidence['course_uid'],
							'actor_type'   => null === $actor ? 'system' : 'user',
							'privacy_tags' => array( 'personal_ref', 'certificate' ),
						)
					);
					return array(
						'id'              => $id,
						'certificate_uid' => $uid,
						'created'         => true,
					);
				}
			);
		} catch ( StorageException $e ) {
			// Lost race on UNIQUE evidence_rev: the other call issued it.
			$existing = $this->existing( $type, $evidence_id );
			if ( null === $existing ) {
				throw $e;
			}
			return $existing;
		}
	}

	/**
	 * Existing certificate for an evidence.
	 *
	 * @param string $type        Evidence type.
	 * @param int    $evidence_id Evidence row.
	 * @return array{id: int, certificate_uid: string, created: bool}|null
	 */
	private function existing( string $type, int $evidence_id ): ?array {
		$row = $this->db->row( $this->db->prepare( 'SELECT id, certificate_uid FROM `' . $this->db->table( 'ccap_certificate' ) . '` WHERE evidence_type = %s AND evidence_id = %d ORDER BY revision_no LIMIT 1', $type, $evidence_id ) );
		return null === $row ? null : array(
			'id'              => (int) $row['id'],
			'certificate_uid' => (string) $row['certificate_uid'],
			'created'         => false,
		);
	}

	/**
	 * Whether the learner already holds a non-revoked certificate of the course.
	 *
	 * @param int $user_id   Learner.
	 * @param int $course_id Course.
	 * @return bool
	 */
	private function holds_valid_certificate( int $user_id, int $course_id ): bool {
		return null !== $this->db->scalar( $this->db->prepare( 'SELECT id FROM `' . $this->db->table( 'ccap_certificate' ) . "` WHERE user_id = %d AND course_id = %d AND status = 'issued' LIMIT 1", $user_id, $course_id ) );
	}

	/**
	 * Normalized evidence, or null when it does not exist.
	 *
	 * @param string $type        Evidence type.
	 * @param int    $evidence_id Row.
	 * @return array<string, mixed>|null
	 */
	private function evidence( string $type, int $evidence_id ): ?array {
		if ( self::EVIDENCE_EXAM === $type ) {
			$row = $this->db->row( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $evidence_id ) );
			if ( null === $row ) {
				return null;
			}
			$snapshot  = (array) json_decode( (string) $row['snapshot'], true );
			$revision  = null === $row['course_revision_id'] ? $this->current_revision( (int) $row['course_id'] ) : (int) $row['course_revision_id'];
			$days      = $snapshot['competency']['validity_days'] ?? null;
			$evaluated = null === $row['evaluated_at'] ? null : (string) $row['evaluated_at'];
			return array(
				'type'               => self::EVIDENCE_EXAM,
				'id'                 => (int) $row['id'],
				'ref_uuid'           => (string) $row['uid'],
				'user_id'            => (int) $row['user_id'],
				'course_id'          => (int) $row['course_id'],
				'course_revision_id' => $revision,
				'course_uid'         => $this->course_uid( (int) $row['course_id'], $revision ),
				'points'             => null === $row['points_milli'] ? null : (int) $row['points_milli'],
				'max_points'         => null === $row['max_points_milli'] ? null : (int) $row['max_points_milli'],
				'passed'             => '1' === (string) $row['passed'],
				'evaluated_at'       => $evaluated,
				'submitted_at'       => null === $row['submitted_at'] ? $evaluated : (string) $row['submitted_at'],
				'evaluation_no'      => (int) $row['evaluation_no'],
				'rule_uid'           => isset( $snapshot['rule']['uid'] ) ? (string) $snapshot['rule']['uid'] : null,
				'rule_version'       => isset( $snapshot['rule']['version'] ) ? (string) $snapshot['rule']['version'] : null,
				'objectives'         => array_values( array_map( 'strval', (array) ( $snapshot['competency']['certifies_objectives'] ?? array() ) ) ),
				'valid_until'        => null === $days || null === $evaluated ? null : gmdate( 'Y-m-d', (int) strtotime( $evaluated . ' UTC' ) + DAY_IN_SECONDS * max( 1, (int) $days ) ),
				'eligible'           => 'evaluated' === $row['status'] && '1' === (string) $row['passed'] && ! empty( $snapshot['evidence_eligible'] ) && empty( $snapshot['reduced'] ) && empty( $snapshot['practice'] ) && null !== $revision && null !== $evaluated,
			);
		}
		if ( self::EVIDENCE_COURSE === $type ) {
			$row = $this->db->row( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_course_progress' ) . '` WHERE id = %d', $evidence_id ) );
			if ( null === $row ) {
				return null;
			}
			$revision = null === $row['completed_revision_id'] ? null : (int) $row['completed_revision_id'];
			$done     = null === $row['completed_at'] ? null : (string) $row['completed_at'];
			return array(
				'type'               => self::EVIDENCE_COURSE,
				'id'                 => (int) $row['id'],
				'ref_uuid'           => self::uuid_from( 'course_completion|' . (int) $row['id'] . '|' . (int) $row['cycle_no'] ),
				'user_id'            => (int) $row['user_id'],
				'course_id'          => (int) $row['course_id'],
				'course_revision_id' => $revision,
				'course_uid'         => null === $revision ? '' : $this->course_uid( (int) $row['course_id'], $revision ),
				'points'             => null,
				'max_points'         => null,
				'passed'             => 'completed' === $row['status'],
				'evaluated_at'       => $done,
				'submitted_at'       => $done,
				'evaluation_no'      => (int) $row['cycle_no'],
				'rule_uid'           => null,
				'rule_version'       => (string) \CourseCaptain\Progress\CourseProgress::RULE_VERSION,
				'objectives'         => array(),
				'valid_until'        => null,
				'eligible'           => 'completed' === $row['status'] && null !== $revision && null !== $done,
			);
		}
		return null;
	}

	/**
	 * E-113: an exam-scope grant was active at submission. Direct grants of
	 * the course whose window contains the submission time count when they
	 * are active or ended by time; revoked/suspended grants do not.
	 *
	 * @param array<string, mixed> $evidence Exam evidence.
	 * @return bool
	 */
	private function grant_at_submission( array $evidence ): bool {
		$user   = (int) $evidence['user_id'];
		$course = (int) $evidence['course_id'];
		$at     = (string) $evidence['submitted_at'];
		$rows   = $this->db->rows( $this->db->prepare( 'SELECT scopes, status, valid_from, valid_until FROM `' . $this->db->table( 'ccap_grant' ) . "` WHERE user_id = %d AND course_id = %d AND status IN ('active', 'expired')", $user, $course ) );
		foreach ( $rows as $row ) {
			$scopes = array_map( 'trim', explode( ',', (string) $row['scopes'] ) );
			if ( ! in_array( 'all', $scopes, true ) && ! in_array( 'exam', $scopes, true ) ) {
				continue;
			}
			if ( null !== $row['valid_from'] && (string) $row['valid_from'] > $at ) {
				continue;
			}
			if ( null !== $row['valid_until'] && (string) $row['valid_until'] <= $at ) {
				continue;
			}
			return true;
		}
		// Bundles and dependent grants: the current effective access.
		return 'ok' === ( new Relations( $this->db ) )->effective_access( $user, $course, 0, 'exam' );
	}

	/**
	 * Build the snapshot of a first issuance.
	 *
	 * @param array<string, mixed>                                                                                               $evidence Evidence.
	 * @param string                                                                                                             $uid      Certificate UID.
	 * @param array{template_uid: string, version: int, kind: string, definition_hash: string, definition: array<string, mixed>} $template Template.
	 * @param Actor|null                                                                                                         $actor    Manager or null (system).
	 * @return array<string, mixed>
	 */
	private function build_snapshot( array $evidence, string $uid, array $template, ?Actor $actor ): array {
		$definition = $template['definition'];
		$user       = get_userdata( (int) $evidence['user_id'] );
		$course_id  = (int) $evidence['course_id'];
		$issuer     = array( 'name' => '' !== (string) $definition['issuer_name'] ? (string) $definition['issuer_name'] : wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) );
		$logo_id    = (int) $definition['logo_id'];
		if ( $logo_id > 0 && wp_attachment_is_image( $logo_id ) ) {
			$file           = get_attached_file( $logo_id );
			$issuer['logo'] = array(
				'attachment_id' => $logo_id,
				'sha256'        => is_string( $file ) && is_readable( $file ) ? hash_file( 'sha256', $file ) : null,
				'alt'           => (string) get_post_meta( $logo_id, '_wp_attachment_image_alt', true ),
			);
		}
		if ( '' !== (string) $definition['signature_label'] ) {
			$issuer['signature_label'] = (string) $definition['signature_label'];
		}
		$title = (string) get_post_field( 'post_title', $course_id, 'raw' );
		return array(
			'format_version'  => CertificateSnapshot::FORMAT_VERSION,
			'certificate_uid' => $uid,
			'revision_no'     => 1,
			'revision_kind'   => 'initial',
			'display_id'      => CertificateSnapshot::display_id( $uid ),
			'template'        => array(
				'template_uid'    => $template['template_uid'],
				'version'         => $template['version'],
				'kind'            => $template['kind'],
				'definition_hash' => $template['definition_hash'],
			),
			'locale'          => array(
				'content' => CourseStructure::course_language( $course_id ),
				'ui'      => (string) get_locale(),
			),
			'issued_at'       => gmdate( 'Y-m-d\TH:i:s\Z', $this->db->time() ),
			'issued_by'       => self::issued_by( $actor ),
			'issuer'          => $issuer,
			'holder'          => array( 'display_name' => $user instanceof \WP_User ? (string) $user->display_name : '' ),
			'course'          => array(
				'course_uid'         => (string) $evidence['course_uid'],
				'title'              => $title,
				'course_revision_id' => (int) $evidence['course_revision_id'],
			),
			'evidence'        => self::evidence_snapshot( $evidence ),
			'objectives'      => array_map(
				static fn( string $objective ): array => array(
					'uid'   => $objective,
					'title' => $objective,
				),
				(array) $evidence['objectives']
			),
			'valid_until'     => $evidence['valid_until'],
			'fields'          => array(),
			'assets'          => array(),
			'texts'           => array(
				'heading' => self::EVIDENCE_EXAM === $evidence['type'] ? __( 'Certificate of achievement', 'course-captain' ) : __( 'Certificate of completion', 'course-captain' ),
				'body'    => self::EVIDENCE_EXAM === $evidence['type'] ? __( 'has passed the assessment of the course', 'course-captain' ) : __( 'has successfully completed the course', 'course-captain' ),
				'footer'  => (string) $definition['footer_text'],
			),
			'verify'          => array( 'path_at_issue' => CertificatePages::verify_path() ),
		);
	}

	/**
	 * Snapshot part `evidence`.
	 *
	 * @param array<string, mixed> $evidence Evidence.
	 * @return array<string, mixed>
	 */
	private static function evidence_snapshot( array $evidence ): array {
		return array(
			'type'              => (string) $evidence['type'],
			'ref_uuid'          => (string) $evidence['ref_uuid'],
			'points'            => $evidence['points'],
			'max_points'        => $evidence['max_points'],
			'passed'            => (bool) $evidence['passed'],
			'evaluated_at'      => self::rfc3339( (string) $evidence['evaluated_at'] ),
			'evaluation_no'     => (int) $evidence['evaluation_no'],
			'rule_uid'          => $evidence['rule_uid'],
			'rule_version'      => $evidence['rule_version'],
			'evidence_eligible' => (bool) $evidence['eligible'],
		);
	}

	/**
	 * `issued_by` without names.
	 *
	 * @param Actor|null $actor Actor.
	 * @return array{type: string, role: string}
	 */
	private static function issued_by( ?Actor $actor ): array {
		return null === $actor || $actor->is_system()
			? array(
				'type' => 'system',
				'role' => 'system',
			)
			: array(
				'type' => 'user',
				'role' => 'certificate_manager',
			);
	}

	/**
	 * R0-23 §5.2: passed → failed by re-evaluation never revokes; it only
	 * records a review task.
	 *
	 * @param int $session Exam session.
	 * @return void
	 */
	private function review_required( int $session ): void {
		$existing = $this->existing( self::EVIDENCE_EXAM, $session );
		if ( null === $existing ) {
			return;
		}
		AuditLog::write( $this->db, Actor::cron(), 'certificate_review_required', 'certificate', $existing['certificate_uid'], null, array( 'evidence_type' => self::EVIDENCE_EXAM ) );
	}

	/**
	 * Idempotency of an admin request (`ccap_request_idempotency`).
	 *
	 * @param Database $db    Database (in transaction).
	 * @param Actor    $actor Actor.
	 * @param string   $scope Scope.
	 * @param string   $key   Key.
	 * @param string   $hash  Request hash.
	 * @return array<string, mixed>|null Stored response, or null for a new request.
	 * @throws CourseException `ccap_idempotency_conflict`, `ccap_request_in_progress`.
	 */
	private function idempotent( Database $db, Actor $actor, string $scope, string $key, string $hash ): ?array {
		$table    = $db->table( 'ccap_request_idempotency' );
		$inserted = $db->execute(
			$db->prepare(
				"INSERT IGNORE INTO `{$table}` (user_id, scope, idem_key, request_hash, status_code, created_at, expires_at) VALUES (%d, %s, %s, %s, 0, %s, %s)",
				$actor->user_id,
				$scope,
				substr( $key, 0, 36 ),
				$hash,
				$db->now(),
				$db->now( self::IDEMPOTENCY_DAYS * DAY_IN_SECONDS )
			)
		);
		if ( 1 === $inserted ) {
			return null;
		}
		$row = (array) $db->row( $db->prepare( "SELECT request_hash, status_code, response FROM `{$table}` WHERE user_id = %d AND scope = %s AND idem_key = %s", $actor->user_id, $scope, substr( $key, 0, 36 ) ) );
		if ( ! hash_equals( (string) ( $row['request_hash'] ?? '' ), $hash ) ) {
			throw new CourseException( 'ccap_idempotency_conflict', 'The request key was used for another request.', 409 );
		}
		$response = json_decode( (string) ( $row['response'] ?? '' ), true );
		if ( 200 !== (int) ( $row['status_code'] ?? 0 ) || ! is_array( $response ) ) {
			throw new CourseException( 'ccap_request_in_progress', 'The request is still in progress.', 409 );
		}
		return $response;
	}

	/**
	 * Whether the holder released the name for this certificate (and the
	 * site policy allows it).
	 *
	 * @param int    $user_id Holder.
	 * @param string $uid     Certificate UID.
	 * @return bool
	 */
	private function name_public( int $user_id, string $uid ): bool {
		if ( 'learner_opt_in' !== Settings::get( 'certificates.holder_name_policy' ) ) {
			return false;
		}
		return in_array( $uid, array_map( 'strval', (array) get_user_meta( $user_id, 'ccap_certificate_name_public', true ) ), true );
	}

	/**
	 * Current published course revision.
	 *
	 * @param int $course_id Course.
	 * @return int|null
	 */
	private function current_revision( int $course_id ): ?int {
		$id = $this->db->scalar( $this->db->prepare( 'SELECT id FROM `' . $this->db->table( 'ccap_course_revision' ) . "` WHERE course_id = %d AND status = 'published' ORDER BY revision_no DESC LIMIT 1", $course_id ) );
		return null === $id ? null : (int) $id;
	}

	/**
	 * Course UID frozen in a revision.
	 *
	 * @param int      $course_id Course.
	 * @param int|null $revision  Revision.
	 * @return string
	 */
	private function course_uid( int $course_id, ?int $revision ): string {
		if ( null !== $revision ) {
			$structure = json_decode( (string) $this->db->scalar( $this->db->prepare( 'SELECT structure FROM `' . $this->db->table( 'ccap_course_revision' ) . '` WHERE id = %d', $revision ) ), true );
			if ( is_array( $structure ) && isset( $structure['course']['uid'] ) ) {
				return (string) $structure['course']['uid'];
			}
		}
		return (string) ( new \CourseCaptain\Course\ObjectIdentity( $this->db ) )->uid_of( 'course', $course_id );
	}

	/**
	 * Deterministic UUID-formatted reference.
	 *
	 * @param string $seed Seed.
	 * @return string
	 */
	private static function uuid_from( string $seed ): string {
		$hex = hash( 'sha256', $seed );
		return sprintf( '%s-%s-4%s-%s%s-%s', substr( $hex, 0, 8 ), substr( $hex, 8, 4 ), substr( $hex, 13, 3 ), dechex( 8 | ( hexdec( $hex[16] ) & 3 ) ), substr( $hex, 17, 3 ), substr( $hex, 20, 12 ) );
	}

	/**
	 * MySQL UTC datetime → RFC 3339.
	 *
	 * @param string $datetime Datetime.
	 * @return string
	 */
	private static function rfc3339( string $datetime ): string {
		return '' === $datetime ? '' : gmdate( 'Y-m-d\TH:i:s\Z', (int) strtotime( $datetime . ' UTC' ) );
	}

	/**
	 * Date without time in the site time zone (R0-29 §2.5).
	 *
	 * @param string $datetime UTC datetime.
	 * @return string
	 */
	public static function site_date( string $datetime ): string {
		return '' === $datetime ? '' : (string) wp_date( 'Y-m-d', (int) strtotime( $datetime . ' UTC' ) );
	}
}
