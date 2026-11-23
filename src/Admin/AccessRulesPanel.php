<?php
/**
 * Access rules of a course in the "Learners" screen (UI-F8, R0-09, R0-11 A-09).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Access\AccessRules;
use CourseCaptain\Access\TrialLimits;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Policy\Actor;

defined( 'ABSPATH' ) || exit;

/**
 * Shows the rules of one course as a table (applies to, kind, value,
 * validity) and offers plain forms for free access, drip per module and
 * partial access per kind; each rule can be removed with a confirmation.
 * Writes go through {@see AccessRules} (rights, audit). Without the right
 * the table is shown read-only.
 */
final class AccessRulesPanel {

	public const ACTION = 'ccap_access_rules_admin';
	public const OPS    = array( 'free_add', 'drip_set', 'limits_set', 'rule_delete' );

	/**
	 * Admin-post entry point.
	 *
	 * @return void
	 */
	public static function handle(): void {
		$out = self::process( wp_unslash( $_POST ), get_current_user_id() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in process().
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => LearnersScreen::SLUG,
					'course'      => $out['course'],
					'view'        => 'rules',
					'ccap_result' => $out['result'],
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Testable core of the handler.
	 *
	 * @param array<string, mixed> $post    Unslashed POST data.
	 * @param int                  $user_id Actor.
	 * @return array{op: string, course: int, result: string}
	 * @throws CourseException Never; domain errors become the result code.
	 */
	public static function process( array $post, int $user_id ): array {
		$op     = sanitize_key( (string) ( $post['op'] ?? '' ) );
		$course = absint( $post['object'] ?? 0 );
		$out    = array(
			'op'     => $op,
			'course' => $course,
			'result' => 'ok',
		);
		if ( ! in_array( $op, self::OPS, true ) ) {
			$out['result'] = 'ccap_not_found';
			return $out;
		}
		if ( ! AdminUi::verify( self::ACTION, $post ) ) {
			$out['result'] = 'ccap_invalid_nonce';
			return $out;
		}
		$actor  = Actor::user( $user_id );
		$rules  = AccessRules::current();
		$module = absint( $post['module'] ?? 0 );
		try {
			switch ( $op ) {
				case 'free_add':
					$rules->add_free( $actor, $course, $module, self::date( (string) ( $post['valid_from'] ?? '' ), '00:00:00' ), self::date( (string) ( $post['valid_until'] ?? '' ), '23:59:59' ) );
					break;
				case 'drip_set':
					$days = trim( (string) ( $post['days'] ?? '' ) );
					if ( '' !== $days && 1 !== preg_match( '/^\d{1,4}$/', $days ) ) {
						throw new CourseException( 'ccap_invalid_param', 'days', 422 );
					}
					$rules->set_drip( $actor, $course, $module, '' === $days ? null : (int) $days );
					break;
				case 'limits_set':
					$limits = array();
					foreach ( array_keys( TrialLimits::KINDS ) as $kind ) {
						$value = trim( (string) ( $post['limits'][ $kind ] ?? '' ) );
						if ( '' !== $value && 1 !== preg_match( '/^\d{1,5}$/', $value ) ) {
							throw new CourseException( 'ccap_invalid_param', $kind, 422 );
						}
						$limits[ $kind ] = '' === $value ? null : (int) $value;
					}
					$rules->set_limits( $actor, $course, $module, $limits );
					break;
				case 'rule_delete':
					if ( empty( $post['confirm'] ) ) {
						throw new CourseException( 'ccap_confirmation_required', 'confirm', 422 );
					}
					$rules->delete( $actor, $course, absint( $post['rule'] ?? 0 ) );
					break;
			}
		} catch ( CourseException $e ) {
			$out['result'] = 'ccap_validation_failed' === $e->error_code ? 'ccap_invalid_param' : $e->error_code;
		}
		return $out;
	}

	/**
	 * Date field (`Y-m-d`) as UTC datetime, null when empty.
	 *
	 * @param string $value Input.
	 * @param string $time  Time of day.
	 * @return string|null
	 * @throws CourseException `ccap_invalid_param` for other formats.
	 */
	private static function date( string $value, string $time ): ?string {
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) || false === strtotime( $value ) ) {
			throw new CourseException( 'ccap_invalid_param', 'date', 422 );
		}
		return $value . ' ' . $time;
	}

