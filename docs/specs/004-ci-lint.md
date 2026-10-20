# 004: CI（lint チェック）

| 項目 | 内容 |
| --- | --- |
| ステータス | Approved |
| 対応タスク | T-004 |
| 作成日 | 2026-10-20 |
| 関連仕様書 | [003](003-bga-architecture.md)（BGA プロジェクト構成・技術方針。特に §3 `src/` の構成、§4.3 `ir` 接頭辞、§6.1 JS、§9 アセット、§10 テスト方針、§11 申し送り、§13 教訓）。後続: T-005（Studio 同期）、T-009（UI・アセット） |

## 目的

`main` へ入る変更が、構文・書式・型・認証情報の混入・原本（`data/`）の混入・BGA フレームワークとのメソッド名の衝突といった、機械的に検出できる問題を含まないことを、PR ごとと `main` への push ごとに GitHub Actions で自動検査するための仕様を定める。姉妹プロジェクト（PE = `project-edgeworth-bga`、MJ = `minimum-journey-bga`）で同じ構成の CI が稼働しているので、それを土台にし、このリポジトリとの差分だけを決める。実装者が本書と姉妹の実ファイルを見て、このリポジトリに導入できる粒度にする。

姉妹との最大の違いは `data/` の扱いである。姉妹 PE の CI は「`data/` を変更していないこと」を検査するが、このリポジトリの `data/` は Git 管理外で CI に存在しない（CLAUDE.md 絶対ルール 6）。そこで検査を「`data/` が Git に入っていないこと」に読み替える（§2.4）。生成物（スプライトなど）も `src/` にコミットする方針（003 §9）なので、姉妹のように CI で生成する工程は持たず、原本なしでできる範囲の上限検査だけを lint に入れる（§2.9）。

## スコープ

- 対象: 検査項目とツール、姉妹から流用する点と変える点、`ir` 接頭辞の検査の適用、`data/` が Git に入っていないことの検査、段階導入の計画、ワークフロー構成、ローカル実行、PR で必須にする運用、実装時に追加するファイル、作者側の準備
- 対象外: ワークフロー・設定ファイル・スクリプトの実装（本書のマージ後に別 PR で行う。本書ではコードもワークフローも作らない）、CD（`src/` の Studio 同期。T-005）、スプライトの設計と生成（T-009）、カードデータの整合検査（T-002。`Material/` と `card.csv` の差分検査は 002 が決め、必要なら `npm run lint` に足す）、単体テストの中身（T-006 以降）

## 記法と調査の前提

- **【姉妹の実装】** は、姉妹の実ファイルを読んで確認した事実（出典を添える）。「PE 005 §2.1」は PE の `docs/specs/005-ci-lint.md` の節、「MJ 004」は MJ の `docs/specs/004-ci-lint.md`。仕様書と実装が食い違うときは実装を正とした。
- **【提案】** は本書の提案。**【未確認】** は確認できなかった事項で、「未確定事項」に載せる。
- 調査日は 2026-10-20。姉妹の基準は PE `main` の `6beec6e`、MJ `main` の `ba13df6`。両プロジェクトとも `Lint` ワークフローは稼働中で、直近の実行（PE の `main` への push と PR）は成功している（`gh run list` で確認）。
- CI 関連の fix の履歴は少ない。PE は `lint.yml` に手を入れた fix が無く（導入 2 回のみ）、MJ は第 1 段階の PR 内で「lychee 0.24 の `include_fragments` は文字列で指定する」「markdownlint の MD007・MD032 の指摘を直す」の 2 件、第 2 段階で「Studio の雛形を lint に通る書式へ整形する（`phpcbf`、`public` の明示、CRLF から LF、未使用引数への `_` 接頭辞）」を行った。これらは本書に反映済み（§2.1・§2.2・§2.4・§3 末尾）。

### ツールとアクションのバージョン

姉妹 PE の実ファイルで使っている版を基準にし、2026-10-20 に最新を確認した。アクションは PE と同じ版・同じコミット SHA で固定する（SHA は PE の `.github/workflows/lint.yml` をコピーする。実装時に再確認し、以後は Dependabot が追随する）。

| 対象 | 版 | 確認 |
| --- | --- | --- |
| actions/checkout | v7.0.1 | `gh api repos/actions/checkout/releases/latest` で最新と一致 |
| actions/setup-node | v7.1.0 | 同上 |
| shivammathur/setup-php | 2.40.0、`php-version: '8.4'` | 同上 |
| actions/cache | v6.1.0 | 同上 |
| gitleaks/gitleaks-action | v3.0.0 | 同上 |
| lycheeverse/lychee-action | v2.9.0、lychee 本体 `v0.24.2` | 同上（lychee 本体も v0.24.2 が最新） |
| Node | `.nvmrc` は `24`。`engines.node` は `^22.13.0 \|\| >=24` | PE `.nvmrc`、`package.json` |
| ESLint / @eslint/js / @eslint/json / globals | `^10.13.0` / `^10.0.1` / `^2.1.1` / `^17.13.0` | PE `package.json`。`npm view eslint version` は 10.13.0、`globals` は 17.13.0 |
| Stylelint / stylelint-config-standard | `^17.16.0` / `^40.0.0` | PE `package.json`。Stylelint は 17.16.0 が最新 |
| markdownlint-cli2 | `^0.23.3` | PE `package.json`。0.23.3 が最新 |
| TypeScript | `^7.0.2` | PE `package.json`。7.0.2 が最新 |
| PHP_CodeSniffer / PHPStan / php-parallel-lint | `^4.0` / `^2.3` / `^1.4` | PE `composer.json`。Packagist の最新は 4.0.4 / 2.3.1 / v1.4.0 |
| PHPUnit | `12.5.39`（固定） | PE `composer.json`。Packagist の最新は 13.4.3 だが、`php>=8.4.1` を要求し、`config.platform.php` の `8.4.0` と衝突する（未確定事項 No.2） |
| image-size（A1 用） | `^2.0` | `npm view image-size version` は 2.0.4。PE は同等の検査に `sharp` を使うが、A1 は寸法を読むだけなので純 JS のこちらにする（§2.9） |

### このリポジトリの GitHub 側の現状（2026-10-20 に `gh api` で確認）

- 公開範囲は **非公開**。ブランチ保護の API は `Upgrade to GitHub Pro or make this repository public` の 403 を返すので、**Free プランでは `main` の保護（必須ステータスチェック）を設定できない**。作者が決めた「保護は設定しない」方針と整合する（§6）。
- マージは squash のみ許可（merge commit・rebase は不許可）。マージ後のブランチ自動削除は有効。Actions の既定権限は Read、許可するアクションは制限なし、`sha_pinning_required` は無効。Dependabot のセキュリティアラートは無効（404）。

## 仕様

### 1. 全体方針

PE 005 §1 を踏襲する。

- **【姉妹の実装】** ワークフロー `Lint`（`.github/workflows/lint.yml`）を `pull_request`（`main` 向け）、`main` への `push`、`workflow_dispatch`、`workflow_call` で実行する。`workflow_call` は T-005 の同期ワークフローが lint を再利用するために残す。`pull_request_target` は使わない。
- **【姉妹の実装】** チェックの実体は `package.json` の `scripts` と `composer.json` の `scripts` に置き、ワークフローはそれを呼ぶだけにする。ローカルでも同じコマンドで実行できる。例外は Node 以外のバイナリが要る gitleaks と lychee のみ。
- **【姉妹の実装】** lint は検査のみで、ファイルを書き換えない。
- **【姉妹の実装】** 必須チェックは集約ジョブ `gate`（表示名 `Lint gate`）の 1 つだけ。ワークフローレベルの `paths` フィルタは付けない。ジョブは並列に動き、各ジョブに `timeout-minutes: 10` を付ける。権限は `contents: read` を既定とする。サードパーティのアクションはコミット SHA で固定する。`concurrency` は PR の古い実行を取り消し、`main` への push は取り消さない。
- **【姉妹の実装】** `gate` は `needs` のどれかが `failure` / `cancelled` / `skipped` なら失敗にする。したがって、対象が無いときの省略は**ジョブ全体を `if` で飛ばさず、ステップ単位の `if`（`hashFiles(...)`）で行う**（PE の `php` ジョブの作り。ジョブごと飛ばすと `skipped` で `gate` が落ちる）。
- **【提案】** 全ツールの対象外: `data/`、`.worktrees/`、`node_modules/`、`vendor/`、`build/`、`tools/bga/`（スタブ置き場）。リポジトリ直下の作業ツリーには `data/`（原本、画像と PDF）と `.worktrees/`（他のタスクの作業ツリー。リポジトリの複製を含む）が実在するので、**ローカルで `npm run lint` を直下で実行しても、これらを見に行かない**設定にする（markdownlint の `ignores`、lychee の `exclude_path`、ESLint の `globalIgnores`。いずれも PE の設定が既に持っている）。CI のチェックアウトには `data/` も `.worktrees/` も無いので、対象外の指定は主にローカル実行のためにある。
- **【提案】** 各ワークツリー（`.worktrees/<slug>`）では `node_modules/` と `vendor/` がワークツリーごとに別になる。ローカルで lint を回す前に、そのワークツリーで `npm ci`（PHP を使う段階以降は `composer install` も）を実行する。

