# BRE State

## Aktuelle Version: 1.2.3

## Zuletzt released: 2026-02-28

## Was zuletzt gemacht wurde
- i18n-Fix: alle hardcoded deutschen Strings in bulk.js über wp_localize_script (breBulk.i18n.*) lokalisiert
- Provider-Labels (Empfohlen/Günstig/etc.) nun über __() übersetzt, English als Default
- testConnection "Verbindung erfolgreich" → __('Connection successful') in allen 4 Providern
- de_DE.po + .mo: 20 neue Einträge für Bulk-JS-Strings und Provider-Labels
- Plugin Check Fix: $blocked_count → $bre_blocked_count in views/txt.php
- Plugin Check Fix: build.sh excludiert nun README.de.md + STATE.md
- Divergierende Git-Historie gelöst: force-push auf main, Tag v1.2.3 neu gesetzt → GitHub Action erneut getriggert

## Bekannte Offene Punkte
- Keine kritischen offenen Punkte

## Nächste Schritte (Vorschläge)
- Neue Features oder Bugfixes
