# CLAUDE.md

自作ボードゲーム『我廻る、故に我あり』（I Revolve, Therefore I Am）を Board Game Arena（BGA）に実装するリポジトリ。
ユーザーとのやり取り、ドキュメント、コミットメッセージ、PR は日本語で書く。

姉妹プロジェクト [minimum-journey-bga](https://github.com/ZoneTsuyoshi/minimum-journey-bga)（MINIMUM JOURNEY の BGA 実装。ローカルでは `../minimum-journey-bga`）と同じ運用ルールで進める。仕様書・CI 設定・BGA 実装の書き方で迷ったら、まず姉妹プロジェクトの該当箇所を参考にする（ただし内容はこのゲーム用に読み替え、そのままコピーしない）。

## 絶対に守るルール

1. **main に直接コミットしない。** すべての変更はタスク用ブランチ → PR → マージで行う。
2. **タスクごとにワークツリーを `.worktrees/` 配下に作成して作業する。** リポジトリ直下の作業ツリーは常に `main` のままにする。
3. **仕様書が先。** 実装の前に `docs/specs/` に仕様書を作成し、PR でユーザーの承認を得る。仕様書のない実装はしない。
4. **各タスクはサブエージェント（sonnet）に委託する。** メインエージェントは分解・指示・レビュー・Git 操作の統括を担当する。
5. **PR マージ後は、該当のワークツリー・ローカルブランチ・リモートブランチを削除する。**
6. **`data/` は原本。編集・移動・削除しない。Git にも上げない（`.gitignore` 済み）。** 加工が必要なら `src/` 側に生成物を作り、生成物のほうをコミットする。
7. **認証情報をコミットしない。** BGA Studio の認証情報（SSH 秘密鍵とユーザー名）は GitHub Secrets のみに置く。ホスト・ポート・リモートパス・known_hosts のような公開情報は GitHub Variables に置いてよい。

## タスクの進め方（要約）

詳細は [docs/development/task-workflow.md](docs/development/task-workflow.md) と [docs/development/git-workflow.md](docs/development/git-workflow.md)。

```bash
# 1. ワークツリー作成（メインエージェントが実行）
git fetch origin
git worktree add .worktrees/<slug> -b <type>/<slug> origin/main

# 2. サブエージェント（sonnet）に委託 → コミット → push → PR 作成
git -C .worktrees/<slug> push -u origin <type>/<slug>
gh pr create --base main --head <type>/<slug>

# 3. マージ後の後始末
git worktree remove .worktrees/<slug>
git branch -D <type>/<slug>
git push origin --delete <type>/<slug>   # GitHub 側で自動削除済みなら不要
git fetch --prune && git pull --ff-only origin main
```

- ブランチ名: `<type>/<slug>`（`type` は `docs` / `feat` / `fix` / `ci` / `chore` / `refactor`）。ワークツリーのディレクトリ名は `<slug>`。
- コミットは Conventional Commits（例: `docs(specs): ゲームルール仕様を追加`）。
- 1 タスク = 1 ブランチ = 1 ワークツリー = 1 PR。仕様書 PR と実装 PR は分ける。
- PR は squash merge。マージはユーザーの承認後に行う。

### サブエージェントへの委託

- Agent ツールを `model: "sonnet"` で起動する。
- Agent ツールの `isolation: "worktree"` は使わない（`.worktrees/` 配下にならないため）。メインエージェントが `git worktree add` で作成し、その**絶対パス**を指示に含める。
- 指示には、目的・作業ディレクトリ（絶対パス）・読むべき資料（ルールブック、関連仕様書）・成果物のパス・やらないこと・完了条件を明記する。
- サブエージェントの報告は鵜呑みにせず、メインエージェントが差分を確認してから PR を作成する。
- 互いに独立したタスクは、別々のワークツリーで並列に委託してよい。

## ドキュメント

- 開発用ドキュメントはすべて `docs/` で管理する。索引は [docs/README.md](docs/README.md)。
- 仕様書は `docs/specs/NNN-<slug>.md`（3 桁連番）。[docs/specs/_template.md](docs/specs/_template.md) から作成し、[docs/specs/README.md](docs/specs/README.md) の一覧に追記する。
- タスク一覧は [docs/tasks.md](docs/tasks.md)。タスクの着手・完了時に更新する。
- ルールの一次情報は `data/rulebook/rulebook_web.pdf` とカード画像 `data/cards/`、補助として公式サイトの [FAQ](https://danjiri-concerto.github.io/i-revolve-therefore-i-am/faq.html)。仕様書とルールブックが食い違う場合はルールブックを正とし、ルールブックに書かれていない点は推測で埋めずユーザー（作者）に確認する。
- ルールブック PDF は日本語フォントが CID のまま埋め込まれており、`pypdf` ではテキストが化ける。テキストを抽出するときは `pdfminer.six`（`pdf2txt.py`）を使う。それでも `(cid:13673)`（廻）、`(cid:20276)`（鍵）などが残るので、前後の文脈やページ画像で補う。

## ゲームの用語

仕様書・コードでは以下の用語に統一する（英語識別子は仕様書 002 で確定させる）。

| 用語 | 意味 |
| --- | --- |
| 人格 | 各プレイヤーのミープル。回廊の部屋を時計回りに移動する |
| 回廊 | 12 の部屋が環状に並んだ共有の場 |
| 部屋 | 床カード 1 枚と、その上に裏向きで置かれた想い出カード 1 枚の組。四隅はレベル 2、それ以外はレベル 1 |
| 床カード | 部屋の土台。床アクションを持ち、レベル 2 の床は周遊ボーナスも持つ |
| 想い出カード | 手札にし、自己構成するカード。レベル 1 / 2。裏面に移動コスト（錠前）が描かれる |
| 想い出の山札 / 捨札 / 場札 | 想い出カードの山。単に「想い出の山札」と言えばレベル 1 の山札を指す。場札は表向き 6 枚 |
| 自己構成 | 自己構成エネルギーを支払い、手札の想い出カードを自己構成済みエリアに出すアクション（エンジン構築） |
| 自己構成済みカード | 自己構成した想い出カード。横向きにする（使用済み）と鍵を使える |
| 精神トラック / 精神カード | 第一〜第五階層の精神カードの並び。各プレイヤーのキューブで階層を示す |
| 精神分析 | 精神分析コストを支払い、精神トラックの階層を 1 つ上げるアクション |
| 鍵 | 移動コスト・精神分析コストの支払いに使う。知恵鍵 / 意志鍵 / 感情鍵 / 万能鍵（万能鍵は他 3 種の代わりになる） |
| 心光 | トークン。同じアクション・フェイズで X 回目に支払うときは X 個で 1 つ分になる（心光の行使） |
| 万能移動鍵トークン | 裏返して使用済みにし、任意の鍵 1 つ分の移動コストを払うトークン |
| 自己構成トークン / 精神分析トークン | 自己構成フェイズの前後に、追加の自己構成 / 精神分析アクションを行うトークン |
| 初期自我カード / 強化自我カード | プレイヤー固有の能力カード。強化自我は第三階層到達時に獲得する |
| 目標カード | 条件を満たすと 1 ターンに 1 枚獲得でき、自己構成スコアを加えるカード |
| 自己構成スコア | 想い出カードと目標カードのスコアの合計。勝利条件「断片の結集」に使う |
| 想起効果 | 想起フェイズで部屋の想い出カードを表向きにしたときに発動する効果 |
| 周遊ボーナス / 精神分析ボーナス | レベル 2 の部屋への入室・スキップ時 / 精神カードへ進んだときに得る即時ボーナス |
| フェイズ | 移動 / 想起 / 床アクション / 自己構成 / クリーンアップ |
| 勝利条件 | 刹那の追憶（1 ターンで全部屋に入室）/ 断片の結集（自己構成スコア 20 以上）/ 心理の極致（精神トラック第五階層）。想い出の山札切れは全員敗北 |
| カード効果の種類 | ターン開始時 / 起動 / 常時 / 即時 / 保有時 |

## 画像ファイル名

`data/` の画像ファイル名と内容の対応（カード番号・枚数の確定は仕様書 002 で行う）。

`data/` は Git 管理外で、メインの作業ツリー（リポジトリ直下）にだけ置いてある。`.worktrees/` 配下のワークツリーや CI には存在しないため、次のように扱う。

- ワークツリーからは絶対パスで参照する。パスは `"$(git rev-parse --path-format=absolute --git-common-dir)/../data"` で求まる。サブエージェントへの指示には、この絶対パスを書く。
- ドキュメントから `data/` へはリンクを張らず、パスをコード表記で書く（リンク切れチェックで失敗するため）。
- CI は `data/` を使えない。スプライトなどの生成物はローカルで作って `src/` にコミットする。

| パス | 内容 |
| --- | --- |
| `cards/memory/1`〜`99` | 想い出カードの表（ルールブック上の No. に対応。全 144 枚のうち同一絵柄の重複があるため 99 種） |
| `cards/memory/Rev{1,2}{B,F,H}` | 想い出カードの裏（レベル 1 / 2 × 移動コストの錠前の種類 B=本 / F=炎 / H=ハート） |
| `cards/floor/F1`〜`F12`、`Frev1`、`Frev2` | 床カード（ID 1〜12 は DEMETER〜HEPHAESTUS の部屋）と裏面 |
| `cards/analysis/R1a`〜`R5`、`R*rev` | 精神カード（数字が階層。第一・第三階層は a〜c の 3 枚から 2 枚を使う）と裏面 |
| `cards/ability/I1`〜`I8`、`Irev` | 初期自我カードと裏面 |
| `cards/enhancement/E1`〜`E5`、`Erev` | 強化自我カードと裏面 |
| `cards/objective/O1`〜`O7`、`Orev` | 目標カードと裏面 |
| `tokens/tokenHR` | 心光トークン |
| `tokens/tokenM`、`tokenMrev` | 万能移動鍵トークン（表 / 使用済み） |
| `tokens/tokenC`、`tokenR` | 自己構成トークン / 精神分析トークン |
| `icons/key_*`、`lock_*` | 鍵・錠前のアイコン（`book` / `fire` / `heart` / `general`）。知恵・意志・感情との対応は仕様書 002 で確定させる |
| `icons/cost`、`draw`、`opendraw`、`score` | その他のアイコン |
| `piecies/meeple/meeple_{r,b,g,y}`、`piecies/cube/cube_*` | プレイヤー色のミープル（人格）とキューブ |
| `box/box@4x.png` | 箱絵 |

## BGA 実装の方針

- BGA Studio に同期するゲーム本体は `src/` 配下に置き、`src/` の中身が BGA 側プロジェクトフォルダと 1 対 1 に対応するようにする。`docs/` や `data/` は同期しない。
- BGA フレームワークの構成（ファイル構成、状態クラス、通知、PHP バージョンなど）は更新が速い。記憶に頼らず、仕様書作成時に [BGA Studio の公式ドキュメント](https://en.doc.boardgamearena.com/Studio) で最新を確認する。
- BGA Studio のプロジェクト名は **`i_revolve_therefore_i_am`**（リポジトリ名から `-bga` を除き、ハイフンをアンダースコアにしたもの）。未作成。作成画面でアンダースコアを使えなかった場合は `irevolvethereforeiam` とし、ここを書き換える。作成後は変更できない。BGA のファイル名・クラス名・名前空間はこの名前に従う。
- UI 文言は英語を原文とし、BGA の翻訳機構で日本語化する（BGA の標準作法）。
- BGA のフレームワークのクラス（`Table`、`GameState` など）を継承するクラスに独自のメソッドを足すときは、名前にこのゲーム固有の接頭辞を付ける（接頭辞は仕様書 003 で決める）。フレームワークに同名のメソッドがあると Studio で致命的エラーになり、開発用スタブ（`_ide_helper.php`）が古いため PHPStan では検出できない（姉妹プロジェクトで 2026-10-15 に `sendNotifications` で発生）。

## CI/CD

方針は [docs/development/cicd.md](docs/development/cicd.md)。まだ未導入で、タスクとして仕様書から作成する。姉妹プロジェクトで導入済みの lint・同期の仕組み（同プロジェクトの仕様書 004 / 005 と `.github/workflows/`）を土台にする。

- CI: PR ごとに lint（PHP / JS / CSS / Markdown など）を実行し、通らない PR はマージしない。
- CD: `main` へのマージをトリガーに `src/` を BGA Studio へ SFTP で自動同期する。
