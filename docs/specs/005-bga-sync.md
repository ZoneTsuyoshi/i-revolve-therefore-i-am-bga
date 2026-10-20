# 005: BGA Studio への自動同期

| 項目 | 内容 |
| --- | --- |
| ステータス | Approved |
| 対応タスク | T-005 |
| 作成日 | 2026-10-20 |
| 関連仕様書 | [003 BGA プロジェクト構成・技術方針](003-bga-architecture.md)（§3 構成、§4.1 名前空間、§9 アセット、§11 申し送り、§13 教訓）、004（CI lint。作成予定の T-004） |

## 目的

`main` にマージされた `src/` を、GitHub Actions が SFTP で BGA Studio のプロジェクトフォルダ `/irevolvethereforeiam` へ自動転送する仕組みを定める。姉妹プロジェクト minimum-journey-bga（以下「MJ」）と project-edgeworth-bga（以下「PE」）で同じ仕組みが稼働しているため、両者の実ファイルを読んだうえで流用し、このリポジトリで変える点を明らかにする。接続情報、転送方式、削除の扱い、`src/` が未整備の間の挙動、Secrets / Variables の登録手順、同期後の Studio 側の操作、段階導入を、実装者がこの仕様書だけで導入できる粒度で決める。

## スコープ

- 対象: 全体の流れ、接続情報、Secrets / Variables と登録手順、転送方式、同期の対象と除外、雛形のファイルの扱いと削除リスト、`src/` が無い間の挙動、ワークフローとモード、同期後に Studio で必要な操作、失敗時の運用、段階導入、MJ・PE から変える点
- 対象外: ワークフロー・スクリプトの実装（承認後に別 PR）、lint の中身（004）、`src/` の中身（003、006 以降）、スプライトの設計（009）、Studio 側の操作の自動化
- 本書の作成に当たって Studio へ書き込んだり削除したりはしていない。認証情報は記載しない。

## 記法

- **【確認】** 実ファイル・実機・公式ドキュメントで確認した事実。確認日と方法を添える。
- **【提案】** 本書の提案。
- **【未確認】** 確認できなかった事項。「未確定事項」に挙げる。
- MJ・PE の「実装」は 2026-10-20 時点の次の実ファイルを指す。仕様書と実装に差がある点は実装を正とする。
  - MJ: `/Users/zone/Developments/minimum-journey-bga` の `docs/specs/005-bga-sync.md`、`.github/workflows/bga-sync.yml`、`scripts/bga-sync/sync.sh`、`scripts/bga-sync/remote-delete.txt`
  - PE: `/Users/zone/Developments/project-edgeworth-bga` の `docs/specs/006-bga-sync.md`、`.github/workflows/bga-sync.yml`、`scripts/bga-sync/sync.sh`、`scripts/bga-sync/remote-delete.txt`

公式ドキュメントの確認（2026-10-20、WebFetch。本文を要約して返すツールのため要約を介した確認）:

| 略称 | URL | 確認した内容 |
| --- | --- | --- |
| Tools | <https://en.doc.boardgamearena.com/Tools_and_tips_of_BGA_Studio> | ホスト `1.studio.boardgamearena.com`、ポート 2022。SSH 鍵をアップロードするとパスワード方式は自動で無効になる。lftp の例は `mirror --reverse --parallel=10 --delete /local/path/to/myproject/ myproject/`（`--delete` の効果は説明なし）。rsync の例もある。MJ・PE 作成時の記述から変更なし |
| FileRef | <https://en.doc.boardgamearena.com/Studio_file_reference> | `dbmodel.sql` はゲームの再起動、`gameoptions` / `gamepreferences` / `stats` はコントロールパネルでの特別な再読込、`Game.js` は F5、CSS と `img/` は Ctrl+F5。画像の削除にはコントロールパネルでの追加操作が必要。`gameinfos.jsonc` の操作の記述はない（MJ 003 は「Reload game informations」を確認済み）。変更なし |

## 仕様

### 1. 全体の流れ

```text
PR（Lint gate 通過）──squash merge──▶ main
                                       │ push（src/ ほか下記 paths の変更時）
                                       ▼
                    ┌────────────── bga-sync.yml ──────────────┐
                    │ job 1: lint（004 の lint.yml を呼ぶ）      │
                    │    └ Lint gate が失敗したら以降を実行しない │
                    │ job 2: sync                                │
                    │   0. 設定の有無を確認（未設定ならスキップ）│
                    │   1. checkout                              │
                    │   2. lftp を導入                           │
                    │   3. 鍵・known_hosts を一時ファイルに      │
                    │   4. sync.sh: 骨格の有無を確認（§6）       │
                    │      → src/ を /irevolvethereforeiam へ    │
                    │   5. remote-delete.txt のファイルを削除    │
                    │   6. 結果を Job Summary に出力             │
                    │   7. 一時鍵を削除（always）                │
                    └────────────────────────────────────────────┘
                                       │ SFTP（ポート 2022、SSH 鍵）
                                       ▼
                    BGA Studio: /irevolvethereforeiam（src/ と 1 対 1）
                                       │
                    作者が Studio 側で必要な操作（§9）
```

