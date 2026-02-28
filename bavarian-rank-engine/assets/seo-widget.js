/* global jQuery, wp, breWidget */
jQuery( function ( $ ) {
    var $widget = $( '#bre-seo-widget' );
    if ( ! $widget.length ) return;

    var siteUrl    = $widget.data( 'site-url' ) || window.location.origin;
    var themeHasH1 = breWidget && breWidget.themeHasH1;
    var locale     = ( breWidget && breWidget.locale ) ? breWidget.locale : navigator.language;
    var debounce   = null;

    function getContent() {
        // Block editor
        if ( window.wp && wp.data && wp.data.select( 'core/editor' ) ) {
            try {
                var blocks = wp.data.select( 'core/editor' ).getBlocks();
                return blocks.map( function ( b ) {
                    return ( b.attributes && b.attributes.content ) ? b.attributes.content : '';
                } ).join( ' ' );
            } catch ( e ) { return ''; }
        }
        // Classic editor (TinyMCE or textarea)
        if ( typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && ! tinyMCE.activeEditor.isHidden() ) {
            return tinyMCE.activeEditor.getContent();
        }
        return $( '#content' ).val() || '';
    }

    function getTitle() {
        if ( window.wp && wp.data && wp.data.select( 'core/editor' ) ) {
            try {
                return wp.data.select( 'core/editor' ).getEditedPostAttribute( 'title' ) || '';
            } catch ( e ) { return ''; }
        }
        return $( '#title' ).val() || '';
    }

    function analyse() {
        var content  = getContent();
        var title    = getTitle();
        var plain    = content.replace( /<[^>]+>/g, ' ' ).replace( /\s+/g, ' ' ).trim();
        var words    = plain ? plain.split( /\s+/ ).length : 0;
        var readMin  = Math.max( 1, Math.ceil( words / 200 ) );
        var minLabel = ( breWidget && breWidget.minLabel ) ? breWidget.minLabel : 'min';

        $( '#bre-title-stat' ).text( title.length + ' / 60' );
        $( '#bre-words-stat' ).text( words.toLocaleString( locale ) );
        $( '#bre-read-stat'  ).text( '~' + readMin + ' ' + minLabel );

        // Headings — count from HTML tags
        var h = { h1: 0, h2: 0, h3: 0, h4: 0 };
        ( content.match( /<h([1-4])[\s>]/gi ) || [] ).forEach( function ( tag ) {
            var level = 'h' + tag.replace( /<h/i, '' )[0];
            if ( h[ level ] !== undefined ) h[ level ]++;
        } );

        var hParts = [];
        [ 'h1', 'h2', 'h3', 'h4' ].forEach( function ( tag ) {
            if ( h[ tag ] > 0 ) hParts.push( h[ tag ] + '\u00D7 ' + tag.toUpperCase() );
        } );
        var noneLabel = ( breWidget && breWidget.none ) ? breWidget.none : 'None';
        $( '#bre-headings-stat' ).text( hParts.length ? hParts.join( '  ' ) : noneLabel );

        // Links
        var allLinks  = content.match( /href="([^"]+)"/gi ) || [];
        var siteHost  = siteUrl.replace( /https?:\/\//, '' ).replace( /\/$/, '' );
        var internal  = 0;
        var external  = 0;

        allLinks.forEach( function ( tag ) {
            var href = ( tag.match( /href="([^"]+)"/ ) || [] )[1] || '';
            if ( href.indexOf( '/' ) === 0 || href.indexOf( siteUrl ) === 0 || href.indexOf( siteHost ) !== -1 ) {
                internal++;
            } else if ( /^https?:\/\//.test( href ) ) {
                external++;
            }
        } );

        var intLabel = ( breWidget && breWidget.internal ) ? breWidget.internal : 'internal';
        var extLabel = ( breWidget && breWidget.external ) ? breWidget.external : 'external';
        $( '#bre-links-stat' ).text( internal + ' ' + intLabel + '  ' + external + ' ' + extLabel );

        // Warnings
        var warnings = [];
        var noH1Label        = ( breWidget && breWidget.noH1 )        ? breWidget.noH1        : 'No H1 heading';
        var multiH1Label     = ( breWidget && breWidget.multipleH1 )   ? breWidget.multipleH1  : 'Multiple H1 headings';
        var noLinksLabel     = ( breWidget && breWidget.noInternalLinks ) ? breWidget.noInternalLinks : 'No internal links';

        if ( h.h1 === 0 && ! themeHasH1 ) warnings.push( '\u26A0 ' + noH1Label );
        if ( h.h1 > 1  ) warnings.push( '\u26A0 ' + multiH1Label + ' (' + h.h1 + ')' );
        if ( internal === 0 && words > 50 ) warnings.push( '\u26A0 ' + noLinksLabel );
        $( '#bre-seo-warnings' ).html( warnings.join( '<br>' ) );
    }

    function scheduledAnalyse() {
        clearTimeout( debounce );
        debounce = setTimeout( analyse, 500 );
    }

    // Block editor
    if ( window.wp && wp.data ) {
        wp.data.subscribe( scheduledAnalyse );
    }

    // Classic editor
    $( document ).on( 'input change', '#content', scheduledAnalyse );
    $( document ).on( 'tinymce-editor-init', function ( event, editor ) {
        editor.on( 'KeyUp Change SetContent', scheduledAnalyse );
    } );
    $( '#title' ).on( 'input', scheduledAnalyse );

    analyse();
} );
