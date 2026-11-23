/**
 * Course Captain: messages of the meta box saves in the block editor (R1-18 B-4, R0-11 F1.1/F1.4).
 *
 * The block editor hides classic admin notices and saves the meta boxes in
 * the background, so "Not saved …" and "Not published …" would never reach
 * the author. This script shows a pending message as an editor notice on
 * load and asks for a new one after every meta box save.
 */
( function ( wp, cfg ) {
	'use strict';
	if ( ! wp || ! wp.data || ! cfg ) {
		return;
	}
	function show( message ) {
		if ( message ) {
			wp.data.dispatch( 'core/notices' ).createErrorNotice( message, { id: 'ccap-editor-save' } );
		}
	}
	function pull() {
		var body = new window.FormData();
		body.append( 'action', 'ccap_editor_flash' );
		body.append( '_ajax_nonce', cfg.nonce );
		window.fetch( cfg.url, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( d ) {
				show( d && d.success && d.data ? d.data.message : '' );
			} )
			.catch( function () {} );
	}
	show( cfg.message );
	var saving = false;
	wp.data.subscribe( function () {
		var editPost = wp.data.select( 'core/edit-post' );
		if ( ! editPost || ! editPost.isSavingMetaBoxes ) {
			return;
		}
		var now = editPost.isSavingMetaBoxes();
		if ( saving && ! now ) {
			pull();
		}
		saving = now;
	} );
}( window.wp, window.ccapEditorNotices ) );