- 同期の方向は常に GitHub → Studio の一方向。Studio 側で直接編集しない（次の同期で上書きされる）。
- 手動実行（`workflow_dispatch`）は同じワークフローで `mode` を選ぶ（§7）。
- MJ・PE と同じ構成。変える点は §11 に集約する。
- スプライトなどの生成物は `src/img/` にコミット済みなので（003 §9）、**CI で画像を生成する工程（Node の導入、`npm ci`、`build:sprites`）は置かない**。これが MJ・PE との最大の違い。

### 2. 接続情報

| 項目 | 値 | 区分と根拠 |
| --- | --- | --- |
| ホスト | `1.studio.boardgamearena.com` | 【確認】Tools |
| ポート | `2022` | 【確認】Tools |
| ユーザー | 開発者登録メールの「SFTP user」（Web ログイン用の `…0`〜`…9` の番号付きアカウントでは SFTP に入れない）。値は Secret にだけ置く | 【確認】MJ 005 §2.1（2026-10-14 の実機確認） |
| 認証 | RSA 4096 の SSH 鍵（パスフレーズなし）。MJ・PE と**同じ鍵**（`~/.ssh/bga_studio_rsa`）を共用する。パスワード認証は使えない（鍵の登録で無効化される） | 【確認】Tools、MJ 005 §2.1・§7 |
| リモートパス | `/irevolvethereforeiam`（末尾スラッシュなし。MJ・PE の実装と同じ） | 【確認】2026-10-20、メインエージェントが手元の SFTP で読み取りできた |
| ホスト鍵の検証 | `StrictHostKeyChecking=yes`。`known_hosts` の内容を Variable に持つ。CI の実行中に `ssh-keyscan` した結果は信頼しない（中間者攻撃を検知できなくなるため） | 【提案】MJ 005 §2.4 と同じ。値は MJ・PE と同一の Studio のホスト鍵 |

手元の接続確認（読み取りのみ。2026-10-20、メインエージェントの実機確認）:

```bash
sftp -P 2022 -i ~/.ssh/bga_studio_rsa -o IdentitiesOnly=yes ZoneTsuyoshi@1.studio.boardgamearena.com
```

- GitHub-hosted runner からの接続は、MJ（2026-10-14 の初回同期）と PE で成功している【確認】。本リポジトリでも段階 1 の `test` で改めて確かめる（§10）。
- 鍵を共用するため、鍵をローテーションするときは MJ・PE・本リポジトリの 3 つの Secret を同時に更新する。

### 3. Secrets / Variables

登録場所はリポジトリ単位（Settings → Secrets and variables → Actions）。Environments は使わない（§11）。値は仕様書・Issue・PR・ログに書かない。

| 名前 | 種別 | 値 | 値の出どころ |
| --- | --- | --- | --- |
| `BGA_SSH_PRIVATE_KEY` | Secret | SSH 秘密鍵の全文（改行を保持） | `~/.ssh/bga_studio_rsa`（MJ・PE と共用） |
| `BGA_SFTP_USER` | Secret | SFTP のユーザー名 | MJ・PE と同じ（`ZoneTsuyoshi`） |
| `BGA_SFTP_HOST` | Variable | `1.studio.boardgamearena.com` | 公式ドキュメント（公開情報） |
| `BGA_SFTP_PORT` | Variable | `2022` | 同上 |
| `BGA_REMOTE_DIR` | Variable | `/irevolvethereforeiam` | Studio のプロジェクト名 |
| `BGA_SSH_KNOWN_HOSTS` | Variable | Studio のホスト鍵の行（`[1.studio.boardgamearena.com]:2022 …` 形式）。公開情報 | MJ（または PE）の同名 Variable の値 |

- CLAUDE.md の絶対ルール 7 のとおり、認証情報（秘密鍵・ユーザー名）は Secrets のみ。ホスト・ポート・リモートパス・known_hosts は Variables に置く（MJ 005 未確定事項 No.2 で作者が了承済みの分け方）。
- `.github/workflows/bga-sync.yml` の先頭 step は、この 6 つの有無を確認し、欠けていれば警告を出して以降を飛ばし成功終了する（MJ・PE の実装。§7）。

#### 3.1 登録手順

**担当: メインエージェントが作者の環境（`gh` が認証済みの Mac）から実行できる。作者の作業は不要**（秘密鍵は `gh` がファイルから読み、画面にも出力にも出ない）。GitHub の画面から登録する場合だけ作者が行う。**登録は段階 1 の実装 PR をマージした後、`push` トリガーが入る前に行ってよい**（段階 1 には `push` トリガーが無いため、登録しても自動で本番同期は走らない。§10）。

