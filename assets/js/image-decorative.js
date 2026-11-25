/**
 * Course Captain: "Decorative image" switch for the image block (UI-F8, E-349, VAL-140/141).
 *
 * Adds the attribute `ccapDecorative` to the core image block and a toggle in
 * its sidebar. Switching it on empties the alternative text; the course check
 * then counts the image as decorative, the front end renders it with alt=""
 * and data-ccap-decorative="1". The saved block markup is not changed, so no
 * block validation issues arise. No build step: plain ES5 with wp.* globals.
 */
( function ( wp, cfg ) {
	'use strict';
	if ( ! wp || ! wp.hooks || ! wp.compose || ! wp.element || ! wp.blockEditor || ! wp.components || ! cfg ) {
		return;
	}
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var ToggleControl = wp.components.ToggleControl;
	var name = cfg.attribute || 'ccapDecorative';

	wp.hooks.addFilter( 'blocks.registerBlockType', 'course-captain/image-decorative-attribute', function ( settings, blockName ) {
		if ( 'core/image' !== blockName ) {
			return settings;
		}
		var attributes = Object.assign( {}, settings.attributes );
		attributes[ name ] = { type: 'boolean', default: false };
		return Object.assign( {}, settings, { attributes: attributes } );
	} );

	var withToggle = wp.compose.createHigherOrderComponent( function ( BlockEdit ) {
		return function ( props ) {
			if ( 'core/image' !== props.name ) {
				return el( BlockEdit, props );
			}
			var on = !! props.attributes[ name ];
			return el(
				Fragment,
				null,
				el( BlockEdit, props ),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: cfg.panel, initialOpen: on },
						el( ToggleControl, {
							label: cfg.label,
							help: cfg.help,
							checked: on,
							onChange: function ( value ) {
								var next = {};
								next[ name ] = !! value;
								if ( value ) {
									next.alt = '';
								}
								props.setAttributes( next );
							}
						} )
					)
				)
			);
		};
	}, 'withCcapDecorativeToggle' );

	wp.hooks.addFilter( 'editor.BlockEdit', 'course-captain/image-decorative-toggle', withToggle );
}( window.wp, window.ccapImageDecorative ) );