### 2. 検査項目の一覧と、姉妹からの流用・変更

| ID | 検査 | 対象 | ツール | 姉妹から | 段階 |
| --- | --- | --- | --- | --- | --- |
| M1 | Markdown の書式 | `**/*.md`（`data/` ほか除外） | markdownlint-cli2 | 流用（設定は PE と同じ内容） | 1 |
| M2 | リンク切れ（内部リンク、オフライン） | 同上 | lychee（`offline = true`） | 流用 | 1 |
| S1 | 認証情報の混入 | 全履歴と作業ツリー | gitleaks | 流用（`title` のみ変更） | 1 |
| S2 | 禁止ファイル・`src/` 衛生・改行コード | `git ls-files` | `scripts/lint/check-repo.mjs` | 流用し、検査を足す・外す | 1 |
| R1 | `data/` が Git に入っていないこと | `git ls-files` と `.gitignore` | 同上（S2 の一部） | **読み替え**（PE は「変更禁止」） | 1 |
| S3 | ドキュメントから `data/`・リポジトリ外へのリンク禁止 | 追跡している `*.md` | 同上 | 新規 | 1 |
| P1 | PHP 構文 | `src` | php-parallel-lint | 流用（対象ディレクトリを `src` のみ） | 2 |
| P2 | PHP 規約（PSR-12、行長 140） | 同上 | PHP_CodeSniffer | 流用（名前などを置換） | 2 |
| P2' | `declare(strict_types=1);` | `src/**/*.php` | `scripts/lint/check-strict-types.mjs` | そのまま流用 | 2 |
| P4 | `ir` 接頭辞 | `src/modules/php/**/*.php` | `scripts/lint/check-method-prefix.mjs` | 流用（接頭辞を `pe` から `ir` に） | 2 |
| J1 | JS の lint | `src/modules/js/**/*.js` ほか | ESLint | そのまま流用 | 2 |
| D1 | JSONC の構文 | `src/**/*.{json,jsonc}` | ESLint + `@eslint/json` | そのまま流用 | 2 |
| C1 | CSS の lint | `src/**/*.css` | Stylelint | そのまま流用 | 2 |
| T1 | lint スクリプトの自己テスト、クライアントの純粋関数のテスト | `scripts/lint/*.test.mjs`、`tests/js/*.test.mjs` | `node --test` | 流用 | 1 / 2 |
| P3 | PHP 静的解析（level 5） | `src/modules/php` | PHPStan | 流用（BGA のスタブの取得が前提） | 2b |
| J2 | JS の型検査 | `src/modules/js/**/*.js` | `tsc --noEmit`（`checkJs`） | 流用（同上） | 2b |
| A1 | `src/img/` の上限検査（原本なし） | `src/img/**` | `scripts/lint/check-assets.mjs` | **変更**（PE は CI で生成して検査） | 3 |
| U1 | 単体テスト | `tests/Unit/` | PHPUnit | 流用（別ワークフロー `Test`） | 4 |

#### 2.1 Markdown の書式（M1）

- **【姉妹の実装】** `.markdownlint-cli2.jsonc`: `globs: ["**/*.md"]`、`ignores: data/** .worktrees/** node_modules/** vendor/**`、`gitignore: false`。ルールは既定（全ルール有効）から MD013（行長）・MD034（裸の URL）・MD036・MD052・MD060 を無効、MD033 は許可要素 `br` のみ、MD024 は `siblings_only`。実行は `npm run lint:md`。
- **変更**: なし。PE の同名ファイルをそのままコピーし、先頭のコメントの参照先だけ「仕様書 004 §2.1」にする。このリポジトリにはまだこのファイルが無いので、第 1 段階の実装 PR で新規に置く。
- 現状の確認: 同じ設定で `npx markdownlint-cli2 "**/*.md"` を `main` の 13 ファイルに実行し、0 件であることを確認した（2026-10-20、markdownlint-cli2 0.23.3）。
- 今後の書き手が引っかかりやすい点（MJ の第 1 段階で実際に指摘された MD007・MD032 を含む）: (1) 言語指定のないコードブロック（MD040。出力例は `text` を付ける）、(2) 山括弧の裸の記述 `<name>`（MD033。コードスパンで囲む）、(3) 見出しレベルの飛び（MD001）、(4) 末尾改行の欠落（MD047）、(5) リスト・表・コードブロックの前後の空行の欠落（MD031・MD032・MD058）、(6) 入れ子リストのインデント（MD007）。

#### 2.2 リンク切れ（M2）と `data/`・サイト側リポジトリへの参照

- **【姉妹の実装】** `lychee.toml`: `offline = true`、`include_fragments = "full"`、`no_progress = true`、`exclude_path = ['^\.worktrees/', '^node_modules/', '^data/', '^vendor/']`。CI は `lycheeverse/lychee-action` に `lycheeVersion: v0.24.2`、`args: --config lychee.toml './**/*.md'`、`fail: true`。外部 URL は検査しない。ローカルは `npm run lint:links`（`brew install lychee` が前提で、`npm run lint` には含めない）。
- **変更**: なし（`lychee.toml` をそのままコピー）。`include_fragments` は lychee 0.24 以降は文字列で指定する（MJ #10 の fix。PE の `lychee.toml` は修正済み）。
- 現状の確認: 公式のバイナリ（lychee v0.24.2、macOS arm64）で、PE の `lychee.toml` のまま `main` の 13 ファイルに実行し、98 件中 40 件が対象、**エラー 0 件**を確認した（2026-10-20）。残りは外部 URL などの除外。日本語見出しのアンカーへのリンクはまだ無いので、フラグメントの照合は実環境で未検証（PE の CI は日本語の見出しを含む文書で成功している）。
- **`data/` とサイト側リポジトリの参照の扱い【提案】**: CLAUDE.md の規約どおり、ドキュメントは `data/…` も `../danjiri-concerto.github.io/…`（サイト側リポジトリの `faq.csv` / `card.csv`）も**リンクにせず、コード表記**で書く。コード表記は lychee も markdownlint も見ない。リンクにしてしまうと、CI（と `.worktrees/` 配下のワークツリー）にはその先が無いので M2 が落ちる。ただし、**直下の作業ツリーでは `data/` もサイト側のリポジトリも実在するので、ローカルの lychee は通ってしまい、CI で初めて落ちる**。そこで S3（§2.4）で、Markdown のリンク先が `data/` かリポジトリ外を指していないことをローカルでも検査する。
- 注意: 仕様書は、未マージの別仕様書へリンクすると、そのマージまで M2 が失敗する（姉妹と同じ。未マージの仕様書は番号とコード表記で参照する）。

#### 2.3 認証情報の混入（S1）