```bash
REPO=ZoneTsuyoshi/i-revolve-therefore-i-am-bga

# Secrets（秘密鍵はファイルから読み込む。< は「ファイルの中身を入力にする」。出力されない）
gh secret set BGA_SSH_PRIVATE_KEY -R "$REPO" < ~/.ssh/bga_studio_rsa
gh secret set BGA_SFTP_USER -R "$REPO" --body "ZoneTsuyoshi"

# Variables
gh variable set BGA_SFTP_HOST -R "$REPO" --body "1.studio.boardgamearena.com"
gh variable set BGA_SFTP_PORT -R "$REPO" --body "2022"
gh variable set BGA_REMOTE_DIR -R "$REPO" --body "/irevolvethereforeiam"

# known_hosts は MJ の Variable をそのまま複製する（ホスト鍵は同じ Studio のもの）
gh variable get BGA_SSH_KNOWN_HOSTS -R ZoneTsuyoshi/minimum-journey-bga \
  | gh variable set BGA_SSH_KNOWN_HOSTS -R "$REPO"

# 登録の確認（名前だけが表示される。値は表示されない）
gh secret list -R "$REPO"
gh variable list -R "$REPO"
```

- `BGA_SFTP_USER` を `--body` で渡すとシェル履歴にユーザー名が残る。気になる場合は `--body` を付けず、実行後の入力プロンプトに入力する。
- 秘密鍵の形式が壊れたとき（CI で `BGA_SSH_PRIVATE_KEY の形式が不正です`、`invalid format`）は、同じコマンドで再登録する（ファイルから読むと改行が保たれる）。画面から貼る場合は `pbcopy < ~/.ssh/bga_studio_rsa` を使い、貼った後に別の文字列をコピーしてクリップボードを上書きする。
- ホスト鍵が一致しなくなった場合（`Host key verification failed`）は偽サーバーかサーバー移行の可能性がある。自動では回避せず、別回線で MJ 005 §2.4 の手順（`ssh-keyscan` とフィンガープリントの比較）をやり直し、正当なら 3 リポジトリの `BGA_SSH_KNOWN_HOSTS` を更新する。

### 4. 転送方式【提案】

MJ・PE の実装（lftp の `mirror --reverse`）をそのまま流用する。

| 論点 | 方針 | 根拠 |
| --- | --- | --- |
| ツール | `lftp`（`ubuntu-latest` に `apt-get install`）。サードパーティ Action は使わない。使う Action は GitHub 公式の `actions/checkout` のみで、コミット SHA で固定する（MJ・PE の SHA と同じものを使う） | MJ・PE の実装 |
| 接続 | `sftp:connect-program` に `ssh -a -x -i <鍵> -o IdentitiesOnly=yes -o UserKnownHostsFile=<known_hosts> -o StrictHostKeyChecking=yes -o BatchMode=yes` を渡し、`open -p <ポート> -u <ユーザー>,dummy sftp://<ホスト>`。`dummy` は入力待ちを避けるための値で、鍵認証が使われる | MJ・PE の実装 |
| 差分判定 | lftp の既定（サイズと時刻）に任せる。CI の checkout は時刻が毎回変わるため、実質は全転送 | MJ・PE の実装 |
| 削除 | **`--delete` を付けない**。Studio にだけあるファイルは残る。消したいファイルだけを `scripts/bga-sync/remote-delete.txt` に列挙し、`sync` の転送後に `rm -f` する（ファイルのみ。ディレクトリは消さない） | MJ・PE の実装。003 §3「Studio にだけあるファイルは取り込まず同期で消さない」 |
| 転送の段階 | (1) `src/img`（再帰）→ (2) `src/modules`（再帰）→ (3) `src/` 直下のファイルのみ（`--no-recursion`） | MJ・PE の実装。画像を先、設定・スキーマを最後にして、部分失敗時の不整合を小さくする |
| オプション | `--no-perms --parallel=2 --verbose`。`net:max-retries 3`、`net:timeout 30`、`cmd:fail-exit yes` | MJ・PE の実装 |
| 失敗時 | `sync` は転送が失敗したら 1 回だけ再実行する（冪等） | MJ・PE の実装 |
| 入力の検査 | ユーザー、ホスト、ポート、リモートパス、鍵のパスは、`lftp -c` に渡す前に許可文字を絞る（コマンド注入の防止）。`remote-delete.txt` の各行は相対パスのみ許可し、絶対パスと `..` を拒否する | MJ・PE の実装 |
| ログ | `set -x` を使わない。秘密鍵は `printf` でファイルに書き（権限 600）、`echo` しない。lftp の `debug` を有効にしない | MJ・PE の実装 |
| 画像のサブディレクトリ | `src/img/` にサブディレクトリがあれば**転送せずに失敗**させる（新規） | BGA の `img/README` はサブディレクトリを使わないよう求める。MJ は 2026-10-14 の初回同期で `img/zoom/` が作られ、手で削除した（MJ 005 §13）。003 §3 も `img/` はサブディレクトリなしとする |

### 5. 同期の対象と除外

#### 5.1 対象

- 対象は **`src/` のコミット済みファイルのみ**。`src/` と Studio の `/irevolvethereforeiam` は 1 対 1（003 §3、CLAUDE.md）。
- `docs/`、`data/`（そもそも Git 管理外）、`scripts/`、`tests/`、`tools/`、設定ファイル類は転送しない。
- スプライトなどの生成物は**コミット済みの `src/img/`** をそのまま転送する（003 §9）。画像の大きさ・個数・命名の検査は 004（lint）の責任で、同期では行わない（003 §11）。

