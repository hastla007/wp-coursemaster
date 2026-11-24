<?php
/**
 * Field/UI contract of the settings tabs and the setup wizard
 * (R0-30 §3.3, T-3 "UX-Vertrag Kap. 43.2", §3.2 Meldungskatalog).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Notification\NotificationPrefs;
use CourseCaptain\Settings\SettingsRules;
use CourseCaptain\Settings\SettingsSchema;

defined( 'ABSPATH' ) || exit;

/**
 * Every rendered field has a label, a visible help text (explanation and
 * effect), a meta line (default, scope, dependency) and field-near error
 * messages. Keys are `<field>` or `<field>.<sub>` for object fields.
 *
 * Elements: `checkbox`, `radio`, `select`, `tristate`, `number`, `url`,
 * `nullable_number`, `text`, `textarea`, `email`, `page`, `set`, `lines`,
 * `percent`, `bools` (object of booleans), `hardening`, `xmlrpc`, `static`.
 */
final class SettingsFields {

	/**
	 * Tab keys in display order (R0-30 §3.1: 11 tabs).
	 *
	 * @var list<string>
	 */
	public const TAB_ORDER = array( 'general', 'display', 'learning', 'exams', 'enrollment', 'roles', 'certificates', 'emails', 'privacy', 'hardening', 'advanced' );

	/**
	 * Tab label.
	 *
	 * @param string $tab Tab.
	 * @return string
	 */
	public static function tab_label( string $tab ): string {
		$labels = array(
			'general'      => __( 'General', 'course-captain' ),
			'display'      => __( 'Course display', 'course-captain' ),
			'learning'     => __( 'Learning', 'course-captain' ),
			'exams'        => __( 'Exams', 'course-captain' ),
			'enrollment'   => __( 'Enrollment', 'course-captain' ),
			'roles'        => __( 'Roles & permissions', 'course-captain' ),
			'certificates' => __( 'Certificates', 'course-captain' ),
			'emails'       => __( 'Emails', 'course-captain' ),
			'privacy'      => __( 'Privacy & security', 'course-captain' ),
			'hardening'    => __( 'Website hardening', 'course-captain' ),
			'advanced'     => __( 'Advanced', 'course-captain' ),
		);
		return $labels[ $tab ] ?? $tab;
	}

	/**
	 * One-sentence tab introduction.
	 *
	 * @param string $tab Tab.
	 * @return string
	 */
	public static function tab_intro( string $tab ): string {
		$intro = array(
			'general'      => __( 'Addresses, pages and basic values for the learning area.', 'course-captain' ),
			'display'      => __( 'How courses appear in the theme. Course Captain uses the colours and fonts of the theme as long as you change nothing.', 'course-captain' ),
			'learning'     => __( 'Defaults for practice mode and course completion. Single courses can deviate.', 'course-captain' ),
			'exams'        => __( 'These values are defaults for exam rules published afterwards. Published rules and running attempts do not change.', 'course-captain' ),
			'enrollment'   => __( 'How learners get into a course. Access always comes from an enrollment; guests see at most the free part of a course.', 'course-captain' ),
			'roles'        => __( 'Which role may do what in Course Captain. Learners need no permissions: course access only comes from enrollments.', 'course-captain' ),
			'certificates' => __( 'Defaults for certificates and the public verification page.', 'course-captain' ),
			'emails'       => __( 'Sender, format and which messages Course Captain sends via WordPress.', 'course-captain' ),
			'privacy'      => __( 'Data requests, retention, external media and network settings.', 'course-captain' ),
			'hardening'    => __( 'Protection of the whole website, not only the learning area. Functions a security plugin already handles pause automatically.', 'course-captain' ),
			'advanced'     => __( 'Technical settings for imports, file delivery and background jobs.', 'course-captain' ),
		);
		return $intro[ $tab ] ?? '';
	}

	/**
	 * Sections of a tab: list of [heading, list of keys].
	 *
	 * @param string $tab Tab.
	 * @return list<array{0: string, 1: list<string>}>
	 */
	public static function layout( string $tab ): array {
		switch ( $tab ) {
			case 'general':
				return array(
					array( __( 'Addresses', 'course-captain' ), array( 'learning_area_slug', 'course_slug' ) ),
					array( __( 'Pages', 'course-captain' ), array( 'catalog_page_id', 'learner_home_page_id' ) ),
					array( __( 'Learning area', 'course-captain' ), array( 'learning_area_title', 'use_profile_locale', 'catalog_indexing' ) ),
					array( __( 'Legal texts', 'course-captain' ), array( 'legal_links.imprint', 'legal_links.terms', 'legal_links.withdrawal', 'legal_links.privacy' ) ),
					array( __( 'Removing the plugin', 'course-captain' ), array( 'delete_data_on_uninstall' ) ),
				);
			case 'display':
				return array(
					array( __( 'Display', 'course-captain' ), array( 'container_width', 'breadcrumbs', 'catalog_sort', 'card_elements' ) ),
					array( __( 'Branding of the learning area', 'course-captain' ), array( 'logo_id', 'accent_color', 'button_labels.enroll', 'button_labels.complete', 'button_labels.start_exam', 'button_labels.submit_exam' ) ),
					array( __( 'Accessibility of the content', 'course-captain' ), array( 'require_captions', 'require_transcripts', 'accessibility_page_id' ) ),
				);
			case 'learning':
				return array(
					array( __( 'Courses', 'course-captain' ), array( 'course_completion_default', 'mastery_algorithm', 'favorites_enabled', 'mistake_training_enabled' ) ),
					array( __( 'Feedback while practising', 'course-captain' ), array( 'feedback.visibility', 'feedback.show_correct_answers' ) ),
					array( __( 'Assignment submissions', 'course-captain' ), array( 'assignment_file_types', 'assignment_max_file_mb', 'assignment_max_files' ) ),
					array( __( 'Review intervals of the smart trainer', 'course-captain' ), array( 'review_intervals.first', 'review_intervals.second', 'review_intervals.third' ) ),
					array( __( 'Modules', 'course-captain' ), array( 'flashcards_enabled', 'assignments_enabled' ) ),
				);
			case 'exams':
				return array(
					array( __( 'Attempts and time', 'course-captain' ), array( 'max_attempts', 'cooldown_seconds', 'count_cancelled', 'time_limit_minutes', 'timer_mode', 'late_tolerance_seconds', 'auto_submit_inactive_days', 'starts_per_hour' ) ),
					array( __( 'Passing and order', 'course-captain' ), array( 'passing_ratio_proposal', 'shuffle_questions', 'shuffle_answers' ) ),
					array( __( 'Result feedback', 'course-captain' ), array( 'feedback.visibility', 'feedback.show_score', 'feedback.show_correct_answers', 'feedback.show_correct_answers_when' ) ),
				);
			case 'enrollment':
				$keys = array( 'self_enrollment', 'default_access_days', 'default_drip_days', 'groups_enabled', 'invite_valid_days', 'claim_policy', 'pending_grant_timeout_hours' );
				if ( (bool) apply_filters( 'course_captain_external_access_sources', false ) ) {
					$keys[] = 'source_disabled_policy';
				}
				return array( array( __( 'Enrollment', 'course-captain' ), $keys ) );
			case 'certificates':
				return array(
					array( __( 'Issuing', 'course-captain' ), array( 'enabled', 'auto_issue', 'holder_name_policy' ) ),
					array( __( 'Defaults for new certificates', 'course-captain' ), array( 'default_validity_days', 'expiry_notice_days', 'paper_format', 'language', 'fixed_locale' ) ),
				);
			case 'emails':
				return array(
					array( __( 'Sender', 'course-captain' ), array( 'from_name', 'from_address', 'footer_text', 'send_format' ) ),
					array( __( 'Messages', 'course-captain' ), array( 'types' ) ),
				);
			case 'privacy':
				return array(
					array( __( 'Data requests', 'course-captain' ), array( 'auto_export_on_confirm', 'auto_erase_on_confirm', 'backup_max_age_days' ) ),
					array( __( 'External media', 'course-captain' ), array( 'external_media_mode', 'external_media_remember', 'consent_category', 'trusted_media_hosts', 'show_avatars' ) ),
					array( __( 'Network and browser protection', 'course-captain' ), array( 'trusted_proxies', 'csp_mode' ) ),
				);
			case 'hardening':
				return array(
					array( __( 'Login', 'course-captain' ), array( 'login_limit', 'known_device', 'known_device_days', 'reset_limit', 'registration_limit' ) ),
					array( __( 'Failed logins per 15 minutes', 'course-captain' ), array( 'login_limits.ip', 'login_limits.account_ip', 'login_limits.account' ) ),
					array( __( 'XML-RPC', 'course-captain' ), array( 'xmlrpc' ) ),
					array( __( 'Security headers', 'course-captain' ), array( 'site_headers' ) ),
				);
			case 'advanced':
				return array(
					array( __( 'Advanced', 'course-captain' ), array( 'cron_stale_warning_minutes' ) ),
					array( __( 'Logging', 'course-captain' ), array( 'log_level', 'error_log_days', 'debug_file_days' ) ),
					array( __( 'Reports', 'course-captain' ), array( 'report_csv_columns', 'report_pseudonymize' ) ),
				);
		}
		return array();
	}

