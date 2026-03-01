/* global jQuery, wp, breLinkSuggest, tinyMCE */
( function ( $ ) {
    'use strict';

    if ( typeof breLinkSuggest === 'undefined' ) { return; }

    var cfg         = breLinkSuggest;
    var i18n        = cfg.i18n;
    var suggestions = [];
    var isRunning   = false;

    /* ── Helpers ─────────────────────────────────────────── */

    function getContent() {
        if ( window.wp && wp.data && wp.data.select( 'core/editor' ) ) {
            try {
                return wp.data.select( 'core/editor' ).getEditedPostContent();
            } catch ( e ) { /* fall through */ }
        }
        if ( typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && ! tinyMCE.activeEditor.isHidden() ) {
            return tinyMCE.activeEditor.getContent();
        }
        return $( '#content' ).val() || '';
    }

    /* ── Core: request suggestions ───────────────────────── */

    function triggerAnalysis() {
        if ( isRunning ) { return; }
        var content = getContent();
        if ( ! content ) { return; }

        isRunning = true;
        $( '#bre-ls-status' ).text( i18n.loading );
        $( '#bre-ls-results' ).hide();
        $( '#bre-ls-applied' ).hide();

        $.post( cfg.ajaxUrl, {
            action:       'bre_link_suggestions',
            nonce:        cfg.nonce,
            post_id:      cfg.postId,
            post_content: content,
        } )
        .done( function ( res ) {
            if ( res && res.success ) {
                suggestions = res.data || [];
                renderSuggestions();
            } else {
                $( '#bre-ls-status' ).text( i18n.networkError );
            }
        } )
        .fail( function () {
            $( '#bre-ls-status' ).text( i18n.networkError );
        } )
        .always( function () {
            isRunning = false;
        } );
    }

    /* ── Render suggestion list ──────────────────────────── */

    function renderSuggestions() {
        var $list    = $( '#bre-ls-list' ).empty();
        var $results = $( '#bre-ls-results' );
        var $actions = $( '#bre-ls-actions' );
        var $status  = $( '#bre-ls-status' );

        if ( ! suggestions.length ) {
            $status.text( i18n.noResults );
            $results.hide();
            return;
        }

        $status.text( '' );

        suggestions.forEach( function ( s, idx ) {
            var $row  = $( '<div class="bre-ls-row" style="display:flex;align-items:flex-start;gap:8px;padding:6px 0;border-bottom:1px solid #f0f0f0;">' );
            var $cb   = $( '<input type="checkbox" class="bre-ls-cb">' ).data( 'idx', idx );
            var $info = $( '<div style="flex:1;font-size:12px;">' );
            var badge = s.boosted ? ' <span style="color:#f0a500;font-size:10px;" title="' + esc( i18n.boosted ) + '">&#9733;</span>' : '';
            $info.html(
                '<strong>' + esc( '\u201c' + s.phrase + '\u201d' ) + '</strong>' + badge +
                '<br><span style="color:#555;">\u2192 ' + esc( s.post_title ) + '</span>'
            );
            var $open = $( '<a href="' + esc( s.url ) + '" target="_blank" rel="noopener" style="font-size:11px;white-space:nowrap;" title="' + esc( i18n.openPost ) + '">[&#8599;]</a>' );
            $row.append( $cb, $info, $open );
            $list.append( $row );
        } );

        $results.show();
        $actions.css( 'display', 'flex' );
        updateApplyButton();
    }

    function esc( str ) {
        return $( '<div>' ).text( str ).html();
    }

    function updateApplyButton() {
        var count = $( '.bre-ls-cb:checked' ).length;
        var label = i18n.applyBtn.replace( '%d', count );
        $( '#bre-ls-apply' ).text( label ).prop( 'disabled', count === 0 );
    }

    /* ── Apply selected ──────────────────────────────────── */

    function applySelected() {
        var selected = [];
        $( '.bre-ls-cb:checked' ).each( function () {
            var idx = $( this ).data( 'idx' );
            if ( suggestions[ idx ] ) {
                selected.push( suggestions[ idx ] );
            }
        } );
        if ( ! selected.length ) { return; }

        // Build preview
        var lines = selected.map( function ( s ) {
            return '\u201c' + s.phrase + '\u201d  \u2192  ' + s.post_title;
        } ).join( '\n' );

        // eslint-disable-next-line no-alert
        if ( ! window.confirm( i18n.preview + ':\n\n' + lines + '\n\n' + i18n.confirm + '?' ) ) {
            return;
        }

        var content = getContent();
        var applied = 0;
        selected.forEach( function ( s ) {
            var result = insertLink( content, s.phrase, s.url );
            if ( result !== content ) {
                content = result;
                applied++;
            }
        } );

        if ( applied ) {
            setContent( content );
            $( '#bre-ls-results' ).hide();
            $( '#bre-ls-applied' ).text( i18n.applied.replace( '%d', applied ) ).show();
            suggestions = [];
        }
    }

    /**
     * Replace first occurrence of phrase (outside <a>) with a link.
     */
    function insertLink( html, phrase, url ) {
        var escaped  = phrase.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
        var re       = new RegExp( '(?<!["\'>])(' + escaped + ')(?![^<]*</a>)', 'i' );
        var replaced = false;
        return html.replace( re, function ( match ) {
            if ( replaced ) { return match; }
            replaced = true;
            return '<a href="' + url + '">' + match + '</a>';
        } );
    }

    function setContent( html ) {
        // Gutenberg
        if ( window.wp && wp.data && wp.data.dispatch ) {
            try {
                var blocks = wp.blocks.parse( html );
                wp.data.dispatch( 'core/block-editor' ).resetBlocks( blocks );
                return;
            } catch ( e ) { /* fall through to classic */ }
        }
        // Classic / TinyMCE
        if ( typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && ! tinyMCE.activeEditor.isHidden() ) {
            tinyMCE.activeEditor.setContent( html );
            return;
        }
        $( '#content' ).val( html );
    }

    /* ── Event bindings ──────────────────────────────────── */

    $( document ).on( 'click', '#bre-ls-analyse',     triggerAnalysis );
    $( document ).on( 'click', '#bre-ls-apply',       applySelected );
    $( document ).on( 'click', '#bre-ls-select-all',  function () { $( '.bre-ls-cb' ).prop( 'checked', true );  updateApplyButton(); } );
    $( document ).on( 'click', '#bre-ls-select-none', function () { $( '.bre-ls-cb' ).prop( 'checked', false ); updateApplyButton(); } );
    $( document ).on( 'change', '.bre-ls-cb',         updateApplyButton );

    /* ── Trigger mode ────────────────────────────────────── */

    if ( cfg.triggerMode === 'interval' && cfg.intervalMs > 0 ) {
        setInterval( triggerAnalysis, cfg.intervalMs );
    }

    if ( cfg.triggerMode === 'save' ) {
        // Gutenberg
        if ( window.wp && wp.data ) {
            var wasSaving = false;
            wp.data.subscribe( function () {
                var isSaving = wp.data.select( 'core/editor' ) &&
                               wp.data.select( 'core/editor' ).isSavingPost();
                if ( ! wasSaving && isSaving ) {
                    triggerAnalysis();
                }
                wasSaving = isSaving;
            } );
        }
        // Classic
        $( document ).on( 'click', '#publish, #save-post', function () {
            setTimeout( triggerAnalysis, 500 );
        } );
    }

    /* ── Settings page: post search for exclude/boost ────── */

    function initPostSearch( $input, $results, onSelect ) {
        var timer;
        $input.on( 'input', function () {
            clearTimeout( timer );
            var q = $input.val().trim();
            if ( q.length < 2 ) { $results.hide(); return; }
            timer = setTimeout( function () {
                $.ajax( {
                    url:     cfg.restUrl,
                    data:    { search: q, type: 'post', subtype: 'any', per_page: 10, _fields: 'id,title,url' },
                    headers: { 'X-WP-Nonce': cfg.restNonce },
                } ).done( function ( items ) {
                    $results.empty().show();
                    if ( ! items.length ) {
                        $results.append( '<div style="padding:6px;">No results</div>' );
                        return;
                    }
                    items.forEach( function ( item ) {
                        $( '<div style="padding:6px;cursor:pointer;" class="bre-ls-result-item">' )
                            .text( item.title.rendered || item.title )
                            .data( 'item', item )
                            .on( 'click', function () {
                                onSelect( item );
                                $results.hide();
                                $input.val( '' );
                            } )
                            .appendTo( $results );
                    } );
                } );
            }, 300 );
        } );
        $( document ).on( 'click', function ( e ) {
            if ( ! $input.is( e.target ) ) { $results.hide(); }
        } );
    }

    // Exclude search
    if ( $( '#bre-ls-exclude-search' ).length ) {
        initPostSearch(
            $( '#bre-ls-exclude-search' ),
            $( '#bre-ls-exclude-results' ),
            function ( item ) {
                var id        = item.id;
                var title     = item.title.rendered || item.title;
                var fieldName = 'bre_link_suggest_settings[excluded_posts][]';
                $( '#bre-ls-excluded-list' ).append(
                    $( '<span class="bre-ls-tag" style="display:inline-flex;align-items:center;gap:4px;background:#e0e0e0;padding:2px 8px;border-radius:3px;margin:2px;">' )
                        .data( 'id', id )
                        .append(
                            $( '<span>' ).text( title ),
                            $( '<input type="hidden">' ).attr( 'name', fieldName ).val( id ),
                            $( '<button type="button" class="bre-ls-remove" style="background:none;border:none;cursor:pointer;color:#555;">✕</button>' )
                        )
                );
            }
        );
    }

    // Boost search
    if ( $( '#bre-ls-boost-search' ).length ) {
        initPostSearch(
            $( '#bre-ls-boost-search' ),
            $( '#bre-ls-boost-results' ),
            function ( item ) {
                var id    = item.id;
                var title = item.title.rendered || item.title;
                var idx   = $( '.bre-ls-boost-row' ).length;
                var base  = 'bre_link_suggest_settings[boosted_posts][' + idx + ']';
                $( '#bre-ls-boosted-list' ).append(
                    $( '<div class="bre-ls-boost-row" style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">' ).append(
                        $( '<span>' ).text( '\u2605 ' + title ),
                        $( '<input type="hidden">' ).attr( 'name', base + '[id]' ).val( id ),
                        $( '<label>' ).append(
                            'Boost: ',
                            $( '<input type="number" step="0.1" min="1" max="10" style="width:60px;">' )
                                .attr( 'name', base + '[boost]' ).val( '1.5' )
                        ),
                        $( '<button type="button" class="button bre-ls-remove">Remove</button>' )
                    )
                );
            }
        );
    }

    // Remove tag / boost row
    $( document ).on( 'click', '.bre-ls-remove', function () {
        $( this ).closest( '.bre-ls-tag, .bre-ls-boost-row' ).remove();
    } );

} )( jQuery );
