<?php
/**
 * Transactional internal outbox (R0-19 §5.1/§5.4, R0-06 §4.6, E-38/E-182).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Event;

use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Writes the envelope v1 into `ccap_outbox_event` and one
 * `ccap_outbox_delivery` row per internal subscriber, inside the caller's
 * transaction. In Free an event is only stored when at least one internal
 * subscriber wants it. Dispatch, retries and dead-letter: R1-43.
 */
final class Outbox {

	public const SCHEMA_VERSION = '1.0';

	/**
	 * Internal subscribers and their event patterns (`*` suffix wildcard).
	 *
	 * @return array<string, list<string>>
	 */
	public static function subscribers(): array {
		$defaults = array(
			// Cache invalidation of access-dependent views (R0-21).
			'cache' => array( 'grant.*', 'access.changed', 'group.*', 'seat.*' ),
		);
		/**
		 * Internal subscribers (Free modules only; external subscribers are Pro, E-38).
		 *
		 * @since 1.0.0
		 * @param array<string, list<string>> $defaults Subscriber => patterns.
		 */
		$filtered = apply_filters( 'course_captain_internal_subscribers', $defaults );
		return is_array( $filtered ) ? $filtered : $defaults;
	}

	/**
	 * Subscribers of an event type.
	 *
	 * @param string $event_type Event type.
	 * @return list<string>
	 */
	public static function subscribers_for( string $event_type ): array {
		$out = array();
		foreach ( self::subscribers() as $subscriber => $patterns ) {
			foreach ( (array) $patterns as $pattern ) {
				$pattern = (string) $pattern;
				if ( $pattern === $event_type || ( str_ends_with( $pattern, '*' ) && str_starts_with( $event_type, substr( $pattern, 0, -1 ) ) ) ) {
					$out[] = (string) $subscriber;
					break;
				}
			}
		}
		return $out;
	}

	/**
	 * Record an event (call inside the business transaction).
	 *
	 * @param Database             $db              Database (in transaction).
	 * @param string               $event_type      Event type, e.g. `grant.activated`.
	 * @param string               $object_type     Aggregate type (grant, seat, course).
	 * @param string               $object_uid      UID of the aggregate.
	 * @param int                  $object_version  Monotonic object version.
	 * @param array<string, mixed> $payload         Payload without personal data.
	 * @param array<string, mixed> $meta            `course_uid`, `user_id`, `actor_type`, `actor_ref`, `correlation_id`, `causation_id`, `idempotency_key`, `privacy_tags`.
	 * @return string|null Event UUID, or null when no subscriber wants it.
	 */
	public static function record( Database $db, string $event_type, string $object_type, string $object_uid, int $object_version, array $payload, array $meta = array() ): ?string {
		$subscribers = self::subscribers_for( $event_type );
		if ( array() === $subscribers ) {
			return null;
		}
		$uuid        = $db->uuid();
		$user_ref    = isset( $meta['user_id'] ) && (int) $meta['user_id'] > 0 ? 'user:' . (int) $meta['user_id'] : null;
		$correlation = (string) ( $meta['correlation_id'] ?? $db->uuid() );
		$tags        = implode( ',', (array) ( $meta['privacy_tags'] ?? array() ) );
		$envelope    = array(
			'event_id'         => $uuid,
			'event_type'       => $event_type,
			'schema_version'   => self::SCHEMA_VERSION,
			'contract_version' => defined( 'COURSE_CAPTAIN_CONTRACT_VERSION' ) ? COURSE_CAPTAIN_CONTRACT_VERSION : '0.0.0',
			'source_plugin'    => 'course-captain',
			'source_version'   => defined( 'COURSE_CAPTAIN_VERSION' ) ? COURSE_CAPTAIN_VERSION : '',
			'site_id'          => get_current_blog_id(),
			'occurred_at'      => gmdate( 'Y-m-d\TH:i:s.000\Z', $db->time() ),
			'object_type'      => $object_type,
			'object_id'        => $object_uid,
			'object_version'   => $object_version,
			'course_uid'       => $meta['course_uid'] ?? null,
			'user_ref'         => $user_ref,
			'actor_type'       => (string) ( $meta['actor_type'] ?? 'system' ),
			'actor_ref'        => $meta['actor_ref'] ?? null,
			'correlation_id'   => $correlation,
			'causation_id'     => $meta['causation_id'] ?? null,
			'idempotency_key'  => (string) ( $meta['idempotency_key'] ?? $event_type . ':' . $object_uid . ':' . $object_version ),
			'privacy_tags'     => array_values( (array) ( $meta['privacy_tags'] ?? array() ) ),
			'payload'          => $payload,
		);
		$db->insert(
			'ccap_outbox_event',
			array(
				'event_uuid'       => $uuid,
				'event_type'       => $event_type,
				'schema_version'   => self::SCHEMA_VERSION,
				'contract_version' => $envelope['contract_version'],
				'object_type'      => $object_type,
				'object_id'        => $object_uid,
				'object_version'   => $object_version,
				'course_uid'       => $envelope['course_uid'],
				'user_ref'         => $user_ref,
				'actor_type'       => $envelope['actor_type'],
				'actor_ref'        => $envelope['actor_ref'],
				'correlation_id'   => $correlation,
				'causation_id'     => $envelope['causation_id'],
				'idempotency_key'  => $envelope['idempotency_key'],
				'privacy_tags'     => $tags,
				'payload'          => (string) wp_json_encode( $envelope ),
			),
			array( 'created_at' )
		);
		foreach ( $subscribers as $subscriber ) {
			$db->insert(
				'ccap_outbox_delivery',
				array(
					'event_uuid'    => $uuid,
					'subscriber_id' => $subscriber,
					'status'        => 'pending',
				),
				array( 'created_at' )
			);
		}
		return $uuid;
	}
}