- **【姉妹の実装】** `.gitleaks.toml`: `[extend] useDefault = true` で既定ルールを継承し、独自ルール `bga-sftp-json-password`（`sftp.json` 形式の `"password"` キー）を 1 つ足し、許可リストは `package-lock.json` のみ。ワークフローの `secrets` ジョブは `fetch-depth: 0` でチェックアウトし、`gitleaks/gitleaks-action` を `GITLEAKS_CONFIG: .gitleaks.toml`、`GITLEAKS_ENABLE_COMMENTS: false` で実行する。ジョブの権限は `contents: read` と `pull-requests: read`。
- **変更**: `title` を `i-revolve-therefore-i-am-bga gitleaks config` にする以外は流用する。許可リストの `package-lock.json` はそのまま。`composer.lock`（第 2 段階で追加）は PE も許可リストに入れていない。誤検出が出たら、広い除外ではなく、理由つきでそのファイルだけを許可リストに足す。
- `data/` は CI に存在しないので、PE が懸念した「約 90 MB の画像を gitleaks が読むか」は問題にならない。逆に、`data/` が誤ってコミットされた場合は R1 が先に失敗する。
- ライセンス: リポジトリの所有者は個人アカウント `ZoneTsuyoshi` なので、gitleaks-action のライセンスキーは不要（PE 005 §2.3、MJ 004 §7）。
- 検出時の対応: 履歴から消すだけでなく、必ず鍵を失効・再発行する（BGA Studio の SSH 鍵は絶対ルール 7）。

#### 2.4 禁止ファイル・`src/` 衛生・`data/` の追跡禁止・リンクの境界（S2・R1・S3）

実装は `scripts/lint/check-repo.mjs` 1 本（外部依存なし。`git ls-files -z` の出力を検査。`npm run lint:repo`）。PE のスクリプトを土台に、次のとおり変える。`git ls-files` は必ず `-z` で読む（`src/` 以外のパスには日本語・空白が入りうる）。

PE から**そのまま流用**する検査（PE 005 §2.4）:

1. 認証情報などの禁止ファイル（`.env`、`.env.*`（`.env.example` を除く）、`sftp.json`、`*.pem`、`id_rsa*`、`id_ed25519*`）。`.gitignore` と同じ集合。
2. `src/` 配下の開発用ファイル（`package.json`、`package-lock.json`、`composer.json`、`composer.lock`、`tsconfig*.json`、`eslint.config.*`、`stylelint.config.*`、`.*rc*`、`phpcs.xml*`、`phpstan.neon*`、`*.test.*`、`*.spec.*`）と開発用ディレクトリ（`node_modules`、`tests`）。
3. `src/` 配下のファイル名が `[A-Za-z0-9_.\-/]` だけであること（空白・括弧・非 ASCII の禁止。BGA の画像ファイル名の制約。003 §9）。`src/` のみに適用する。
4. `src/` に BGA の非推奨ファイルを置かない（003 §3）。禁止: `states.inc.php`、`material.inc.php`、`*.action.php`、`*.view.php`、`*.tpl`、`src/ts/`、`rollup.config.mjs`、`src-disabled/`。
5. BGA 配布のスタブ `_ide_helper.php` と `bga-framework.d.ts` が、リポジトリのどこにも追跡されていないこと（再配布不可。`.gitignore` だけでは `git add -f` を防げない）。

PE から**外す**検査:

- PE の「スプライト生成物（`src/img/pe_*.webp`、`src/modules/js/cards/sprites.js`）が追跡されていないこと」。姉妹は生成物をコミットしないが、このリポジトリはコミットする（003 §9）ので、検査を入れない。

このリポジトリで**新規に足す**検査:

- **R1（`data/` が Git に入っていないこと）**: (a) `git ls-files` に `data/` で始まるパスが 1 つでもあれば失敗。(b) `.gitignore` に行 `/data/` が存在すること（消された場合の検知）。同じ要領で `.worktrees/`・`node_modules/`・`vendor/`・`build/` で始まる追跡パスも失敗にする。PE の `check-data-unchanged.mjs`（`BASE_SHA` と `git diff` が要る）は**採用しない**。理由: 検査の対象が「差分」ではなく「ツリーの状態」になり、基準コミットも全履歴のチェックアウトも要らず（`repo` ジョブの `fetch-depth: 0` と `BASE_SHA` の受け渡しが不要）、`workflow_dispatch` や新規ブランチの push でも同じ検査ができるため。PR 内で追加して後のコミットで消した場合はこの検査をすり抜けるが、PR は squash merge で `main` の履歴には残らない。
- **S3（ドキュメントのリンクの境界）**: 追跡している `*.md` から、コードブロックとコードスパンを除いて `[文](相対パス)` のリンク先を取り出し、ファイルのあるディレクトリから解決した先が (a) `data/` 配下、(b) リポジトリの外（解決すると `..` で始まる）のどちらかなら失敗。`http(s):` などのスキームつきと `#` のみのリンクは見ない。参照形式のリンクは見ない（M2 が先の存在を見る）。目的は §2.2 のとおり、ローカルでは通って CI で落ちるリンクを手元で見つけること。現状の 13 ファイルに簡易版を実行し、違反 0 件を確認した。
- **改行コード**: `src/` の追跡ファイルのうち PHP・JS・CSS・JSONC・SQL に CR（`\r`）が含まれていたら失敗。MJ の第 2 段階で、Studio の雛形 `Game.php` が CRLF で、整形の対象になったため。PHPCS の PSR-12 は PHP の改行を見るが、JS・CSS・JSONC は見ない。
- **大きな追跡ファイル**: `src/img/` 以外で 1,000,000 バイトを超える追跡ファイルは失敗。原本の画像・PDF が `data/` 以外の場所（`docs/` など）に置かれるのを防ぐ。`src/img/` は A1 が見る。未確定事項 No.4。

失敗時は `check-repo: N 件の問題があります。` と各行 `<ファイル>: <理由>` を出して終了コード 1。`src/` が無い間は `src/` 向けの検査が空振りして成功する。S3 の判定部分（リンク先の解決）は関数として切り出し、`scripts/lint/check-repo.test.mjs`（`node --test`）で、`../` の解決・コードスパン内の除外・`data/` を指すリンク・スキームつきリンクを固定する。

#### 2.5 PHP の構文・規約・`strict_types`（P1・P2・P2'）

- **【姉妹の実装】** ツールは `composer.json` の `require-dev` に固定し、`composer.lock` をコミットする。`config.platform.php` は `8.4.0`。CI は `setup-php`（`php-version: '8.4'`、`coverage: none`、`tools: composer`）。Composer のダウンロードキャッシュだけを `actions/cache` で保存し、`vendor/` はキャッシュしない。`composer lint` は `lint:syntax`（`parallel-lint`）と `lint:cs`（`phpcs`）の 2 つ。
- **【姉妹の実装】** `phpcs.xml.dist`: PSR-12 ベース。行長は `lineLimit` 140・`absoluteLineLimit` 0、`Generic.Files.LineLength.TooLong` を error に引き上げる。`PSR1.Methods.CamelCapsMethodName` は `src/modules/php/Game.php` に限り除外する（`debug_` で始まるメソッドが Studio のデバッグメニューに出るため。003 §4.2）。`ignore_warnings_on_exit` は設定しない（警告も失敗にする）。
- **【姉妹の実装】** `declare(strict_types=1);` の検査は `scripts/lint/check-strict-types.mjs`（`npm run lint:strict`）。PHP を実行しないので `node` ジョブに置く。
- **変更**:
  - `composer.json`: `name` は `zonetsuyoshi/i-revolve-therefore-i-am-bga`。`autoload` は `Bga\\Games\\IRevolveThereforeIAm\\` → `src/modules/php/`（003 §4.1 で確定した名前空間。大文字小文字混在）。`autoload-dev` は `IRevolveTests\\` → `tests/`（003 §10 の暫定名）。`phpcs.xml.dist` の `ruleset name` と説明文も置換する。
  - **対象ディレクトリは存在するものだけにする**。PE の `parallel-lint src tests` と `<file>tests</file>` は、`tests/` が `src/` より後にできるこのリポジトリでは、第 2 段階で `src` のみにし、`tests/` ができた PR（第 4 段階）で足す。
  - PE の `phpcs.xml.dist` にある「`Material/CardTexts.php` を行長から除外」は引き継がない。`Material/`（003 §3。002 の生成物）の行長の扱いは、002 の実装でファイルの形が決まってから、理由つきで足す（生成スクリプトが 140 文字以内に折り返せるなら除外は要らない）。
  - `PHPUnit` は第 4 段階まで `require-dev` に入れない。
- Studio の雛形を最初に取り込む PR では、MJ の第 2 段階（`phpcbf` の整形、メソッドへの `public` の明示、CRLF から LF）と同じ整形が要る可能性が高い。整形のみで動作を変えない（§3、未確定事項 No.8）。