#### 5.2 除外（Studio にだけあるファイルは触らない）

次のファイルは `src/` に置かず `--delete` も使わないので、同期後も Studio に残る。

| Studio のファイル | 扱い |
| --- | --- |
| `_ide_helper.php`、`bga-framework.d.ts` | Studio が配置・更新する開発用スタブ（再配布不可）。取り込まない、消さない |
| `img/README`、`misc/README`、`modules/README`、`misc/` | Studio の説明ファイル。残す |
| `package.json`、`rollup.config.mjs`、`tsconfig.json`、`jsconfig.json` | TypeScript テンプレート一式。使わないが残す。`src/` の外のリポジトリ直下にある同名ファイルとは無関係 |

### 6. 雛形のファイルの扱いと削除リスト

#### 6.1 Studio の初期ファイル【確認】

2026-10-20 にメインエージェントが SFTP で読み取り取得した（Studio へは書き込んでいない。取得物はリポジトリの外）。

- 名前空間 `Bga\Games\IRevolveThereforeIAm`（003 §4.1）。
- 状態クラスは `modules/php/States/` の 3 つ: `PlayerTurn`（状態 ID 10）、`NextPlayer`（90）、`EndScore`（98）。
- `src-disabled/` に TypeScript テンプレートの原本 5 ファイル（`scss/Game.scss`、`ts/Game.ts`、`ts/States/PlayerTurn.ts`、`ts/libs.ts`、`ts/types.d.ts`）。
- `modules/js/Game.js` と `irevolvethereforeiam.css` は TypeScript / SCSS をコンパイルした出力。`dbmodel.sql`、`gameinfos.jsonc`、`gameoptions.jsonc`、`gamepreferences.jsonc`、`stats.jsonc`、`LICENCE_BGA` も含む。非推奨ファイル（`states.inc.php` など）は無い。

#### 6.2 雛形のファイルごとの扱い

| 雛形のファイル | 扱い | 理由 |
| --- | --- | --- |
| `modules/php/Game.php` | `src/` の `Game.php` で**上書き**される。削除しない | 同名のファイルを転送するだけでよい |
| `modules/js/Game.js`、`irevolvethereforeiam.css`、`gameinfos.jsonc` などの設定、`dbmodel.sql`、`LICENCE_BGA` | `src/` の同名ファイルで上書きされる。削除しない | 同上。`src/` に無い間は雛形のまま残る |
| `modules/php/States/{PlayerTurn,NextPlayer,EndScore}.php` | **削除リストに入れる**（§6.3） | 自前の状態クラスが同名で上書きしない限り Studio に残り、状態 ID 10・90・98 が衝突して Express start が失敗する。MJ は実際に失敗し、削除リストに加えて直した（MJ #35。003 §3・§13） |
| `src-disabled/` の 5 ファイル | **削除リストに入れる**【提案】 | 使わない TypeScript テンプレートの原本。残しても動作に影響しない（MJ は残し、PE は削除リストに入れた）。整理のため消す。未確定事項 No.2 |
| `package.json`、`rollup.config.mjs`、`tsconfig.json`、`jsconfig.json` | 残す（§5.2） | Studio 側の設定。消す利点がない |

#### 6.3 削除リスト（`scripts/bga-sync/remote-delete.txt`）【提案】

1 行 1 パス、`src/` からの相対パス（Studio 上では `/irevolvethereforeiam/` 配下の同じ相対パス）。空行と `#` で始まる行は無視する。ファイルのみ。`mode: sync` のとき、転送の後にだけ削除する。

初期内容（段階 2 で作成）:

```text
modules/php/States/PlayerTurn.php
modules/php/States/NextPlayer.php
modules/php/States/EndScore.php
src-disabled/scss/Game.scss
src-disabled/ts/Game.ts
src-disabled/ts/States/PlayerTurn.ts
src-disabled/ts/libs.ts
src-disabled/ts/types.d.ts
```

運用:

- **削除のタイミングは「このゲームの `Game.php` が Studio に転送された後」**でなければならない。雛形の `Game.php` は `States\PlayerTurn` などを参照するため、先に消すと壊れる。転送の後に削除する順序（§7）と、`Game.php` が無い間は同期自体を行わない骨格の確認（§8）で担保する。
- **新規の安全装置（MJ・PE に無い）**: `remote-delete.txt` の行と同じパスのファイルが `src/` に存在する場合は、削除せずに警告を出してスキップする。自前の状態クラスが `PlayerTurn.php` などと同名の場合に、転送したばかりのファイルを消してしまう事故を防ぐ。それでも該当行は `remote-delete.txt` から外す（警告が出続けるため）。
- **状態 ID の衝突**: 自前の状態クラスが別の名前でも、ID に 10・90・98 を使うと、雛形の状態クラスが残っている間は衝突する。006（状態遷移）で状態 ID を決める際に、この 3 つの ID を使うなら同名ファイルで上書きするか、削除リストで雛形を消すかをそろえる。
- **実装の進み具合に合わせて追記する運用**: 実装中にファイル名やスプライト名を変えたときは、旧名を追記する。例: スプライトの名前を変えた場合の旧画像（`img/` のファイルを消すと、Studio のコントロールパネルでの追加操作が要る。§9）。追記した PR のレビューで、追加した行の対象が `src/` に残っていないことを確認する。
- ディレクトリそのもの（`src-disabled/`、`States/` など）は `rm -f` では消えず、空のディレクトリが残る。許容する。

