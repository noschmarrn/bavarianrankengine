# STATE.md — Bavarian Rank Engine

---

## Aktueller Stand

- Plugin v1.3.0 — Internal Link Suggestions
- **Alle 102 Tests grün** (102 tests, 216 assertions)

### v1.3.0 — Internal Link Suggestions (März 2026)

**Neue Dateien:**
- `includes/Features/LinkSuggest.php` — Matching-Engine + AJAX-Handler + Meta Box
- `includes/Admin/LinkSuggestPage.php` — Einstellungsseite
- `includes/Admin/views/link-suggest-settings.php` — Einstellungsformular (Trigger, Exclude, Boost, AI-Optionen)
- `includes/Admin/views/link-suggest-box.php` — Editor Meta Box HTML-Skelett
- `assets/link-suggest.js` — Trigger-Logik, UI, Apply-Logik (Gutenberg + Classic)

**Features:**
- Editor Meta Box: Vorschläge `„Phrase" → Ziel-Beitrag`, User bestätigt vor Apply
- Multi-Select + Preview-Confirm Dialog
- Text-Matching: `(title_overlap × 3) + (tag_overlap × 2) + (cat_overlap × 1)`
- Optionales KI-Upgrade: Top-20 Kandidaten an AI-Provider
- Trigger-Modi: Manual, On-Save, Interval
- Ausschluss-Liste + Boost/Priorisierung in eigener Admin-Seite
- Transient-Caching (1h), N+1-frei via update_object_term_cache
- Gutenberg: wp.blocks.parse() + resetBlocks(); Classic: tinyMCE.setContent()
- Vollständige Lokalisierung: de_DE + en_US (inkl. .mo)

### v1.2.4 — UX Polish & i18n Fixes (Feb 2026)
- AI Enable default OFF, Active Provider Dashboard-Fix, Locale-aware Prompt, SEO Widget i18n

### v1.2.3 — TXT Files Admin Page (Feb 2026)
### v1.2.2 — Dashboard UX & Security (Feb 2026)

---

## Offene Punkte / Risiken

- Website (github-website/) noch NICHT auf v1.3.0 aktualisiert
- README.md + README.de.md noch auf v1.2.4
- v1.3.0 noch kein git tag gesetzt
- KI-Integration in LinkSuggest vorbereitet aber nicht implementiert (für v1.3.x)

---

## Nächste Schritte (vor Release)

1. README.md + README.de.md auf v1.3.0 aktualisieren
2. github-website/ (EN+DE): index.html + changelog.html aktualisieren
3. git tag v1.3.0 && git push origin master --tags
4. bash bin/build.sh

---

## Release-Status

- **Aktuelle Version:** 1.3.0
- **Website synchronisiert:** Nein — noch auf v1.2.4
- **readme.txt:** 1.3.0 Changelog aktuell ✓
- **Tests:** 102/102 grün ✓

---

## Wichtige Commands

```bash
cd /var/www/plugins/bre/bre-dev
php composer.phar exec phpunit
bash bin/build.sh
git tag v1.3.0 && git push origin master --tags
```
