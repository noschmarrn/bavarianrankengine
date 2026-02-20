<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap seo-geo-settings">
    <h1>GEO Bulk Meta-Generator</h1>
    <p>Generiert Meta-Beschreibungen für Artikel ohne vorhandene Meta-Beschreibung.</p>

    <div id="seo-geo-bulk-stats" style="background:#fff;padding:15px;border:1px solid #ddd;margin-bottom:20px;">
        <em>Lade Statistiken…</em>
    </div>

    <table class="form-table">
        <tr>
            <th scope="row">Provider</th>
            <td>
                <select id="seo-geo-bulk-provider">
                    <?php foreach ( $providers as $id => $provider ) : ?>
                    <option value="<?php echo esc_attr( $id ); ?>"
                        <?php selected( $settings['provider'], $id ); ?>>
                        <?php echo esc_html( $provider->getName() ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row">Modell</th>
            <td>
                <select id="seo-geo-bulk-model">
                    <?php
                    $active_provider = $registry->get( $settings['provider'] );
                    if ( $active_provider ) :
                        $saved_model = $settings['models'][ $settings['provider'] ] ?? array_key_first( $active_provider->getModels() );
                        foreach ( $active_provider->getModels() as $mid => $mlabel ) :
                    ?>
                    <option value="<?php echo esc_attr( $mid ); ?>"
                        <?php selected( $saved_model, $mid ); ?>>
                        <?php echo esc_html( $mlabel ); ?>
                    </option>
                    <?php endforeach; endif; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row">Max. Artikel diesen Run</th>
            <td>
                <input type="number" id="seo-geo-bulk-limit" value="20" min="1" max="500">
                <p class="description" id="seo-geo-cost-estimate"></p>
            </td>
        </tr>
    </table>

    <p>
        <button id="seo-geo-bulk-start" class="button button-primary">Bulk-Run starten</button>
        <button id="seo-geo-bulk-stop" class="button" style="display:none;">Abbrechen</button>
    </p>

    <div id="seo-geo-progress-wrap" style="display:none;margin:15px 0;">
        <div style="background:#ddd;border-radius:3px;height:20px;width:100%;">
            <div id="seo-geo-progress-bar"
                 style="background:#0073aa;height:20px;border-radius:3px;width:0;transition:width .3s;"></div>
        </div>
        <p id="seo-geo-progress-text">0 / 0 verarbeitet</p>
    </div>

    <div id="seo-geo-bulk-log"
         style="background:#1e1e1e;color:#d4d4d4;padding:15px;font-family:monospace;font-size:12px;max-height:400px;overflow-y:auto;display:none;"></div>
</div>
