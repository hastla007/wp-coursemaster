<?php
/**
 * REST contract layer for `course-captain/v1` (R0-19 §1.5, §2.1, §3.1).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Every response of the namespace carries `X-Request-Id`; every error body
 * gets the RFC 9457 fields in the WordPress envelope (`code`, `message`,
 * `data.status` + `type`, `title`, `status`, `detail`, `request_id`,
 * `reason`, `retry_after`, `errors`, `details`). Deprecated routes answer
 * with `Deprecation`, `Sunset` and `Link` headers.
 */
final class RestContract {

	public const NAMESPACE = 'course-captain/v1';

	/**
	 * Request ID of the current request.
	 *
	 * @var string|null
	 */
	private static ?string $request_id = null;

	/**
	 * Deprecated routes: route regex → since (Unix), sunset (Unix), doc URL.
	 *
	 * @var array<string, array{since: int, sunset: int, link: string}>
	 */
	private static array $deprecated = array();

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'rest_post_dispatch', array( self::class, 'shape' ), 98, 3 );
	}

	/**
	 * Request ID (`req_<32 hex>`), stable within the request.
	 *
	 * @return string
	 */
	public static function request_id(): string {
		if ( null === self::$request_id ) {
			self::$request_id = 'req_' . bin2hex( random_bytes( 16 ) );
		}
		return self::$request_id;
	}

	/**
	 * Mark a route as deprecated (R0-19 §1.5).
	 *
	 * @param string $route  Route regex as registered.
	 * @param int    $since  Deprecation time (Unix).
	 * @param int    $sunset Removal time (Unix).
	 * @param string $link   Documentation URL.
	 * @return void
	 */
	public static function deprecate( string $route, int $since, int $sunset, string $link ): void {
		self::$deprecated[ $route ] = array(
			'since'  => $since,
			'sunset' => $sunset,
			'link'   => $link,
		);
	}

	/**
	 * Shape responses of the own namespace.
	 *
	 * @param mixed $response Response.
	 * @param mixed $server   Server.
	 * @param mixed $request  Request.
	 * @return mixed
	 */
	public static function shape( $response, $server, $request ) {
		unset( $server );
		if ( ! $response instanceof \WP_HTTP_Response || ! $request instanceof \WP_REST_Request || 0 !== strpos( $request->get_route(), '/' . self::NAMESPACE . '/' ) ) {
			return $response;
		}
		$id = self::request_id();
		$response->header( 'X-Request-Id', $id );
		$matched = $response instanceof \WP_REST_Response ? (string) $response->get_matched_route() : '';
		if ( isset( self::$deprecated[ $matched ] ) ) {
			$info = self::$deprecated[ $matched ];
			$response->header( 'Deprecation', '@' . $info['since'] );
			$response->header( 'Sunset', gmdate( 'D, d M Y H:i:s', $info['sunset'] ) . ' GMT' );
			$response->header( 'Link', '<' . esc_url_raw( $info['link'] ) . '>; rel="deprecation"' );
		}
		$status = $response->get_status();
		$data   = $response->get_data();
		if ( $status < 400 || ! is_array( $data ) || ! isset( $data['code'] ) ) {
			return $response;
		}
		$response->set_data( self::problem( $data, $status, $id ) );
		$retry = $response->get_data()['retry_after'];
		if ( null !== $retry ) {
			$response->header( 'Retry-After', (string) $retry );
		}
		return $response;
	}

	/**
	 * Error body per §3.1.
	 *
	 * @param array<string, mixed> $data   WordPress error body.
	 * @param int                  $status HTTP status.
	 * @param string               $id     Request ID.
	 * @return array<string, mixed>
	 */
	public static function problem( array $data, int $status, string $id ): array {
		$code = (string) $data['code'];
		if ( 401 === $status && in_array( $code, array( 'rest_forbidden', 'rest_not_logged_in' ), true ) ) {
			$code = 'ccap_auth_required';
		}
		$extra   = is_array( $data['data'] ?? null ) ? $data['data'] : array();
		$message = self::message( $code, (string) ( $data['message'] ?? '' ) );
		$details = array_diff_key( $extra, array_flip( array( 'status', 'reason', 'retry_after', 'errors', 'details', 'params' ) ) );
		if ( isset( $extra['details'] ) && is_array( $extra['details'] ) ) {
			$details = $extra['details'] + $details;
		}
		return array(
			'code'        => $code,
			'message'     => $message,
			'data'        => array( 'status' => $status ) + ( isset( $extra['params'] ) ? array( 'params' => $extra['params'] ) : array() ),
			'type'        => 'urn:course-captain:problem:' . str_replace( '_', '-', (string) preg_replace( '/^ccap_/', '', $code ) ),
			'title'       => self::title( $code ),
			'status'      => $status,
			'detail'      => $message,
			'request_id'  => $id,
			'reason'      => isset( $extra['reason'] ) ? (string) $extra['reason'] : null,
			'retry_after' => isset( $extra['retry_after'] ) ? (int) $extra['retry_after'] : null,
			'errors'      => isset( $extra['errors'] ) && is_array( $extra['errors'] ) ? $extra['errors'] : null,
			'details'     => array() === $details ? null : $details,
		);
	}

	/**
	 * User-facing message: own codes use the catalog text; WordPress core codes keep theirs.
	 *
	 * @param string $code    Code.
	 * @param string $message Original message.
	 * @return string
	 */
	private static function message( string $code, string $message ): string {
		if ( 0 !== strpos( $code, 'ccap_' ) ) {
			return $message;
		}
		$catalog = self::messages();
		return $catalog[ $code ] ?? __( 'The request could not be completed.', 'course-captain' );
	}

	/**
	 * Short title per code.
	 *
	 * @param string $code Code.
	 * @return string
	 */
	public static function title( string $code ): string {
		$titles = array(
			'ccap_auth_required'             => __( 'Login required', 'course-captain' ),
			'ccap_forbidden'                 => __( 'Not allowed', 'course-captain' ),
			'ccap_account_locked'            => __( 'Account locked', 'course-captain' ),
			'ccap_content_locked'            => __( 'Not available yet', 'course-captain' ),
			'ccap_not_found'                 => __( 'Not found', 'course-captain' ),
			'ccap_validation_failed'         => __( 'Check your input', 'course-captain' ),
			'ccap_validation_unacknowledged' => __( 'Confirmation needed', 'course-captain' ),
			'ccap_invalid_param'             => __( 'Invalid input', 'course-captain' ),
			'ccap_invalid_token'             => __( 'Invalid link or token', 'course-captain' ),
			'ccap_idempotency_conflict'      => __( 'Request conflict', 'course-captain' ),
			'ccap_request_in_progress'       => __( 'Still in progress', 'course-captain' ),
			'ccap_version_conflict'          => __( 'Changed in the meantime', 'course-captain' ),
			'ccap_rate_limited'              => __( 'Too many requests', 'course-captain' ),
			'ccap_maintenance'               => __( 'Maintenance', 'course-captain' ),
			'ccap_temporarily_unavailable'   => __( 'Temporarily unavailable', 'course-captain' ),
			'ccap_exam_not_startable'        => __( 'Exam cannot start', 'course-captain' ),
			'ccap_exam_start_busy'           => __( 'Exam is starting', 'course-captain' ),
			'ccap_exam_attempts_exhausted'   => __( 'No attempts left', 'course-captain' ),
			'ccap_exam_expired'              => __( 'Time is over', 'course-captain' ),
			'ccap_exam_already_submitted'    => __( 'Already submitted', 'course-captain' ),
			'ccap_exam_aborted'              => __( 'Attempt cancelled', 'course-captain' ),
			'ccap_exam_answer_locked'        => __( 'Answer locked', 'course-captain' ),
			'ccap_exam_invalid_answer'       => __( 'Invalid answer', 'course-captain' ),
			'ccap_exam_conflict'             => __( 'Changed in the meantime', 'course-captain' ),
			'ccap_submission_not_editable'   => __( 'Cannot be changed', 'course-captain' ),
			'ccap_submission_incomplete'     => __( 'Submission incomplete', 'course-captain' ),
			'ccap_submission_file_rejected'  => __( 'File not accepted', 'course-captain' ),
			'ccap_grade_invalid'             => __( 'Check the grading', 'course-captain' ),
		);
		return $titles[ $code ] ?? __( 'Error', 'course-captain' );
	}

	/**
	 * Translated messages per code (no technical details).
	 *
	 * @return array<string, string>
	 */
	private static function messages(): array {
		return array(
			'ccap_auth_required'             => __( 'Please log in.', 'course-captain' ),
			'ccap_forbidden'                 => __( 'You are not allowed to do this.', 'course-captain' ),
			'ccap_account_locked'            => __( 'Your learning access on this site is locked.', 'course-captain' ),
			'ccap_content_locked'            => __( 'This content is not available to you right now.', 'course-captain' ),
			'ccap_not_found'                 => __( 'The requested item was not found.', 'course-captain' ),
			'ccap_validation_failed'         => __( 'Some data is not valid. Please check it.', 'course-captain' ),
			'ccap_validation_unacknowledged' => __( 'Please confirm the warnings first.', 'course-captain' ),
			'ccap_invalid_param'             => __( 'A parameter is missing or invalid.', 'course-captain' ),
			'ccap_invalid_token'             => __( 'This link or token is not valid.', 'course-captain' ),
			'ccap_idempotency_conflict'      => __( 'This request key was already used for a different request.', 'course-captain' ),
			'ccap_request_in_progress'       => __( 'The same request is still being processed. Please try again shortly.', 'course-captain' ),
			'ccap_version_conflict'          => __( 'Someone changed this in the meantime. Please reload.', 'course-captain' ),
			'ccap_rate_limited'              => __( 'Too many requests. Please wait a moment.', 'course-captain' ),
			'ccap_maintenance'               => __( 'Maintenance is running. Please try again shortly.', 'course-captain' ),
			'ccap_temporarily_unavailable'   => __( 'This is temporarily unavailable. Please try again later.', 'course-captain' ),
			'ccap_exam_not_startable'        => __( 'This exam cannot be started right now.', 'course-captain' ),
			'ccap_exam_start_busy'           => __( 'The exam is already starting. Please wait a moment.', 'course-captain' ),
			'ccap_exam_attempts_exhausted'   => __( 'You have no attempts left for this exam right now.', 'course-captain' ),
			'ccap_exam_expired'              => __( 'The time for this exam is over.', 'course-captain' ),
			'ccap_exam_already_submitted'    => __( 'This attempt has already been submitted.', 'course-captain' ),
			'ccap_exam_aborted'              => __( 'This attempt was cancelled.', 'course-captain' ),
			'ccap_exam_answer_locked'        => __( 'This answer can no longer be changed.', 'course-captain' ),
			'ccap_exam_invalid_answer'       => __( 'Please choose a valid answer.', 'course-captain' ),
			'ccap_exam_conflict'             => __( 'The attempt changed in the meantime. Please try again.', 'course-captain' ),
			'ccap_submission_not_editable'   => __( 'This submission can no longer be changed.', 'course-captain' ),
			'ccap_submission_incomplete'     => __( 'Please add the required text or file before submitting.', 'course-captain' ),
			'ccap_submission_file_rejected'  => __( 'This file was not accepted. Please check its type and size.', 'course-captain' ),
			'ccap_grade_invalid'             => __( 'Please check the points and add the required comment or reason.', 'course-captain' ),
		);
	}
}
