/* global seoGeo */
jQuery( function ( $ ) {
    function updateProviderRows() {
        var active = $( '#seo-geo-provider' ).val();
        $( '.seo-geo-provider-row' ).removeClass( 'active' );
        $( '.seo-geo-provider-row[data-provider="' + active + '"]' ).addClass( 'active' );
    }
    updateProviderRows();
    $( '#seo-geo-provider' ).on( 'change', updateProviderRows );

    $( document ).on( 'click', '.seo-geo-test-btn', function () {
        var btn        = $( this );
        var providerId = btn.data( 'provider' );
        var resultEl   = $( '#test-result-' + providerId );

        resultEl.removeClass( 'success error' ).text( 'Teste\u2026' );
        btn.prop( 'disabled', true );

        $.post( seoGeo.ajaxUrl, {
            action:   'bre_test_connection',
            nonce:    seoGeo.nonce,
            provider: providerId,
            // api_key removed — server reads stored encrypted key
        } ).done( function ( res ) {
            if ( res.success ) {
                resultEl.addClass( 'success' ).text( '\u2713 ' + res.data );
            } else {
                resultEl.addClass( 'error' ).text( '\u2717 ' + res.data );
            }
        } ).fail( function () {
            resultEl.addClass( 'error' ).text( '\u2717 Netzwerkfehler' );
        } ).always( function () {
            btn.prop( 'disabled', false );
        } );
    } );

    $( '#seo-geo-reset-prompt' ).on( 'click', function () {
        if ( ! confirm( 'Prompt wirklich zur\u00fccksetzen?' ) ) return;
        $.post( seoGeo.ajaxUrl, {
            action: 'bre_get_default_prompt',
            nonce:  seoGeo.nonce,
        } ).done( function ( res ) {
            if ( res.success ) {
                $( 'textarea[name*="prompt"]' ).val( res.data );
            }
        } );
    } );
} );