#### 2.6 PHP の静的解析（P3）と JS の型検査（J2）

- **【姉妹の実装】** `phpstan.neon.dist`: `level: 5`、`phpVersion: 80400`、`paths` と `scanDirectories` は `src/modules/php`、`scanFiles` は `tools/bga/_ide_helper.php`。`tsconfig.json`: `allowJs`・`checkJs`・`noEmit`・`strict`、`target`/`module` は `ES2022`、`moduleResolution: Bundler`、`include: ["src/modules/js/**/*.js", "tools/bga/bga-framework.d.ts"]`。
- **【姉妹の実装】** スタブ `_ide_helper.php`（PHP）と `bga-framework.d.ts`（JS の型）は BGA の配布物で、再配布の許諾が確認できないためコミットしない（003 §3、`.gitignore` 済み）。CI の専用ジョブ `static`（表示名 `Static analysis (BGA stubs)`）が Studio から SFTP で取得して PHPStan と `tsc` を実行する（`scripts/lint/fetch-bga-stubs.sh`、`lftp`）。Secrets / Variables が無いとき（フォーク・Dependabot の PR・Secrets を渡さない `workflow_call`）は警告つきでスキップして成功し、`GITHUB_STEP_SUMMARY` に書く。`npm run lint:types` はスタブが無ければ警告してスキップし、`--require` を付けると失敗にする（CI は `--require`）。
- **変更**:
  - 第 2 段階の本体とは切り離して「2b」とする（PE と同じ）。理由: 取得に使う Secrets / Variables（`BGA_SSH_PRIVATE_KEY`、`BGA_SFTP_USER`、`BGA_SFTP_HOST`、`BGA_SFTP_PORT`、`BGA_REMOTE_DIR`、`BGA_SSH_KNOWN_HOSTS`。PE の名前）は T-005 の同期と共通で、まだ登録されていない。2b は T-005 の接続が確立し、Secrets / Variables が登録されてから入れる。名前は T-005 の仕様書と揃える。
  - `tsconfig.json` に `exclude` を付けない、`phpstan-baseline.neon` を作らない、`types/bga-globals.d.ts` を作らない（PE 005 §2.6 と同じ。最初から実装コードを書くため）。ただし Studio の雛形を取り込むなら、MJ のように一時的な除外が要る場合がある。そのときは理由と外す時期を書く。
  - `tsc` は `include` に一致するファイルが 0 件だと TS18003 で失敗する（MJ 004 が確認）。`lint:types` は `Game.js` が入ってから追加する。
  - **PHPStan はフレームワークとのメソッド名の衝突を検出できない**（スタブが古いため。003 §4.3）。この穴は P4（`ir` 接頭辞）で埋める。

#### 2.7 JS の lint（J1）と JSONC の構文（D1）、CSS（C1）

- **【姉妹の実装】** `eslint.config.mjs`（flat config、`defineConfig` と `globalIgnores`）。無視: `node_modules/`、`vendor/`、`data/`、`.worktrees/`、`build/`、`src/img/`、`tools/bga/`。全 `*.{js,mjs}` に `@eslint/js` の `recommended` と `eqeqeq`・`no-var`・`prefer-const`・`no-shadow`・`no-unused-vars`（`_` 始まりは許可）。`src/modules/js/**/*.js` は ES モジュールで、`globals.browser` と BGA のグローバル（`_`、`$`、`dojo`、`ebg`、`define`、`gameui`、`getLibUrl`、`importDojoLibs`、`importEsmLib`、`g_gamethemeurl`、`g_replayFrom`、`g_archive_mode`、`g_tutorialwritten`）を `readonly` で宣言し、`dojo` の使用は警告。`scripts/**/*.mjs`・`tests/js/*.mjs`・`*.config.mjs` は `globals.node`。`src/**/*.{json,jsonc}` は `@eslint/json` の `json/jsonc` と `recommended`（構文と重複キー。末尾カンマは不可）。実行は `npm run lint:js`（`eslint .`）。
- **【姉妹の実装】** `stylelint.config.mjs`: `extends: ['stylelint-config-standard']` のみ。実行は `stylelint "src/**/*.css" --allow-empty-input`（`npm run lint:css`）。
- **変更**: どちらもそのまま流用する。PE の `tools/preview/main.js` 向けの設定ブロックはこのリポジトリに該当が無いので省く。注意点は 3 つ。
  1. 003 §6.1 のとおり JS は `Game.js` を入口に `states/`・`board/`・`cards/`・`ui/` へ分割するため、ESLint の対象は複数ファイルになる（グロブは `src/modules/js/**/*.js` で足りる）。`modules/js/` の相対 `import` が Studio の本番 minify で動くかは 003 の未確定事項 No.3 で、lint の設定には影響しない。
  2. CSS は `src/irevolvethereforeiam.css` の 1 ファイル（003 §3）。クラス名に `ir-` を付ける規約（003 §4.3）を Stylelint の `selector-class-pattern` で強制することは**しない**。bga-cards など外部ライブラリのクラスを上書きする規則が正当に出てくるため（003 §6.2）。レビューで見る。
  3. 生成物（002 の `Material/`、009 のスプライト配置表 `sprites.js` など）もコミットするので、**生成物自身が lint を通る形で出力される**必要がある。生成スクリプトが規約どおりに出力するか、出力後に整形する。lint から生成物を除外する設定は作らない（PE は `sprites.js` を生成物として追跡せず、この点が無かった）。

#### 2.8 `ir` 接頭辞の検査（P4）

003 §4.3 が決めた規則（フレームワークを継承するクラスに足す独自メソッドは `ir` + 大文字で始める）を機械で守らせる。PE の `scripts/lint/check-method-prefix.mjs`（426 行）と自己テスト `check-method-prefix.test.mjs`（151 行）が実装済みで稼働中なので、**接頭辞だけ `pe` から `ir` に変えて流用する**。仕様の要点は PE 005 §2.10 のとおりで、ここには再掲せず、変更点と確認事項だけを書く。

- **変更点**:
  - 合格の正規表現を `^ir[A-Z]` にする（例: `irSendNotifications` は合格、`irregular`・`irq` は不合格）。許可コメントは `// ir-lint: allow <理由>`（理由は必須）。出力メッセージの `pe` を `ir` に直す。
  - 名前空間は検査の対象外判定に使わない（PE のスクリプトは親クラスの完全修飾名 `Bga\GameFramework\…` で判定する）ので、`Bga\Games\IRevolveThereforeIAm`（大文字小文字混在）でも変更は要らない。自己テストの PHP 断片の名前空間だけ置換する。
- **許可リストの初期値**（PE のスクリプトの `ALLOWED` を維持）: 魔術メソッド（`__*`）。`Table` 系は `setupNewGame`・`getAllDatas`・`getGameProgression`・`upgradeTableDb` と `debug_*`。状態クラス（`GameState` 系）は `onEnteringState`・`getArgs`・`zombie` と、`#[PossibleAction]` 付きの `act*`。003 §4.3 の許可リストと一致する。実装 PR で公式（StateDir、GamePhp）を再確認し、足りないものは根拠の URL つきで足す（未確定事項 No.7）。
- **確認済み**: Studio の初期ファイルのスタブ（`_ide_helper.php`、371 関数）に `ir` + 大文字で始まる名前は無い（003 §4.3、2026-10-20）。したがって `ir` の独自メソッドは現時点で衝突しない。
- **自己テストを必ず含める**: PE の事例表（`Table` を継承したクラスの `sendNotifications` が失敗、`irSendNotifications` が成功、属性なしの `actPass` が失敗、基底クラスを挟んだ継承、トレイト、`new class extends Table` の失敗、許可コメント、コメント・文字列中の `function` の無視ほか）をそのまま引き継ぎ、`node --test "scripts/lint/*.test.mjs"`（`npm run test:lint`）で実行する。MJ の事故（`Game::sendNotifications` と `Table::sendNotifications` の衝突。MJ #34）を再現する事例が、検査の存在理由なので落とさない。
- **実行場所**: 検査は `node` ジョブのステップ（`npm run lint:prefix`）で、`npm run lint` にも含める。自己テストは `repo` ジョブの `npm run test:lint`（§4.1）。`src/modules/php/` が無ければ対象 0 件で成功する。
- 限界（PE と同じ）: PHP の全構文は解釈しない簡易解析で、メソッドが上書きかどうかは判定せず名前だけを見る。許可リストにある名前は、フレームワークの上書きであるかを確かめない。

