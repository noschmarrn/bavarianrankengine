#!/usr/bin/env bash
set -euo pipefail

# ─────────────────────────────────────────────────────────────────
# Bavarian Rank Engine — Lokaler Build
# Erzeugt ZIP für WordPress.org-Einreichung.
# GitHub Releases: git tag vX.Y.Z && git push origin vX.Y.Z
# ─────────────────────────────────────────────────────────────────

BASE_DIR="/var/www/plugins/bre"
PLUGIN_SRC="$BASE_DIR/bre-dev"
BUILD_DIR="$BASE_DIR/build/bavarian-rank-engine"
ZIP_FILE="$BASE_DIR/zip/bavarian-rank-engine.zip"

echo "▶ Cleaning previous build..."
rm -rf "$BUILD_DIR"
rm -f  "$ZIP_FILE"
mkdir -p "$BUILD_DIR" "$BASE_DIR/zip"

echo "▶ Copying plugin files..."
rsync -a \
    --exclude='.git/' \
    --exclude='.gitignore' \
    --exclude='.github/' \
    --exclude='.claude/' \
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
    --exclude='README.md' \
    --exclude='README.de.md' \
    --exclude='STATE.md' \
    --exclude='composer.phar' \
    --exclude='phpunit.xml' \
    --exclude='.phpunit.result.cache' \
    --exclude='firebase-debug.log' \
    --exclude='*.log' \
    "$PLUGIN_SRC/" "$BUILD_DIR/"

echo "▶ Installing production dependencies..."
if [ -f "$PLUGIN_SRC/composer.phar" ]; then
    php "$PLUGIN_SRC/composer.phar" install \
        --working-dir="$BUILD_DIR" \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --quiet
else
    echo "  ℹ composer.phar not found — skipping composer install"
fi

echo "▶ Removing dev-only composer dependencies (vendor/bin)..."
rm -rf "$BUILD_DIR/vendor/bin"

echo "▶ Creating zip archive..."
cd "$BASE_DIR/build"
zip -r "$ZIP_FILE" bavarian-rank-engine/ --quiet

ZIP_SIZE=$(du -sh "$ZIP_FILE" | cut -f1)
FILE_COUNT=$(find "$BUILD_DIR" -type f | wc -l | tr -d ' ')

echo ""
echo "✓ Build complete!"
echo "  Output : $BUILD_DIR"
echo "  ZIP    : $ZIP_FILE ($ZIP_SIZE, $FILE_COUNT files)"

# ─── Plugin → GitHub (github-plugin/) ───────────────────────────────────────
GITHUB_PLUGIN_DIR="$BASE_DIR/github-plugin"

if [ -d "$GITHUB_PLUGIN_DIR/.git" ]; then
    echo ""
    echo "▶ Syncing plugin to GitHub repo..."

    PLUGIN_VERSION=$(grep -m1 "Version:" "$BUILD_DIR/bavarian-rank-engine.php" | grep -oP '[\d]+\.[\d]+\.[\d]+')

    # Sync built files into bavarian-rank-engine/ subfolder
    mkdir -p "$GITHUB_PLUGIN_DIR/bavarian-rank-engine"
    rsync -a --delete \
        --exclude='composer.json' \
        --exclude='composer.lock' \
        "$BUILD_DIR/" "$GITHUB_PLUGIN_DIR/bavarian-rank-engine/"

    # READMEs stay at repo root (come from bre-dev, not the build output)
    cp "$PLUGIN_SRC/README.md"    "$GITHUB_PLUGIN_DIR/"
    cp "$PLUGIN_SRC/README.de.md" "$GITHUB_PLUGIN_DIR/"

    cd "$GITHUB_PLUGIN_DIR"
    git add -A

    if git diff --cached --quiet; then
        echo "  ℹ No plugin changes to commit."
    else
        git commit -m "release: v${PLUGIN_VERSION}"
        git push origin main
        echo "  ✓ Plugin pushed to GitHub (v${PLUGIN_VERSION})."
    fi
else
    echo ""
    echo "  ℹ GitHub plugin repo not found at $GITHUB_PLUGIN_DIR — skipping."
fi

# ─── Website (GitHub Pages) ──────────────────────────────────────────────────
GITHUB_REPO_DIR="$BASE_DIR/github-website"

if [ -d "$GITHUB_REPO_DIR/.git" ]; then
    echo ""
    echo "▶ Pushing website to GitHub..."

    PLUGIN_VERSION=$(grep -m1 "Version:" "$BUILD_DIR/bavarian-rank-engine.php" | grep -oP '[\d]+\.[\d]+\.[\d]+')

    cd "$GITHUB_REPO_DIR"
    git add -A

    if git diff --cached --quiet; then
        echo "  ℹ No website changes to commit."
    else
        git commit -m "release: v${PLUGIN_VERSION}"
        git push origin main
        echo "  ✓ Website pushed to GitHub (v${PLUGIN_VERSION})."
    fi
else
    echo ""
    echo "  ℹ GitHub website repo not found at $GITHUB_REPO_DIR — skipping."
fi