	/**
	 * UI definition of a key.
	 *
	 * @param string $tab Tab.
	 * @param string $key `<field>` or `<field>.<sub>`.
	 * @return array{label: string, help: string, element: string, options: array<string, string>, unit: string, scope: string, depends: string, none: string, warn: string}
	 */
	public static function ui( string $tab, string $key ): array {
		$all  = self::catalog();
		$base = array(
			'label'   => $key,
			'help'    => '',
			'element' => 'text',
			'options' => array(),
			'unit'    => '',
			'scope'   => 'all',
			'depends' => '',
			'none'    => '',
			'warn'    => '',
		);
		return array_merge( $base, $all[ $tab ][ $key ] ?? array() );
	}

	/**
	 * Schema definition of a key (subfield definitions merged with the default).
	 *
	 * @param string $tab Tab.
	 * @param string $key Key.
	 * @return array<string, mixed>|null
	 */
	public static function schema( string $tab, string $key ): ?array {
		$fields          = SettingsSchema::fields()[ $tab ] ?? array();
		list( $f, $sub ) = array_pad( explode( '.', $key, 2 ), 2, '' );
		if ( ! isset( $fields[ $f ] ) ) {
			return null;
		}
		if ( '' === $sub ) {
			return $fields[ $f ];
		}
		$def = $fields[ $f ]['fields'][ $sub ] ?? null;
		// Sub-keys inherit the "Legal review" marking of their object (SET-CC-06).
		return null === $def ? null : $def + array(
			'default'      => $fields[ $f ]['default'][ $sub ] ?? null,
			'legal_review' => ! empty( $fields[ $f ]['legal_review'] ),
		);
	}

	/**
	 * Scope text of the meta line.
	 *
	 * @param string $scope Scope key.
	 * @return string
	 */
	public static function scope_label( string $scope ): string {
		switch ( $scope ) {
			case 'course':
				return __( 'for all, changeable per course', 'course-captain' );
			case 'exam':
				return __( 'default, changeable per exam', 'course-captain' );
			case 'system':
				return __( 'whole website', 'course-captain' );
		}
		return __( 'for all', 'course-captain' );
	}