#### 2.9 `src/img/` の上限検査（A1）

- **【姉妹の実装】** PE の第 3 段階 A1 は、別ワークフロー `Assets`（`assets.yml`）が `data/` から webp を生成し（`npm run build:sprites`）、続けて `npm run check:sprites`（`sharp` を使い生成はしない）で検査する。上限は 1 ファイル 2,000,000 バイト、総量 15,000,000 バイト（10,000,000 バイト超で警告）、幅・高さ 4096 px、ファイル名 `^pe_[a-z0-9_]+\.webp$`、`img/` 直下のファイル数 10 以上で警告、配置情報とマニフェストの整合。`Lint gate` には入れていない（PE の理由: 生成が遅い、原本の読み込みに失敗しうる、同期の直前に同じ検査をする）。
- **変更【提案】**: このリポジトリは生成物を**コミットする**（003 §9）ので、CI は生成せず、コミットされた `src/img/` を**直接検査**する。検査は数秒で終わり、原本も `sharp`（ネイティブ依存）も要らないため、**`Lint` ワークフローの `node` ジョブとは別の小さな `assets` ジョブ**として `Lint gate` の `needs` に入れる。スクリプトは `scripts/lint/check-assets.mjs`（`npm run lint:assets`）。寸法の読み取りは純 JS の `image-size`（webp・png・jpeg・svg 対応）を使う。
- **検査内容**（上限の値は PE と同じ。BGA の公式ドキュメントの食い違い「1 枚 2 MB と 4 MB」は厳しい 2 MB を採る。003 §9・§14）:
  - 1 ファイルが 2,000,000 バイト以下。
  - `src/img/` 全体が 15,000,000 バイト以下。10,000,000 バイトを超えたら警告（GitHub の `::warning::`。失敗にしない。目標値は 009 が決めたら合わせる）。
  - 画像の幅と高さが 4096 px 以下（スプライトがモバイルで失敗しうる。003 §9）。svg は寸法を見ない。
  - ファイル名が `^[a-z0-9_]+\.(webp|png|jpg|svg)$`（空白・括弧・大文字・サブディレクトリなし。003 §3 の「サブディレクトリなし」と Img の命名制約）。接頭辞の規則（`ir_` など）は 009 が決めたら足す。
  - `src/img/` 直下のファイル数が 10 以上なら警告（Img は「十数個まで」。ルート直下の画像はすべてプリロードされる。003 §9）。
  - 拡張子が画像でないファイルが `src/img/` にあれば失敗。
- **マニフェストとの整合は本書では決めない**: 003 §9 は「生成時に同時に出力するマニフェスト（配置表）と実ファイルの整合」を原本なしの検査の候補に挙げているが、マニフェストの形式と置き場所は 009 が決める。009 の実装 PR で、`check-assets.mjs` に `--manifest` の検査を足す（拡張点として関数を分けておく）。原本と生成物の一致の検査はローカルだけで行う（003 §9）。
- `src/img/` が無い間は対象 0 件で成功する。段階は、最初の画像がコミットされるとき（§3）。

#### 2.10 テスト（T1・U1）

- **T1（Node のテスト）**: lint スクリプトの自己テスト（`npm run test:lint` = `node --test "scripts/lint/*.test.mjs"`）と、クライアントの純粋関数のテスト（`npm run test:js` = `node --test "tests/js/*.test.mjs"`。回廊の座標計算のように PHP と二重実装になるものだけ。003 §10）。どちらも PHP を使わず Secrets も要らないので、Lint の中のジョブに置き、`Lint gate` に含める（PE の `node` ジョブと同じ）。`tests/js/` が無い間は `test:js` のステップを `hashFiles('tests/js/*.test.mjs')` で省略する。JS のテストは生成物（スプライトの配置表など）に依存させない（003 §6.1。PE #41）。
- **U1（PHPUnit の単体テスト）【提案】**: CI で実行する。003 §10 は「CI での実行有無は 004 が決める」としている。PE は `Test` という**別のワークフロー**（`test.yml`）にし、`Lint gate` に含めていない（`Lint` は `workflow_call` で同期ワークフローから呼ばれるため、テストの失敗が同期の可否に直結する範囲を別に決めたい）。このリポジトリも同じ構成にする。
  - ジョブ `PHPUnit`（表示名）: `composer test`（スイート `unit`。DB 不要）。ステップは `hashFiles('tests/Unit/**/*.php')` が空なら省略する。
  - 結合テスト（MariaDB）: ジョブ `PHPUnit (MariaDB)`（`services` に MariaDB 11、`src/dbmodel.sql` を流して `Data/` と `Setup/` を検査。PE の構成）は、`dbmodel.sql` と `tests/Integration/` ができてから足す（T-007 以降）。ステップは `hashFiles('tests/Integration/**/*.php')` で省略する。本書では枠だけ決める（未確定事項 No.2）。
  - PHPUnit は PE と同じ `12.5.x` の系列から始め、`composer.json` で版を固定する（`config.platform.php` は `8.4.0` のまま）。PHPUnit 13 は `php>=8.4.1` を要求して `platform` と衝突するため、使うなら `platform` を `8.4.1` 以上に上げる判断が先に要る。
  - ローカルでは `composer test`。PHP が無い環境では Docker（`php:8.4-cli`）で実行する（PE の `tests/Dockerfile` と `tests/run-unit.sh` を流用。未確定事項 No.5）。

### 3. 段階的な導入計画

姉妹と同じく、段階ごとに別 PR で導入する。このリポジトリは `src/` がまだ無く、実ファイルの無い設定を先に書くと検証できない設定が残るため、この順にする。

| 段階 | 追加する検査 | 前提 | ジョブ / ワークフロー |
| --- | --- | --- | --- |
| 第 1 段階 | M1 Markdown、M2 リンク、S1 gitleaks、S2 禁止ファイル、R1 `data/` の追跡禁止、S3 リンクの境界、T1（`check-repo` の自己テスト） | `src/` が無くてよい。Markdown は既にある | `markdown`、`secrets`、`repo`、`gate`（`Lint`） |
| 第 2 段階 | P1 構文、P2 規約、P2' `strict_types`、P4 `ir` 接頭辞、J1 ESLint、D1 JSONC、C1 CSS、T1（`ir` 接頭辞の自己テスト、`tests/js/`） | `src/` に最初の PHP / JS / CSS / JSONC がマージ済み、または同じ PR に含める。Secrets は不要 | `php`、`node`（`Lint`） |
| 第 2b 段階 | P3 PHPStan、J2 `tsc` | 第 2 段階が済み、T-005 の接続が確立し、`static` に必要な Secrets / Variables が登録済み | `static`（`Lint`） |
| 第 3 段階 | A1 `src/img/` の上限 | `src/img/` に最初の画像がコミット済み。スプライトの生成スクリプト（T-009）の完成は待たない | `assets`（`Lint`） |
| 第 4 段階 | U1 PHPUnit | `tests/Unit/` に最初のテストがある（PHPUnit 導入）。結合テストは `dbmodel.sql` ができてから | `PHPUnit`（ワークフロー `Test`。`Lint gate` に含めない） |

- 第 1 段階は本書のマージ後すぐに実装する。M1・M2・S1・S2・R1・S3 は `src/` が無くても、既存の Markdown に対して動作確認できる。
- 第 2 段階を、`src/` の最初の実装 PR に同梱するか、その直後の別 PR にするかは、`src/` の最初の PR を計画するときに決める（PE と MJ は雛形の直後の別 PR だった。未確定事項 No.1）。同梱する場合も、`ir` 接頭辞の検査は最初の `Game.php` から効かせる。
- **`gate` は第 1 段階で作り、以降は `needs` にジョブを足すだけにする**（PE と同じ）。`Lint gate` という表示名を変えない。
- 対象が揃っていない間もステップが失敗しないようにする（PE の作り）。`php` ジョブのステップに `if: ${{ hashFiles('src/**/*.php') != '' }}` を付ける。`node` ジョブは、ESLint が設定ファイル自身を常に対象に持つこと、Stylelint が `--allow-empty-input` を持つこと、`lint:strict`・`lint:prefix`・`lint:assets` が対象 0 件で成功することで、ガード無しで通る。`hashFiles` はステップレベルの `if` で使う（ジョブレベルでは使えない）。
- 第 2b 段階の `static` は、PE のとおり、Secrets / Variables が無いときは警告つきでスキップして成功する。したがって、いつ `gate` に入れても、フォークや Dependabot の PR が止まらない。**ただしスキップされている間は PHPStan と `tsc` が実行されていない**ことを、PR の画面（`GITHUB_STEP_SUMMARY`）で見落とさない。
- MJ の第 2 段階の教訓: Studio の雛形（`PlayerTurn`・`NextPlayer`・`EndScore`、003 §3）を取り込むと、そのままでは PHPCS・ESLint・`tsc` が大量に指摘する。雛形を書式の整形のみ（動作を変えない）で lint に通してから第 2 段階を入れる。中身の書き換えが要るものは、一時除外にせず、雛形を置き換える実装 PR（T-006 以降）まで取り込まないほうがよい（未確定事項 No.8）。

