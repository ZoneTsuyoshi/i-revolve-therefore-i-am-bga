#!/usr/bin/env bash
# BGA Studio への SFTP 同期（仕様書 005）。段階 1: test と dry-run のみ（書き込みなし）。
# 使い方: scripts/bga-sync/sync.sh <test|dry-run>
# 必要な環境変数（ログに出さない）:
#   BGA_KEY_FILE          秘密鍵ファイル（権限 600）
#   BGA_KNOWN_HOSTS_FILE  known_hosts ファイル
#   BGA_SFTP_USER / BGA_SFTP_HOST / BGA_SFTP_PORT / BGA_REMOTE_DIR
# 注意: set -x を使わない。lftp の debug を有効にしない。
set -euo pipefail

mode="${1:-}"
case "$mode" in test | dry-run) ;; *)
  echo "使い方: $0 <test|dry-run>" >&2
  exit 2
  ;;
esac

for v in BGA_KEY_FILE BGA_KNOWN_HOSTS_FILE BGA_SFTP_USER BGA_SFTP_HOST BGA_SFTP_PORT BGA_REMOTE_DIR; do
  if [ -z "${!v:-}" ]; then
    echo "環境変数 $v が空です" >&2
    exit 2
  fi
done

# lftp -c に文字列として渡すため、許可する文字を絞る（コマンド注入の防止）
[[ "$BGA_SFTP_USER" =~ ^[A-Za-z0-9._-]+$ ]] || { echo "BGA_SFTP_USER に使えない文字があります" >&2; exit 2; }
[[ "$BGA_SFTP_HOST" =~ ^[A-Za-z0-9.-]+$ ]] || { echo "BGA_SFTP_HOST に使えない文字があります" >&2; exit 2; }
[[ "$BGA_SFTP_PORT" =~ ^[0-9]+$ ]] || { echo "BGA_SFTP_PORT は数字のみです" >&2; exit 2; }
[[ "$BGA_REMOTE_DIR" =~ ^/[A-Za-z0-9._/-]*$ ]] || { echo "BGA_REMOTE_DIR に使えない文字があります" >&2; exit 2; }
[[ "$BGA_KEY_FILE$BGA_KNOWN_HOSTS_FILE" =~ ^[A-Za-z0-9._/-]+$ ]] || { echo "鍵ファイルのパスに使えない文字があります" >&2; exit 2; }

remote="${BGA_REMOTE_DIR%/}"
summary="${GITHUB_STEP_SUMMARY:-/dev/null}"

# lftp の共通設定。ダミーのパスワード（dummy）は入力待ちを避けるため。鍵認証が使われる
lftp_run() {
  lftp -c "
    set cmd:fail-exit yes
    set net:max-retries 3
    set net:timeout 30
    set sftp:auto-confirm no
    set sftp:connect-program 'ssh -a -x -i $BGA_KEY_FILE -o IdentitiesOnly=yes -o UserKnownHostsFile=$BGA_KNOWN_HOSTS_FILE -o StrictHostKeyChecking=yes -o BatchMode=yes'
    open -p $BGA_SFTP_PORT -u $BGA_SFTP_USER,dummy sftp://$BGA_SFTP_HOST
    $1
  "
}

# --- test: 接続して一覧を表示するだけ（書き込みしない）---
run_test() {
  local out rc=0 failed=0
  out="$(mktemp)"
  {
    echo "## BGA Studio 接続テスト"
    echo
    echo "- lftp: $(lftp --version | head -n1)"
    echo "- ssh: $(ssh -V 2>&1)"
    echo
  } >>"$summary"

  # 1) ホームの一覧（必須）。2) リモートパス（必須）。3) 末尾スラッシュ付き（参考。仕様書 C6）
  local label cmd required
  while IFS='|' read -r label cmd required; do
    echo "== $label =="
    rc=0
    lftp_run "$cmd" </dev/null >"$out" 2>&1 || rc=$?
    cat "$out"
    {
      echo "### $label: $([ "$rc" -eq 0 ] && echo 成功 || echo "失敗 (終了コード $rc)")"
      echo
      echo '```text'
      cat "$out"
      echo '```'
      echo
    } >>"$summary"
    if [ "$rc" -ne 0 ] && [ "$required" = required ]; then failed=1; fi
  done <<LIST
ホームの一覧 (ls)|ls|required
リモートパスの一覧 (ls $remote)|ls $remote|required
末尾スラッシュ付き (ls $remote/)|ls $remote/|optional
LIST
  rm -f "$out"
  return "$failed"
}

# 骨格の確認（仕様書 005 §8）。src/modules/php/Game.php と src/gameinfos.jsonc の両方が必要
check_skeleton() {
  if [ ! -f src/modules/php/Game.php ] || [ ! -f src/gameinfos.jsonc ]; then
    echo "骨格（src/modules/php/Game.php と src/gameinfos.jsonc）がありません。転送予定を作れないので終了します" >&2
    return 1
  fi
  # src/img/ のサブディレクトリは BGA の規約に反する（仕様書 005 §4）
  if [ -d src/img ] && [ -n "$(find src/img -mindepth 1 -type d -print -quit)" ]; then
    echo "src/img/ にサブディレクトリがあります。BGA では使えません" >&2
    return 1
  fi
}

# 転送予定（mirror --dry-run。書き込みなし）。3 段階（img → modules → ルート直下）。--delete なし
plan_transfer() {
  local cmds=""
  if [ -d src/img ]; then
    cmds+="mirror --reverse --no-perms --parallel=2 --verbose --dry-run src/img $remote/img; "
  fi
  if [ -d src/modules ]; then
    cmds+="mirror --reverse --no-perms --parallel=2 --verbose --dry-run src/modules $remote/modules; "
  fi
  cmds+="mirror --reverse --no-perms --no-recursion --verbose --dry-run src $remote"
  lftp_run "$cmds"
}

# remote-delete.txt の対象の表示のみ（削除はしない。段階 2 の sync で実行する）
show_delete_list() {
  local file=scripts/bga-sync/remote-delete.txt line
  [ -f "$file" ] || { echo "(remote-delete.txt なし)"; return 0; }
  while IFS= read -r line || [ -n "$line" ]; do
    line="${line%$'\r'}"
    case "$line" in '' | '#'*) continue ;; esac
    if [[ "$line" == /* || "$line" == *..* || ! "$line" =~ ^[A-Za-z0-9._/-]+$ ]]; then
      echo "remote-delete.txt に不正なパスがあります: $line" >&2
      return 1
    fi
    if [ -e "src/$line" ]; then
      echo "スキップ予定（src/ に同名のファイルがある）: $line"
    else
      echo "削除予定: $remote/$line"
    fi
  done <"$file"
}

case "$mode" in
  test)
    run_test
    ;;
  dry-run)
    {
      echo "## BGA Studio dry-run（転送せず予定を表示）"
      echo
    } >>"$summary"
    log="$(mktemp)"
    rc=0
    (
      check_skeleton || exit 1
      echo "== 転送予定 =="
      plan_transfer
      echo "== 削除リストの対象（dry-run では削除しない）=="
      show_delete_list
    ) </dev/null >"$log" 2>&1 || rc=$?
    cat "$log"
    { echo '```text'; cat "$log"; echo '```'; } >>"$summary"
    rm -f "$log"
    exit "$rc"
    ;;
esac
