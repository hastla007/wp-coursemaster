<?php
/**
 * Semantic field rules beyond the type check (R0-27 §1.3 "Validierung",
 * R0-30 §3.2 F-01…F-13, F-80).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Settings;

use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\Relations;

defined( 'ABSPATH' ) || exit;

/**
 * Rules return machine keys (`field => rule`); the admin layer turns them
 * into field-near messages (`cc.set.<rule>`, R0-30 §3.2). Rules only run
 * for fields that were submitted and changed, so a stored value that later
 * becomes invalid (page moved to trash) never blocks saving other fields.
 */
final class SettingsRules {

	/**
	 * Reserved first path segments (F-02): WordPress rewrite bases and core paths.
	 *
	 * @var list<string>
	 */
	public const RESERVED_SLUGS = array( 'admin', 'login', 'feed', 'rss', 'rss2', 'rdf', 'atom', 'page', 'comments', 'search', 'author', 'category', 'tag', 'type', 'embed', 'trackback', 'attachment', 'sitemap', 'robots', 'favicon', 'xmlrpc', 'register', 'dashboard' );

	/**
	 * Rule key for a value the type validator rejected.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Raw value.
	 * @return string `format`, `range`, `length`, `email`, `ip`, `choice` or `invalid`.
	 */
	public static function invalid_rule( array $field, $value ): string {
		switch ( $field['type'] ) {
			case 'int':
			case 'page_id':
				return is_int( $value ) || ( is_string( $value ) && 1 === preg_match( '/^-?\d+$/', $value ) ) ? 'range' : 'number';
			case 'string':
			case 'text':
				return is_string( $value ) ? 'length' : 'invalid';
			case 'slug':
				return 'format';
			case 'email':
				return 'email';
			case 'ip_list':
				return is_array( $value ) && count( $value ) > (int) ( $field['max'] ?? 50 ) ? 'count' : 'ip';
			case 'enum':
			case 'set':
			case 'bool':
				return 'choice';
		}
		return 'invalid';
	}

	/**
	 * Semantic checks of a tab candidate.
	 *
	 * @param Actor                $actor     Actor.
	 * @param string               $tab       Tab.
	 * @param array<string, mixed> $candidate Candidate values.
	 * @param array<string, mixed> $before    Stored values.
	 * @param array                $touched   Submitted, type-valid fields.
	 * @phpstan-param list<string> $touched
	 * @return array<string, string>
	 */
	public static function check( Actor $actor, string $tab, array $candidate, array $before, array $touched ): array {
		$errors = array();
		$check  = static fn( string $field ): bool => in_array( $field, $touched, true ) && $candidate[ $field ] !== $before[ $field ];
		if ( 'general' === $tab ) {
			foreach ( array( 'learning_area_slug', 'course_slug' ) as $field ) {
				if ( $check( $field ) ) {
					$rule = self::slug_rule( (string) $candidate[ $field ] );
					if ( null !== $rule ) {
						$errors[ $field ] = $rule;
					}
				}
			}
			if ( ! isset( $errors['course_slug'] ) && ( $check( 'course_slug' ) || $check( 'learning_area_slug' ) ) && $candidate['course_slug'] === $candidate['learning_area_slug'] ) {
				$errors['course_slug'] = 'equal';
			}
			foreach ( array( 'catalog_page_id', 'learner_home_page_id' ) as $field ) {
				if ( $check( $field ) && 0 !== $candidate[ $field ] ) {
					$rule = self::page_rule( $actor, (int) $candidate[ $field ] );
					if ( null !== $rule ) {
						$errors[ $field ] = $rule;
					}
				}
			}
		}
		/**
		 * Further semantic rules of a tab (`field => rule`).
		 *
		 * @param array<string, string> $errors    Errors so far.
		 * @param string                $tab       Tab.
		 * @param array<string, mixed>  $candidate Candidate.
		 * @param array<string, mixed>  $before    Stored values.
		 */
		$extra = apply_filters( 'course_captain_settings_rules', array(), $tab, $candidate, $before );
		foreach ( is_array( $extra ) ? $extra : array() as $field => $rule ) {
			if ( is_string( $field ) && is_string( $rule ) && isset( $candidate[ $field ] ) && ! isset( $errors[ $field ] ) ) {
				$errors[ $field ] = sanitize_key( $rule );
			}
		}
		return $errors;
	}

	/**
	 * F-02/F-03/F-05 for a slug (format is checked by the schema).
	 *
	 * @param string $slug Slug.
	 * @return string|null `reserved`, `collision`, `foreign_cpt` or null.
	 */
	public static function slug_rule( string $slug ): ?string {
		if ( in_array( $slug, self::RESERVED_SLUGS, true ) || str_starts_with( $slug, 'wp-' ) ) {
			return 'reserved';
		}
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( in_array( $type->name, array( Relations::COURSE_TYPE, Relations::MODULE_TYPE, Relations::LESSON_TYPE ), true ) ) {
				continue;
			}
			$base = is_array( $type->rewrite ) ? (string) ( $type->rewrite['slug'] ?? '' ) : '';
			if ( $slug === $base || $slug === $type->name || ( true === $type->has_archive ? $slug === $base : $slug === (string) $type->has_archive ) ) {
				return in_array( $type->name, array( 'post', 'page', 'attachment' ), true ) ? 'reserved' : 'foreign_cpt';
			}
		}
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $taxonomy ) {
			$base = is_array( $taxonomy->rewrite ) ? (string) ( $taxonomy->rewrite['slug'] ?? '' ) : '';
			if ( '' !== $base && $slug === $base ) {
				return 'reserved';
			}
		}
		$page = get_page_by_path( $slug, OBJECT, array( 'page', 'post' ) );
		if ( $page instanceof \WP_Post && 'trash' !== $page->post_status ) {
			return 'collision';
		}
		return null;
	}

	/**
	 * F-10…F-12 for a selected page.
	 *
	 * @param Actor $actor Actor.
	 * @param int   $id    Page ID.
	 * @return string|null `page_missing`, `page_unpublished`, `page_forbidden` or null.
	 */
	public static function page_rule( Actor $actor, int $id ): ?string {
		$page = get_post( $id );
		if ( ! $page instanceof \WP_Post || 'page' !== $page->post_type || 'trash' === $page->post_status ) {
			return 'page_missing';
		}
		if ( 'publish' !== $page->post_status ) {
			return 'page_unpublished';
		}
		if ( ! $actor->is_system() && ! user_can( $actor->user_id, 'edit_page', $id ) ) {
			return 'page_forbidden';
		}
		return null;
	}

	/**
	 * Suggestion for an invalid slug (F-01: transliterated, lower case).
	 *
	 * @param string $raw Raw input.
	 * @return string
	 */
	public static function slug_suggestion( string $raw ): string {
		$map  = array(
			'ä' => 'ae',
			'ö' => 'oe',
			'ü' => 'ue',
			'Ä' => 'ae',
			'Ö' => 'oe',
			'Ü' => 'ue',
			'ß' => 'ss',
		);
		$slug = sanitize_title( strtr( $raw, $map ) );
		$slug = trim( (string) preg_replace( '/[^a-z0-9-]+/', '', $slug ), '-' );
		$slug = substr( $slug, 0, 50 );
		if ( strlen( $slug ) < 2 ) {
			$slug = 'lernen';
		}
		$base = $slug;
		$i    = 2;
		while ( $i < 20 && null !== self::slug_rule( $slug ) ) {
			$slug = substr( $base, 0, 46 ) . '-' . $i;
			++$i;
		}
		return $slug;
	}
}