### 4. ワークフロー構成

#### 4.1 `Lint`（`.github/workflows/lint.yml`）

PE の `lint.yml` を土台にし、トリガー・`permissions`・`concurrency`・SHA 固定・`timeout-minutes` は同じにする。ジョブは次のとおり。

| ジョブ ID | 表示名 | 段階 | 内容 | PE との違い |
| --- | --- | --- | --- | --- |
| `markdown` | `Markdown` | 1 | checkout → setup-node（`.nvmrc`、`cache: npm`）→ `npm ci` → `npm run lint:md` → lychee（`lychee.toml`） | 同じ |
| `secrets` | `Secrets` | 1 | checkout（`fetch-depth: 0`、`persist-credentials: false`）→ gitleaks。権限は `contents: read` と `pull-requests: read` | 同じ |
| `repo` | `Repository rules` | 1 | checkout → setup-node → `npm run lint:repo` → `npm run test:lint`（lint スクリプトの自己テスト。どちらも外部依存が無いので `npm ci` は不要。第 2 段階で `ir` 接頭辞の自己テストが加わってもここで実行する） | **`fetch-depth: 0` と `BASE_SHA` の受け渡しが要らない**（R1 が差分でなくツリーの検査のため） |
| `php` | `PHP` | 2 | checkout → setup-php（8.4）→ Composer キャッシュ → `composer install` → `composer lint`。各ステップは `hashFiles('src/**/*.php') != ''` のとき | 同じ |
| `node` | `JS / CSS / JSONC` | 2 | checkout → setup-node → `npm ci` → `lint:js` → `lint:strict` → `lint:prefix` → `test:js`（`tests/js` がある場合）→ `lint:css`。各ステップに `if: ${{ !cancelled() }}`（先行ステップが失敗しても続けて、1 回で全エラーを見る） | 同じ（`lint:prefix` は `ir`）。`test:lint` は PE と違い `repo` ジョブで実行する |
| `static` | `Static analysis (BGA stubs)` | 2b | PE と同じ（スタブを取得して `composer lint:stan` と `npm run lint:types -- --require`） | 同じ。T-005 の後 |
| `assets` | `Assets` | 3 | checkout → setup-node → `npm ci` → `npm run lint:assets` | **PE は別ワークフローで生成する**。ここでは lint に含める（§2.9） |
| `gate` | `Lint gate` | 1 | `if: ${{ always() }}`、`needs` に上記すべて。結果に `failure` / `cancelled` / `skipped` があれば失敗 | 第 1 段階の `needs` は `[markdown, secrets, repo]` |

- `lint:prefix`・`lint:strict`・`lint:assets` は PHP を実行しないので、`node` / `assets` ジョブ（Secrets なし）に置く。
- 実行時間の見込み（PE の実績。`Lint` は 37〜50 秒）。リポジトリは非公開で Free プランなので Actions の無料枠（分）に上限があるが、PE と同規模なら 1 PR あたり数分で、通常の運用では超えない見込み（**【未確認】**。超えそうなら Markdown だけの PR で重いジョブを省く条件を検討する）。

#### 4.2 `Test`（`.github/workflows/test.yml`。第 4 段階）

PE の `test.yml` を土台にする。トリガーと `concurrency` は `Lint` と同じ形で、`workflow_call` は付けない。ジョブは `PHPUnit`（単体）と、結合テストを足すときの `PHPUnit (MariaDB)`。`Lint gate` には含めない（§2.10）。

### 5. ローカル実行

チェックの実体を `package.json` / `composer.json` の `scripts` に置く（§1）。`npm run lint` は最初の失敗で止まる `&&` 連結で、検査のみ。

| コマンド | 内容 | 段階 |
| --- | --- | --- |
| `npm run lint` | `lint:md && lint:repo`（段階が進むと `lint:js && lint:strict && lint:prefix && lint:css && lint:assets` が加わる） | 1〜 |
| `npm run lint:md` | markdownlint | 1 |
| `npm run lint:links` | lychee（`brew install lychee` が前提。`npm run lint` に含めない） | 1 |
| `npm run lint:repo` | S2・R1・S3 | 1 |
| `npm run test:lint` | lint スクリプトの自己テスト | 1 |
| `npm run lint:js` / `lint:strict` / `lint:prefix` / `lint:css` | ESLint（JS と JSONC）、`strict_types`、`ir` 接頭辞、Stylelint | 2 |
| `npm run test:js` | クライアントの純粋関数のテスト | 2 |
| `composer lint` | `parallel-lint` と `phpcs` | 2 |
| `npm run lint:types` / `composer lint:stan` | `tsc` / PHPStan（`tools/bga/` にスタブが必要。ローカルに置いた場合のみ。CI は `static` ジョブ） | 2b |
| `npm run lint:assets` | A1 | 3 |
| `composer test` | PHPUnit の単体テスト | 4 |

- gitleaks は CI のみ（ローカル実行は不要）。lychee は上記のとおり任意。
- **PR を作る前に、そのワークツリーで `npm run lint`（PHP を触ったら `composer lint` も）を実行する**。結果の確認はメインエージェントが `gh pr checks` で行う（§6）。
- この環境のローカルには PHP も Composer も無い（2026-10-20 時点）。PHP の lint はローカルでは Docker か CI で確認する（未確定事項 No.5）。Node は 23.11.0 で、`engines.node`（`^22.13.0 || >=24`）の範囲外だが、MJ 004 が同じ版で ESLint が動くことを確認している。`.nvmrc` の 24 に揃えるのが望ましい（作者側の準備、任意）。
- pre-commit フックは導入しない（PE・MJ と同じ）。

### 6. PR で必須にするチェックと、マージの運用

- **必須とするのは `Lint gate` の 1 つ**。ジョブ単位では選ばない（ジョブの追加・改名のたびに設定を直さずに済む）。
- **`main` の保護は設定しない**（作者の決定。PE 005 §5 と同じ）。このリポジトリは非公開の Free プランで、そもそもブランチ保護の API が使えない（確認済み。上記）。
- **代わりの担保は運用**: 第 4 段階までは、マージ前にメインエージェントが `gh pr checks <PR 番号>` で、実行されたチェックがすべて成功であることを確かめる。具体的には `Lint gate`、第 4 段階以降は `PHPUnit`（と導入していれば `PHPUnit (MariaDB)`）。**1 つでも失敗・実行中の PR はマージしない**。サブエージェントはマージしない。Dependabot の PR も同じ。
- `main` への push でも lint は実行される。保護の代わりではなく、直接 push や運用ミスを事後に検知するため。
- 実装 PR では、`docs/development/git-workflow.md` の「`main` を保護: PR 必須、ステータスチェック（lint）必須、force push 禁止」を「保護は設定しない（Free プランの非公開リポジトリでは設定できない）。運用で担保する」に直す。
- 将来、保護を設定できるようになった場合（リポジトリを公開する、または有料プランにする）は、ワークフローが 1 回以上実行された後に、Settings → Branches（または Rulesets）で必須チェックに `Lint gate` を指定する。

### 7. 実装時に追加・コピーするファイル

「コピー」は PE の同名ファイルを元にし、記載の点だけ置換する。PE のパスは `/Users/zone/Developments/project-edgeworth-bga/` 配下（読み取り専用の手本）。

