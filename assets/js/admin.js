( function( $ ) {
    'use strict';
    var mediaFrame;
    $( document ).on( 'click', '.mfsd-hw-media-btn', function( e ) {
        e.preventDefault();
        var $btn = $( this );
        var targetId  = $btn.data( 'target' );
        var previewId = $btn.data( 'preview' );
        mediaFrame = wp.media( { title: 'Select Image', button: { text: 'Use this image' }, multiple: false, library: { type: 'image' } } );
        mediaFrame.on( 'select', function() {
            var att = mediaFrame.state().get( 'selection' ).first().toJSON();
            $( '#' + targetId ).val( att.id );
            var url = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
            $( '#' + previewId ).attr( 'src', url ).show();
            $btn.text( 'Change Image' );
        } );
        mediaFrame.open();
    } );
    $( document ).on( 'click', '.mfsd-hw-media-clear', function( e ) {
        e.preventDefault();
        var $btn = $( this );
        $( '#' + $btn.data( 'target' ) ).val( '0' );
        $( '#' + $btn.data( 'preview' ) ).attr( 'src', '' ).hide();
        $btn.siblings( '.mfsd-hw-media-btn' ).text( 'Select Image' );
    } );
} )( jQuery );
