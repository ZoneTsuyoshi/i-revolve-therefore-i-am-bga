#!/bin/sh
# 英語版ルールブックの下地を作る。仮想環境が無ければ作って依存を入れ、prepare.py を実行する。
# 出力は main の作業ツリーの build/rulebook-en/（Git 管理外）。原本は data/rulebook/rulebook_web.pdf（読み取りのみ）。
set -eu
HERE=$(cd "$(dirname "$0")" && pwd)
ROOT=$(git -C "$HERE" rev-parse --path-format=absolute --git-common-dir)/..
ROOT=$(cd "$ROOT" && pwd)
OUT="${IR_RULEBOOK_OUT:-$ROOT/build/rulebook-en}"
DATA="${IR_DATA_DIR:-$ROOT/data}"
VENV="$OUT/venv"
mkdir -p "$OUT"
if [ ! -x "$VENV/bin/python" ]; then
  echo "仮想環境を作る: $VENV"
  python3 -m venv "$VENV"
fi
if ! cmp -s "$HERE/requirements.txt" "$VENV/.requirements.installed"; then
  "$VENV/bin/pip" install --quiet -r "$HERE/requirements.txt"
  cp "$HERE/requirements.txt" "$VENV/.requirements.installed"
fi
exec "$VENV/bin/python" -I "$HERE/prepare.py" --data "$DATA" --out "$OUT" "$@"