| パス | 段階 | PE から | 内容・置換点 |
| --- | --- | --- | --- |
| `.github/workflows/lint.yml` | 1（以降は追記） | コピー＋変更 | §4.1 のジョブ構成。第 1 段階は `markdown`・`secrets`・`repo`・`gate`。アクションの SHA は PE の値をコピーして最新を確認 |
| `.github/dependabot.yml` | 1 | コピー | `github-actions` と `npm` の週次更新（グループ化）。`composer` は第 2 段階で足す |
| `package.json`、`package-lock.json` | 1 | コピー＋変更 | `name` は `i-revolve-therefore-i-am-bga`。第 1 段階の devDependencies は `markdownlint-cli2` のみ。`scripts` は `lint`・`lint:md`・`lint:links`・`lint:repo`・`test:lint`。第 2 段階で ESLint 系・Stylelint・TypeScript と `lint:js`・`lint:strict`・`lint:prefix`・`lint:css`・`lint:types`・`test:js`、第 3 段階で `image-size` と `lint:assets`。`sharp`・`build:*`・`check:*` は T-009 以降の別の判断 |
| `.nvmrc` | 1 | コピー | `24` |
| `.markdownlint-cli2.jsonc` | 1 | コピー | 先頭のコメントの参照先のみ（§2.1） |
| `lychee.toml` | 1 | コピー | 変更なし（§2.2） |
| `.gitleaks.toml` | 1 | コピー＋変更 | `title` のみ（§2.3） |
| `scripts/lint/check-repo.mjs`、`check-repo.test.mjs` | 1 | コピー＋変更 | §2.4。スプライト生成物の検査を外し、R1・S3・改行コード・大きな追跡ファイルを足す。`check-data-unchanged.mjs` は採用しない |
| `.gitignore` | 1 | 追記なし | 既に `/data/`、スタブ、`.phpunit.result.cache` を持つ。`build/` を使うなら追記 |
| `composer.json`、`composer.lock` | 2 | コピー＋変更 | §2.5。`phpunit` は第 4 段階まで入れない |
| `phpcs.xml.dist` | 2 | コピー＋変更 | `ruleset name`・説明文の置換。`<file>` は `src` のみ。`CardTexts` の除外は持ち込まない |
| `scripts/lint/check-strict-types.mjs` | 2 | コピー | 変更なし |
| `scripts/lint/check-method-prefix.mjs`、`check-method-prefix.test.mjs` | 2 | コピー＋変更 | 接頭辞を `ir` に（§2.8） |
| `eslint.config.mjs` | 2 | コピー＋変更 | `tools/preview` のブロックを省く |
| `stylelint.config.mjs` | 2 | コピー | 変更なし |
| `phpstan.neon.dist`、`tsconfig.json` | 2b | コピー | 変更なし（`exclude` と baseline は作らない） |
| `scripts/lint/check-types.mjs`、`fetch-bga-stubs.sh` | 2b | MJ からコピー | 変更なし。PE にも同等のものがある。Secrets / Variables の名前は T-005 と共通 |
| `scripts/lint/check-assets.mjs`（と自己テスト） | 3 | 新規 | §2.9。PE の `check-sprites.mjs` の上限の値を持ち込む |
| `tests/phpunit.xml.dist`、`.github/workflows/test.yml` ほか | 4 | コピー＋変更 | §2.10、§4.2 |
| `docs/development/cicd.md` | 1 | 更新 | 「未導入」の記述を導入済みの内容に書き直す。PE の同名文書の構成が手本。`CLAUDE.md` の「CI/CD」節から参照 |
| `docs/development/git-workflow.md`、`task-workflow.md` | 1 | 追記 | 「PR 作成前に `npm run lint`」。`main` の保護に関する文言の修正（§6） |
| `docs/specs/README.md`、`docs/tasks.md`、本書のステータス | 1 | 更新 | 本書を Implemented に（全段階が済んだとき）。T-004 の状態更新。「GitHub リポジトリの設定」行を現状（ブランチ自動削除と squash のみは設定済み）に合わせる |

- PE の `.claude/settings.local.json` は個人設定で、コピーしない。
- PE の `scripts/bga-sync/`、`.github/workflows/bga-sync.yml` は T-005 のもの。本書では扱わない。
- 追加する依存は、すべてロックファイル（`package-lock.json`、`composer.lock`）で固定する。

### 8. 作者側の準備

**必須の作業は無い**（なし）。T-004 の導入に作者の操作は要らない。確認できた設定は次のとおり（2026-10-20）。

| 項目 | 現状 | 要否 |
| --- | --- | --- |
| マージ後のブランチ自動削除、squash のみ許可 | 設定済み | 不要 |
| Actions の既定権限が Read | 設定済み | 不要 |
| `main` の保護 | 設定しない（Free プランの非公開リポジトリでは設定不可） | 不要 |
| gitleaks のライセンスキー | 個人アカウントなので不要 | 不要 |
| Dependabot のセキュリティアラート | 無効 | 任意。有効にすると脆弱な依存の通知が来る（版の更新 PR は `dependabot.yml` で動く） |
| ローカルの Node を 24 にする | 現在 23.11.0 | 任意（`.nvmrc` に揃える） |

SSH 鍵と Secrets / Variables の登録は、第 2b 段階と T-005 のための作者の作業で、本書の対象外（`docs/tasks.md` の「ユーザー側の準備事項」にある）。

### 9. 後続への申し送り

- **T-005（同期）**: `Lint` を `workflow_call` で呼べるようにしておく。Secrets は `workflow_call` では既定で渡らないので、`static` は警告つきでスキップされる（`secrets: inherit` を使うかは T-005 が決める）。Secrets / Variables の名前は `static` と共通にする。`src/` に開発用ファイルが混入しないことは S2 が保証する。
- **T-002（カードデータ）**: `Material/` と `card.csv` の差分検査（PE の `lint:cards` に相当）を作るなら、`npm run lint` に足す。サイト側の `card.csv` は CI から見えないので、検査は `src/` 内で閉じる形（取り込んだコピーとの整合）にする必要がある。002 が決める。
- **T-009（UI・アセット）**: `src/img/` の命名規則（接頭辞）、マニフェストの形式と置き場所、`sprites.js` のような生成物の書式（lint を通ること）を決める。決まったら A1 の規則（§2.9）に足す。
- **T-006 以降**: 最初の状態クラスで `ir` 接頭辞の許可リストを実機確認する（`onLeavingState` など、使うフレームワークのメソッドが増えたら根拠つきで足す）。

## エッジケース

- `src/` が存在しない間（第 1 段階）: S2 の `src/` 配下の検査は空振りして成功する。第 2 段階以降のジョブはまだ無い。
- `data/` を誤って `git add -f` した場合: R1 が失敗する。`.gitignore` の `/data/` 行を消した場合も R1 が失敗する。
- ドキュメントに `data/…` やサイト側リポジトリへのリンクを書いてしまった場合: S3 がローカルで失敗する。CI では M2 も失敗する。直し方はコード表記にする（CLAUDE.md の規約）。
- `.worktrees/` 配下のワークツリーで lint を回す場合: そのワークツリーには `data/` が無く、`git ls-files` はそのワークツリーの追跡ファイルを返す。直下の作業ツリーで回す場合は `data/` と他のワークツリーが実在するが、各ツールが対象外にする（§1）。
- フォークや Dependabot の PR: Secrets が渡らないため、`static`（第 2b 段階）は警告つきでスキップして成功する。それ以外は Secrets を使わない。
- `main` への squash merge 直後の push: lint が再実行される。R1・S3 は状態の検査なので、PR で通っていれば通る。
- Studio の雛形（`PlayerTurn`・`NextPlayer`・`EndScore` など）を取り込む最初の PR: 雛形の独自メソッドに `ir` が付いていない場合は、リネームするか、許可リストに根拠つきで足す（MJ では雛形の整形が必要だった）。
- 生成物（`Material/`、スプライトの配置表）が lint に落ちる場合: 生成スクリプトの出力を直す。除外で逃げない（§2.7）。
- BGA がフレームワークのメソッドを新しく追加した場合: 許可リストにあるメソッドは影響を受けないが、`ir` が付いていない独自メソッドは今後も衝突しうる。`ir` 接頭辞の規則そのものが安全策。
- lychee のフラグメント検査: 日本語の見出しへのアンカーが GitHub と一致するかは、最初に日本語アンカーへのリンクを書く PR で実機確認する（PE は成功している。**【未確認】**）。

