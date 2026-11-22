#!/usr/bin/env sh
# プレビュー用の固定データを、PHP 8.4 の Docker コンテナで本物のサーバーのコードから生成する（Docker だけが必要）。
# 実行: npm run preview:fixtures
set -eu

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
IMAGE="ir-test-php"

docker build -q -t "$IMAGE" "$ROOT/tests" >/dev/null
docker run --rm -v "$ROOT":/app -w /app "$IMAGE" \
  sh -c 'composer install --no-interaction --no-progress --quiet && php scripts/preview/dump-fixtures.php scripts/preview/fixtures'