	/**
	 * Panel HTML.
	 *
	 * @param array<string, mixed> $request GET.
	 * @param int                  $user_id Viewer.
	 * @param int                  $course  Course.
	 * @return string
	 */
	public static function html( array $request, int $user_id, int $course ): string {
		$actor   = Actor::user( $user_id );
		$service = AccessRules::current();
		$can     = $service->can_manage( $actor, $course );
		$modules = array();
		foreach ( CourseStructure::current()->modules( $course ) as $link ) {
			$modules[ (int) $link['module_id'] ] = (string) get_the_title( (int) $link['module_id'] );
		}
		$back = '<p><a href="' . esc_url(
			add_query_arg(
				array(
					'page'   => LearnersScreen::SLUG,
					'course' => $course,
				),
				admin_url( 'admin.php' )
			)
		) . '">' . esc_html__( 'Back to the learner list', 'course-captain' ) . '</a></p>';
		$html = $back . AdminUi::notice(
			sanitize_key( (string) ( $request['ccap_result'] ?? '' ) ),
			array(
				'ccap_confirmation_required' => __( 'Please confirm the action (tick the confirmation box).', 'course-captain' ),
			),
			__( 'Access rules saved. They apply at once.', 'course-captain' )
		);
		/* translators: %s: course */
		$html .= '<h2>' . esc_html( sprintf( __( 'Access rules of %s', 'course-captain' ), (string) get_the_title( $course ) ) ) . '</h2>';
		$html .= '<p>' . esc_html__( 'Rules decide what learners see in addition to their access: free access opens content to every logged-in visitor, a delay (drip) opens a module some days after the start of the access, partial access opens only the first items to visitors without access.', 'course-captain' ) . '</p>';
		$html .= self::table( $service->rules( $course ), $modules, $can, $course );
		if ( ! $can ) {
			return $html . '<p>' . esc_html__( 'You may view these rules but not change them.', 'course-captain' ) . '</p>';
		}
		return $html . self::free_form( $course, $modules ) . self::drip_form( $course, $modules ) . self::limits_form( $course, $modules );
	}

	/**
	 * Rule table.
	 *
	 * @param list<array<string, mixed>> $rules   Rules.
	 * @param array<int, string>         $modules Modules of the course.
	 * @param bool                       $can     Viewer may change rules.
	 * @param int                        $course  Course.
	 * @return string
	 */
	private static function table( array $rules, array $modules, bool $can, int $course ): string {
		$html = '<table class="widefat striped"><caption class="screen-reader-text">' . esc_html__( 'Access rules', 'course-captain' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Applies to', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Rule', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Value', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Valid (UTC)', 'course-captain' ) . '</th>' . ( $can ? '<th scope="col">' . esc_html__( 'Actions', 'course-captain' ) . '</th>' : '' ) . '</tr></thead><tbody>';
		if ( array() === $rules ) {
			$html .= '<tr><td colspan="' . ( $can ? 5 : 4 ) . '">' . esc_html__( 'No rules: only learners with access see the course, all modules open at the start (unless the site-wide drip plan applies).', 'course-captain' ) . '</td></tr>';
		}
		foreach ( $rules as $rule ) {
			$target = 0 === $rule['module_id'] ? __( 'whole course', 'course-captain' ) : sprintf( /* translators: %s: module */ __( 'module %s', 'course-captain' ), $modules[ $rule['module_id'] ] ?? (string) get_the_title( (int) $rule['module_id'] ) );
			$html  .= '<tr><th scope="row">' . esc_html( $target ) . '</th><td>' . esc_html( self::kind_label( (string) $rule['access_type'], $rule['limit_type'] ) ) . '</td><td>' . esc_html( self::value_label( $rule ) ) . '</td><td>' . esc_html( self::window_label( $rule['valid_from'], $rule['valid_until'] ) ) . '</td>';
			if ( $can ) {
				$id    = (int) $rule['id'];
				$html .= '<td>' . AdminUi::form( self::ACTION, 'rule_delete', $course, '<input type="hidden" name="rule" value="' . $id . '"><p><label for="ccap-rule-del-' . $id . '"><input type="checkbox" id="ccap-rule-del-' . $id . '" name="confirm" value="1"> ' . esc_html__( 'Confirm: remove this rule', 'course-captain' ) . '</label></p>', __( 'Remove rule', 'course-captain' ) ) . '</td>';
			}
			$html .= '</tr>';
		}
		return $html . '</tbody></table>';
	}