	/**
	 * Field-near message of a rule (R0-30 §3.2); always starts with "Error:".
	 *
	 * @param string               $label  Field label.
	 * @param string               $rule   Rule key.
	 * @param array<string, mixed> $def    Schema definition.
	 * @param array<string, mixed> $params Extra parameters (`line`, `value`, `suggestion`).
	 * @return string
	 */
	public static function message( string $label, string $rule, array $def, array $params = array() ): string {
		$min = (string) ( $def['min'] ?? 0 );
		$max = (string) ( $def['max'] ?? '' );
		switch ( $rule ) {
			case 'format':
				/* translators: 1: field label, 2: suggested address */
				return sprintf( __( 'Error: %1$s: allowed are lower-case letters a–z, digits and hyphens, 2 to 50 characters, no hyphen at the start or end. Suggestion: “%2$s”.', 'course-captain' ), $label, (string) ( $params['suggestion'] ?? '' ) );
			case 'reserved':
				/* translators: 1: address, 2: suggested address */
				return sprintf( __( 'Error: “%1$s” is reserved in WordPress. Choose another address, for example “%2$s”.', 'course-captain' ), (string) ( $params['value'] ?? '' ), (string) ( $params['suggestion'] ?? '' ) );
			case 'collision':
				/* translators: 1: address, 2: suggested address */
				return sprintf( __( 'Error: “%1$s” is already used by a page or post. Choose another address, for example “%2$s”, or rename the page.', 'course-captain' ), (string) ( $params['value'] ?? '' ), (string) ( $params['suggestion'] ?? '' ) );
			case 'foreign_cpt':
				/* translators: %s: address */
				return sprintf( __( 'Error: “%s” is already used by another plugin for its own content. Choose another address.', 'course-captain' ), (string) ( $params['value'] ?? '' ) );
			case 'slug_conflict':
				/* translators: %s: suggested address */
				return sprintf( __( 'Error: this address is already used by another post type, taxonomy, base or rewrite rule. Choose another address, for example “%s”, or confirm the conflict.', 'course-captain' ), (string) ( $params['suggestion'] ?? '' ) );
			case 'equal':
				return __( 'Error: the learning area and single courses need different addresses.', 'course-captain' );
			case 'page_unpublished':
				return __( 'Error: the selected page is not published. Publish it or choose another page.', 'course-captain' );
			case 'page_missing':
				return __( 'Error: the selected page no longer exists. Choose another page or create a new one.', 'course-captain' );
			case 'page_forbidden':
				return __( 'Error: you may not edit the selected page. Choose another page.', 'course-captain' );
			case 'length':
				/* translators: 1: field label, 2: maximum number of characters */
				return sprintf( __( 'Error: %1$s: at most %2$s characters, no line breaks.', 'course-captain' ), $label, '' === $max ? '255' : $max );
			case 'range':
				if ( '' === $max ) {
					/* translators: 1: field label, 2: minimum */
					return sprintf( __( 'Error: %1$s: enter a whole number of at least %2$s.', 'course-captain' ), $label, $min );
				}
				/* translators: 1: field label, 2: minimum, 3: maximum */
				return sprintf( __( 'Error: %1$s: enter a whole number from %2$s to %3$s.', 'course-captain' ), $label, $min, $max );
			case 'number':
				/* translators: %s: field label */
				return sprintf( __( 'Error: %s: enter a whole number, for example 30.', 'course-captain' ), $label );
			case 'percent_range':
				return __( 'Error: passing threshold: enter a value above 0 and at most 100 %.', 'course-captain' );
			case 'percent_precision':
				return __( 'Error: passing threshold: at most one decimal place, for example 66.7 %.', 'course-captain' );
			case 'email':
				/* translators: %s: entered value */
				return sprintf( __( 'Error: sender address: “%s” is not a valid email address.', 'course-captain' ), (string) ( $params['value'] ?? '' ) );
			case 'ip':
				/* translators: 1: line number, 2: entered value */
				return sprintf( __( 'Error: line %1$d: “%2$s” is not a valid IP address and not a valid range (for example 10.0.0.0/8).', 'course-captain' ), (int) ( $params['line'] ?? 0 ), (string) ( $params['value'] ?? '' ) );
			case 'ip_all':
				/* translators: 1: line number, 2: entered value */
				return sprintf( __( 'Error: line %1$d: “%2$s” would trust every address and is not allowed.', 'course-captain' ), (int) ( $params['line'] ?? 0 ), (string) ( $params['value'] ?? '' ) );
			case 'count':
				/* translators: %d: number of entries */
				return sprintf( __( 'Error: at most 50 entries (now %d).', 'course-captain' ), (int) ( $params['count'] ?? 0 ) );
			case 'host':
				/* translators: 1: line number, 2: entered value */
				return sprintf( __( 'Error: line %1$d: “%2$s” is not a valid host name (for example media.example.org, without https:// and without a path).', 'course-captain' ), (int) ( $params['line'] ?? 0 ), (string) ( $params['value'] ?? '' ) );
			case 'interval_order':
				return __( 'Error: review intervals: each stage must be at least as long as the one before.', 'course-captain' );
			case 'confirm_word':
				/* translators: 1: German confirmation word, 2: English confirmation word (both typed literally) */
				return sprintf( __( 'Error: to switch on the data deletion, type %1$s or %2$s into the confirmation field.', 'course-captain' ), 'LÖSCHEN', 'DELETE' );
			case 'reason_required':
				return __( 'Error: reason: required when evidence is kept despite an erasure request (at most 500 characters).', 'course-captain' );
			case 'required_mail':
				return __( 'Error: this is a required message and cannot be switched off.', 'course-captain' );
			case 'cap_not_own':
				return __( 'Error: you cannot grant a permission you do not have yourself.', 'course-captain' );
			case 'cap_admin_only':
				return __( 'Error: only administrators may grant this permission.', 'course-captain' );
			case 'role_admin':
				return __( 'Error: the administrator role always has all permissions and cannot be changed here.', 'course-captain' );
			case 'title_required':
				return __( 'Error: course title: required field.', 'course-captain' );
			case 'title_length':
				return __( 'Error: course title: at most 200 characters.', 'course-captain' );
			case 'color':
				/* translators: %s: field label */
				return sprintf( __( 'Error: %s: enter a colour as #rrggbb, for example #1d4ed8, or leave the field empty.', 'course-captain' ), $label );
			case 'contrast':
				/* translators: 1: entered colour, 2: contrast ratio, 3: required ratio */
				return sprintf( __( 'Error: the colour %1$s is too light: white text on it has a contrast of %2$s:1, at least %3$s:1 is needed. Choose a darker colour, for example #1d4ed8.', 'course-captain' ), (string) ( $params['value'] ?? '' ), number_format_i18n( SettingsRules::contrast( (string) ( $params['value'] ?? '#ffffff' ), '#ffffff' ), 2 ), number_format_i18n( SettingsRules::MIN_CONTRAST, 1 ) );
			case 'choice':
				/* translators: %s: field label */
				return sprintf( __( 'Error: %s: choose one of the offered options.', 'course-captain' ), $label );
			case 'logo_missing':
				return __( 'Error: logo: no image with this media ID exists. Enter the ID of an image from the media library or 0.', 'course-captain' );
			case 'url':
				/* translators: %s: field label */
				return sprintf( __( 'Error: %s: enter a complete address starting with https:// or http://, for example https://example.org/imprint, or leave the field empty.', 'course-captain' ), $label );
			case 'locale':
				return __( 'Error: certificate language: choose one of the languages installed on this website.', 'course-captain' );
		}
		/* translators: %s: field label */
		return sprintf( __( 'Error: %s: check the value.', 'course-captain' ), $label );
	}

	/**
	 * Installed languages for the fixed certificate language (SET-CC-08).
	 *
	 * @return array<string, string> Locale => name ('' = none chosen).
	 */
	private static function locale_options(): array {
		$out = array( '' => __( '– choose –', 'course-captain' ) );
		if ( ! function_exists( 'get_available_languages' ) ) {
			return $out + array( 'en_US' => 'English (United States)' );
		}
		foreach ( \CourseCaptain\Kernel\Locale::installed() as $locale ) {
			// Native name without a network lookup; the locale code keeps variants (`de_DE_formal`) apart.
			$name           = function_exists( 'locale_get_display_name' ) ? (string) locale_get_display_name( $locale, $locale ) : '';
			$out[ $locale ] = '' === $name ? $locale : $name . ' (' . $locale . ')';
		}
		return $out;
	}

