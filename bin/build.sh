#!/usr/bin/env bash
set -euo pipefail

# ─────────────────────────────────────────────────────────────────
# Bavarian Rank Engine — Build & Package Script
# Output: /var/www/dev/plugins/bavarian-rank-engine.zip
# ─────────────────────────────────────────────────────────────────

PLUGIN_SRC="/var/www/dev/plugins/bre-dev"
OUT_DIR="/var/www/dev/plugins/bavarian-rank-engine"
ZIP_FILE="/var/www/dev/plugins/bavarian-rank-engine.zip"

echo "▶ Cleaning previous build..."
rm -rf "$OUT_DIR"
rm -f  "$ZIP_FILE"
mkdir -p "$OUT_DIR"

echo "▶ Copying plugin files..."
rsync -a \
    --exclude='.git/' \
    --exclude='.gitignore' \
    --exclude='node_modules/' \
    --exclude='vendor/phpunit/' \
    --exclude='vendor/nikic/' \
    --exclude='vendor/sebastian/' \
    --exclude='vendor/phar-io/' \
    --exclude='vendor/theseer/' \
    --exclude='vendor/myclabs/' \
    --exclude='tests/' \
    --exclude='docs/' \
    --exclude='bin/' \
    --exclude='composer.phar' \
    --exclude='phpunit.xml' \
    --exclude='.phpunit.result.cache' \
    --exclude='firebase-debug.log' \
    --exclude='*.log' \
    "$PLUGIN_SRC/" "$OUT_DIR/"

echo "▶ Renaming main plugin file..."
mv "$OUT_DIR/seo-geo.php" "$OUT_DIR/bavarian-rank-engine.php"

echo "▶ Installing production dependencies..."
if [ -f "$PLUGIN_SRC/composer.phar" ]; then
    php "$PLUGIN_SRC/composer.phar" install \
        --working-dir="$OUT_DIR" \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --quiet
else
    echo "  ℹ composer.phar not found — skipping composer install"
fi

echo "▶ Removing dev-only composer dependencies (vendor/bin)..."
rm -rf "$OUT_DIR/vendor/bin"

echo "▶ Creating zip archive..."
cd /var/www/dev/plugins
zip -r "$ZIP_FILE" bavarian-rank-engine/ --quiet

ZIP_SIZE=$(du -sh "$ZIP_FILE" | cut -f1)
FILE_COUNT=$(find "$OUT_DIR" -type f | wc -l | tr -d ' ')

echo ""
echo "✓ Build complete!"
echo "  Output directory : $OUT_DIR"
echo "  Zip archive      : $ZIP_FILE ($ZIP_SIZE)"
echo "  Files in build   : $FILE_COUNT"
