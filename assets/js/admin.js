/* global mfsdHwAdmin */
( function( $ ) {
    'use strict';

    // ── By Role tab: up/down reorder (AJAX, no page reload) ──────────────────

    $( document ).on( 'click', '.mfsd-hw-role-reorder-btn', function( e ) {
        e.preventDefault();
        if ( ! window.mfsdHwAdmin ) return;

        var $btn = $( this );
        if ( $btn.prop( 'disabled' ) ) return;

        var $row      = $btn.closest( '.mfsd-hw-role-widget-row' );
        var $list     = $btn.closest( '.mfsd-hw-role-widget-list' );
        var direction = $btn.data( 'direction' );
        var widgetId  = $row.data( 'widget-id' );
        var role      = $list.data( 'role' );
        var $sibling  = direction === 'up' ? $row.prev( '.mfsd-hw-role-widget-row' ) : $row.next( '.mfsd-hw-role-widget-row' );

        if ( ! $sibling.length ) return;

        $list.find( '.mfsd-hw-role-reorder-btn' ).prop( 'disabled', true );

        // Optimistic UI: swap the two rows immediately.
        if ( direction === 'up' ) {
            $row.insertBefore( $sibling );
        } else {
            $row.insertAfter( $sibling );
        }
        renumberRoleWidgetList( $list );

        $.post( mfsdHwAdmin.ajaxUrl, {
            action:    'mfsd_hw_ajax_reorder',
            nonce:     mfsdHwAdmin.reorderNonce,
            role:      role,
            widget_id: widgetId,
            direction: direction
        } ).fail( function() {
            // Revert on failure — simplest safe recovery is a fresh render.
            window.location.reload();
        } ).always( function() {
            renumberRoleWidgetList( $list ); // re-enables and re-applies first/last disabled state
        } );
    } );

    function renumberRoleWidgetList( $list ) {
        var $rows = $list.find( '.mfsd-hw-role-widget-row' );
        $rows.each( function( i ) {
            var $row = $( this );
            $row.find( '.mfsd-hw-role-widget-row__pos' ).text( i + 1 );
            $row.find( '.mfsd-hw-role-reorder-btn[data-direction="up"]' ).prop( 'disabled', i === 0 );
            $row.find( '.mfsd-hw-role-reorder-btn[data-direction="down"]' ).prop( 'disabled', i === $rows.length - 1 );
        } );
    }


    // ── By Role tab: Available Widgets drag-to-assign + Remove (AJAX, no reload) ──
    //
    // Each row/item carries a hidden <template class="mfsd-hw-alt-state"> with
    // the markup for its OTHER state (active row <-> available item). Moving a
    // widget just extracts that template, drops it in the destination list, and
    // gives the newly-inserted element its own fresh alt-state template captured
    // from the element that's leaving — so the swap stays reversible indefinitely
    // without the server ever needing to re-render anything.

    function getAltStateClone( $el ) {
        var tpl = $el.children( 'template.mfsd-hw-alt-state' )[ 0 ];
        if ( ! tpl ) return null;
        var frag = document.importNode( tpl.content, true );
        return $( frag ).children().first();
    }

    function setAltStateFromElement( $target, $sourceEl ) {
        var $clone = $sourceEl.clone( false );
        $clone.children( 'template.mfsd-hw-alt-state' ).remove();
        var tpl = document.createElement( 'template' );
        tpl.className = 'mfsd-hw-alt-state';
        tpl.content.appendChild( $clone.get( 0 ) );
        $target.append( tpl );
    }

    function refreshEmptyMessage( $list, itemSelector, messageSelector ) {
        var isEmpty = $list.children( itemSelector ).length === 0;
        $list.siblings( messageSelector ).toggle( isEmpty );
    }

    // Drag start/end on Available Widgets items.
    $( document ).on( 'dragstart', '.mfsd-hw-available-item', function( e ) {
        e.originalEvent.dataTransfer.setData( 'text/plain', String( $( this ).data( 'widget-id' ) ) );
        e.originalEvent.dataTransfer.effectAllowed = 'move';
        $( this ).addClass( 'is-dragging' );
    } );

    $( document ).on( 'dragend', '.mfsd-hw-available-item', function() {
        $( this ).removeClass( 'is-dragging' );
    } );

    // Drop target: the active widget list for the role being viewed.
    $( document ).on( 'dragover', '.mfsd-hw-role-widget-list', function( e ) {
        e.preventDefault();
        if ( e.originalEvent.dataTransfer ) e.originalEvent.dataTransfer.dropEffect = 'move';
        $( this ).addClass( 'is-drop-target' );
    } );

    $( document ).on( 'dragleave', '.mfsd-hw-role-widget-list', function() {
        $( this ).removeClass( 'is-drop-target' );
    } );

    $( document ).on( 'drop', '.mfsd-hw-role-widget-list', function( e ) {
        e.preventDefault();
        var $list = $( this ).removeClass( 'is-drop-target' );
        if ( ! window.mfsdHwAdmin ) return;

        var widgetId = e.originalEvent.dataTransfer.getData( 'text/plain' );
        var role     = $list.data( 'role' );
        var $item    = $( '.mfsd-hw-available-item[data-widget-id="' + widgetId + '"]' );
        if ( ! $item.length ) return;

        var $newRow = getAltStateClone( $item );
        if ( ! $newRow ) return;
        setAltStateFromElement( $newRow, $item );

        $list.append( $newRow );
        renumberRoleWidgetList( $list );
        refreshEmptyMessage( $list, '.mfsd-hw-role-widget-row', '.mfsd-hw-role-empty-message' );

        var $availableList = $item.closest( '.mfsd-hw-available-list' );
        $item.remove();
        refreshEmptyMessage( $availableList, '.mfsd-hw-available-item', '.mfsd-hw-available-empty-message' );

        $.post( mfsdHwAdmin.ajaxUrl, {
            action:    'mfsd_hw_ajax_assign_role',
            nonce:     mfsdHwAdmin.roleAssignNonce,
            role:      role,
            widget_id: widgetId
        } ).fail( function() {
            window.location.reload();
        } );
    } );

    // Remove button: moves a widget out of the active list back into Available.
    $( document ).on( 'click', '.mfsd-hw-role-remove-btn', function( e ) {
        e.preventDefault();
        if ( ! window.mfsdHwAdmin ) return;

        var $row  = $( this ).closest( '.mfsd-hw-role-widget-row' );
        var $list = $( this ).closest( '.mfsd-hw-role-widget-list' );
        var widgetId = $row.data( 'widget-id' );
        var role     = $list.data( 'role' );

        var $newItem = getAltStateClone( $row );
        if ( ! $newItem ) return;
        setAltStateFromElement( $newItem, $row );

        var $availableList = $( '.mfsd-hw-available-list' );
        $availableList.append( $newItem );
        refreshEmptyMessage( $availableList, '.mfsd-hw-available-item', '.mfsd-hw-available-empty-message' );

        $row.remove();
        renumberRoleWidgetList( $list );
        refreshEmptyMessage( $list, '.mfsd-hw-role-widget-row', '.mfsd-hw-role-empty-message' );

        $.post( mfsdHwAdmin.ajaxUrl, {
            action:    'mfsd_hw_ajax_remove_role',
            nonce:     mfsdHwAdmin.roleAssignNonce,
            role:      role,
            widget_id: widgetId
        } ).fail( function() {
            window.location.reload();
        } );
    } );


    // ── Media Library picker (existing) ──────────────────────────────────────

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
            $btn.siblings( '.mfsd-hw-media-clear' ).show();
        } );
        mediaFrame.open();
    } );

    $( document ).on( 'click', '.mfsd-hw-media-clear', function( e ) {
        e.preventDefault();
        var $btn = $( this );
        $( '#' + $btn.data( 'target' ) ).val( '0' );
        $( '#' + $btn.data( 'preview' ) ).attr( 'src', '' ).hide();
        $btn.siblings( '.mfsd-hw-media-btn' ).text( 'Select Image' );
        $btn.hide();
    } );


    // ── Multi-item news: Add Article ─────────────────────────────────────────

    $( document ).on( 'click', '#mfsd-hw-add-item', function( e ) {
        e.preventDefault();
        var $container = $( '#mfsd-hw-items-container' );
        var count      = $container.children( '.mfsd-hw-admin__item-block' ).length;

        if ( count >= 10 ) {
            alert( 'Maximum of 10 articles reached.' );
            return;
        }

        var idx    = count;
        var prefix = 'config[items][' + idx + ']';
        var uid    = 'mfsd_hw_item_img_' + idx;
        var widgetType = $( this ).data( 'type' );

        var linkField;
        if ( widgetType === 'news_external' ) {
            linkField = '<div class="mfsd-hw-admin__field">' +
                '<label>External URL</label>' +
                '<input type="url" name="' + prefix + '[link]" value="" style="width:100%;max-width:560px;">' +
                '</div>';
        } else {
            // Clone the page dropdown from the first item and clear selection.
            var $firstPageSelect = $container.find( 'select[name*="[link]"]' ).first();
            if ( $firstPageSelect.length ) {
                var $cloned = $firstPageSelect.clone();
                $cloned.attr( 'name', prefix + '[link]' ).val( '' );
                linkField = '<div class="mfsd-hw-admin__field">' +
                    '<label>Link to Page</label>' +
                    $cloned.prop( 'outerHTML' ) +
                    '<p class="description">Select the page this widget card should link to.</p>' +
                    '</div>';
            } else {
                linkField = '<div class="mfsd-hw-admin__field">' +
                    '<label>Link to Page</label>' +
                    '<input type="text" name="' + prefix + '[link]" value="" style="width:100%;max-width:560px;">' +
                    '</div>';
            }
        }

        var html = '<div class="mfsd-hw-admin__item-block" data-item-index="' + idx + '">' +
            '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">' +
                '<strong style="font-size:14px;">Article ' + ( idx + 1 ) + '</strong>' +
                '<button type="button" class="button mfsd-hw-remove-item" style="color:#b32d2e;border-color:#b32d2e;">Remove</button>' +
            '</div>' +

            '<div class="mfsd-hw-admin__field">' +
                '<label>Headline</label>' +
                '<input type="text" name="' + prefix + '[headline]" value="" style="width:100%;max-width:560px;">' +
            '</div>' +

            '<div class="mfsd-hw-admin__field">' +
                '<label>Summary</label>' +
                '<textarea name="' + prefix + '[summary]" rows="3" style="width:100%;max-width:560px;font-size:13px;padding:6px;resize:vertical;"></textarea>' +
            '</div>' +

            '<div class="mfsd-hw-admin__field">' +
                '<label>Image</label>' +
                '<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">' +
                    '<img id="' + uid + '_preview" src="" style="height:60px;width:auto;max-width:120px;border:1px solid #ccd0d4;border-radius:3px;display:none;">' +
                    '<input type="hidden" name="' + prefix + '[image_id]" id="' + uid + '" value="0">' +
                    '<button type="button" class="button mfsd-hw-media-btn" data-target="' + uid + '" data-preview="' + uid + '_preview">Select Image</button>' +
                    '<button type="button" class="button mfsd-hw-media-clear" data-target="' + uid + '" data-preview="' + uid + '_preview" style="display:none;">Remove</button>' +
                '</div>' +
            '</div>' +

            linkField +

            '<div class="mfsd-hw-admin__field">' +
                '<label>Button Label</label>' +
                '<input type="text" name="' + prefix + '[cta_text]" value="Read More" style="width:100%;max-width:560px;">' +
            '</div>' +

            '<hr style="border:none;border-top:2px solid #C9A84C;margin:20px 0;">' +
        '</div>';

        $container.append( html );

        // Hide add button if at limit.
        if ( idx + 1 >= 10 ) {
            $( '#mfsd-hw-add-item' ).hide();
        }
    } );


    // ── Multi-item news: Remove Article ──────────────────────────────────────

    $( document ).on( 'click', '.mfsd-hw-remove-item', function( e ) {
        e.preventDefault();
        if ( ! confirm( 'Remove this article?' ) ) return;

        $( this ).closest( '.mfsd-hw-admin__item-block' ).remove();

        // Re-index all remaining items so there are no gaps.
        $( '#mfsd-hw-items-container .mfsd-hw-admin__item-block' ).each( function( i ) {
            var $block = $( this );
            $block.attr( 'data-item-index', i );
            $block.find( 'strong' ).first().text( 'Article ' + ( i + 1 ) );

            // Update all field names from items[old] to items[i].
            $block.find( '[name]' ).each( function() {
                var name = $( this ).attr( 'name' );
                name = name.replace( /config\[items\]\[\d+\]/, 'config[items][' + i + ']' );
                $( this ).attr( 'name', name );
            } );

            // Update image field IDs.
            var uid = 'mfsd_hw_item_img_' + i;
            $block.find( 'input[type="hidden"][id^="mfsd_hw_item_img_"]' ).attr( 'id', uid );
            $block.find( 'img[id$="_preview"]' ).attr( 'id', uid + '_preview' );
            $block.find( '.mfsd-hw-media-btn' ).attr( 'data-target', uid ).attr( 'data-preview', uid + '_preview' );
            $block.find( '.mfsd-hw-media-clear' ).attr( 'data-target', uid ).attr( 'data-preview', uid + '_preview' );

            // Hide remove button on first item.
            if ( i === 0 ) {
                $block.find( '.mfsd-hw-remove-item' ).hide();
            } else {
                $block.find( '.mfsd-hw-remove-item' ).show();
            }
        } );

        // Show add button again if under limit.
        var count = $( '#mfsd-hw-items-container .mfsd-hw-admin__item-block' ).length;
        if ( count < 10 ) {
            $( '#mfsd-hw-add-item' ).show();
        }
    } );

} )( jQuery );