### 7. ワークフロー【提案】

MJ・PE の `.github/workflows/bga-sync.yml` を土台にし、次のとおりにする。

| 項目 | 仕様 |
| --- | --- |
| ファイル | `.github/workflows/bga-sync.yml`（名前 `BGA sync`）。スクリプトは `scripts/bga-sync/sync.sh` |
| トリガー | `push`（`branches: [main]`、`paths` は下記。段階 3 から）と `workflow_dispatch` |
| `workflow_dispatch` の入力 | `mode`: `test` / `dry-run` / `sync`。既定は `test`（段階 1 の時点では `test` と `dry-run` のみ） |
| 権限 | ワークフロー全体は `contents: read`。`lint` job は 004 の `lint.yml` が要求する権限を追加（読み取りのみ） |
| concurrency | `group: bga-studio-sync`、`cancel-in-progress: false`（転送中は止めない。待機中は最新 1 件に置き換わる） |
| lint | job `lint` が `uses: ./.github/workflows/lint.yml`（004 が `workflow_call` に対応させる。003 §11 の T-004 への申し送りに追加する）。job `sync` は `needs: lint`。`Lint gate` が失敗すれば `sync` は動かない。段階 2 から（段階 1 は書き込みをしないので lint を待たない） |
| 実行条件 | `sync` job は `github.ref == 'refs/heads/main'` のときだけ（PR ブランチからの同期は許可しない） |
| 設定の確認 | 先頭の step で §3 の 6 つの有無を確認し、未登録なら欠けた名前を警告と Job Summary に出して以降を飛ばし、成功終了する |
| タイムアウト | `sync` は `timeout-minutes: 15` |
| 鍵 | `$RUNNER_TEMP` に一時ファイル（権限 600）を作る。`ssh-keygen -y` で形式だけ検査（出力しない）。最後に `if: always()` で削除 |
| Node | **使わない**（§1）。`actions/setup-node`、`npm ci` は置かない |
| 手動操作の案内 | `sync` 完了時の Job Summary に、固定の案内文「Studio 側の操作は仕様書 005 §9 を確認」を出す（自動列挙はしない。未確定事項 No.5） |

`push` の `paths`（`docs/**` や他のワークフローだけの変更では同期しない）:

```yaml
paths:
  - 'src/**'
  - 'scripts/bga-sync/**'
  - '.github/workflows/bga-sync.yml'
```

- MJ・PE は `package.json` と `package-lock.json`、`data/`・`scripts/build-sprites.mjs` などを含むが、本リポジトリは CI で画像を作らないので含めない。
- `paths` の判定が squash merge のマージコミットで期待どおり働くかは、MJ・PE で動作している【確認】が、本リポジトリの段階 3 の最初のマージでも確かめる（MJ 005 C10）。

#### 7.1 モード

| モード | 動作 | 書き込み |
| --- | --- | --- |
| `test` | 接続して `ls`（ホーム、`$BGA_REMOTE_DIR`、末尾スラッシュ付き）。結果を Job Summary に出す。`src/` の有無に関係なく実行できる | なし |
| `dry-run` | 骨格の確認（§8）→ `mirror --dry-run` の出力（転送予定の一覧）と、削除リストの対象を Job Summary に出す | なし |
| `sync` | 骨格の確認 → 転送 → `remote-delete.txt` の削除 → 完了メッセージ | あり |
| `push`（`main`、段階 3 から） | `sync` と同じ（骨格が無ければスキップして成功終了。§8） | あり |

- ローカルでも `scripts/bga-sync/sync.sh <mode>` を、必要な環境変数（`BGA_KEY_FILE`、`BGA_KNOWN_HOSTS_FILE`、`BGA_SFTP_USER`、`BGA_SFTP_HOST`、`BGA_SFTP_PORT`、`BGA_REMOTE_DIR`）を渡して実行できる。手元での確認は `dry-run` に限る。
- 実行は GitHub の Actions タブの「Run workflow」、または `gh workflow run bga-sync.yml --ref main -f mode=dry-run` で行う。結果は `gh run watch` / `gh run view --log` で見られる。
- 通知: 失敗時は GitHub 標準のメール通知のみ（追加の通知は作らない）。未確定事項 No.6。

### 8. `src/` が未整備の間の挙動【提案】

2026-10-20 時点でこのリポジトリには `src/` が無い。空の `src/` を `mirror --reverse` すると、`--delete` を付けない限り Studio は消えないが、雛形と新しいクラスが混在する中途半端な同期になりうる。そのため `sync.sh` は転送の前に**骨格の確認**を行う。

