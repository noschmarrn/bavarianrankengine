<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap bre-settings">
    <h1>Bavarian Rank Engine</h1>

    <?php settings_errors( 'bre' ); ?>

    <form method="post" action="options.php">
        <?php settings_fields( 'bre' ); ?>

        <h2>AI-Provider</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Aktiver Provider</th>
                <td>
                    <select name="bre_settings[provider]" id="bre-provider">
                        <?php foreach ( $providers as $id => $provider ) : ?>
                        <option value="<?php echo esc_attr( $id ); ?>"
                            <?php selected( $settings['provider'], $id ); ?>>
                            <?php echo esc_html( $provider->getName() ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <?php foreach ( $providers as $id => $provider ) : ?>
            <tr class="bre-provider-row" data-provider="<?php echo esc_attr( $id ); ?>">
                <th scope="row"><?php echo esc_html( $provider->getName() ); ?> API Key</th>
                <td>
                    <?php if ( ! empty( $masked_keys[ $id ] ) ) : ?>
                    <span class="bre-key-saved">
                        Gespeichert: <code><?php echo esc_html( $masked_keys[ $id ] ); ?></code>
                    </span><br>
                    <?php endif; ?>
                    <input type="password"
                           name="bre_settings[api_keys][<?php echo esc_attr( $id ); ?>]"
                           value=""
                           placeholder="<?php echo ! empty( $masked_keys[ $id ] ) ? esc_attr( 'Neuen Key eingeben zum Überschreiben' ) : esc_attr( 'API Key eingeben' ); ?>"
                           class="regular-text"
                           autocomplete="new-password">
                    <button type="button" class="button bre-test-btn" data-provider="<?php echo esc_attr( $id ); ?>">
                        Verbindung testen
                    </button>
                    <span class="bre-test-result" id="test-result-<?php echo esc_attr( $id ); ?>"></span>
                    <br><br>
                    <label><?php esc_html_e( 'Modell:', 'bavarian-rank-engine' ); ?></label>
                    <select name="bre_settings[models][<?php echo esc_attr( $id ); ?>]">
                        <?php
                        $saved_model = $settings['models'][ $id ] ?? array_key_first( $provider->getModels() );
                        foreach ( $provider->getModels() as $model_id => $model_label ) :
                        ?>
                        <option value="<?php echo esc_attr( $model_id ); ?>"
                            <?php selected( $saved_model, $model_id ); ?>>
                            <?php echo esc_html( $model_label ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <h2>Meta-Generator</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Auto-Modus</th>
                <td>
                    <label>
                        <input type="checkbox"
                               name="bre_settings[meta_auto_enabled]"
                               value="1"
                               <?php checked( $settings['meta_auto_enabled'], true ); ?>>
                        Meta-Beschreibung automatisch beim Veröffentlichen generieren
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">Post-Types</th>
                <td>
                    <?php foreach ( $post_types as $pt_slug => $pt_obj ) : ?>
                    <label style="margin-right:15px;">
                        <input type="checkbox"
                               name="bre_settings[meta_post_types][]"
                               value="<?php echo esc_attr( $pt_slug ); ?>"
                               <?php checked( in_array( $pt_slug, $settings['meta_post_types'], true ), true ); ?>>
                        <?php echo esc_html( $pt_obj->labels->singular_name ); ?>
                    </label>
                    <?php endforeach; ?>
                </td>
            </tr>
            <tr>
                <th scope="row">Token-Modus</th>
                <td>
                    <label>
                        <input type="radio" name="bre_settings[token_mode]" value="full"
                               <?php checked( $settings['token_mode'], 'full' ); ?>>
                        Ganzen Artikel senden
                    </label>
                    &nbsp;&nbsp;
                    <label>
                        <input type="radio" name="bre_settings[token_mode]" value="limit"
                               <?php checked( $settings['token_mode'], 'limit' ); ?>>
                        Auf
                        <input type="number"
                               name="bre_settings[token_limit]"
                               value="<?php echo esc_attr( $settings['token_limit'] ); ?>"
                               min="100" max="8000" style="width:80px;">
                        Token kürzen
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">Prompt</th>
                <td>
                    <textarea name="bre_settings[prompt]"
                              rows="8"
                              class="large-text code"><?php echo esc_textarea( $settings['prompt'] ); ?></textarea>
                    <p class="description">
                        Variablen: <code>{title}</code>, <code>{content}</code>, <code>{excerpt}</code>, <code>{language}</code><br>
                        <button type="button" class="button" id="bre-reset-prompt">Prompt zurücksetzen</button>
                    </p>
                </td>
            </tr>
        </table>

        <h2>Schema.org Enhancer (GEO)</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Aktivierte Schema-Typen</th>
                <td>
                    <?php foreach ( $schema_labels as $type => $label ) : ?>
                    <label style="display:block;margin-bottom:8px;">
                        <input type="checkbox"
                               name="bre_settings[schema_enabled][]"
                               value="<?php echo esc_attr( $type ); ?>"
                               <?php checked( in_array( $type, $settings['schema_enabled'], true ), true ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </label>
                    <?php endforeach; ?>
                </td>
            </tr>
            <tr>
                <th scope="row">Organization sameAs URLs</th>
                <td>
                    <p class="description">Eine URL pro Zeile (Twitter, LinkedIn, GitHub, Facebook…)</p>
                    <textarea name="bre_settings[schema_same_as][organization]"
                              rows="5"
                              class="large-text"><?php echo esc_textarea( implode( "\n", $settings['schema_same_as']['organization'] ?? [] ) ); ?></textarea>
                </td>
            </tr>
        </table>

        <?php submit_button( 'Einstellungen speichern' ); ?>
    </form>

    <hr>
    <p style="color:#999;font-size:12px;">
        Bavarian Rank Engine <?php echo esc_html( BRE_VERSION ); ?> &mdash;
        entwickelt mit ♥ von <a href="https://donau2space.de" target="_blank" rel="noopener">Donau2Space.de</a>
    </p>
</div>
