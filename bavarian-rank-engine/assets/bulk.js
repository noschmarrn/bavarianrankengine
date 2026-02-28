/* global breBulk */
jQuery( function ( $ ) {
    var running    = false;
    var stopFlag   = false;
    var processed  = 0;
    var total      = 0;
    var failedItems = [];

    if ( breBulk.isLocked ) {
        showLockWarning( breBulk.lockAge );
    }

    loadStats();

    function showLockWarning( age ) {
        var msg = breBulk.i18n.lockWarning + ( age ? ' (' + breBulk.i18n.since + ' ' + age + 's)' : '' ) + '.';
        $( '#bre-lock-warning' ).text( msg ).show();
        $( '#bre-bulk-start' ).prop( 'disabled', true );
    }

    function hideLockWarning() {
        $( '#bre-lock-warning' ).hide();
        $( '#bre-bulk-start' ).prop( 'disabled', false );
    }

    function loadStats() {
        $.post( breBulk.ajaxUrl, { action: 'bre_bulk_stats', nonce: breBulk.nonce } )
            .done( function ( res ) {
                if ( ! res.success ) return;
                var html = '<strong>' + breBulk.i18n.postsWithoutMeta + '</strong><ul>';
                var t = 0;
                $.each( res.data, function ( pt, count ) {
                    html += '<li>' + $( '<span>' ).text( pt ).html() + ': <strong>' + parseInt( count, 10 ) + '</strong></li>';
                    t += parseInt( count, 10 );
                } );
                html += '</ul><strong>' + breBulk.i18n.total + ' ' + t + '</strong>';
                total = t;
                $( '#bre-bulk-stats' ).html( html );
                updateCostEstimate();
            } );
    }

    $( '#bre-bulk-limit, #bre-bulk-model, #bre-bulk-provider' ).on( 'change', updateCostEstimate );

    function updateCostEstimate() {
        var limit        = parseInt( $( '#bre-bulk-limit' ).val(), 10 ) || 20;
        var inputTokens  = limit * 800;
        var outputTokens = limit * 50;
        var costHtml     = '~' + inputTokens + ' ' + breBulk.i18n.inputTokens + ' + ' + outputTokens + ' ' + breBulk.i18n.outputTokens;

        var costData = breBulk.costs || {};
        var provider = $( '#bre-bulk-provider' ).val();
        var model    = $( '#bre-bulk-model' ).val();

        if ( costData[ provider ] && costData[ provider ][ model ] ) {
            var c      = costData[ provider ][ model ];
            var inCost = ( inputTokens  / 1000000 ) * parseFloat( c.input  || 0 );
            var outCost= ( outputTokens / 1000000 ) * parseFloat( c.output || 0 );
            var total  = inCost + outCost;
            if ( total > 0 ) {
                costHtml += ' ≈ $' + total.toFixed( 4 );
            }
        }
        $( '#bre-cost-estimate' ).text( costHtml );
    }

    $( '#bre-bulk-start' ).on( 'click', function () {
        if ( running ) return;
        $.post( breBulk.ajaxUrl, { action: 'bre_bulk_status', nonce: breBulk.nonce } )
            .done( function ( res ) {
                if ( res.success && res.data.locked ) {
                    showLockWarning( res.data.lock_age );
                    return;
                }
                startRun();
            } );
    } );

    function startRun() {
        running     = true;
        stopFlag    = false;
        processed   = 0;
        failedItems = [];

        $( '#bre-bulk-start' ).prop( 'disabled', true );
        $( '#bre-bulk-stop' ).show();
        $( '#bre-progress-wrap' ).show();
        $( '#bre-bulk-log' ).show().html( '' );
        $( '#bre-failed-summary' ).hide().html( '' );
        hideLockWarning();

        var limit    = parseInt( $( '#bre-bulk-limit' ).val(), 10 ) || 20;
        var provider = $( '#bre-bulk-provider' ).val();
        var model    = $( '#bre-bulk-model' ).val();

        log( breBulk.i18n.logStart.replace( '{limit}', limit ).replace( '{provider}', provider ) );
        runBatch( 'post', limit, provider, model, true );
    }

    $( '#bre-bulk-stop' ).on( 'click', function () {
        stopFlag = true;
        log( '⚠ ' + breBulk.i18n.stopRequested, 'warn' );
        releaseLock();
    } );

    function releaseLock() {
        $.post( breBulk.ajaxUrl, { action: 'bre_bulk_release', nonce: breBulk.nonce } );
    }

    function runBatch( postType, remaining, provider, model, isFirst ) {
        if ( stopFlag || remaining <= 0 ) {
            finish();
            return;
        }

        var batchSize = Math.min( 20, remaining );
        var isLast    = ( remaining - batchSize ) <= 0;

        log( breBulk.i18n.logProcess.replace( '{count}', batchSize ).replace( '{remaining}', remaining ) );

        $.post( breBulk.ajaxUrl, {
            action:     'bre_bulk_generate',
            nonce:      breBulk.nonce,
            post_type:  postType,
            batch_size: batchSize,
            provider:   provider,
            model:      model,
            is_first:   isFirst ? 1 : 0,
            is_last:    isLast  ? 1 : 0,
        } ).done( function ( res ) {
            if ( ! res.success ) {
                if ( res.data && res.data.locked ) {
                    showLockWarning( res.data.lock_age );
                    finish();
                    return;
                }
                log( '✗ Fehler: ' + $( '<span>' ).text( ( res.data && res.data.message ) || breBulk.i18n.unknownError ).html(), 'error' );
                finish();
                return;
            }

            $.each( res.data.results, function ( i, item ) {
                if ( item.success ) {
                    var note = item.attempts > 1 ? ' (' + breBulk.i18n.attempt + ' ' + item.attempts + ')' : '';
                    log(
                        '✓ [' + item.id + '] ' +
                        $( '<span>' ).text( item.title ).html() + note +
                        '<br><small style="color:#9cdcfe;">' +
                        $( '<span>' ).text( item.description ).html() +
                        '</small>'
                    );
                } else {
                    failedItems.push( item );
                    log(
                        '✗ [' + item.id + '] ' +
                        $( '<span>' ).text( item.title ).html() +
                        ' — ' + $( '<span>' ).text( item.error ).html(),
                        'error'
                    );
                }
                processed++;
            } );

            updateProgress( processed, total );

            var newRemaining = remaining - batchSize;
            if ( res.data.remaining > 0 && ! stopFlag && newRemaining > 0 ) {
                setTimeout( function () {
                    runBatch( postType, newRemaining, provider, model, false );
                }, breBulk.rateDelay );
            } else {
                if ( isLast || res.data.remaining === 0 ) releaseLock();
                finish();
            }
        } ).fail( function () {
            log( '✗ ' + breBulk.i18n.networkError, 'error' );
            releaseLock();
            finish();
        } );
    }

    function updateProgress( done, t ) {
        var pct = t > 0 ? Math.round( ( done / t ) * 100 ) : 100;
        $( '#bre-progress-bar' ).css( 'width', pct + '%' );
        $( '#bre-progress-text' ).text( done + ' / ' + t + ' ' + breBulk.i18n.processed );
    }

    /**
     * Append a line to the log console.
     * @param {string} msg  Pre-escaped HTML string. User data MUST be escaped via
     *                      $('<span>').text(val).html() before passing here.
     * @param {string} type 'error' | 'warn' | undefined
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
        log( breBulk.i18n.done );

        if ( failedItems.length > 0 ) {
            var html = '<strong>⚠ ' + failedItems.length + ' ' + breBulk.i18n.postsFailed + '</strong><ul>';
            $.each( failedItems, function ( i, item ) {
                html += '<li>[' + item.id + '] ' +
                    $( '<span>' ).text( item.title ).html() +
                    ': <em>' + $( '<span>' ).text( item.error ).html() + '</em></li>';
            } );
            html += '</ul>';
            $( '#bre-failed-summary' ).html( html ).show();
        }
        loadStats();
    }
} );