- 骨格 = `src/modules/php/Game.php` と `src/gameinfos.jsonc` の両方が存在すること。
- 骨格が無い場合:
  - `push` の自動実行: 「骨格が無いためスキップ」と警告と Job Summary に出し、**転送も削除もせずに成功終了**する（`main` への push が失敗し続けないため）。
  - `workflow_dispatch` の `dry-run` / `sync`: **エラー終了**する（明示した操作が黙って成功扱いになるのを避ける）。`test` は骨格に関係なく実行する。
- `src/` に空のディレクトリしかない、`.gitkeep` だけがある場合も、骨格が無いものとして同じ扱い。
- 削除リストの削除は、転送が成功した後にだけ行う（骨格の確認を通った場合のみ）。
- `src/img/` にサブディレクトリがある場合は、転送前にエラー終了する（§4）。

### 9. 同期後に Studio 側で必要な操作

CI が転送しても、Studio 側の再読込は人手が必要（自動化できる公式手段は確認できていない）。FileRef の記述（2026-10-20 確認）と MJ 005 §8 を基にする。

| 変更したファイル | 同期後に必要な操作 |
| --- | --- |
| `dbmodel.sql` | 進行中のテストゲームを破棄し、新しくゲームを開始する（DB は再作成される）。本番公開後は移行が必要 |
| `gameinfos.jsonc` | コントロールパネルの「Reload game informations」 |
| `gameoptions.jsonc`、`gamepreferences.jsonc`、`stats.jsonc` | コントロールパネルで該当の再読込 |
| `modules/js/**` | 開いているゲーム画面の再読込（F5）。他のタブも再読込 |
| `irevolvethereforeiam.css`、`img/` の追加・更新 | 強制再読込（Cmd+Shift+R） |
| `img/` のファイル削除 | コントロールパネルでの追加操作が必要（FileRef。具体的な操作は【未確認】。未確定事項 No.4） |
| `modules/php/**` | 新しいゲームを開始（または進行中のゲームを再読込）。状態クラスの `id` や遷移を変えたら、進行中のゲームを破棄する |
| いずれの変更後も | Express Start で新しいゲームを 1 回開始して動作確認 |

- 同期自体は冪等で、Studio の画面の操作は `sync` 後に作者が行う。
- 状態 ID の衝突（§6）で Express Start が失敗した場合は、`remote-delete.txt` の対象と Studio 側のファイル一覧を確認する。

### 10. 段階導入【提案】

CLAUDE.md の「1 タスク = 1 PR」に従い、各段階を別の PR とし、結果を確認してから次に進む。MJ は 2026-10-14 に、Secrets を登録済みの状態でワークフローをマージしたため、接続テストを経ずに最初の本番同期が走った（MJ 005 §13）。この教訓から、**`push` トリガーは最後の段階まで入れない**。

| 段階 | 内容 | 前提 | 完了条件 |
| --- | --- | --- | --- |
| 0 | 本仕様書のマージ | — | 未確定事項（技術判断）にメインエージェントが回答し、ステータスが Approved（2026-10-20） |
| 1 | Secrets / Variables の登録（§3.1）と、`bga-sync.yml`（`workflow_dispatch` のみ）・`sync.sh`（`test` と `dry-run`）の導入。`docs/development/cicd.md` の更新。`push`、`mode: sync`、`remote-delete.txt` は入れない | 登録は実装 PR のマージ後（マージ前でも可。トリガーが無いので同期は走らない） | `gh workflow run … -f mode=test` が成功し、`/irevolvethereforeiam` の一覧が Summary に出る。ランナーからの接続（MJ 005 C3）と末尾スラッシュの有無（C6）を PR に記録 |
| 2 | `mode: sync`、`remote-delete.txt`（§6.3）、`lint` job（`needs: lint`）、骨格の確認（§8）を追加。`push` はまだ入れない | 004（T-004）の `lint.yml` が `workflow_call` に対応して `main` にある。`src/` に骨格（`Game.php`、`gameinfos.jsonc` など）が取り込まれている（006 以降の最初の実装 PR） | 作者が見ている状態で、`dry-run`（転送予定と削除対象が想定どおり）→ `sync` を 1 回成功させ、Studio で Express Start が動く |
| 3 | `push`（`main`、§7 の `paths`）トリガーを有効化 | 段階 2 の完了 | `main` へのマージで同期が成功し、`paths` の判定が squash merge で期待どおり動く |

- 段階 1 の実装 PR に `src/` の変更は含めない。
- 骨格が無い間は、段階 2 の `sync` を手動で実行しても §8 によりエラー終了し、Studio は変更されない。
- 段階 2 の最初の本番同期の前に、`dry-run` の出力で「転送予定のファイル」と「削除リストの対象」を作者が確認する。

### 11. MJ・PE から変える点

