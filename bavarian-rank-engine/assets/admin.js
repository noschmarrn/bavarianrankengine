/* global breAdmin */
jQuery( function ( $ ) {
    function updateProviderRows() {
        var active = $( '#bre-provider' ).val();
        $( '.bre-provider-row' ).removeClass( 'active' );
        $( '.bre-provider-row[data-provider="' + active + '"]' ).addClass( 'active' );
    }
    updateProviderRows();
    $( '#bre-provider' ).on( 'change', updateProviderRows );

    $( document ).on( 'click', '.bre-test-btn', function () {
        var btn        = $( this );
        var providerId = btn.data( 'provider' );
        var resultEl   = $( '#test-result-' + providerId );

        resultEl.removeClass( 'success error' ).text( breAdmin.testing );
        btn.prop( 'disabled', true );

        $.post( breAdmin.ajaxUrl, {
            action:   'bre_test_connection',
            nonce:    breAdmin.nonce,
            provider: providerId,
            // api_key removed — server reads stored encrypted key
        } ).done( function ( res ) {
            if ( res.success ) {
                resultEl.addClass( 'success' ).text( '\u2713 ' + res.data );
            } else {
                resultEl.addClass( 'error' ).text( '\u2717 ' + res.data );
            }
        } ).fail( function () {
            resultEl.addClass( 'error' ).text( '\u2717 ' + breAdmin.networkError );
        } ).always( function () {
            btn.prop( 'disabled', false );
        } );
    } );

    $( '#bre-reset-prompt' ).on( 'click', function () {
        if ( ! confirm( breAdmin.resetConfirm ) ) return;
        $.post( breAdmin.ajaxUrl, {
            action: 'bre_get_default_prompt',
            nonce:  breAdmin.nonce,
        } ).done( function ( res ) {
            if ( res.success ) {
                $( 'textarea[name*="prompt"]' ).val( res.data );
            }
        } );
    } );

    $( '#bre-dismiss-welcome' ).on( 'click', function () {
        $( '#bre-welcome-notice' ).slideUp( 200 );
        $.post( breAdmin.ajaxUrl, {
            action: 'bre_dismiss_welcome',
            nonce:  breAdmin.nonce,
        } );
    } );
    function bre_update_ai_fields() {
        if ( $( '#bre-ai-enabled' ).is( ':checked' ) ) {
            $( '#bre-ai-fields' ).show();
        } else {
            $( '#bre-ai-fields' ).hide();
        }
    }
    if ( $( '#bre-ai-enabled' ).length ) {
        bre_update_ai_fields();
        $( '#bre-ai-enabled' ).on( 'change', bre_update_ai_fields );
    }
} );
