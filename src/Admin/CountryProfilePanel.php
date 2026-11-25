<?php
/**
 * Country profile suggestions with before/after (SET-CC-07, SET-CC-P3).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Settings\CountryProfiles;
use CourseCaptain\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Shown in setup step 1 and on the settings tab "General". Choosing a
 * profile only shows its suggestions (GET, saves nothing); the operator
 * applies each value explicitly, selected rows or all at once (admin-post,
 * nonce, `ccap_manage_settings`; WordPress values need `manage_options`).
 * Works without JavaScript.
 */
final class CountryProfilePanel {

	public const ACTION = 'ccap_country_profile_apply';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Profile name.
	 *
	 * @param string $id Profile ID.
	 * @return string
	 */
	public static function label( string $id ): string {
		$options = SettingsFields::ui( 'general', 'country_profile' )['options'];
		return (string) ( $options[ $id ] ?? $id );
	}

	/**
	 * Name of a profile key.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public static function key_label( string $key ): string {
		$wp = array(
			'wp.WPLANG'          => __( 'Website language (WordPress; also sets number and date formats)', 'course-captain' ),
			'wp.timezone_string' => __( 'Time zone (WordPress)', 'course-captain' ),
			'wp.date_format'     => __( 'Date format (WordPress)', 'course-captain' ),
			'wp.time_format'     => __( 'Time format (WordPress)', 'course-captain' ),
			'wp.start_of_week'   => __( 'Week starts on (WordPress)', 'course-captain' ),
			'open.age_limit'     => __( 'Age limit and parental consent', 'course-captain' ),
		);
		if ( isset( $wp[ $key ] ) ) {
			return $wp[ $key ];
		}
		list( $tab, $field ) = array_pad( explode( '.', $key, 2 ), 2, '' );
		if ( 'setting' !== CountryProfiles::kind( $key ) ) {
			return $key;
		}
		/* translators: 1: settings tab, 2: setting label */
		return sprintf( __( '%1$s › %2$s', 'course-captain' ), SettingsFields::tab_label( $tab ), SettingsFields::ui( $tab, $field )['label'] );
	}

	/**
	 * Readable value of a profile key.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @return string
	 */
	public static function value_text( string $key, $value ): string {
		if ( null === $value ) {
			return 'open.age_limit' === $key ? __( 'No setting yet', 'course-captain' ) : __( 'none', 'course-captain' );
		}
		switch ( $key ) {
			case 'wp.date_format':
			case 'wp.time_format':
				/* translators: 1: PHP date format, 2: example */
				return sprintf( __( '%1$s (for example %2$s)', 'course-captain' ), (string) $value, (string) wp_date( (string) $value ) );
			case 'wp.start_of_week':
				global $wp_locale;
				return $wp_locale instanceof \WP_Locale ? (string) $wp_locale->get_weekday( (int) $value ) : (string) $value;
			case 'wp.WPLANG':
			case 'wp.timezone_string':
				return (string) $value;
		}
		list( $tab, $field ) = array_pad( explode( '.', $key, 2 ), 2, '' );
		return SettingsForm::display( SettingsFields::ui( $tab, $field ), (array) SettingsFields::schema( $tab, $field ), $value );
	}

	/**
	 * Status text of a comparison row.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return string
	 */
	private static function status_text( array $row ): string {
		switch ( $row['status'] ) {
			case 'differs':
				return __( 'Changes the current value', 'course-captain' );
			case 'same':
				return __( 'Already set', 'course-captain' );
			case 'open':
				/* translators: %s: number of the owner decision, for example E4 */
				return sprintf( __( 'open – decision by the owner (%s); never applied', 'course-captain' ), (string) $row['open'] );
		}
		return 'language_missing' === $row['reason']
			? __( 'Install this language under Settings › General first.', 'course-captain' )
			: __( 'Cannot be applied here.', 'course-captain' );
	}

	/**
	 * Panel HTML.
	 *
	 * @param string               $context `settings` or `setup`.
	 * @param array<string, mixed> $request GET.
	 * @param int                  $user_id User.
	 * @return string
	 */
	public static function html( string $context, array $request, int $user_id ): string {
		if ( $user_id <= 0 || ! user_can( $user_id, Settings::CAP ) ) {
			return '';
		}
		$stored  = (string) Settings::get( CountryProfiles::CHOICE_KEY );
		$picked  = isset( $request['ccap_profile'] ) ? strtoupper( sanitize_key( (string) $request['ccap_profile'] ) ) : '';
		$profile = in_array( $picked, CountryProfiles::IDS, true ) ? $picked : $stored;
		$html    = '<section id="ccap-country-profile" class="ccap-country-profile" aria-labelledby="ccap-country-profile-title">';
		$html   .= '<h2 id="ccap-country-profile-title">' . esc_html__( 'Country profile suggestions', 'course-captain' ) . '</h2>';
		$html   .= '<p>' . esc_html__( 'A country profile suggests values for existing settings, for example date format, time zone and how external videos load. Nothing changes until you apply a value; you see the current value and the suggestion side by side.', 'course-captain' ) . '</p>';
		$html   .= '<p>' . esc_html__( 'Values marked “unreviewed” have not been checked legally. Course Captain decides nothing legal for you: have them checked (legal review) before you rely on them.', 'course-captain' ) . '</p>';
		$html   .= self::message( $request );
		$html   .= '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" class="ccap-inline-form">';
		$html   .= 'setup' === $context
			? '<input type="hidden" name="page" value="' . esc_attr( SetupWizard::SLUG ) . '"><input type="hidden" name="step" value="purpose_language">'
			: '<input type="hidden" name="page" value="' . esc_attr( SettingsScreen::SLUG ) . '"><input type="hidden" name="tab" value="general">';
		$html   .= '<label for="ccap-country-profile-pick">' . esc_html__( 'Show suggestions of', 'course-captain' ) . '</label> <select id="ccap-country-profile-pick" name="ccap_profile">';
		foreach ( CountryProfiles::IDS as $id ) {
			$html .= '<option value="' . esc_attr( $id ) . '"' . selected( $id, $profile, false ) . '>' . esc_html( self::label( $id ) ) . '</option>';
		}
		$html .= '</select> <button type="submit" class="button">' . esc_html__( 'Show suggestions', 'course-captain' ) . '</button></form>';
		/* translators: %s: country profile name */
		$html .= '<p>' . esc_html( '' === $stored ? __( 'No profile has been applied yet.', 'course-captain' ) : sprintf( __( 'Chosen profile: %s', 'course-captain' ), self::label( $stored ) ) ) . '</p>';
		if ( '' === $profile ) {
			return $html . '</section>';
		}
		$rows = CountryProfiles::compare( $profile );
		if ( array() === $rows ) {
			return $html . '<p>' . esc_html__( 'This profile has no suggestions.', 'course-captain' ) . '</p></section>';
		}
		$admin = user_can( $user_id, 'manage_options' );
		$body  = '';
		$open  = 0;
		foreach ( $rows as $i => $row ) {
			$label  = self::key_label( $row['key'] );
			$cell   = '–';
			$usable = 'differs' === $row['status'] && ( 'wp' !== $row['kind'] || $admin );
			if ( $usable ) {
				$id = 'ccap-country-profile-key-' . $i;
				/* translators: %s: setting name */
				$cell = '<input type="checkbox" id="' . esc_attr( $id ) . '" name="keys[]" value="' . esc_attr( $row['key'] ) . '"> <label for="' . esc_attr( $id ) . '">' . esc_html__( 'Apply', 'course-captain' ) . '<span class="screen-reader-text">: ' . esc_html( $label ) . '</span></label>';
			}
			$status = esc_html( self::status_text( $row ) );
			if ( 'differs' === $row['status'] && 'wp' === $row['kind'] && ! $admin ) {
				$status .= ' ' . esc_html__( 'Only administrators can change WordPress values.', 'course-captain' );
			}
			if ( $row['legal'] && ! $row['reviewed'] ) {
				$status .= '<br><strong>' . esc_html__( 'unreviewed', 'course-captain' ) . '</strong> – ' . esc_html__( 'legal review needed', 'course-captain' );
			}
			$open += 'open' === $row['status'] ? 1 : 0;
			$body .= '<tr data-ccap-profile-key="' . esc_attr( $row['key'] ) . '" data-ccap-profile-status="' . esc_attr( $row['status'] ) . '"><td>' . $cell . '</td><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( 'open.age_limit' === $row['key'] ? self::value_text( $row['key'], null ) : self::value_text( $row['key'], $row['current'] ) ) . '</td><td>' . esc_html( 'open' === $row['status'] ? '–' : self::value_text( $row['key'], $row['suggested'] ) ) . '</td><td>' . $status . '</td></tr>';
		}
		/* translators: %s: country profile name */
		$caption = sprintf( __( 'Suggestions of the profile “%s” compared with your current values', 'course-captain' ), self::label( $profile ) );
		$html   .= '<form id="cc-country-profile" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$html   .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="profile" value="' . esc_attr( $profile ) . '"><input type="hidden" name="context" value="' . esc_attr( $context ) . '"><input type="hidden" name="seen" value="' . esc_attr( CountryProfiles::fingerprint( $rows ) ) . '">' . wp_nonce_field( self::ACTION, '_wpnonce', true, false );
		$html   .= '<div class="ccap-table-scroll" role="region" tabindex="0" aria-label="' . esc_attr( $caption ) . '"><table class="widefat striped"><caption>' . esc_html( $caption ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Select', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Setting', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Current value (before)', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Suggestion (after)', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Status', 'course-captain' ) . '</th></tr></thead><tbody>' . $body . '</tbody></table></div>';
		if ( $open > 0 ) {
			$html .= '<p>' . esc_html( sprintf( /* translators: %d: number of open values */ _n( '%d value waits for a decision by the owner and stays unchanged.', '%d values wait for a decision by the owner and stay unchanged.', $open, 'course-captain' ), $open ) ) . '</p>';
		}
		$html .= '<p class="submit"><button type="submit" name="mode" value="selected" class="button button-primary">' . esc_html__( 'Apply selected values', 'course-captain' ) . '</button> <button type="submit" name="mode" value="all" class="button">' . esc_html__( 'Apply all suggestions', 'course-captain' ) . '</button></p></form>';
		return $html . '</section>';
	}

	/**
	 * Result message of an apply.
	 *
	 * @param array<string, mixed> $request GET.
	 * @return string
	 */
	private static function message( array $request ): string {
		$result = isset( $request['ccap_profile_result'] ) ? sanitize_key( (string) $request['ccap_profile_result'] ) : '';
		$count  = absint( $request['ccap_profile_count'] ?? 0 );
		$type   = 'success';
		switch ( $result ) {
			case 'applied':
				/* translators: %d: number of applied values */
				$text = sprintf( _n( '%d value applied. The change is in the audit log.', '%d values applied. The change is in the audit log.', $count, 'course-captain' ), $count );
				break;
			case 'nothing':
				$text = __( 'No value changed. Values that are already set, open or unavailable are never applied.', 'course-captain' );
				$type = 'info';
				break;
			case 'none_selected':
				$text = __( 'Select at least one value or apply all suggestions.', 'course-captain' );
				$type = 'error';
				break;
			case 'conflict':
				$text = __( 'The current values changed in the meantime. Check the comparison again; nothing was applied.', 'course-captain' );
				$type = 'warning';
				break;
			case 'ccap_invalid_nonce':
				$text = __( 'The form has expired. Reload the page and try again.', 'course-captain' );
				$type = 'error';
				break;
			case 'ccap_forbidden':
				$text = __( 'You are not allowed to change these settings.', 'course-captain' );
				$type = 'error';
				break;
			default:
				return '';
		}
		return '<div class="notice notice-' . esc_attr( $type ) . '" role="' . ( 'error' === $type ? 'alert' : 'status' ) . '" tabindex="-1" data-ccap-focus="1"><p>' . esc_html( $text ) . '</p></div>';
	}

	/**
	 * Admin-post entry point.
	 *
	 * @return void
	 */
	public static function handle(): void {
		$out  = self::process( wp_unslash( $_POST ), get_current_user_id() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in process().
		$args = array(
			'ccap_profile'        => $out['profile'],
			'ccap_profile_result' => $out['result'],
			'ccap_profile_count'  => $out['count'],
		);
		$url  = 'setup' === $out['context']
			? add_query_arg( array_merge( array( 'page' => SetupWizard::SLUG, 'step' => 'purpose_language' ), $args ), admin_url( 'admin.php' ) ) // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short URL args.
			: SettingsScreen::url( 'general', $args );
		wp_safe_redirect( $url . '#ccap-country-profile' );
		exit;
	}

	/**
	 * Testable core of "Apply".
	 *
	 * @param array<string, mixed> $post    Unslashed POST.
	 * @param int                  $user_id User.
	 * @return array{context: string, profile: string, result: string, count: int, applied: list<string>}
	 */
	public static function process( array $post, int $user_id ): array {
		$profile = isset( $post['profile'] ) ? strtoupper( sanitize_key( (string) $post['profile'] ) ) : '';
		$out     = array(
			'context' => 'setup' === ( $post['context'] ?? '' ) ? 'setup' : 'settings',
			'profile' => $profile,
			'result'  => 'applied',
			'count'   => 0,
			'applied' => array(),
		);
		if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), self::ACTION ) ) {
			$out['result'] = 'ccap_invalid_nonce';
			return $out;
		}
		if ( $user_id <= 0 || ! user_can( $user_id, Settings::CAP ) ) {
			$out['result'] = 'ccap_forbidden';
			return $out;
		}
		if ( ! in_array( $profile, CountryProfiles::IDS, true ) ) {
			$out['result'] = 'ccap_not_found';
			return $out;
		}
		if ( 'all' === ( $post['mode'] ?? '' ) ) {
			$keys = array();
			foreach ( CountryProfiles::compare( $profile ) as $row ) {
				if ( 'differs' === $row['status'] ) {
					$keys[] = $row['key'];
				}
			}
		} else {
			$keys = is_array( $post['keys'] ?? null ) ? array_values( array_map( 'strval', array_filter( $post['keys'], 'is_string' ) ) ) : array();
			if ( array() === $keys ) {
				$out['result'] = 'none_selected';
				return $out;
			}
		}
		try {
			$result = CountryProfiles::apply( Actor::user( $user_id ), $profile, $keys, (string) ( $post['seen'] ?? '' ) );
		} catch ( CourseException $e ) {
			$out['result'] = $e->error_code;
			return $out;
		}
		$out['result']  = $result['result'];
		$out['applied'] = $result['applied'];
		$out['count']   = count( $result['applied'] );
		return $out;
	}
}