| 項目 | MJ・PE | このリポジトリ | 理由 |
| --- | --- | --- | --- |
| リモートパス | `/minimumjourney`、`/projectedgeworth` | `/irevolvethereforeiam` | 別プロジェクト。`BGA_REMOTE_DIR` で切り替わる（コードにプロジェクト名を書かない） |
| スプライトの生成 | CI で `data/` から生成（`npm ci`、`build:sprites`。PE は `check:sprites` も） | **生成しない**。コミット済みの `src/img/` を転送 | `data/` は Git 管理外で CI から使えない（CLAUDE.md の絶対ルール 6、003 §9） |
| Node・`.nvmrc` | `actions/setup-node` を使う | 使わない | 上記 |
| `push` の `paths` | `data/**`、スプライトの生成スクリプト、`package*.json` を含む | `src/**`、`scripts/bga-sync/**`、ワークフロー自身のみ | 上記 |
| 画像のサブディレクトリ | 検査なし（MJ は事故で作られ、手で削除） | `src/img/` にサブディレクトリがあれば失敗 | §4。MJ の事故の再発防止 |
| `src/` が無い間 | MJ は `src/` が無いとエラー（`push` の `paths` で起動しないので問題にならない）、PE は同期の前に取り込み PR を挟む手順で担保 | 骨格（`Game.php`、`gameinfos.jsonc`）の確認。`push` ではスキップ、手動ではエラー | §8。段階 2 の前提を機械的に守る |
| 削除リストの安全装置 | 無い（`src/` に同名のファイルがあっても削除する） | `src/` に同じパスのファイルがあれば削除しない | §6.3。上書きした直後に消す事故を防ぐ |
| 削除リストの初期内容 | MJ は `States/*.php` の 3 行、PE は 3 行と仮の状態クラス 2 つ、`src-disabled/` の 5 ファイル | `States/*.php` の 3 行と `src-disabled/` の 5 ファイル | §6.3。PE の仮の状態クラスは本ゲームには無い |
| 導入の順序 | MJ は `push` トリガー込みで導入し、初回の本番同期が意図せず走った | `push` は最後の段階（§10） | MJ 005 §13 の教訓 |
| lint | MJ は `lint.yml` を `workflow_call` で呼ぶ | 同じ。ただし T-004 の導入後（段階 2）から | `lint.yml` がまだ無い |
| `environment` | 仕様は提案したが、実装はコメントアウト | 使わない | 非公開リポジトリで環境を使えるプランか未確認。`main` 限定は `if: github.ref == 'refs/heads/main'` で担保 |
| 手動操作サマリー | 固定の案内文のみ | 同じ | 自動列挙は運用してから足す（No.5） |

MJ・PE の履歴から拾った同期関連の fix（2026-10-20 時点）: MJ #35「Studio に残った雛形の状態クラスを削除対象にする」（本書 §6 に反映済み）。PE の履歴には同期関連の fix はなく、導入（#33、#38）のみだった。MJ の `img/zoom/` 事故（§4）は履歴ではなく MJ 005 §13 に記録がある。

### 12. 運用

| 論点 | 方針【提案】 |
| --- | --- |
| 失敗時 | GitHub 標準のメール通知。同じコミットで `workflow_dispatch`（`mode: sync`）から再実行する（冪等） |
| ロールバック | 戻したいコミットを `git revert` した PR を `main` に入れる（履歴を残す）。同期は冪等なので、戻した内容も同じ手順で転送される。**Studio にあって `src/` に無いファイルは戻らない**（削除しない方針）。`remote-delete.txt` で削除したファイルは、戻すと再アップロードされる |
| Studio での直接編集 | 禁止（次の同期で上書きされる）。やむを得ず試す場合は、同じ内容を直ちに GitHub に反映する |
| Studio 内蔵 SVN | GitHub が正、SVN は Studio 側の保険（マイルストーン時に作者が手動でコミット）。SVN から GitHub への逆同期はしない |
| 鍵のローテーション | 年 1 回、または漏えいが疑われるとき。MJ・PE・本リポジトリの 3 つの Secret を同時に更新する。漏えい時は Studio の公開鍵を差し替えてから Secret を更新する |
| 権限 | Secrets / Variables を編集できるのは作者のみ。ブランチ保護はしない（作者決定）ため、`main` 限定は `if` 条件で担保する |

## エッジケース

- **同期が途中で失敗した**: `sync.sh` が 1 回だけ再実行する。それでも失敗したら、同じコミットで `workflow_dispatch` から再実行する。Studio は開発環境なので、一時的な中途半端な状態は許容する。
- **Secrets / Variables が欠けている**: 欠けた名前を警告に出して以降を飛ばし、成功終了する。
- **`Lint gate` が失敗している**: `sync` job は実行されない（段階 2 以降）。
- **`src/` が無い、骨格が足りない**: §8。`push` はスキップ、手動はエラー。
- **`src/img/` にサブディレクトリがある**: 転送前にエラー終了する。
- **Studio にだけあるファイルとの衝突**: `--delete` を使わないので消えない。同名のファイルは `src/` の内容で上書きされる。
- **雛形の状態クラスが残った**: `remote-delete.txt` で消すまで残り、同名ファイルを `src/` に置けば上書きされる。ID が衝突すると Express Start が失敗する（§6）。
- **`remote-delete.txt` に誤ったパス**: 存在しないファイルは無視する。絶対パスと `..` はスクリプトが拒否する。`src/` にあるパスは削除しない。
- **画像を削除した、名前を変えた**: 旧名を `remote-delete.txt` に追記し、Studio のコントロールパネルでの追加操作を行う（§9）。残っても動作に影響しないが、総量 15 MB の制限（003 §9）に影響する。
- **同時に 2 回マージされた**: concurrency により 1 件目は実行中、2 件目は待機。3 件目が来たら 2 件目は取り消され、最後のコミットだけが同期される。
- **PR ブランチを Studio で試したい**: 許可しない（`main` 限定）。必要になったら別途仕様化する。
- **ホスト鍵が一致しない**: 自動で回避せず、作者が別回線で確認する（§3.1）。
- **BGA が鍵認証を拒否する設定に変わった**: `test` で検出する。公式ドキュメントを再確認する。