	/**
	 * Free access form.
	 *
	 * @param int                $course  Course.
	 * @param array<int, string> $modules Modules.
	 * @return string
	 */
	private static function free_form( int $course, array $modules ): string {
		$fields  = '<p><label for="ccap-free-module">' . esc_html__( 'Applies to', 'course-captain' ) . '</label> ' . self::module_select( 'ccap-free-module', $modules, true ) . '</p>';
		$fields .= '<p><label for="ccap-free-from">' . esc_html__( 'From (date, empty = now)', 'course-captain' ) . '</label> <input id="ccap-free-from" type="date" name="valid_from"> <label for="ccap-free-until">' . esc_html__( 'Until (date, empty = no end)', 'course-captain' ) . '</label> <input id="ccap-free-until" type="date" name="valid_until"></p>';
		return '<h3>' . esc_html__( 'Free access', 'course-captain' ) . '</h3><p class="description">' . esc_html__( 'Every logged-in visitor may learn the chosen content, without enrolment.', 'course-captain' ) . '</p>' . AdminUi::form( self::ACTION, 'free_add', $course, $fields, __( 'Add free access', 'course-captain' ) );
	}

	/**
	 * Drip form.
	 *
	 * @param int                $course  Course.
	 * @param array<int, string> $modules Modules.
	 * @return string
	 */
	private static function drip_form( int $course, array $modules ): string {
		if ( array() === $modules ) {
			return '';
		}
		$fields  = '<p><label for="ccap-drip-module">' . esc_html__( 'Module', 'course-captain' ) . '</label> ' . self::module_select( 'ccap-drip-module', $modules, false ) . '</p>';
		$fields .= '<p><label for="ccap-drip-days">' . esc_html__( 'Opens this many days after the start of the access (empty = remove the delay)', 'course-captain' ) . '</label> <input id="ccap-drip-days" type="number" min="0" max="' . (int) AccessRules::MAX_DAYS . '" step="1" name="days"></p>';
		return '<h3>' . esc_html__( 'Delayed modules (drip)', 'course-captain' ) . '</h3>' . AdminUi::form( self::ACTION, 'drip_set', $course, $fields, __( 'Save delay', 'course-captain' ) );
	}

