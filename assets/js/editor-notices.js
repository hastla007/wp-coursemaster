/**
 * Course Captain: messages of the meta box saves in the block editor (R1-18 B-4, R0-11 F1.1/F1.4).
 *
 * The block editor hides classic admin notices and saves the meta boxes in
 * the background, so "Not saved …" and "Not published …" would never reach
 * the author. This script shows a pending message as an editor notice on
 * load and asks for a new one after every meta box save.
 *
 * UI-F6: the block editor does not reload the meta boxes after saving them,
 * so the panels kept the state of the first page load (module list,
 * published revision, validation) and "Publish a new revision when saving"
 * stayed ticked. After every meta box save the box is unticked at once and
 * the panels are replaced with HTML rendered from the stored state.
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
	function untick() {
		var boxes = document.querySelectorAll( 'input[name="ccap_publish_revision"]' );
		for ( var i = 0; i < boxes.length; i++ ) {
			boxes[ i ].checked = false;
		}
	}
	function refresh( boxes ) {
		if ( ! boxes ) {
			return;
		}
		Object.keys( boxes ).forEach( function ( id ) {
			var box = document.getElementById( id );
			var inside = box ? box.querySelector( '.inside' ) : null;
			if ( ! inside ) {
				return;
			}
			// Keep keyboard users where they were (the focused control is replaced).
			var focused = document.activeElement && inside.contains( document.activeElement ) ? document.activeElement.id : '';
			// The panel HTML is rendered and escaped by the server, exactly as on page load
			// (EditorScreens::fresh_boxes()); DOMParser never runs scripts.
			var parsed = new window.DOMParser().parseFromString( String( boxes[ id ] ), 'text/html' );
			inside.replaceChildren.apply( inside, Array.prototype.slice.call( parsed.body.childNodes ) );
			if ( focused && document.getElementById( focused ) ) {
				document.getElementById( focused ).focus();
			}
		} );
	}
	function postId() {
		var editor = wp.data.select( 'core/editor' );
		return editor && editor.getCurrentPostId ? editor.getCurrentPostId() : cfg.postId;
	}
	function pull() {
		untick();
		var body = new window.FormData();
		body.append( 'action', 'ccap_editor_flash' );
		body.append( '_ajax_nonce', cfg.nonce );
		body.append( 'post_id', String( postId() || 0 ) );
		window.fetch( cfg.url, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( d ) {
				var data = d && d.success && d.data ? d.data : {};
				refresh( data.boxes );
				show( data.message || '' );
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
