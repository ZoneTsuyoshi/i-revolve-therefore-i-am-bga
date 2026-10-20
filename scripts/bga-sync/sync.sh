#!/usr/bin/env bash
# BGA Studio への SFTP 同期（仕様書 005）。段階 2: test / dry-run（書き込みなし）と sync（転送と削除リストの削除）。
# 使い方: scripts/bga-sync/sync.sh <test|dry-run|sync>
# 必要な環境変数（ログに出さない）:
#   BGA_KEY_FILE          秘密鍵ファイル（権限 600）
#   BGA_KNOWN_HOSTS_FILE  known_hosts ファイル
#   BGA_SFTP_USER / BGA_SFTP_HOST / BGA_SFTP_PORT / BGA_REMOTE_DIR
# 注意: set -x を使わない。lftp の debug を有効にしない。
set -euo pipefail

# remote-delete.txt の解釈（仕様書 005 §6.3）。lftp を呼ばない純粋な関数。
# 標準出力に "RM<TAB>行" か "SKIP<TAB>行" を出す。不正な行があれば 1 を返す。
# DELETE_LIST（既定: scripts/bga-sync/remote-delete.txt）と SRC_DIR（既定: src）で差し替えられる（テスト用）
plan_delete() {
  local file="${DELETE_LIST:-scripts/bga-sync/remote-delete.txt}" srcdir="${SRC_DIR:-src}" line
  [ -f "$file" ] || return 0
  while IFS= read -r line || [ -n "$line" ]; do
    line="${line%$'\r'}"
    case "$line" in '' | '#'*) continue ;; esac
    if [[ "$line" == /* || "$line" == *..* || "$line" == */ || ! "$line" =~ ^[A-Za-z0-9._/-]+$ ]]; then
      echo "remote-delete.txt に不正なパスがあります: $line" >&2
      return 1
    fi
    if [ -e "$srcdir/$line" ]; then
      printf 'SKIP\t%s\n' "$line"
    else
      printf 'RM\t%s\n' "$line"
    fi
  done <"$file"
}

# 関数だけ読み込みたいとき（テスト用）
if [ "${BGA_SYNC_LIB:-}" = 1 ]; then return 0 2>/dev/null || exit 0; fi

mode="${1:-}"
case "$mode" in test | dry-run | sync) ;; *)
  echo "使い方: $0 <test|dry-run|sync>" >&2
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

# 転送（img → modules → ルート直下の 3 段階）。--delete なし。第 1 引数が dry なら mirror --dry-run（書き込みなし）
transfer() {
  local flag="" cmds=""
  [ "${1:-}" = dry ] && flag="--dry-run"
  if [ -d src/img ]; then
    cmds+="mirror --reverse --no-perms --parallel=2 --verbose $flag src/img $remote/img; "
  fi
  if [ -d src/modules ]; then
    cmds+="mirror --reverse --no-perms --parallel=2 --verbose $flag src/modules $remote/modules; "
  fi
  cmds+="mirror --reverse --no-perms --no-recursion --verbose $flag src $remote"
  lftp_run "$cmds"
}

# remote-delete.txt の対象の表示のみ（dry-run 用。削除しない）
show_delete_list() {
  local plan kind line
  [ -f scripts/bga-sync/remote-delete.txt ] || { echo "(remote-delete.txt なし)"; return 0; }
  plan="$(plan_delete)" || return 1
  [ -n "$plan" ] || { echo "(削除対象なし)"; return 0; }
  while IFS=$'\t' read -r kind line; do
    if [ "$kind" = SKIP ]; then
      echo "スキップ予定（src/ に同名のファイルがある）: $line"
    else
      echo "削除予定: $remote/$line"
    fi
  done <<<"$plan"
}

# 転送の後に remote-delete.txt を実行する。src/ に同じパスがある行はスキップ。
# rm -f は存在しないパスでもエラーにならない（冪等）
delete_listed() {
  local plan kind line cmds=""
  echo "== 削除リストの処理 =="
  if [ ! -f scripts/bga-sync/remote-delete.txt ]; then
    echo "(remote-delete.txt なし)"
    return 0
  fi
  plan="$(plan_delete)" || return 1
  [ -n "$plan" ] || { echo "(削除対象なし)"; return 0; }
  while IFS=$'\t' read -r kind line; do
    if [ "$kind" = SKIP ]; then
      echo "::warning title=remote-delete skipped::src/ に同名のファイルがあるためスキップ: $line（remote-delete.txt から外してください）"
      echo "スキップ（src/ に同名あり）: $line"
    else
      echo "削除: $remote/$line"
      cmds+="rm -f $remote/$line; "
    fi
  done <<<"$plan"
  if [ -n "$cmds" ]; then
    lftp_run "$cmds"
  fi
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
      transfer dry
      echo "== 削除リストの対象（dry-run では削除しない）=="
      show_delete_list
    ) </dev/null >"$log" 2>&1 || rc=$?
    cat "$log"
    { echo '```text'; cat "$log"; echo '```'; } >>"$summary"
    rm -f "$log"
    exit "$rc"
    ;;
  sync)
    {
      echo "## BGA Studio 同期"
      echo
      echo "コミット: \`${GITHUB_SHA:-local}\`"
      echo
    } >>"$summary"
    log="$(mktemp)"
    rc=0
    # push で骨格が無いときは何もせず成功終了する（仕様書 005 §8。手動実行ではエラー）
    if [ "${GITHUB_EVENT_NAME:-}" = push ] && { [ ! -f src/modules/php/Game.php ] || [ ! -f src/gameinfos.jsonc ]; }; then
      echo "::warning title=BGA sync skipped::骨格が無いため push での同期をスキップしました"
      echo "骨格が無いため push での同期をスキップしました" >>"$summary"
      exit 0
    fi
    (
      check_skeleton || exit 1
      # 削除リストの不正は転送の前に検出する
      plan_delete >/dev/null || exit 1
      echo "== 転送 =="
      # 冪等なので、失敗したら 1 回だけ再実行する（仕様書 005 §4）
      transfer || { echo "転送に失敗。1 回だけ再実行します"; transfer; }
      delete_listed
    ) </dev/null >"$log" 2>&1 || rc=$?
    cat "$log"
    {
      echo "### 転送と削除の記録"
      echo
      echo '```text'
      cat "$log"
      echo '```'
      echo
    } >>"$summary"
    rm -f "$log"
    if [ "$rc" -eq 0 ]; then
      {
        echo "### 同期完了"
        echo
        echo "Studio 側の操作は仕様書 005 §9 を確認"
      } >>"$summary"
    else
      echo "失敗 (終了コード $rc)" >>"$summary"
    fi
    exit "$rc"
    ;;
esac