## 未確定事項

| No. | 質問 | 暫定案 | 回答 |
| --- | --- | --- | --- |
| 1 | `main` への push のたびに、承認なしで自動同期してよいか（`push` トリガーは段階 3 で有効化）。MJ では作者が了承済み | 自動同期する。必須レビュアーは設定しない | メインエージェントの判断で暫定案のとおり（2026-10-20。技術判断のため作者確認は不要） |
| 2 | 初回の削除リストに `src-disabled/` の 5 ファイルを入れてよいか（MJ は残し、PE は削除した。残しても動作に影響しない） | 入れる（整理のため）。`States/*.php` の 3 つは常に入れる | メインエージェントの判断で暫定案のとおり（2026-10-20。技術判断のため作者確認は不要） |
| 3 | 骨格の定義を `Game.php` と `gameinfos.jsonc` の 2 つにしてよいか。`dbmodel.sql` や CSS を加えるか | 2 つ。006 以降の最初の実装 PR で、起動できる最小の骨格をそろえる | メインエージェントの判断で暫定案のとおり（2026-10-20。技術判断のため作者確認は不要） |
| 4 | `img/` のファイルを削除したときにコントロールパネルで行う具体的な操作（FileRef は「追加操作が必要」とだけ書く）。MJ の `img/zoom/` 削除も手元で行った | 初めて画像を削除する時点で作者が確認し、本書 §9 を更新する | メインエージェントの判断: 初めて `img/` のファイルを削除するときに Studio で確認し、本仕様書に追記する（2026-10-20） |
| 5 | `sync` 完了時の Job Summary に、変更ファイルから必要な手動操作を自動で列挙するか | 固定の案内文のみ。運用してから足す | メインエージェントの判断で暫定案のとおり（2026-10-20。技術判断のため作者確認は不要） |
| 6 | 失敗時の通知は GitHub 標準のメールのみでよいか | メールのみ | メインエージェントの判断で暫定案のとおり（2026-10-20。技術判断のため作者確認は不要） |
| 7 | 設定未登録のときに「スキップして成功終了」する挙動を残すか（Secrets 登録後は失敗にもできる） | 残す（鍵のローテーション中などに `main` への push が失敗し続けないため） | メインエージェントの判断で暫定案のとおり（2026-10-20。技術判断のため作者確認は不要） |
| 8 | `.github/workflows/lint.yml`（T-004）が無い段階 1 のあいだ、同期ワークフローに lint を付けなくてよいか（段階 1 は書き込まない） | 付けない。段階 2 で `needs: lint` を追加する | メインエージェントの判断で暫定案のとおり（2026-10-20。技術判断のため作者確認は不要） |
| 9 | GitHub-hosted runner からの接続（MJ・PE では成功済み）と `paths` の squash merge での判定は、本リポジトリでも段階 1・3 で確かめる扱いでよいか | 確かめる | メインエージェントの判断で暫定案のとおり（2026-10-20。技術判断のため作者確認は不要） |

## 受け入れ条件

- [ ] 接続情報（ホスト、ポート、リモートパス、認証方式）と Secrets / Variables の名前・値の出どころが、秘密の値を書かずに記載されている
- [ ] `gh secret set` / `gh variable set` による登録手順と、その担当（メインエージェントが実行できること）が書かれている
- [ ] 転送方式（`--delete` なし、削除リスト、転送の順序）、対象と除外、雛形のファイルごとの扱い、削除リストの初期内容と運用が書かれている
- [ ] `src/` が未整備の間の挙動（骨格の確認、`push` ではスキップ、手動ではエラー）が書かれている
- [ ] ワークフローのトリガー、モード、権限、concurrency、lint との関係が書かれ、CI で画像を生成しないことが明記されている
- [ ] 同期後に Studio で必要な操作、失敗時の再実行とロールバックが書かれている
- [ ] 段階導入（接続テスト → dry-run → 本番 → `push`）が、`push` を最後に有効化する順序で書かれている
- [ ] MJ・PE から変える点が一覧になっている
- [ ] 判断できない点が「未確定事項」に挙がっている
- [ ] 本書の作成で Studio への書き込み・削除をしていない
