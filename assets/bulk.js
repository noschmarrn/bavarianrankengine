/* global breBulk */
jQuery( function ( $ ) {
    var running   = false;
    var stopFlag  = false;
    var processed = 0;
    var total     = 0;

    loadStats();

    function loadStats() {
        $.post( breBulk.ajaxUrl, {
            action: 'bre_bulk_stats',
            nonce:  breBulk.nonce,
        } ).done( function ( res ) {
            if ( ! res.success ) return;
            var html = '<strong>Posts ohne Meta-Beschreibung:</strong><ul>';
            var t    = 0;
            $.each( res.data, function ( pt, count ) {
                html += '<li>' + $( '<span>' ).text( pt ).html() + ': <strong>' + parseInt( count, 10 ) + '</strong></li>';
                t    += parseInt( count, 10 );
            } );
            html += '</ul><strong>Gesamt: ' + t + '</strong>';
            total = t;
            $( '#bre-bulk-stats' ).html( html );
            updateCostEstimate();
        } );
    }

    $( '#bre-bulk-limit, #bre-bulk-model' ).on( 'change', updateCostEstimate );

    function updateCostEstimate() {
        var limit        = parseInt( $( '#bre-bulk-limit' ).val(), 10 ) || 20;
        var inputTokens  = limit * 800;
        var outputTokens = limit * 50;
        $( '#bre-cost-estimate' ).text(
            'Grobe Kostenschätzung: ~' + inputTokens + ' Input-Token + ' + outputTokens + ' Output-Token'
        );
    }

    $( '#bre-bulk-start' ).on( 'click', function () {
        if ( running ) return;
        running  = true;
        stopFlag = false;
        processed = 0;

        $( this ).prop( 'disabled', true );
        $( '#bre-bulk-stop' ).show();
        $( '#bre-progress-wrap' ).show();
        $( '#bre-bulk-log' ).show().html( '' );

        var limit    = parseInt( $( '#bre-bulk-limit' ).val(), 10 ) || 20;
        var provider = $( '#bre-bulk-provider' ).val();
        var model    = $( '#bre-bulk-model' ).val();

        runBatch( 'post', limit, provider, model );
    } );

    $( '#bre-bulk-stop' ).on( 'click', function () {
        stopFlag = true;
        log( '\u26a0 Abbruch angefordert\u2026', 'warn' );
    } );

    function runBatch( postType, remaining, provider, model ) {
        if ( stopFlag || remaining <= 0 ) {
            finish();
            return;
        }

        var batchSize = Math.min( 5, remaining );

        $.post( breBulk.ajaxUrl, {
            action:     'bre_bulk_generate',
            nonce:      breBulk.nonce,
            post_type:  postType,
            batch_size: batchSize,
            provider:   provider,
            model:      model,
        } ).done( function ( res ) {
            if ( ! res.success ) {
                log( '\u2717 Fehler: ' + $( '<span>' ).text( res.data ).html(), 'error' );
                finish();
                return;
            }

            $.each( res.data.results, function ( i, item ) {
                if ( item.success ) {
                    log(
                        '\u2713 [' + item.id + '] ' +
                        $( '<span>' ).text( item.title ).html() +
                        '<br><small style="color:#9cdcfe;">' +
                        $( '<span>' ).text( item.description ).html() +
                        '</small>'
                    );
                } else {
                    log(
                        '\u2717 [' + item.id + '] ' +
                        $( '<span>' ).text( item.title ).html() +
                        ' \u2014 ' +
                        $( '<span>' ).text( item.error ).html(),
                        'error'
                    );
                }
                processed++;
            } );

            updateProgress( processed, total );

            if ( res.data.remaining > 0 && ! stopFlag ) {
                runBatch( postType, remaining - batchSize, provider, model );
            } else {
                finish();
            }
        } ).fail( function () {
            log( '\u2717 Netzwerkfehler', 'error' );
            finish();
        } );
    }

    function updateProgress( done, t ) {
        var pct = t > 0 ? Math.round( ( done / t ) * 100 ) : 100;
        $( '#bre-progress-bar' ).css( 'width', pct + '%' );
        $( '#bre-progress-text' ).text( done + ' / ' + t + ' verarbeitet' );
    }

    /**
     * Append a line to the log console.
     * @param {string} msg  Pre-escaped HTML string. User data MUST be escaped via
     *                      $('<span>').text(val).html() before passing here.
     * @param {string} type 'error' | 'warn' | undefined (info)
     */
    function log( msg, type ) {
        var color = type === 'error' ? '#f48771' : type === 'warn' ? '#dcdcaa' : '#9cdcfe';
        $( '#bre-bulk-log' ).append(
            '<div style="color:' + color + ';margin-bottom:4px;">' + msg + '</div>'
        );
        var el = document.getElementById( 'bre-bulk-log' );
        el.scrollTop = el.scrollHeight;
    }

    function finish() {
        running = false;
        $( '#bre-bulk-start' ).prop( 'disabled', false );
        $( '#bre-bulk-stop' ).hide();
        log( '\u2014 Fertig \u2014' );
        loadStats();
    }
} );
