# BRE State

## Aktuelle Version: 1.2.3

## Zuletzt released: 2026-02-28

## Was zuletzt gemacht wurde
- README.md + README.de.md auf v1.2.3 gebracht: Version-Badge, Verzeichnisstruktur (TxtPage statt LlmsPage/RobotsPage), AJAX-Tabelle
- Deutsche Website (github-website/de/) auf v1.2.3: index.html + changelog.html mit v1.2.3-Eintrag auf Deutsch
- CLAUDE.md Release-Prozess ergänzt: README.md/README.de.md und DE+EN Website als Pflichtschritte
- llms.txt-Seite (LlmsPage) und robots.txt-Seite (RobotsPage) in eine einzige "TXT Files"-Seite (TxtPage) zusammengeführt
- Neues Tab-System via WordPress-nativer `nav-tab-wrapper`: Tab "llms.txt" mit Status-Dot, Tab "robots.txt" mit Blocked-Bot-Zähler-Badge
- Alte Dateien gelöscht: LlmsPage.php, RobotsPage.php, views/llms.php, views/robots.php
- .po-Dateien (de_DE + en_US) aktualisiert: neue Strings "TXT Files", "Save robots.txt Settings"
- Website (github-website/) aktualisiert: changelog.html + index.html auf v1.2.3

## Bekannte Offene Punkte
- Lokales bre-dev/-Repo hat divergierende Historie zum Remote (Remote hat Dateien in Unterordner `bavarian-rank-engine/` reorganisiert, lokal liegt alles flach). Der Tag v1.2.3 wurde erfolgreich gepusht (GitHub Action wurde getriggert), aber `git push origin main` vom bre-dev/-Ordner schlägt fehl wegen Divergenz. Der build.sh-Weg via github-plugin/ hat funktioniert.

## Nächste Schritte (Vorschläge)
- bre-dev/-Repo mit Remote synchronisieren (git pull --allow-unrelated-histories oder Force-Push nach Rücksprache)
- Neue Features oder Bugfixes