	/**
	 * Labels of all fields.
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	private static function catalog(): array {
		$hardening_toggle = static fn( string $label, string $help ): array => array(
			'label'   => $label,
			'help'    => $help,
			'element' => 'hardening',
			'scope'   => 'system',
		);
		$limit_help       = static function ( string $key ): string {
			$minutes = intdiv( \CourseCaptain\Security\Hardening::LIMITS[ $key ][1], 60 );
			/* translators: %d: minutes */
			return sprintf( _n( 'Attempts within %d minute before the lock applies. The time window is fixed.', 'Attempts within %d minutes before the lock applies. The time window is fixed.', $minutes, 'course-captain' ), $minutes );
		};
		$feedback_when    = array(
			'no_attempts_left_or_passed' => __( 'Only when no attempts are left or the exam is passed', 'course-captain' ),
			'after_submit'               => __( 'Directly after every submission', 'course-captain' ),
		);
		$types            = array();
		foreach ( array_keys( (array) ( SettingsSchema::fields()['emails']['types']['fields'] ?? array() ) ) as $type ) {
			$types[ (string) $type ] = NotificationPrefs::label( (string) $type );
		}
		return array(
			'general'      => array(
				'learning_area_slug'       => array(
					'label' => __( 'Address of the learning area', 'course-captain' ),
					'help'  => __( 'Learners find their courses, exams and certificates here. Old addresses keep redirecting permanently. Allowed: lower-case letters, digits, hyphens.', 'course-captain' ),
				),
				'course_slug'              => array(
					'label' => __( 'Address of single courses', 'course-captain' ),
					'help'  => __( 'Stands in front of the name of every course.', 'course-captain' ),
				),
				'catalog_page_id'          => array(
					'label'   => __( 'Course catalogue page', 'course-captain' ),
					'help'    => __( 'Page with the “Course catalogue” block. Course Captain never overwrites its content.', 'course-captain' ),
					'element' => 'page',
					'none'    => __( 'No own page – show the catalogue in the learning area', 'course-captain' ),
				),
				'learner_home_page_id'     => array(
					'label'   => __( 'Start page for learners', 'course-captain' ),
					'help'    => __( 'Learners land here via “My learning”. If the page disappears later, Course Captain uses the learning area and reports it in the system status.', 'course-captain' ),
					'element' => 'page',
					'none'    => __( 'My learning in the learning area', 'course-captain' ),
				),
				'learning_area_title'      => array(
					'label' => __( 'Title of the learning area', 'course-captain' ),
					'help'  => __( 'Leave empty to use the website title.', 'course-captain' ),
				),
				'use_profile_locale'       => array(
					'label'   => __( 'Language for learners', 'course-captain' ),
					'help'    => __( 'Automatic: learners see the learning area in their profile language as soon as more than one language is installed.', 'course-captain' ),
					'element' => 'tristate',
					'options' => array(
						''  => __( 'Automatic', 'course-captain' ),
						'1' => __( 'Always profile language', 'course-captain' ),
						'0' => __( 'Never, always website language', 'course-captain' ),
					),
				),
				'catalog_indexing'         => array(
					'label'   => __( 'Search engines', 'course-captain' ),
					'help'    => __( 'Public catalogue and course pages may appear in search engines. Learning area, lessons, certificates and invitations never appear.', 'course-captain' ),
					'element' => 'radio',
					'scope'   => 'course',
					'options' => array(
						'public_catalog' => __( 'Catalogue can be indexed', 'course-captain' ),
						'noindex'        => __( 'Index nothing', 'course-captain' ),
					),
				),
				'legal_links'              => array(
					'label' => __( 'Legal texts', 'course-captain' ),
					'help'  => __( 'Links to your imprint, terms and conditions, right of withdrawal and privacy policy in the footer of the learning area and of all mails.', 'course-captain' ),
				),
				'legal_links.imprint'      => array(
					'label'   => __( 'Imprint (address)', 'course-captain' ),
					'help'    => __( 'Address of your imprint page, for example https://example.org/imprint. Empty = no link.', 'course-captain' ),
					'element' => 'url',
					'scope'   => 'system',
					'none'    => __( 'no link', 'course-captain' ),
				),
				'legal_links.terms'        => array(
					'label'   => __( 'Terms and conditions (address)', 'course-captain' ),
					'help'    => __( 'Address of your terms and conditions. Empty = no link.', 'course-captain' ),
					'element' => 'url',
					'scope'   => 'system',
					'none'    => __( 'no link', 'course-captain' ),
				),
				'legal_links.withdrawal'   => array(
					'label'   => __( 'Right of withdrawal (address)', 'course-captain' ),
					'help'    => __( 'Address of your information on the right of withdrawal. Empty = no link.', 'course-captain' ),
					'element' => 'url',
					'scope'   => 'system',
					'none'    => __( 'no link', 'course-captain' ),
				),
				'legal_links.privacy'      => array(
					'label'   => __( 'Privacy policy (address)', 'course-captain' ),
					'help'    => __( 'Empty = the privacy policy page of WordPress (Settings › Privacy), if one is published. Enter an address only if your privacy policy is somewhere else.', 'course-captain' ),
					'element' => 'url',
					'scope'   => 'system',
					'none'    => __( 'privacy page of WordPress', 'course-captain' ),
				),
				'delete_data_on_uninstall' => array(
					'label'   => __( 'Remove all data when the plugin is deleted', 'course-captain' ),
					/* translators: 1: German confirmation word, 2: English confirmation word (both typed literally) */
					'help'    => sprintf( __( 'When you delete the plugin under Plugins, all Course Captain data of this website is removed: courses, modules, lessons, questions and exams, enrollments and groups, learning progress, exam results, certificates, submissions and uploaded files, logs and settings. Deactivating never deletes anything; background jobs are always removed. On a network each website decides for itself. Importing settings never takes over this setting. To switch it on, type %1$s or %2$s below; switching it off needs no confirmation.', 'course-captain' ), 'LÖSCHEN', 'DELETE' ),
					'element' => 'checkbox',
					'scope'   => 'system',
				),
			),
			'display'      => array(
				'container_width'           => array(
					'label'   => __( 'Width of the learning area', 'course-captain' ),
					'help'    => __( 'Theme uses the content width of your theme; the other options widen only the learning area.', 'course-captain' ),
					'element' => 'radio',
					'scope'   => 'course',
					'options' => array(
						'theme' => __( 'Theme', 'course-captain' ),
						'wide'  => __( 'Wide', 'course-captain' ),
						'full'  => __( 'Full width', 'course-captain' ),
					),
				),
				'breadcrumbs'               => array(
					'label'   => __( 'Show breadcrumb navigation', 'course-captain' ),
					'help'    => __( 'Shows the path course › module › lesson above the content.', 'course-captain' ),
					'element' => 'checkbox',
					'scope'   => 'course',
				),
				'catalog_sort'              => array(
					'label'   => __( 'Order in the catalogue', 'course-captain' ),
					'help'    => __( 'With equal position, courses are sorted by title.', 'course-captain' ),
					'element' => 'select',
					'options' => array(
						'manual' => __( 'Manual', 'course-captain' ),
						'title'  => __( 'Title', 'course-captain' ),
						'newest' => __( 'Newest', 'course-captain' ),
					),
				),
				'card_elements'             => array(
					'label'   => __( 'Content of course cards', 'course-captain' ),
					'help'    => __( 'Without a selection, the card shows only the title.', 'course-captain' ),
					'element' => 'set',
					'options' => array(
						'cover'        => __( 'Cover image', 'course-captain' ),
						'summary'      => __( 'Summary', 'course-captain' ),
						'lesson_count' => __( 'Number of lessons', 'course-captain' ),
						'progress'     => __( 'Progress', 'course-captain' ),
					),
				),
				'require_captions'          => array(
					'label'   => __( 'Require captions for videos', 'course-captain' ),
					'help'    => __( 'Courses with videos without captions can then not be published. Missing alternative texts always block.', 'course-captain' ),
					'element' => 'checkbox',
				),
				'require_transcripts'       => array(
					'label'   => __( 'Require transcripts for videos', 'course-captain' ),
					'help'    => __( 'The course check always reports videos without a transcript. With this box ticked, such courses can no longer be published. Enter the transcript as text or link in the video lesson.', 'course-captain' ),
					'element' => 'checkbox',
				),
				'accessibility_page_id'     => array(
					'label'   => __( 'Accessibility statement', 'course-captain' ),
					'help'    => __( 'Page with your accessibility statement. The learning area links to it at the bottom of every page.', 'course-captain' ),
					'element' => 'page',
					'none'    => __( 'No link', 'course-captain' ),
				),
				'logo_id'                   => array(
					'label'   => __( 'Logo (media ID)', 'course-captain' ),
					'help'    => __( 'ID of an image in the media library, shown at the top of the learning area with the website name as alternative text; 0 = no logo. Not taken over by a settings import.', 'course-captain' ),
					'element' => 'number',
				),
				'accent_color'              => array(
					'label' => __( 'Accent colour', 'course-captain' ),
					'help'  => __( 'For buttons in the learning area, written as #rrggbb. Buttons get white text, so the colour needs a contrast of at least 4.5:1 to white; lighter colours are rejected. Leave empty for the neutral default.', 'course-captain' ),
				),
				'button_labels.enroll'      => array(
					'label' => __( 'Button “Enroll for free”', 'course-captain' ),
					'help'  => __( 'Own label in all languages; leave empty for the translated default text.', 'course-captain' ),
				),
				'button_labels.complete'    => array(
					'label' => __( 'Button “Mark as completed”', 'course-captain' ),
					'help'  => __( 'Own label in all languages; leave empty for the translated default text.', 'course-captain' ),
				),
				'button_labels.start_exam'  => array(
					'label' => __( 'Button “Start exam”', 'course-captain' ),
					'help'  => __( 'Own label in all languages; leave empty for the translated default text.', 'course-captain' ),
				),
				'button_labels.submit_exam' => array(
					'label' => __( 'Button “Submit exam”', 'course-captain' ),
					'help'  => __( 'Own label in all languages; leave empty for the translated default text.', 'course-captain' ),
				),
			),
			'learning'     => array(
				'course_completion_default'     => array(
					'label'   => __( 'A course counts as completed when …', 'course-captain' ),
					'help'    => __( 'Takes effect with the next publication of a course.', 'course-captain' ),
					'element' => 'radio',
					'scope'   => 'course',
					'options' => array(
						'required_modules'          => __( '… all required modules are done', 'course-captain' ),
						'required_modules_and_exam' => __( '… additionally an exam is passed', 'course-captain' ),
					),
				),
				'mastery_algorithm'             => array(
					'label'   => __( 'Repetition strategy', 'course-captain' ),
					'help'    => __( 'Decides which questions come back while practising. A change only affects new answers.', 'course-captain' ),
					'element' => 'select',
					'scope'   => 'course',
					'options' => array( 'smarttrainer.v1' => __( 'Smarttrainer v1 (only available strategy)', 'course-captain' ) ),
				),
				'favorites_enabled'             => array(
					'label'   => __( 'Allow favourites', 'course-captain' ),
					'help'    => __( 'Favourites are private; trainers and reports never see them.', 'course-captain' ),
					'element' => 'checkbox',
					'scope'   => 'course',
				),
				'mistake_training_enabled'      => array(
					'label'   => __( 'Offer mistake training', 'course-captain' ),
					'help'    => __( 'Learners can practise the questions they answered wrongly.', 'course-captain' ),
					'element' => 'checkbox',
					'scope'   => 'course',
				),
				'feedback.visibility'           => array(
					'label'   => __( 'Feedback while practising', 'course-captain' ),
					'help'    => __( 'Shows right or wrong and the correct answer.', 'course-captain' ),
					'element' => 'radio',
					'scope'   => 'course',
					'options' => array(
						'always'       => __( 'Immediately after every answer', 'course-captain' ),
						'after_submit' => __( 'After submitting the exercise', 'course-captain' ),
						'never'        => __( 'Never', 'course-captain' ),
					),
				),
				'feedback.show_correct_answers' => array(
					'label'   => __( 'Show the correct answer', 'course-captain' ),
					'help'    => __( 'Only effective when feedback is shown.', 'course-captain' ),
					'element' => 'checkbox',
					'scope'   => 'course',
					'depends' => 'feedback.visibility',
				),
				'assignment_file_types'         => array(
					'label'   => __( 'Allowed file types for submissions', 'course-captain' ),
					'help'    => __( 'Single assignments may only allow fewer types. Without a selection, learners cannot upload files, only text.', 'course-captain' ),
					'element' => 'set',
					'options' => array(
						'pdf'  => __( 'PDF', 'course-captain' ),
						'docx' => __( 'Word (DOCX)', 'course-captain' ),
						'odt'  => __( 'OpenDocument text (ODT)', 'course-captain' ),
						'txt'  => __( 'Plain text (TXT)', 'course-captain' ),
						'jpg'  => __( 'JPEG image', 'course-captain' ),
						'png'  => __( 'PNG image', 'course-captain' ),
					),
				),
				'assignment_max_file_mb'        => array(
					'label'   => __( 'Maximum size per file', 'course-captain' ),
					'help'    => __( 'Single assignments may only set a smaller limit. The upload limit of the server can be lower.', 'course-captain' ),
					'element' => 'number',
					'unit'    => __( 'MB', 'course-captain' ),
				),
				'assignment_max_files'          => array(
					'label'   => __( 'Maximum number of files per submission', 'course-captain' ),
					'help'    => __( 'Single assignments may only set a smaller number.', 'course-captain' ),
					'element' => 'number',
				),
				'review_intervals.first'        => array(
					'label'   => __( 'Review after the first secure stage', 'course-captain' ),
					'help'    => __( 'A securely known question comes back for review after this many days. Applies to new answers.', 'course-captain' ),
					'element' => 'number',
					'unit'    => __( 'days', 'course-captain' ),
				),
				'review_intervals.second'       => array(
					'label'   => __( 'Review after the second secure stage', 'course-captain' ),
					'help'    => __( 'Must be at least as long as the first interval.', 'course-captain' ),
					'element' => 'number',
					'unit'    => __( 'days', 'course-captain' ),
				),
				'review_intervals.third'        => array(
					'label'   => __( 'Review after further secure answers', 'course-captain' ),
					'help'    => __( 'Must be at least as long as the second interval.', 'course-captain' ),
					'element' => 'number',
					'unit'    => __( 'days', 'course-captain' ),
				),
				'flashcards_enabled'            => array(
					'label'   => __( 'Use flashcards', 'course-captain' ),
					'help'    => __( 'Switching off hides it for learners and in the administration, stops its interfaces and background jobs and leaves it out of the course check. Nothing is deleted; switching on again restores everything.', 'course-captain' ),
					'element' => 'checkbox',
					'scope'   => 'system',
				),
				'assignments_enabled'           => array(
					'label'   => __( 'Use assignments', 'course-captain' ),
					'help'    => __( 'Switching off hides it for learners and in the administration, stops its interfaces and background jobs and leaves it out of the course check. Nothing is deleted; switching on again restores everything.', 'course-captain' ),
					'element' => 'checkbox',
					'scope'   => 'system',
				),
			),
			'exams'        => array(
				'starts_per_hour'                    => array(
					'label'   => __( 'Exam starts per learner and hour', 'course-captain' ),
					'help'    => __( 'Protects against automated starts. Further starts within the hour are refused with a waiting time.', 'course-captain' ),
					'element' => 'number',
					'scope'   => 'system',
				),
				'max_attempts'                       => array(
					'label'   => __( 'Attempts per exam', 'course-captain' ),
					'help'    => __( 'Counted per learner and exam; frozen when an attempt starts.', 'course-captain' ),
					'element' => 'nullable_number',
					'scope'   => 'exam',
					'none'    => __( 'Unlimited', 'course-captain' ),
				),
				'cooldown_seconds'                   => array(
					'label'   => __( 'Waiting time between attempts', 'course-captain' ),
					'help'    => __( 'Interrupted attempts can be continued at any time.', 'course-captain' ),
					'element' => 'number',
					'unit'    => __( 'seconds', 'course-captain' ),
					'scope'   => 'exam',
				),
				'count_cancelled'                    => array(
					'label'   => __( 'Cancelled attempts count', 'course-captain' ),
					'help'    => __( 'Only effective with an attempt limit. Attempts voided by support or ended by a course withdrawal never count.', 'course-captain' ),
					'element' => 'checkbox',
					'scope'   => 'exam',
					'depends' => 'max_attempts',
				),
				'time_limit_minutes'                 => array(
					'label'   => __( 'Time limit (suggestion)', 'course-captain' ),
					'help'    => __( 'The server time is decisive.', 'course-captain' ),
					'element' => 'nullable_number',
					'unit'    => __( 'minutes', 'course-captain' ),
					'scope'   => 'exam',
					'none'    => __( 'No time limit', 'course-captain' ),
				),
				'timer_mode'                         => array(
					'label'   => __( 'Timer mode', 'course-captain' ),
					'help'    => __( 'Practice: learners may hide and extend the timer; the result does not count as evidence.', 'course-captain' ),
					'element' => 'radio',
					'scope'   => 'exam',
					'options' => array(
						'strict'   => __( 'Strict', 'course-captain' ),
						'practice' => __( 'Practice', 'course-captain' ),
					),
				),
				'late_tolerance_seconds'             => array(
					'label'   => __( 'Tolerance for connection loss', 'course-captain' ),
					'help'    => __( 'Answers arriving up to this time after the end are still counted.', 'course-captain' ),
					'element' => 'number',
					'unit'    => __( 'seconds', 'course-captain' ),
					'scope'   => 'exam',
				),
				'auto_submit_inactive_days'          => array(
					'label'   => __( 'Submit automatically when inactive', 'course-captain' ),
					'help'    => __( 'Only for attempts without time limit. Learners are warned beforehand.', 'course-captain' ),
					'element' => 'nullable_number',
					'unit'    => __( 'days', 'course-captain' ),
					'scope'   => 'exam',
					'none'    => __( 'Off', 'course-captain' ),
				),
				'passing_ratio_proposal'             => array(
					'label'   => __( 'Passing threshold (suggestion)', 'course-captain' ),
					'help'    => __( 'Suggestion for new exams. There is no official default; every exam stores its own threshold.', 'course-captain' ),
					'element' => 'percent',
					'unit'    => '%',
				),
				'shuffle_questions'                  => array(
					'label'   => __( 'Shuffle questions (pool exams)', 'course-captain' ),
					'help'    => __( 'Fixed question sheets always keep their order.', 'course-captain' ),
					'element' => 'select',
					'scope'   => 'exam',
					'options' => array(
						'none'           => __( 'No', 'course-captain' ),
						'within_section' => __( 'Within sections', 'course-captain' ),
						'all'            => __( 'All', 'course-captain' ),
					),
				),
				'shuffle_answers'                    => array(
					'label'   => __( 'Shuffle answers', 'course-captain' ),
					'help'    => __( 'Answers marked as fixed (for example “All of the above”) keep their place.', 'course-captain' ),
					'element' => 'checkbox',
					'scope'   => 'exam',
				),
				'feedback.visibility'                => array(
					'label'   => __( 'Result feedback', 'course-captain' ),
					'help'    => __( 'Whether learners see a result after submitting.', 'course-captain' ),
					'element' => 'radio',
					'scope'   => 'exam',
					'options' => array(
						'after_submit' => __( 'After submission', 'course-captain' ),
						'never'        => __( 'Never', 'course-captain' ),
					),
				),
				'feedback.show_score'                => array(
					'label'   => __( 'Show points', 'course-captain' ),
					'help'    => __( 'Only effective when result feedback is shown.', 'course-captain' ),
					'element' => 'checkbox',
					'scope'   => 'exam',
					'depends' => 'feedback.visibility',
				),
				'feedback.show_correct_answers'      => array(
					'label'   => __( 'Show correct answers', 'course-captain' ),
					'help'    => __( 'Only effective when result feedback is shown.', 'course-captain' ),
					'element' => 'checkbox',
					'scope'   => 'exam',
					'depends' => 'feedback.visibility',
				),
				'feedback.show_correct_answers_when' => array(
					'label'   => __( 'When correct answers appear', 'course-captain' ),
					'help'    => __( 'After every submission learners always see which questions were right or wrong. Showing solutions directly makes question pools exploitable across repeated attempts.', 'course-captain' ),
					'element' => 'radio',
					'scope'   => 'exam',
					'options' => $feedback_when,
				),
			),
			'enrollment'   => array(
				'default_drip_days'           => array(
					'label'   => __( 'Drip plan: release modules one after another', 'course-captain' ),
					'help'    => __( 'Each further module opens this many days after the previous one, counted from the start of the access. A drip rule of a single module wins. Existing progress stays open.', 'course-captain' ),
					'element' => 'nullable_number',
					'unit'    => __( 'days', 'course-captain' ),
					'scope'   => 'all',
					'none'    => __( 'All modules at once', 'course-captain' ),
				),
				'self_enrollment'             => array(
					'label'   => __( 'Enrollment', 'course-captain' ),
					'help'    => __( 'Applies to all courses without an own setting. Selling via a shop is set up later under Integrations.', 'course-captain' ),
					'element' => 'radio',
					'scope'   => 'course',
					'options' => array(
						'0' => __( 'I enroll learners', 'course-captain' ),
						'1' => __( 'Logged-in people enroll themselves (free)', 'course-captain' ),
					),
				),
				'default_access_days'         => array(
					'label'   => __( 'Duration of a new access', 'course-captain' ),
					'help'    => __( 'Applies to new enrollments. Existing access stays unchanged. Duration of the access, not of the data.', 'course-captain' ),
					'element' => 'nullable_number',
					'unit'    => __( 'days', 'course-captain' ),
					'scope'   => 'course',
					'none'    => __( 'Unlimited', 'course-captain' ),
				),
				'groups_enabled'              => array(
					'label'   => __( 'Use groups with seats', 'course-captain' ),
					'help'    => __( 'For companies, clubs or classes. Switching off only prevents new groups; existing groups, seats and access stay.', 'course-captain' ),
					'element' => 'checkbox',
					'scope'   => 'course',
				),
				'invite_valid_days'           => array(
					'label'   => __( 'Validity of invitations', 'course-captain' ),
					'help'    => __( 'Applies to new invitations; single groups can be shorter.', 'course-captain' ),
					'element' => 'number',
					'unit'    => __( 'days', 'course-captain' ),
				),
				'claim_policy'                => array(
					'label'   => __( 'Who may accept an invitation?', 'course-captain' ),
					'help'    => __( 'An email address does not prove who someone is.', 'course-captain' ),
					'element' => 'radio',
					'options' => array(
						'transferable'         => __( 'Everyone with the link', 'course-captain' ),
						'invited_address_only' => __( 'Only the invited address', 'course-captain' ),
					),
				),
				'pending_grant_timeout_hours' => array(
					'label'   => __( 'Waiting enrollments count as failed after', 'course-captain' ),
					'help'    => __( 'Applies to enrollments from external sources that were not confirmed in time.', 'course-captain' ),
					'element' => 'number',
					'unit'    => __( 'hours', 'course-captain' ),
				),
				'source_disabled_policy'      => array(
					'label'   => __( 'When an access source disappears', 'course-captain' ),
					'help'    => __( 'Access is never withdrawn automatically.', 'course-captain' ),
					'element' => 'radio',
					'options' => array(
						'keep'          => __( 'Keep access', 'course-captain' ),
						'keep_and_flag' => __( 'Keep and mark for review', 'course-captain' ),
					),
				),
			),
			'certificates' => array(
				'enabled'                       => array(
					'label'   => __( 'Use certificates', 'course-captain' ),
					'help'    => __( 'Switching off hides it for learners and in the administration, stops its interfaces and background jobs and leaves it out of the course check. Nothing is deleted; switching on again restores everything. Issued certificates stay verifiable on the public verification page.', 'course-captain' ),
					'element' => 'checkbox',
					'scope'   => 'system',
				),
				'auto_issue'                    => array(
					'label'   => __( 'Issue certificates automatically', 'course-captain' ),
					'help'    => __( 'Applies to courses without an own setting that have a completion rule. Issued certificates do not change.', 'course-captain' ),
					'element' => 'checkbox',
					'scope'   => 'course',
				),
				'holder_name_policy'            => array(
					'label'   => __( 'Name on the public verification page', 'course-captain' ),
					'help'    => __( 'Email addresses never appear there.', 'course-captain' ),
					'element' => 'radio',
					'options' => array(
						'never'          => __( 'Never', 'course-captain' ),
						'learner_opt_in' => __( 'When the person allows it for their certificate', 'course-captain' ),
					),
				),
				'default_validity_days'         => array(
					'label'   => __( 'Validity of new certificates', 'course-captain' ),
					'help'    => __( 'Applies to certificates of course completions and of exams without their own validity. An exam rule with its own validity wins. Issued certificates keep their date.', 'course-captain' ),
					'element' => 'nullable_number',
					'unit'    => __( 'days', 'course-captain' ),
					'none'    => __( 'Unlimited', 'course-captain' ),
				),
				'expiry_notice_days'            => array(
					'label'   => __( 'Renewal notice before the end of validity', 'course-captain' ),
					'help'    => __( 'From this many days before the end of validity, learners see a notice at the certificate in their list.', 'course-captain' ),
					'element' => 'number',
					'unit'    => __( 'days', 'course-captain' ),
				),
				'paper_format'                  => array(
					'label'   => __( 'Paper format', 'course-captain' ),
					'help'    => __( 'Page size when printing or saving as PDF. Issued certificates keep their format.', 'course-captain' ),
					'element' => 'radio',
					'options' => array(
						'a4'     => __( 'A4', 'course-captain' ),
						'letter' => __( 'US Letter', 'course-captain' ),
					),
				),
				'language'                      => array(
					'label'   => __( 'Language of new certificates', 'course-captain' ),
					'help'    => __( 'Language of the fixed texts on the certificate, such as the heading and “Valid until”. Course title and names stay as they are. Issued certificates keep their language.', 'course-captain' ),
					'element' => 'radio',
					'options' => array(
						'site'    => __( 'Website language', 'course-captain' ),
						'learner' => __( 'Profile language of the learner', 'course-captain' ),
						'fixed'   => __( 'Always this language:', 'course-captain' ),
					),
				),
				'fixed_locale'                  => array(
					'label'   => __( 'Fixed certificate language', 'course-captain' ),
					'help'    => __( 'Only used with “Always this language”. Offered are the languages installed on this website.', 'course-captain' ),
					'element' => 'select',
					'options' => self::locale_options(),
					'depends' => 'language',
				),
				'base_template.issuer_name'     => array(
					'label' => __( 'Issuer', 'course-captain' ),
					'help'  => __( 'Leave empty to use the website title. Changes create a new template version; issued certificates keep theirs.', 'course-captain' ),
				),
				'base_template.footer_text'     => array(
					'label'   => __( 'Footer', 'course-captain' ),
					'help'    => __( 'Text at the bottom of every certificate.', 'course-captain' ),
					'element' => 'textarea',
				),
				'base_template.logo_id'         => array(
					'label'   => __( 'Logo (media ID)', 'course-captain' ),
					'help'    => __( 'ID of an image in the media library; 0 = no logo.', 'course-captain' ),
					'element' => 'number',
				),
				'base_template.signature_label' => array(
					'label' => __( 'Signature line', 'course-captain' ),
					'help'  => __( 'Text below the signature area, for example a role.', 'course-captain' ),
				),
			),
			'emails'       => array(
				'from_name'    => array(
					'label' => __( 'Sender name (optional)', 'course-captain' ),
					'help'  => __( 'Leave empty to use the WordPress default.', 'course-captain' ),
				),
				'from_address' => array(
					'label'   => __( 'Sender address (optional)', 'course-captain' ),
					'help'    => __( 'Leave empty to use the WordPress default. An address of your own domain lands in spam less often.', 'course-captain' ),
					'element' => 'email',
				),
				'footer_text'  => array(
					'label'   => __( 'Footer of all Course Captain mails', 'course-captain' ),
					'help'    => __( 'Allowed: simple formatting and links.', 'course-captain' ),
					'element' => 'textarea',
				),
				'send_format'  => array(
					'label'   => __( 'Format', 'course-captain' ),
					'help'    => __( 'Text only is read best by some mail programs and screen readers.', 'course-captain' ),
					'element' => 'radio',
					'options' => array(
						'multipart' => __( 'HTML with text version', 'course-captain' ),
						'text_only' => __( 'Text only', 'course-captain' ),
					),
				),
				'types'        => array(
					'label'   => __( 'Which messages are sent', 'course-captain' ),
					'help'    => __( 'Learners can unsubscribe from informational messages themselves. Required messages (important for access or rights) cannot be switched off and are not listed.', 'course-captain' ),
					'element' => 'bools',
					'options' => $types,
				),
			),
			'privacy'      => array(
				'auto_export_on_confirm'  => array(
					'label'   => __( 'Provide data export automatically after a confirmed request', 'course-captain' ),
					'help'    => __( 'The person receives the download link at their confirmed address.', 'course-captain' ),
					'element' => 'checkbox',
					'scope'   => 'system',
				),
				'auto_erase_on_confirm'   => array(
					'label'   => __( 'Delete the account automatically after a confirmed erasure request', 'course-captain' ),
					'help'    => __( 'Accounts with administrative permissions are excluded.', 'course-captain' ),
					'element' => 'checkbox',
					'scope'   => 'system',
				),
				'backup_max_age_days'     => array(
					'label'   => __( 'Maximum age of your backups', 'course-captain' ),
					'help'    => __( 'So that deleted people do not reappear after a restore, Course Captain remembers deletions this long plus 30 days.', 'course-captain' ),
					'element' => 'number',
					'unit'    => __( 'days', 'course-captain' ),
					'scope'   => 'system',
				),
				'external_media_mode'     => array(
					'label'   => __( 'External videos (for example YouTube, Vimeo)', 'course-captain' ),
					'help'    => __( 'By default the learning area loads external videos immediately. The browser of your learners then transfers at least the IP address to the video service without prior consent. Check with your privacy advisor whether you want this. With “Load after click” the connection is only made after a click or a consent in your consent tool.', 'course-captain' ),
					'element' => 'radio',
					'options' => array(
						'always_load'   => __( 'Always load', 'course-captain' ),
						'click_to_load' => __( 'Load after click', 'course-captain' ),
						'link_only'     => __( 'Show as link only', 'course-captain' ),
					),
				),
				'external_media_remember' => array(
					'label'   => __( 'Learners may choose “Always load”', 'course-captain' ),
					'help'    => __( 'Only effective with “Load after click”. The box is never pre-ticked for learners.', 'course-captain' ),
					'element' => 'checkbox',
					'depends' => 'external_media_mode',
				),
				'consent_category'        => array(
					'label'   => __( 'Category in the consent tool', 'course-captain' ),
					'help'    => __( 'Only effective with “Load after click”: Course Captain asks for consent in this category via the WP Consent API.', 'course-captain' ),
					'element' => 'select',
					'depends' => 'external_media_mode',
					'options' => array(
						'marketing'   => __( 'Marketing', 'course-captain' ),
						'statistics'  => __( 'Statistics', 'course-captain' ),
						'preferences' => __( 'Preferences', 'course-captain' ),
						'functional'  => __( 'Functional', 'course-captain' ),
					),
				),
				'trusted_media_hosts'     => array(
					'label'   => __( 'Own media hosts', 'course-captain' ),
					'help'    => __( 'One host name per line, for example media.example.org. Videos and files from these hosts count as your own and load without the external media notice. Only enter hosts you operate or have a processing agreement for.', 'course-captain' ),
					'element' => 'hosts',
					'scope'   => 'system',
				),
				'show_avatars'            => array(
					'label'   => __( 'Show profile pictures (Gravatar)', 'course-captain' ),
					'help'    => __( 'Hashed email addresses are transferred to Gravatar.', 'course-captain' ),
					'element' => 'checkbox',
				),
				'trusted_proxies'         => array(
					'label'   => __( 'Trusted proxies', 'course-captain' ),
					'help'    => __( 'One address or range per line. Only enter addresses if your website runs behind your own proxy or load balancer.', 'course-captain' ),
					'element' => 'lines',
					'scope'   => 'system',
				),
				'csp_mode'                => array(
					'label'   => __( 'Content Security Policy for Course Captain pages', 'course-captain' ),
					'help'    => __( 'Applies to the learning area including lessons and the exam room. Only scripts that WordPress loads in the regular way may run; frames only from this website and the known video services. Report only should be used only briefly, to find conflicts with the theme or other plugins in the browser console.', 'course-captain' ),
					'element' => 'radio',
					'options' => array(
						'enforce'     => __( 'Enforce', 'course-captain' ),
						'report_only' => __( 'Report only (for troubleshooting)', 'course-captain' ),
					),
				),
			),
			'hardening'    => array(
				'login_limit'             => $hardening_toggle( __( 'Login limit', 'course-captain' ), __( 'Blocks further login attempts after too many failures. Pauses automatically while a security plugin has its own login lockout.', 'course-captain' ) ),
				'known_device'            => array(
					'label'   => __( 'Exempt known devices from address lockouts', 'course-captain' ),
					'help'    => __( 'A signed, technically necessary cookie recognises devices on which the person already logged in successfully.', 'course-captain' ),
					'element' => 'checkbox',
					'scope'   => 'system',
					'depends' => 'login_limit',
				),
				'known_device_days'       => array(
					'label'   => __( 'Remember known devices for', 'course-captain' ),
					'help'    => __( 'Lifetime of the device cookie. Applies to new logins; existing cookies keep their expiry.', 'course-captain' ),
					'element' => 'number',
					'unit'    => __( 'days', 'course-captain' ),
					'scope'   => 'system',
					'depends' => 'login_limit',
				),
				'login_limits.ip'         => array(
					'label'   => __( 'Failed logins per address', 'course-captain' ),
					'help'    => $limit_help( 'login_ip' ),
					'element' => 'number',
					'scope'   => 'system',
				),
				'login_limits.account_ip' => array(
					'label'   => __( 'Failed logins per account and address', 'course-captain' ),
					'help'    => $limit_help( 'login_account_ip' ),
					'element' => 'number',
					'scope'   => 'system',
				),
				'login_limits.account'    => array(
					'label'   => __( 'Failed logins per account', 'course-captain' ),
					'help'    => $limit_help( 'login_account' ),
					'element' => 'number',
					'scope'   => 'system',
				),
				'reset_limit'             => $hardening_toggle( __( 'Limit for “Lost password”', 'course-captain' ), __( 'Limits requests per address and account. The answer is the same for known and unknown accounts.', 'course-captain' ) ),
				'registration_limit'      => $hardening_toggle( __( 'Limit for registrations', 'course-captain' ), __( 'Limits new accounts per address and hour. Only effective while anyone can register.', 'course-captain' ) ),
				'xmlrpc'                  => array(
					'label'   => __( 'XML-RPC', 'course-captain' ),
					'help'    => __( 'Old interface for external editors and pingbacks; often used for attacks.', 'course-captain' ),
					'element' => 'xmlrpc',
					'scope'   => 'system',
					'options' => array(
						'off'  => __( 'WordPress default', 'course-captain' ),
						'auto' => __( 'Switch off', 'course-captain' ),
					),
				),
				'site_headers'            => $hardening_toggle( __( 'Security headers', 'course-captain' ), __( 'nosniff, Referrer-Policy, embedding only on this website and a Permissions-Policy for Course Captain responses. Headers the server already sends are not sent twice.', 'course-captain' ) ),
			),
			'advanced'     => array(
				'cron_stale_warning_minutes' => array(
					'label'   => __( 'Warn when background jobs did not run for longer than', 'course-captain' ),
					'help'    => __( 'The system status and Site Health warn after this time.', 'course-captain' ),
					'element' => 'number',
					'unit'    => __( 'minutes', 'course-captain' ),
					'scope'   => 'system',
				),
				'log_level'                  => array(
					'label'   => __( 'Log level', 'course-captain' ),
					'help'    => __( 'Course Captain writes log files to the protected folder, never into the web root. “Info” and “Debug” write much more and fall back to “Warnings and errors” automatically after 7 days. Logs never contain answers, passwords or tokens.', 'course-captain' ),
					'element' => 'radio',
					'scope'   => 'system',
					'options' => array(
						'error'   => __( 'Errors only', 'course-captain' ),
						'warning' => __( 'Warnings and errors', 'course-captain' ),
						'info'    => __( 'Info (for troubleshooting, 7 days)', 'course-captain' ),
						'debug'   => __( 'Debug (for troubleshooting, 7 days)', 'course-captain' ),
					),
				),
				'error_log_days'             => array(
					'label'   => __( 'Keep error logs for', 'course-captain' ),
					'help'    => __( 'Log files with errors and warnings are deleted after this time.', 'course-captain' ),
					'element' => 'number',
					'unit'    => __( 'days', 'course-captain' ),
					'scope'   => 'system',
				),
				'debug_file_days'            => array(
					'label'   => __( 'Keep debug logs for', 'course-captain' ),
					'help'    => __( 'Log files with info and debug entries are deleted after this time (at most 30 days).', 'course-captain' ),
					'element' => 'number',
					'unit'    => __( 'days', 'course-captain' ),
					'scope'   => 'system',
				),
				'report_csv_columns'         => array(
					'label'   => __( 'Columns of the progress report CSV', 'course-captain' ),
					'help'    => __( 'Preselected columns of the CSV download in Learners. The download form can change them for one export.', 'course-captain' ),
					'element' => 'set',
					'options' => \CourseCaptain\Progress\CourseReport::column_labels(),
				),
				'report_pseudonymize'        => array(
					'label'   => __( 'Pseudonymise exports', 'course-captain' ),
					'help'    => __( 'Report and certificate CSV files then contain a stable pseudonym per person instead of name and user ID; email addresses are never exported. The pseudonym stays the same across exports of this website.', 'course-captain' ),
					'element' => 'checkbox',
					'scope'   => 'system',
				),
			),
		);
	}
}
