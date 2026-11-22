# ローカルのプレビュー環境

BGA Studio に上げなくても、実際のクライアント（`src/modules/js/Game.js` と `src/irevolvethereforeiam.css`）を手元のブラウザで表示し、各画面幅 × ライト・ダークのスクリーンショットを撮るための開発用ツール。BGA へは同期しない（同期の対象は `src/**` だけ。仕様書 010 §11.1）。

## 前提

- `npm ci` 済みであること。スプライト（`src/img/`）はコミット済みなので、事前の生成は不要。
- ネットワークに接続できること。bga-cards と bga-animations を BGA の CDN（`x.boardgamearena.net`）から読み込む。
- スクリーンショットには Google Chrome が必要。場所は環境変数 `CHROME_PATH`（既定 `/Applications/Google Chrome.app/Contents/MacOS/Google Chrome`）。ブラウザのダウンロードはしない。
- 固定データを作り直すときだけ Docker が必要（`npm run preview:fixtures`）。

## 使い方

```bash
npm run preview          # サーバーを起動し、開く URL を表示（ポートは PREVIEW_PORT、既定 8765。127.0.0.1 のみ）
npm run preview:shots    # 全場面 × 全サイズ × 2 テーマを撮影（数分かかる）
npm run preview:shots -- --fixture 4p-late --size 1920x1080 --theme dark
npm run preview:fixtures # 固定データを本物のサーバーのコードから作り直す（Docker）
```

- URL の引数: `fixture`（固定データの名前）、`theme`（`light` / `dark`）、`spectator=1`（観戦者）、`animations=1`（アニメーションを有効に。既定は無効）、`touch=0/1`（タッチ端末の扱いを固定）、`captions=1`（Card captions を On に）。
- `preview:shots` の引数は `--fixture` / `--size` / `--theme`（いずれもカンマ区切りで複数可）、`--spectator`、`--strict`。サイズは 360x780、390x844、768x1024、1024x768、1280x800、1366x768、1440x900、1920x1080、2560x1440。
- 出力は `scripts/preview/out/<fixture>/<W>x<H>-<theme>.png`（見えている範囲）と `...-full.png`（ページ全体）。`out/` は `.gitignore` 済み。
- ページのエラー（`pageerror`、`console.error`、リクエスト失敗、`data-error`）があると一覧を出して終了コード 1 になる。ページの横はみ出し（`scrollWidth > innerWidth`）は別に数えて警告として出す（`--strict` で失敗にする）。読み込みが終わると `<html data-ready="1">` が立つ。
- 画面の大きさ（`--size`）の高さは、デスクトップ幅（980px 以上）ではブラウザのタブ・アドレスバーの約 130px を引いたものを見える高さ（ビューポート）にする（スクリーンショットの高さは `--size` より 130px 低い）。モバイル幅は `--size` のまま。
- 配置の検証: 撮影のたびに、配置（`data-layout`）、環の下端と見える高さ（`xl` / `wide` で `W` が下限 150 より大きいとき、環が収まるか）、盤面の左右の余白 8px、操作できる要素（ボタン・押せるトークン・ジャンプリンク）が 32px 以上か、画面の外へはみ出す要素を数えて出力する（警告のみ。010 §11.1）。
- 操作の記録: `performAction` は `console.log` され、`window.__irActions` に積まれる。

## 固定データ

`fixtures/*.json` は `{ preview, gamedatas }`。`gamedatas` は `getAllDatas` と同じ形、`preview` は自分の ID、入る状態（名前・args・自分がアクティブか）、`steps`（状態に入った後の操作。`{ click: セレクター }`）、`replay`（`setup` の後に呼ぶ `notif_*`）を持つ。`catalog.json` は全場面で共通の `gamedatas.catalog`。

**手書きではなく、本物のサーバーのコードから生成する。** `npm run preview:fixtures`（`scripts/preview/dump-fixtures.sh`）が Docker の PHP で `scripts/preview/dump-fixtures.php` を動かし、`tests/Support/PreviewRig.php`（SQLite 上に本物の `GameSetup` でゲームを作り、本番の `Game` と同じ組み立ての `PublicView`・`MovePhase`・`TurnKit` を持つ）の状態をリポジトリ経由で書き換えて場面を作る。`gamedatas` は `PublicView::allDatas`、状態の args は各状態クラスの `getArgs` と同じ関数（`MovePhase::planningData`、`ConstructPhase::args`、`ComposeAction::args`、`AnalysisAction::args`、`ChoiceFlow::args` など）の出力。サーバーの形を変えたら、再生成して差分を見る。

| 名前 | 内容 |
| --- | --- |
| `2p-setup` | 2 人、初期自我のドラフト（`SetupEgoPick`） |
| `2p-setup-place` | 2 人、人格の配置（`SetupMeeplePlace`） |
| `4p-early` | 4 人、1 ターン目の移動の開始（`Move`、手札 5 枚、経路なし） |
| `4p-move-plan` | 4 人、移動の計画中（`steps` で部屋 2・3・4 を順に選ぶ） |
| `4p-recall` | 4 人、想起中（`EffectChoice`。場札から選ぶ） |
| `4p-construct` | 4 人、自己構成フェイズ（`Construct`、トークンあり） |
| `4p-compose-pay` | 4 人、自己構成の支払い（`Compose`） |
| `4p-analysis` | 4 人、精神分析（`Analysis`。第一階層の 2 択） |
| `4p-late` | 4 人、終盤（手札 8 枚、自己構成済み 11 枚、強化自我、目標を獲得済み） |
| `4p-late-hand9` | `4p-late` で手札 9 枚（`HandLimitDiscard`） |
| `3p-mid` | 3 人、中盤。自分は手番ではない（他の人の自己構成フェイズを待つ） |
| `4p-end` | 4 人、ゲーム終了（`GameEnd`。断片の結集） |

## BGA の画面との違い

模している範囲: 上のトップバー（ダミー）、ステータスバー（タイトルとボタン。青・赤・白枠・灰の 4 色）、ゲーム領域（`#game_play_area`）、プレイヤーパネル（デスクトップは右に幅 260px の列、モバイルは 2 列の格子をゲーム領域の上）、標準のスコア欄 `#player_score_<id>`。`body` の `id="ebd-body"`、`desktop_version` / `mobile_version`、`touch-device` / `notouch-device`、`html[data-theme]`、`html` の木目風の背景。

違い・模していない点:

- モバイルの境目は `MOBILE_MAX_WIDTH = 980`（`preview.js` と `shoot.mjs` の定数）。BGA の実際の境目は未確認。
- トップバー・ステータスバーは固定表示（sticky）にしていない。ボタンやパネルの寸法・フォント・色は目安で、BGA の実物と一致しない。`Wc` と `Hv`（ゲーム領域の実測値）は Studio で確かめる（010 §11.2）。
- パネルの中身は `Game.js` が入れた要素だけ（BGA のアバター、時計、ログ、ツールチップの実物、ジャンプリンクなどは無い）。
- 翻訳（`_()`）は原文をそのまま返す。ログ、通知、手番の進行、サーバー側の処理は無い。ステータスバーのタイトルは、状態クラスが `setTitle` した文だけ（BGA が状態の説明から出す初期の文は無い）。
- 画像・ライブラリは手元の `src/img/` と CDN の最新（1.x）を使う。