## 未確定事項

ルールではなく技術判断なので、暫定案に理由を添える。メインエージェントが決められる。

| No. | 質問 | 暫定案 | 回答 |
| --- | --- | --- | --- |
| 1 | 段階導入（第 1 段階は本書のマージ後すぐ、第 2 段階は `src/` の最初の PR と同時またはその直後、第 2b は T-005 の後、第 3 は最初の画像の後、第 4 は最初の単体テストの後）でよいか。第 2 段階を最初の `src/` 取り込み PR に同梱するか、直後の別 PR にするか | 段階ごとに別 PR。第 2 段階は `src/` の最初の PR の直後の別 PR。ただし `ir` 検査は最初の `Game.php` から効かせるため、同じ週のうちに入れる。理由: PE と MJ がそうで、雛形の整形（書式のみ）と設定の追加を分けるとレビューしやすい | メインエージェントの判断で暫定案のとおり（2026-10-20。技術判断） |
| 2 | 単体テスト（PHPUnit）を CI でどう実行するか。(a) `Lint gate` に含める、(b) PE のように別ワークフロー `Test` にして含めない。結合テスト（MariaDB）をいつ足すか。PHPUnit の版（12.5.x か、13 系にして `platform.php` を `8.4.1` 以上にするか） | (b)。結合テストは `dbmodel.sql` ができてから（T-007 以降）。版は PE と同じ 12.5.x。理由: `Lint` は `workflow_call` で同期から呼ばれ、テストの失敗が同期を止める範囲を T-005 で別に決められる。13 系は `php>=8.4.1` を要求して `platform.php: 8.4.0` と衝突する。BGA の本番 PHP の細かい版は未確認で、12.5.x にしておけば `platform` を動かさずに済む | メインエージェントの判断で暫定案のとおり（2026-10-20。技術判断） |
| 3 | A1（`src/img/` の上限検査）を `Lint gate` に含めるか。PE は別ワークフロー（生成が必要なため）で、含めていない | 含める（`assets` ジョブ）。理由: このリポジトリは生成物をコミットするので CI は生成せず、数秒で終わる。原本もネイティブ依存（`sharp`）も要らない（寸法の読み取りは純 JS の `image-size`）。上限を超える画像が `main` に入ると BGA の提出で問題になる（003 §9） | メインエージェントの判断で暫定案のとおり（2026-10-20。技術判断） |
| 4 | `check-repo.mjs` に足す検査（S3 のリンクの境界、`src/` の CR 検査、`src/img/` 以外の 1,000,000 バイト超の追跡ファイルの禁止）は過剰ではないか | 3 つとも採用。理由: いずれも数十行で、(S3) ローカルでは通って CI で落ちるリンクを手元で見つける、(CR) MJ で実際に必要になった、(大きなファイル) 原本の混入が `data/` の外に回り込むのを防ぐ。邪魔になれば削る | メインエージェントの判断で暫定案のとおり（2026-10-20。技術判断） |
| 5 | ローカルに PHP が無い環境で `composer lint` をどう回すか | Docker（`php:8.4-cli`）で回す手順を `docs/development/cicd.md` に書き、PE の `tests/Dockerfile` を流用する。CI が正で、ローカルは任意。理由: 作者の環境に PHP を入れさせずに済み、CI と同じ版で再現できる。`npm run lint:php` のような専用の入口を作るかは第 2 段階の実装者が決める | メインエージェントの判断で暫定案のとおり（2026-10-20。技術判断） |
| 6 | CSS のクラス名の接頭辞 `ir-`（003 §4.3）を Stylelint で強制するか | 強制しない。理由: bga-cards など外部ライブラリのクラスの上書きが正当に出る（003 §6.2）。許可パターンを列挙するより、レビューで見るほうが保守が軽い。実装後に違反が多ければ再検討 | メインエージェントの判断で暫定案のとおり（2026-10-20。技術判断） |
| 7 | `ir` 検査の許可リストの初期値（PE の `ALLOWED` を維持）に、公式ドキュメントで足すべきものがあるか | 実装 PR で StateDir・GamePhp を再確認し、足りないものを根拠の URL つきで足す（PE 005 の未確定事項 No.3 と同じ扱い）。理由: 足し忘れは lint が失敗するだけで、Studio での事故にはならない（失敗側に倒れる） | メインエージェントの判断で暫定案のとおり（2026-10-20。技術判断） |
| 8 | Studio の雛形を取り込む最初の `src/` PR の扱い。雛形は MJ で CRLF・`public` の欠落・未使用引数などの指摘があった | 取り込む PR で書式の整形のみ行い（動作は変えない）、lint に通す。一時除外（`tsconfig.json` の `exclude`、PHPStan の baseline）は、MJ のように必要になったときだけ、理由と外す時期を書いて足す。理由: 最初から通る状態で始めると、除外の外し忘れが残らない | メインエージェントの判断で暫定案のとおり（2026-10-20。技術判断） |
| 9 | PE の `.nvmrc`（24）・`engines.node`（`^22.13.0 \|\| >=24`）をそのまま採用してよいか。作者のローカルは Node 23.11.0 | そのまま採用。理由: CI は `.nvmrc` の 24 で動き、ローカルの 23.11.0 でも MJ で動作を確認している。ローカルの Node は任意で 24 に更新 | メインエージェントの判断で暫定案のとおり（2026-10-20。技術判断） |

## 受け入れ条件

この仕様が正しく実装されたと判断するための条件。段階ごとに満たす。

第 1 段階:

- [ ] `Lint` ワークフローが `pull_request`・`main` への `push`・`workflow_dispatch`・`workflow_call` で動き、`Lint gate` が `markdown`・`secrets`・`repo` の結果を集約する
- [ ] `main` の Markdown に対して `npm run lint:md` と lychee（内部リンク）が 0 件で通る
- [ ] ダミーの秘密鍵（`-----BEGIN OPENSSH PRIVATE KEY-----`）や `sftp.json` をコミットした PR で、gitleaks または `lint:repo` が失敗する（確認後は取り消す）
- [ ] `data/` 配下のファイルを `git add -f` でコミットした PR、および `.gitignore` から `/data/` を消した PR で `lint:repo` が失敗する（確認後は取り消す）
- [ ] Markdown に `data/` や `../` で始まるリポジトリ外へのリンクを書いた PR で `lint:repo`（S3）が失敗し、コード表記にすると成功する
- [ ] `src/` が無くても全ジョブが成功し、`npm run lint` を直下の作業ツリー（`data/` と `.worktrees/` がある）で実行しても、それらを検査しない
- [ ] `main` の保護は設定せず、`gh pr checks` で確認する運用（§6）が `docs/development/` に書かれている

第 2 段階:

- [ ] `composer lint` が `src` の PHP の構文と PSR-12（行長 140）を検査し、`npm run lint:js`・`lint:css`・`lint:strict` が通る
- [ ] `npm run test:lint` が、`ir` 接頭辞の検査の事例（`Table` を継承したクラスの `sendNotifications` が失敗、`irSendNotifications` が成功ほか、§2.8）を満たす
- [ ] `Game` に `sendNotifications` のような `ir` の無いメソッドを足した PR で `lint:prefix` が失敗し、`irSendNotifications` にすると成功する
- [ ] `src/` に `package.json` や `states.inc.php` を置いた PR、`src/` のファイルに CRLF が含まれる PR で `lint:repo` が失敗する
- [ ] `src/` に PHP が無い PR（Markdown だけの PR）でも `php` ジョブが成功する（ステップの省略）

第 2b 段階（T-005 の後）:

- [ ] `static` ジョブが Studio からスタブを取得して PHPStan（level 5）と `tsc` を実行し、Secrets が無いときは警告つきでスキップして成功する

第 3 段階:

- [ ] `src/img/` が、1 ファイル 2,000,000 バイト・総量 15,000,000 バイト・4096 px・ファイル名の規則を満たさないとき、`lint:assets` が失敗する。10,000,000 バイト超とファイル数 10 以上は警告になる

第 4 段階:

- [ ] `Test` ワークフローの `PHPUnit` が `tests/Unit/` を実行し、失敗した PR を `gh pr checks` で検知できる。`Lint gate` には含まれない
