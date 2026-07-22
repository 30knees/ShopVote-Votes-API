#!/usr/bin/env bash
# Build a deployable PrestaShop module zip with a production-only vendor.
#
# Guards against the php-parser incident (2026-07-22): a dev vendor deployed
# to the shop shadows PrestaShop's own nikic/php-parser v4 with v5 (pulled in
# by PHPUnit) and breaks the BO Translations page with
# "Undefined method PhpParser\ParserFactory::create".
#
# Usage: ./build-release.sh   (from the repo root)

set -euo pipefail

MODULE=shopvotereviews
ROOT="$(cd "$(dirname "$0")" && pwd)"
VERSION=$(sed -n "s/.*\$this->version = '\([0-9.]*\)'.*/\1/p" "$ROOT/$MODULE/$MODULE.php")
STAGE=$(mktemp -d)/$MODULE
OUT="$ROOT/$MODULE-$VERSION.zip"

echo "Building $MODULE v$VERSION"

cd "$ROOT/$MODULE"
composer install --no-dev --quiet

mkdir -p "$STAGE"
tar cf - \
    --exclude='./tests' \
    --exclude='./phpunit.xml' \
    --exclude='./.phpunit.result.cache' \
    --exclude='./composer.lock' \
    --exclude='./.git' \
    . | (cd "$STAGE" && tar xf -)

# Hard guard: the release vendor must not contain any real packages.
if find "$STAGE/vendor" -mindepth 1 -maxdepth 1 -type d ! -name composer ! -name bin | grep -q .; then
    echo "ERROR: staged vendor contains packages beyond the autoloader:" >&2
    find "$STAGE/vendor" -mindepth 1 -maxdepth 1 >&2
    exit 1
fi
if [ -d "$STAGE/vendor/nikic" ] || grep -q 'PhpParser' "$STAGE/vendor/composer/autoload_psr4.php"; then
    echo "ERROR: php-parser leaked into the release vendor" >&2
    exit 1
fi

rm -f "$OUT"
if command -v zip >/dev/null 2>&1; then
    (cd "$(dirname "$STAGE")" && zip -rq "$OUT" "$MODULE")
else
    powershell.exe -NoProfile -Command \
        "Compress-Archive -Path '$(cygpath -w "$STAGE")' -DestinationPath '$(cygpath -w "$OUT")' -Force"
fi

# Restore the dev vendor so local test runs keep working.
composer install --quiet

echo "OK: $OUT"
unzip -l "$OUT" 2>/dev/null | tail -1 || true