	/**
	 * Partial access form.
	 *
	 * @param int                $course  Course.
	 * @param array<int, string> $modules Modules.
	 * @return string
	 */
	private static function limits_form( int $course, array $modules ): string {
		$labels  = array(
			TrialLimits::QUESTIONS => __( 'First questions per module', 'course-captain' ),
			TrialLimits::LESSONS   => __( 'First lessons per module', 'course-captain' ),
			TrialLimits::VARIANTS  => __( 'First fixed exam versions', 'course-captain' ),
			TrialLimits::FILES     => __( 'First files', 'course-captain' ),
		);
		$fields  = '<p><label for="ccap-lim-module">' . esc_html__( 'Applies to', 'course-captain' ) . '</label> ' . self::module_select( 'ccap-lim-module', $modules, true ) . '</p>';
		$fields .= '<fieldset><legend>' . esc_html__( 'Number of free items (empty = no limit of this kind)', 'course-captain' ) . '</legend>';
		foreach ( $labels as $kind => $label ) {
			$fields .= '<p><label for="ccap-lim-' . esc_attr( $kind ) . '">' . esc_html( $label ) . '</label> <input id="ccap-lim-' . esc_attr( $kind ) . '" type="number" min="0" max="10000" step="1" name="limits[' . esc_attr( $kind ) . ']"></p>';
		}
		$fields .= '</fieldset>';
		return '<h3>' . esc_html__( 'Partial access (trial)', 'course-captain' ) . '</h3><p class="description">' . esc_html__( 'Visitors without access may use the first items. Saving replaces all four limits of the chosen course or module.', 'course-captain' ) . '</p>' . AdminUi::form( self::ACTION, 'limits_set', $course, $fields, __( 'Save partial access', 'course-captain' ) );
	}

	/**
	 * Module select (`name="module"`).
	 *
	 * @param string             $id      Field ID.
	 * @param array<int, string> $modules Modules.
	 * @param bool               $course  Offer "whole course".
	 * @return string
	 */
	private static function module_select( string $id, array $modules, bool $course ): string {
		$html = '<select id="' . esc_attr( $id ) . '" name="module">' . ( $course ? '<option value="0">' . esc_html__( 'whole course', 'course-captain' ) . '</option>' : '' );
		foreach ( $modules as $module => $title ) {
			$html .= '<option value="' . (int) $module . '">' . esc_html( $title ) . '</option>';
		}
		return $html . '</select>';
	}

	/**
	 * Kind label.
	 *
	 * @param string      $type       Access type.
	 * @param string|null $limit_type Limit type.
	 * @return string
	 */
	private static function kind_label( string $type, ?string $limit_type ): string {
		if ( 'free_limited' === $type ) {
			$kinds = array(
				TrialLimits::QUESTIONS => __( 'partial access: questions', 'course-captain' ),
				TrialLimits::LESSONS   => __( 'partial access: lessons', 'course-captain' ),
				TrialLimits::VARIANTS  => __( 'partial access: fixed exam versions', 'course-captain' ),
				TrialLimits::FILES     => __( 'partial access: files', 'course-captain' ),
			);
			return $kinds[ (string) $limit_type ] ?? __( 'partial access: all kinds', 'course-captain' );
		}
		$labels = array(
			'free' => __( 'free access', 'course-captain' ),
			'drip' => __( 'delay (drip)', 'course-captain' ),
		);
		return $labels[ $type ] ?? $type;
	}

	/**
	 * Value label.
	 *
	 * @param array<string, mixed> $rule Rule.
	 * @return string
	 */
	private static function value_label( array $rule ): string {
		$value = null === $rule['limit_value'] ? 0 : (int) $rule['limit_value'];
		if ( 'drip' === $rule['access_type'] ) {
			/* translators: %d: number of days */
			return sprintf( _n( 'opens %d day after the start', 'opens %d days after the start', $value, 'course-captain' ), $value );
		}
		if ( 'free_limited' === $rule['access_type'] ) {
			/* translators: %d: number of items */
			return sprintf( _n( 'first %d item', 'first %d items', $value, 'course-captain' ), $value );
		}
		return '–';
	}

	/**
	 * Validity label.
	 *
	 * @param string|null $from  Start.
	 * @param string|null $until End.
	 * @return string
	 */
	private static function window_label( ?string $from, ?string $until ): string {
		if ( null === $from && null === $until ) {
			return __( 'always', 'course-captain' );
		}
		/* translators: 1: start, 2: end */
		return sprintf( __( '%1$s to %2$s', 'course-captain' ), $from ?? '…', $until ?? '…' );
	}
}
