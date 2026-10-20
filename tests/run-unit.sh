#!/usr/bin/env sh
# PHP 8.4 の Docker コンテナで単体テスト（PHPUnit）を実行する。DB は不要。
# 実行: npm run test:unit（または bash tests/run-unit.sh）。Docker だけが必要。
# 追加の引数は PHPUnit に渡す（例: bash tests/run-unit.sh --filter EffectCatalogTest）。
set -u

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
IMAGE="ir-test-php"

docker build -q -t "$IMAGE" "$ROOT/tests" >/dev/null || exit 1

# --rm でコンテナは終了時に消える。vendor/ はリポジトリ直下に作られる（.gitignore 済み）
docker run --rm -v "$ROOT":/app -w /app "$IMAGE" \
  sh -c 'composer install --no-interaction --no-progress --quiet && composer test -- "$@"' sh "$@"
