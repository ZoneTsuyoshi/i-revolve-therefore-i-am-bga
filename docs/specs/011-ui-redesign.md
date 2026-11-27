# 011: UI の再設計（姉妹 PROJECT EDGEWORTH の審査の指摘への先回り）

| 項目 | 内容 |
| --- | --- |
| ステータス | Approved（実装済み。作者の Studio での確認待ち。受け入れ条件） |
| 対応タスク | T-011 |
| 作成日 | 2026-11-27 |
| 関連仕様書 | 010（Public Alpha 審査に向けた UI の見直し。本書はその上に積む）、009（UI・アセット設計）、003（BGA 構成・技術方針）、004（lint）、006（状態遷移）、007（データモデル・通知）。姉妹 PROJECT EDGEWORTH の仕様書 014（UI の再設計） |

## 目的

姉妹プロジェクト PROJECT EDGEWORTH の BGA 実装が、2026-11-27 に BGA の Public Alpha の審査で UI を全面的に差し戻された。審査は「部品の周りの UI が汎用的で一貫性がなく、卓上のゲームではなく独自のアプリに見える」ことを主な理由に挙げ、ガイドラインを個別の見た目の修正ではなく 1 つの UX の体系として当てはめ直すよう求めている。**このゲームはまだ審査を受けていない。** 同じ審査者に同じ指摘を受けないよう、先回りして直す。

方針は 014 と同じ: **暗い卓の上に、回廊・カード・トークン・コマを直接置く。** ゲーム領域に置くのは部品と、卓に印刷された線と小さなラベルだけにし、Web アプリ風の面（角丸の箱、丸い札、色の太い枠、影の浮いた箱）、独自のボタン、独自のツールチップ、重複した数値をやめる。このゲームは 010（U-11〜U-16、実装済み）で姉妹 MINIMUM JOURNEY の指摘に先回りして大きく直しているので、本書はその上に積む。**010 で作者が確定した決定（010 の未確定事項 No.1〜10）は維持し、変えるときは理由を書く。**

## スコープ

- 対象: 視覚体系（形・色・文字・ゾーンの示し方・記号）、レイアウトと浮いた操作（bga-jump-to の廃止）、上部と案内（想起の案内パネルの廃止、ステータスバーの文）、プレイヤーの領域とパネル、操作の見た目（共通のクラス）、ツールチップの作り直し、読みやすさ（部屋の錠前、カード名）、確認の道具（プレビューと自動検査）、実装の分け方。
- 対象外: ルール（001。ルールの不明点は出ていない）、サーバー（PHP）の変更（不要。§3.2 で確認）、カード画像・スプライトの再生成、メタ画像、背景（010 案 B の暗いセピアをそのまま使う）、009・010 の本文の書き換え（実装 PR の最後 U-20 で反映する。本書の PR では書き換えない）。

## 記法と調査の前提

- **【確認】** は公式ドキュメント・ライブラリのソース・このリポジトリのコードで確認した事実、**【実測】** は本タスクで実際に測った値、**【計算】** はコードの定数や画像からの算術、**【提案】** は本書の提案（数値は実装時のプレビューで調整してよい基準値）、**【推定】** は実測できず推定した値、**【未確認】** は確認できなかった事項。コードの事実は `ファイル:行`（2026-11-27 の `main`、`135e773`。パスは `src/` からの相対）。
- 調査日は 2026-11-27。BGA の API は更新が速いので、実装時に再確認する。
- **審査の指摘の原文**は仕様書に貼らず、要約する（各指摘から 1 文だけ引用する）。審査の結論は「Public Alpha にはまだ早い。徹底した UX/UI の見直しを行い、ガイドラインを 1 つの UX の体系として読んで適用すること」。
- 実測は、プレビュー（`scripts/preview/`、Google Chrome、`npm run preview:shots` と同じ読み込み）で行った。画面の大きさは 010 §11.1 と同じく、デスクトップ幅（980px 以上）ではブラウザのタブ・アドレスバーの約 130px を引いた値を見える高さにする（1920×1080 は 1920×950、1366×768 は 1366×638）。モバイル幅（390×844）はそのまま。ライトテーマ。BGA 実物のゲーム領域の幅 `Wc` と見える高さ `Hv` は Studio で測り直す（【未確認】。010 §3.1 と同じ）。

参照した公式文書と、このリポジトリ外の資料（2026-11-27 に確認）:

| 略称 | URL | 確認した内容 |
| --- | --- | --- |
| UxPdf | <https://doc.studio.boardgamearena.com/assets/framework-documentation/guidelines/Guidelines_UX.pdf> | 公式 UX ガイドライン。PDF から本文を取り出して A.3・B.1・C.4（アンカー）・D.1 を直接読んだ。**審査が挙げた各節の要点は、014 の「審査が挙げたガイドラインの節」の表を出典として引用する**（本書の末尾の「ガイドラインの節と本書の対応」） |
| StatusBar | <https://doc.studio.boardgamearena.com/framework-documentation/front-end/game-interface-logic/status-bar/> | `statusBar.addActionButton(label, callback, params?)` は `HTMLButtonElement` を返す。`params` は `color`（`primary` = 青 = 既定、`secondary`、`alert`）・`id`・`classes`・`destination`（「ボタンを足す DOM 要素。指定しなければステータスバー」）・`title`（ラベルがアイコンのときの平文の説明）・`disabled`・`tooltip`・`confirm`・`autoclick`。`setTitle(title, args?)` は `${you}`・`${actplayer}` と任意の変数を使える。**【未確認】** `destination` に置いたボタンが `removeActionButtons()`（「タイトルバーのボタンをすべて外す」とだけ書かれている）や状態の切り替えで外れるか。ステータスバーが画面の上に貼り付く（sticky）か |
| Dialogs | <https://doc.studio.boardgamearena.com/framework-documentation/front-end/game-interface-logic/dialogs/> | `bga.dialogs.showMessage(msg, type)`: `'info'` は白い背景の情報、`'error'` は赤い背景で**ログにも載る**。どちらも画面の上に数秒出て消える。「介入的で多用すべきでない（ログが通常の知らせ方）」。**【未確認】** `info` がログに載る条件（文書は「場合による」とだけ書く） |
| GameUi | <https://doc.studio.boardgamearena.com/framework-documentation/front-end/game-interface-logic/gameui/> | BGA 標準のツールチップ: `addTooltip(nodeId, help, action, delay?)`、`addTooltipHtml(nodeId, html, delay?)`、`removeTooltip(nodeId)`。対象に **id が要る**。`delay` は主に「重要な情報のとき 0 にする」ためのもの。タッチは「モバイルでは実用的でない」と書かれるだけで、挙動の説明はない。「重要な情報をツールチップだけに置かない（表示されないブラウザがある）」 |
| bga-cards のソース | <https://x.boardgamearena.net/data/game-libs/bga-cards/1.x/dist/bga-cards.esm.js> | 手元に取得して読んだ。選択モードの Stock は、選べるカードの**面の要素（`card-sides` の中）**に `bga-cards_selectable-card`（既定の見た目: 5px のオレンジの破線の outline、`cursor: pointer`）、選べないカードに `bga-cards_unselectable-card`（`cursor: not-allowed` と `filter: contrast(0.6)`）、選択中に `bga-cards_selected-card`（5px の紫の実線の outline）を付ける。クラス名は Manager の設定 `selectableCardStyle` / `unselectableCardStyle` / `selectedCardStyle` の `class` で変えられる（`outlineSize` / `outlineColor` は inline の CSS 変数を設定するだけ） |
| 014 | `../project-edgeworth-bga/docs/specs/014-ui-redesign.md`（ローカル。姉妹の仕様書） | 審査の指摘の要約、ガイドラインの節の要点の表、ツールチップの作り直し・共通の選択クラス・自動検査の考え方。**そのままコピーせず、このゲームに読み替える** |

## 審査の指摘（2026-11-27）の要約

| # | 指摘の要約 | 審査の言葉（原文から 1 文） | 本書の節 |
| --- | --- | --- | --- |
| R1 | 全体の構造を作り直す。大きな角丸の枠、浮いたボタン、ばらばらの情報の領域、不揃いの見た目。独自のアプリに見える | "It feels more like a custom application than a coherent tabletop adaptation." | §1、§2、§4 |
| R2 | ツールチップを直す。読める・正しい位置・画面内に収まる・マウスでもタッチでも出せる・大事な部品を不必要に隠さない | "Tooltips must be readable, properly positioned, constrained to the viewport and accessible through both mouse and touch interactions." | §6 |
| R3 | 上部を簡素にする。大きなパネルが場所を取り、重複した情報がある。部品と、説明・補助の移動を区別する | "Please distinguish actual gameplay components from instructions and secondary navigation." | §3 |
| R4 | プレイヤーの領域を見直す。盤面の下の大量のカードと細かい情報に階層が要る。コンパクトな情報は標準のプレイヤーパネルへ。紙のゲームが必要としない重複をやめる | "Keep compact player information in the standard BGA player panels and avoid duplicating it elsewhere unless the physical game requires it." | §4 |
| R5 | 見た目の一貫性と部品の読みやすさ。盤面・カード・操作・周囲の UI を 1 つの視覚体系に。カードの文字が特に小さい | "Some card text is particularly small, and the gameplay components should remain readable without excessive zooming." | §1、§7 |
| R6 | 浮いた操作とレスポンシブ。左のボタンが切り離された UI に見え、スクロールで BGA のフッターに重なる。PC・ワイド・スマホを確認し、不要な入れ子のスクロールをやめる | "The buttons on the left should not behave as detached interface elements or overlap the BGA footer when scrolling." | §2、§8 |
| R7 | 操作を分かりやすく。できる操作・置ける場所・選んだ部品を、追加の説明文ではなく、一貫したハイライトと文脈に応じた反応で伝える | "Available actions, legal destinations and selected components should be communicated through consistent highlighting and contextual feedback rather than relying primarily on additional instructions." | §5 |

審査が挙げたガイドラインの節: A.1 Layout arrangement / A.2 Action Bar / A.3 Player panels / A.5 Clarity & Zoom / B.1 Tooltips / C.2 Highlights & Glowing auras / C.4 Sizing & responsiveness / E.1 Design for all / E.3 Tap targets。各節の要点は本書の末尾の表（014 を出典）にまとめた。審査は「これらを個別の見た目の修正ではなく、1 つの UX の体系として読んで適用する」ことを求めている。

## 仕様

節の番号は 010 と独立で、メインエージェントの設計（2026-11-27）の §0〜§10 に対応する。

### 0. 指摘と現状の原因（コードで確かめた事実）

#### 0.1 原因の対応表

| # | 現状の原因（根拠） | 本書の節 |
| --- | --- | --- |
| R1 | **箱と枠**: `.ir-player-area` は枠 1px・角丸（`--ir-mat-radius` = 10px）・背景の箱（`irevolvethereforeiam.css:469-478`）。手番の領域は枠が 3px の `--ir-turn-border`（`:487-491`、`board/PlayerAreas.js:204-209` が `ir-turn` を付ける）。手札・自己構成済みのマット `.ir-zone`（`:565-575`）は枠と角丸 10px の面で、色違いの背景。**丸い札**: 「Your turn / Current turn」の札（`board/Board.js:200`、CSS `:526-533`、`999px`）、自己構成スコア「N / 20」（`:516-524`、`999px`）、マットの丸いタブ（`:595-613`、`999px`）、トークンの入れ物（`.ir-token` `:671-683`、`999px`）と「+」（`:700-713`、`999px`）と選択数のバッジ（`:715-729`、`50%`）、手札の枚数警告（`:631-650`、`999px` と `50%` の「!」）、山札の枚数の札（`.ir-count` `:1148-1158`、8px）。**独自のボタン**: 手札のタブの中の `.ir-zone-button`（`board/PlayerAreas.js:736-745`、CSS `:616-629`、`999px`、「Discard 2 for Psychic Light」）。**箱のパネル**: `.ir-recall-panel`（`:440-449`）、`.ir-pick-panel`（`:904-915`）。**CSS の `content` の記号**: ○（`:848`）・✓（`:868`）・★（`:891`）、手札警告の「!」（`:642`）。**`border-radius` の宣言は 27 か所**（grep で 28 行はコメント 1 行を含む）【実測】。内訳: `999px` が 7、`50%` が 6、`var(--ir-mat-radius)`（10px）が 4、`4px` が 4、`6px` が 3、`8px` が 2、`2px` が 1。影は `box-shadow` が 4 か所（手札の束のアイコン `:660`、`.ir-pending` `:887`、`.ir-card` `:1237`、`.ir-tip` `:1314`）、`filter: drop-shadow` は 0 | §1、§2、§4 |
| R2 | **カードのツールチップ**（`ui/card-info.js`）はマウスのホバーだけ（0.4 秒、`handlePointerOver` は `pointerType !== 'mouse'` を捨てる。`:178-180`）。タッチは 0.5 秒の長押しで、画面の高さにほぼ収める「拡大表示」（モーダル。`ui/zoom-math.js` の `zoomLayout`、選べるカードには Select ボタン）。**カード以外は `title` 属性**（タッチでは出ず、見た目も OS 任せ）。**位置**: `tooltipPosition`（`ui/zoom-math.js:69-77`）は、右に入らなければ左に出すだけで、左右どちらにも入らないと左端に詰めて**対象を覆う**。縦は `card.top` を画面内に挟むだけで、**BGA のステータスバー（`#page-title`）の下端を見ていない**（バーを考慮するのは拡大表示の `top` だけ。`ui/card-info.js:383-384`）。`.ir-tip` に `max-height` がなく、背の高い内容は画面の下へ出る（`irevolvethereforeiam.css:1303-1316`）。【実測】プレビュー（1366×768、見える高さ 638）で、横長カードのツールチップは高さ 423〜430px、縦長は 305〜311px。プレビューにステータスバーの `#page-title` がなく、バーとの重なりは今の検査では見えない。**`title` 属性**は §0.2 の一覧のとおりソース上 16 か所（実行時は 4p-early で `#ir-root` の中に 40 要素、パネルに 24 要素）【実測】 | §6 |
| R3 | **想起の案内パネル** `.ir-recall-panel`（009 §3.9。想起中だけ現れる箱）は、xl・wide では共通の列の先頭に入って精神トラック・強化自我・目標を押し下げ、medium・narrow では環の上に入って下の部品を押し下げる（`:200-240`、`:1460-1463`、`:1515-1535`）。【実測】高さは 1366×768 で 168px、390×844 で 203px、1920×1080 で 175px。内容は拡大画像と「RECALL PHASE / カード名 / Level / Recall: 効果文 / Recall effect by: 名前」で、**カードのツールチップと中身がほぼ同じ**（B.1 の「同じ情報を同じ見せ方で繰り返さない」）。候補の置き場 `.ir-pick-panel`（`states/ActionState.js:309`。初期自我のドラフト、強化自我の選択）は箱（高さ 93px【実測】）。**ステータスバーの文が長い**: 【実測】移動の計画中は `Route: Room 2 > Room 3 > Room 4. Locks: 0 / 4 paid. Still needed: 2 × Wisdom, 2 × Will`（86 文字。経路は盤面の数字と重複）。自己構成の支払いは `Compose ${card}. Energy: paid …. Still needed: …. Unused: …`、精神分析の支払いは `Tier N a: Keys: … paid. Still needed: …`（`states/MoveState.js:250`、`ComposeState.js:163`、`AnalysisState.js:131`、`payment-ui.js:64-76`） | §3 |
| R4 | 自分と他プレイヤーの値が、パネルと領域に重複する: **パネル**（`board/PlayerPanels.js:182-197`）は 2 行（スコア・手札の枚数 ／ 鍵 4 種）。**領域**は名前の行にスコア「N / 20」（`board/Board.js:199`。010 の例外）と手番の札、トークン 4 種のチップ（`board/PlayerAreas.js:268-282`）、手札のタブに枚数の警告。4p-late の自分の領域は高さ 1190px（1920×1080）、1019px（1366×768）、1264px（390×844）【実測】。010 で整理済みだが、「紙のゲームが必要としない重複」の観点で再点検が要る（§4.4） | §4 |
| R5 | **12px 未満の文字**: `font-size: 11px` が 5 か所（`.ir-under-badge` `:793`、`.ir-energy-badge` `:806`、`.ir-used-band` `:832`、カードの下の英語名 `.ir-caption` `:1035`、目標カードの現在値 `.ir-goal-badge` `:1168`）【実測】。**部品の寸法**は §0.3 の表。特に**部屋の裏向きの想い出カードの錠前**: 画像の錠前の幅はカード幅の 17.7%（`data/cards/memory/Rev1B.png` ほか 6 枚で測った。722px 幅の画像で錠前が x 295〜423）【実測】。390px の画面では部屋のカードが幅 42px で、錠前は **約 7.4px 幅**【計算】 | §1、§7 |
| R6 | **左の浮いたボタン**: bga-jump-to（`Game.js:33` の読み込み、`:48`、`:160`、`:169-192` の `setupJumpTo`）。medium・narrow の左端に丸いボタンが `position: fixed` で浮く（ライブラリ側の CSS。プレビューで `getComputedStyle` が `fixed`）。【実測】390×844 で位置は左 10px・上 276px、大きさ 46×46px。部屋のカードとミープルに重なる（スクリーンショットで確認）。xl・wide は CSS で隠す（`:1508-1513`）。BGA のフッターとの重なりはプレビューにフッターがなく確認できない。**入れ子のスクロール**: `overflow` が auto / scroll の規則は `.ir-zoom-scroll`（`:1378-1381`。拡大表示の文章）の 1 か所だけ【実測】（`overflow: hidden` は `.ir-caption` `:1034` と `.ir-zoom-dialog` `:1357`）。`position: fixed` は背景の `body::before`（`:98-100`）とツールチップ `.ir-tip`（`:1304`）・拡大表示 `.ir-zoom`（`:1334`）の 3 か所 | §2、§8 |
| R7 | **選択の見た目がばらばら**: `.ir-card.ir-selectable`（文字色の 2px の outline と ○。`:841-859`）、`.ir-card.ir-selected`（`--ir-turn-border` の 3px と ✓。`:861-881`）、`.ir-card.ir-pending`（青 `--ir-pending` の 5px と ★ と光。`:883-902`。自己構成するカード。`ComposeState.js:164`）、`.ir-room.ir-room-selectable`（赤 3px。`:359-363`）、経路の順番（赤い丸の数字 `:369-385`）、トークンの選べる枠（`:690-698`）、**bga-cards の既定の見た目**（オレンジの破線 5px。§0.1 の表の出典のとおり。4p-recall の場札で見える）。**赤（`--ir-turn-border`）が「選べる部屋」と「選択中のカード」の両方に使われ**（11 か所で参照）、意味が混ざる。**選べないものを押しても無反応**（`MoveState.js`・`ActionState.js:186-215`・`cards/selection.js` の `nextSelection` は選べない ID を黙って捨てる）。**選べないカードは bga-cards の既定で `contrast(0.6)` の暗さになるが、リポジトリの CSS は何も上書きしていない**（009 §4.3 の「選べない: 暗くしない」と食い違う。§5.3） | §5 |

#### 0.2 `title` 属性の一覧（ソース上 16 か所。実行時の要素数はもっと多い）【実測】

| ファイル | 箇所 | 置き換え（§6） |
| --- | --- | --- |
| `board/Board.js:199` | 自己構成スコア「N / 20」 | 文字だけのツールチップ（`aria-label` は残す） |
| `board/Board.js:203`・`:204` | 手札・自己構成済みのタブ（ラベルをレールに変える） | レールのラベル自身が名前。`title` を削除 |
| `board/PlayerPanels.js:189` | パネルの項目 6 つ（スコア・手札・鍵 4 種）×人数 | 文字だけのツールチップ |
| `board/PlayerAreas.js:279`・`:327` | トークンのチップ（名前と個数） | 文字だけのツールチップ |
| `board/PlayerAreas.js:354`・`:357` | 手札の上限超過の警告 | 文字だけのツールチップ |
| `board/PlayerAreas.js:444` | 手札のエネルギーのバッジ | 文字だけのツールチップ |
| `board/PlayerAreas.js:741` | 手札のタブの独自ボタン | ボタン自体を廃止（§8） |
| `board/PlayerAreas.js:805`・`:848` | 押せるトークンの説明（支払いの数量） | 文字だけのツールチップ |
| `board/PlayerAreas.js:826` | トークンの選択数のバッジ | 文字だけのツールチップ |
| `board/CommonArea.js:139` | 山札・捨札・強化自我の山の枚数 | 文字だけのツールチップ |
| `board/CommonArea.js:213` | 精神トラックの階層 0（Start） | 文字だけのツールチップ |
| `board/CommonArea.js:280`・`:296` | 精神トラックのキューブ・ミープルの持ち主 | 文字だけのツールチップ |

`addActionButton` に渡す `tooltip` は DOM の `title` 属性ではなく BGA 標準のボタンの引数で、ステータスバー（`#ir-root` の外）に出るので、全廃の対象外（`MoveState.js:156`、`ComposeState.js:178` など）。ただし §8 のとおり `destination` で `#ir-root` の中に置くボタンは `title` 属性が付くと検査（§9 の 4）に掛かる。

#### 0.3 部品の表示寸法【実測】（4p-early・4p-late。px）

| 画面（見える高さ） | 配置 | 部屋の床 W | 部屋の裏向きカード | 錠前の幅（17.7%）【計算】 | 手札・自己構成済み | 場札 | 精神・目標・自我（横長） | 山札 | 領域の幅 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1920×1080（950） | xl | 158 | 79 | **14.0** | 128 | 94 | 110 | 94 | 564 |
| 1366×768（638） | wide | 150 | 75 | **13.3** | 104 | 90 | 105 | 90 | 468 |
| 390×844（844） | narrow | 84 | 42 | **7.4** | 84 | 84 | 114 | 58 | 354 |

- 部屋の裏向きカードの幅は床の W のちょうど半分（`--ir-room-card-w` = 0.5 W）。レベル 2 の部屋の裏には錠前が 2 つ縦に並ぶ（2 つ合わせた高さは画像の 24.6%）。錠前の種類は色（知恵 = 青、意志 = 赤、感情 = ピンク）と絵柄で見分ける。
- 錠前の幅が 12px を下回るのは、部屋のカードが約 68px 未満（W が約 136px 未満、`Wc` が約 560px 未満）の画面。実機のスマホはすべてここに入る。
- **U-20 の再測定**（2026-11-28、`4p-late`、錠前のアイコンを入れた後）: 印刷の錠前の幅は 1920×950 が 14.0px、1366×638・1024×638・768×1024 が 13.3px、幅 600px が 12.0px（部屋のカード 68px。オーバーレイは出さない）、390×844 が 7.4px、360×780 が 6.7px。**幅 390px・360px では、印刷の錠前を覆って錠前のアイコン（見かけ約 16 × 15px。レベル 2 は 2 つで 33px）を出す**ので、読める大きさは 16px（12px 以上）。xl・wide・medium は変わらない（`#ir-root` の `data-lock-overlay` が 0）。アイコンはスプライトの枠（66 × 64）の余白が大きい（錠前は枠の約 37%）ため、42 × 41px で描いて外側の 16 × 15px の枠で切り出している。
- 12px 未満の文字の実測（レンダリングされたもの）: 4p-early で `ir-goal-badge` 5 件、4p-late でさらに `ir-used-band` 8 件。
- 部品の最小寸法: 押せるボタンは `ir-zone-button`（179×32）、押せるトークン（79×46）、狭い画面のジャンプの丸（46×46）。32×32px 未満の操作要素は 0 件（既存の検査）。

### 1. 視覚体系（卓と部品）

**考え方**: 010 案 B の暗いセピアの卓（`body::before`、`#1c1812` 基調。`irevolvethereforeiam.css:96-116`。**そのまま使う**）の上に、回廊・カード・トークン・コマを直接置く。ゲーム領域に置くのは部品と、**卓に印刷された線と小さなラベル**だけ。Web アプリ風の面（角丸の箱、丸い札、色の太い枠、影の浮いた箱）は使わない。

#### 1.1 形・影・文字

- **形**: 部品以外の UI は角丸 0〜3px（`--ir-r: 2px` を 1 つ定義して使う）。**丸は物理的に丸い部品の画像だけ**（トークン・心光・キューブ・コマはスプライトの画像の形そのもので、CSS の `border-radius` は使わない）。枚数の札は角丸 2px の小さな長方形。
- **影**: カード・コマの接地の影だけ（`box-shadow: 0 0 0 1px rgb(255 255 255 / 20%), 0 2px 5px rgb(0 0 0 / 55%)`。現状の `.ir-card` `:1234-1239` のまま）。それ以外の影（`.ir-pending` の光 `:887` など）は廃止（ツールチップは浮く面なので §6.7 で別に許す）。`filter: drop-shadow` は使わない（Safari で重い。014 が引く BGA の stylesheet の文書）。
- **文字**: BGA の既定のフォントのまま（`font-family` は指定しない）。本文 14px、補助 13px、レールのラベル 12px。**12px 未満を使わない**（§0.1 R5 の 5 か所を直す: `.ir-under-badge`・`.ir-energy-badge`・`.ir-used-band`・`.ir-caption`・`.ir-goal-badge` を 12px に）。
- **記号の文字をアイコンに使わない**: CSS の `content` の ○（`:848`）・✓（`:868`）・★（`:891`）・手札警告の「!」（`:642`）は、インライン SVG に置き換える（`ui/icons.js` の方式。四角の中に文字を描く `letterSvg` と同じく `viewBox` つきの小さな SVG）。`content: attr(data-order)`（経路の順番 `:370`）は数字そのものなので文字のまま（背景は金の小さな四角。§5.5）。
- 記号の SVG は `ui/icon-names.js`・`ui/icons.js` に足す（`check`、`chevron-down`、`warn`）。色は `currentColor`。

#### 1.2 ゾーンの示し方（レール）

領域の区切りは**箱で囲まず**、上端の 1px の線（`--ir-line`）と、その線の左端に載る 12px の小さなラベルで示す（卓に印刷された見出しのように）。

```text
── Hand ────────────────────────────────  （上端の 1px の線 + 左端の 12px のラベル）
[手札のカード …（折り返す）]
```

- DOM / CSS: `.ir-rail`（`border-top: 1px solid var(--ir-line)`、`padding-top: 4px`、`position: relative`）と `.ir-rail-label`（12px、`--ir-ink-dim`、字間 0.04em、背景なし）。ラベルは文字そのものが名前で、`title` を付けない。（U-17 で変更: `margin-top: -9px` ではラベルの下半分を線が横切って読みにくいので、ラベルを入れる `.ir-rail-title` を `position: absolute; bottom: 100%` で線の真上に置き、レールの上に `margin-top: 14px` の余白を取った。手札の警告・ボタンの置き場 `.ir-rail-actions` もレールの中。）
- **プレイヤーの領域の見出し**: 左端だけプレイヤー色の 3px の線 + 名前 + 自己構成スコア「N / 20」。名前は暗い卓の上で 4.5:1 以上の**明るいプレイヤー色の文字**（§1.4 の `--ir-pc-*-ink`）。`.ir-player-header` に `border-left: 3px solid var(--ir-player-ink)`。ミープルの形（色だけにしない。010 §6・E.2）は名前の前に残す。
- **手札と自己構成済みの区別**（作者指示・010 未確定事項 No.3 を維持）: レールのラベルの文字（"Hand" / "Constructed"）と、**ごく薄いマットの色合いの違い**で保つ。マットは角丸 3px 以下の薄い面で、手札は青系（`rgb(96 150 240 / 10%)`）、自己構成済みは琥珀系（`rgb(224 172 80 / 10%)`）。上端の線の色もそれぞれ（手札 `#6f93d0`、自己構成済み `#c49a50`）。**010 の丸いタブ（`.ir-zone-tab`）と枠の太い箱はやめる**。マットはカードの行の背景だけで、枠は上端の線のみ。
- 他プレイヤーの領域の見出し・レールも同じ（手札のレールは出さない。§4）。
- （U-17 で変更: 領域の箱をやめたので、領域の内側の余白の合計 `--ir-area-chrome` / `AREA_CHROME` は 28px から、薄いマットの余白 6px を両側で 12px にした。領域の幅 `P_w` が 16px 狭くなり、xl の境目が 16px 下がる。`tests/js/layout.test.mjs` の境目の値を更新した。）

#### 1.3 トークン（丸い札の入れ物をやめる）

- 入れ物（`.ir-token` の枠・背景・`999px`）をやめ、**トークンの画像（幅 40px）+ 個数の数字**を並べる（画像は `token_light`・`token_wildkey`・`token_compose`・`token_analysis` のスプライトのまま。`board/player-view-model.js:12-17`）。数字は 14px の太字・`--ir-ink`。押せる要素は 32×32px 以上（画像 40px で満たす）。（U-17 の実装: トークンの種類は `light`・`wildUp`（表）・`wildUsed`（使用済み）・`compose`・`analysis` の 5 つで、`wildUsed` は押せず `title` も付けない（`aria-label` のみ）。`tokenChip` の `usedCount` は廃止。）
- **万能移動鍵**: 現状は「1 + 0 used」の文字 1 つ（`board/PlayerAreas.js:322`）。**表（`token_wildkey`）と使用済み（`token_wildkey_used`。`ui/sprites.js:319` に既にある）の 2 つの画像を、それぞれ個数つきで並べる**形にする（紙と同じ見え方）。0 個の側は薄く（`opacity: 0.45`）出して位置を固定する（009 §3.8 の方針を維持）。
- 押せるトークン（支払いの数量。010 §2.4）の見た目は §5.4。選択数のバッジ（`.ir-token-badge`）は金の小さな長方形（32×32px、角丸 2px）。

#### 1.4 色の変数

`.ir-root` で暗い卓用に固定する（テーマに従わない。010 §7.2 のまま）。ツールチップは BGA のテーマに従うので別の変数（`--ir-tip-*`。§6.7）。コントラスト比は WCAG の相対輝度から計算した【計算】。背景は卓の基調 `#1c1812` と、最も明るい所の近似 `#3a3225`（010 の見積もり）の 2 つに対して出す。

| 変数 | 値 | 用途 | `#1c1812` | `#3a3225` |
| --- | --- | --- | --- | --- |
| `--ir-ink` | `#ece7db` | 卓の上の文字 | 14.3 | 10.2 |
| `--ir-ink-dim` | `#bdb6a6` | 補助の文字・レールのラベル | 8.8 | 6.3 |
| `--ir-line` | `#8f8672` | 卓の線（細い。非文字のコントラスト 3:1 以上） | 4.9 | 3.5 |
| `--ir-line-strong` | `#a89e88` | 空き枠の破線・強調した線 | 6.7 | 4.8 |
| `--ir-can` | `#ffffff` | 選べる（輪郭） | 17.7 | 12.6 |
| `--ir-can-glow` | `rgb(255 255 255 / 35%)` | 選べる（外側の柔らかい光） | — | — |
| `--ir-pick` | `#ffd23f` | 選択中（輪郭・印の地） | 12.2 | 8.7 |
| `--ir-on-pick` | `#1a1408` | 金の地の上の文字・チェック | 金に対して 12.7 | — |
| `--ir-subject` | `#f0b84a` | 効果の発生源・問いの対象（破線） | 9.8 | 7.0 |
| `--ir-warn` | `#ff9c8b` | 警告（必ずアイコンか文字を併用） | 8.7 | 6.2 |
| `--ir-pc-r-ink` | `#ff8a80` | プレイヤー色（赤。`b3202a` の明るい版） | 7.7 | 5.5 |
| `--ir-pc-b-ink` | `#7db4ff` | プレイヤー色（青。`2a4fa0`） | 8.3 | 5.9 |
| `--ir-pc-g-ink` | `#6fd68f` | プレイヤー色（緑。`5f9c3a`） | 9.8 | 7.0 |
| `--ir-pc-y-ink` | `#f0c75a` | プレイヤー色（黄。`d6b61e`） | 11.0 | 7.8 |
| `--ir-hand-label` / `--ir-composed-label` | `#c4d9ff` / `#f5dca8` | 手札・自己構成済みのレールのラベル（薄いマットの上） | 10.8 / 11.0（マット込み） | 7.7 / 7.8 |

- 生のプレイヤー色（`b3202a` の赤は `#1c1812` に対して 2.7:1、`#3a3225` に対して 1.9:1）は暗い卓の上で文字にも非文字にも使えない。名前の文字・左端の 3px の線ともに `--ir-pc-*-ink` を使う（赤の線の 3:1 を満たすため）。
- 枚数の札・注記の地（`rgb(0 0 0 / 55%)` を卓に重ねた色）の上の `--ir-ink` は 14.6〜15.9。
- **廃止する変数**（現在の参照数）: `--ir-text`（26。`.ir-root` 内は `--ir-ink` へ。ツールチップ内は `--ir-tip-text` へ分ける）、`--ir-text-muted`（2）、`--ir-panel-bg`（11）、`--ir-panel-border`（7）、`--ir-slot-bg`（2）・`--ir-slot-border`（3。空き枠の破線は `--ir-line-strong` に）、`--ir-slot-line`・`--ir-slot-fill`（0。未使用）、`--ir-turn-border`（11。意味ごとに `--ir-pick` / `--ir-warn` に振り分ける）、`--ir-pending`（3。§5.6）、`--ir-mat-bg`・`--ir-mat-border`・`--ir-mat-radius`（3・4・4）、`--ir-mat-pad`（5。`--ir-pad: 6px` に改名）、`--ir-bare-line`・`--ir-bare-fill`・`--ir-bare-text`（2・2・0。`--ir-line-strong` などに統合）、`--ir-zone-*`（手札・自己構成済みの 4 色ずつ。マットの色と上の 2 色に）。`html[data-theme="dark"]` の `--ir-text` などの上書き（`:76-90`）は、ツールチップ用の `--ir-tip-*` だけにする。
- 色だけに頼らない: 選択の状態は輪郭の有無・太さ・破線・印（§5）、手札と自己構成済みはラベルの文字、プレイヤーはミープルの形と名前。点滅・ループのアニメーションはしない。

#### 1.5 角丸の宣言の整理（27 か所）

| 現在 | 数 | 新しい値 |
| --- | --- | --- |
| `999px`（`.ir-player-score`、`.ir-turn-mark`、`.ir-zone-tab`、`.ir-zone-button`、`.ir-hand-info`、`.ir-token`、`.ir-token-plus`） | 7 | 札・タブ・ボタンをやめる（§4、§8）。残るもの（「+」の札）は 2px |
| `50%`（`.ir-room-route::after`、`.ir-hand-info::before`、`.ir-token-badge`、`.ir-card.ir-selectable::before`・`.ir-selected::after`・`.ir-pending::after`） | 6 | 金の小さな四角い札（2px）と SVG の印に（§5） |
| `var(--ir-mat-radius)` = 10px（`.ir-recall-panel`、`.ir-player-area`、`.ir-zone`、`.ir-pick-panel`） | 4 | 想起の案内パネルは廃止（§3）、領域の箱は廃止（§4）、マットは 3px、候補の置き場はレール（§3.3） |
| `4px`（`.ir-slot`、`.ir-spirit-start`、`.ir-zoom-image`、`.ir-recall-image`） | 4 | 空き枠は 3px、`.ir-spirit-start` は 2px、拡大表示と想起の案内パネルは廃止 |
| `6px`（`.ir-token.ir-selected`、`.ir-tip`、`.ir-zoom-actions button`） | 3 | トークンは §5、ツールチップは §6.7 の 3px、拡大表示は廃止 |
| `8px`（`.ir-count`、`.ir-zoom-dialog`） | 2 | 枚数の札は 2px、拡大表示は廃止 |
| `2px`（`.ir-hand-icon`） | 1 | そのまま（パネルの手札の束のアイコン。CSS で描く部品） |

#### 1.6 変更するファイル（U-17）

`src/irevolvethereforeiam.css`、`src/modules/js/board/Board.js`、`board/PlayerAreas.js`、`board/PlayerPanels.js`、`board/CommonArea.js`、`ui/icon-names.js`、`ui/icons.js`、`tests/js/player-areas.test.mjs`・`common-area.test.mjs`（DOM の変更に合わせる）。

### 2. レイアウト（指摘 R1・R6）

010 の 4 配置（xl / wide / medium / narrow）と中央寄せ・最大幅（`max-width: 2000px`）は**維持する**。変えるのは DOM の順と、各配置の並びの記述（想起の案内パネルの廃止と bga-jump-to の廃止による）。

#### 2.1 DOM の順

```text
#ir-root
  #ir-main
    .ir-pick-panel（候補の置き場。ドラフトと強化自我の選択の間だけ。§3.3）
    #ir-slot-piles-top   … xl・wide・medium では ir-piles がここ
    #ir-cloister         … 4 × 4 の環（12 部屋）。中央は xl・wide・medium は ir-open、narrow は ir-piles
    #ir-slot-open-below  … narrow では ir-open がここ
  #ir-common             … 精神トラック・強化自我の山・目標カード
  #ir-players            … ir-player-area[data-player]（自分が先頭）
```

- `#ir-recall-panel`（`board/Board.js:143`、`:155`）は廃止。
- **`ir-pick-panel` を `#ir-main` の先頭の子にする**（現状は `#ir-root` の先頭。`states/ActionState.js:310` の `root.prepend`）。環の上という位置は変わらない（`#ir-main` は環の幅で、候補は横長カード 3〜5 枚なので `flex-wrap` で収まる。xl の環は 656〜876px、narrow は全幅）。理由: `grid-area: top` と、それを出すための `:has(> .ir-pick-panel, …)` の切り替え（`irevolvethereforeiam.css:200-224`）が**すべて不要になる**。→ §12 の決定 1。
- 廃止する CSS: `.ir-root[data-layout="xl"]:has(…)`・`wide`・`medium` の 3 規則（`:200-224`）、想起中の 2 規則（`:226-238`）、`.ir-recall-panel*` の規則（`:436-449`、`:1414-1447`、`:1460-1463`、`:1515-1535`）。xl・wide・medium の `grid-template` は、`:173-199` の基本の定義だけになる。

#### 2.2 各配置の並び（変更後）

```text
xl:      [ 山札の行 ........ ][ 精神トラック ][ YOU          ]
         [ 回廊の環 (4x4) ... ][ 強化自我の山 ][ B / C / D     ]   全体を中央寄せ
         [ (中央に場札)  ... ][ 目標カード   ][              ]
wide:    [ 山札の行 ........ ][ 精神トラック ]
         [ 回廊の環 (4x4) ... ][ 目標カード   ]                  環と共通の列を中央寄せ
         [ YOU ][ B ][ C ][ D ]                                  プレイヤーは内容の幅の列で、入らなければ折り返す
medium:  [ 山札の行 ][ 回廊の環 ]
         [ 精神トラック ][ 目標カード ]    プレイヤーは内容の幅の列で折り返す
narrow:  [ 回廊の環（中央に山札 2x2）]
         [ 場札 3x2 ]
         [ YOU ]                          自分の領域を、共通の領域（精神トラック・目標）より先に置く（010 §3.7。維持）
         [ 精神トラック ][ 目標カード ]
         [ B ][ C ][ D ]
```

- 010 §3.3 の配置の判定式・カード幅の式（`layout-math.js`）は変えない。想起の案内パネルの高さを環の下端から引く検査の処理（`scripts/preview/shoot.mjs` の `measureLayout`）は、候補の置き場だけを引く形に簡単にする。
- 配置ごとの見え方（010 §3.4 の表）は想起の案内パネルを含んでいなかったので、変わらない。

#### 2.3 bga-jump-to を廃止し、パネルの移動ボタンにする

**【確認】** UxPdf A.3: パネルは冗長な情報を入れず、「主な部品へ移る HTML のアンカー（ショートカット）」は置いてよい。C.4: 縦のレイアウトにはジャンプのリンクを置く。審査が名指しした「左の浮いたボタン」は、bga-jump-to のライブラリの `position: fixed` の丸（§0.1 R6）と同じ種類の部品なので、**ライブラリごと廃止**する。

- 廃止するもの: `Game.js:33` の `importEsmLib('bga-jump-to', '1.x')`、`:48` の `this.jumpTo`、`:160` の呼び出し、`:169-192` の `setupJumpTo`、`localStorageFoldedKey`、CSS の `.bga-jump-to_controls` の規則（`:1505-1513`）、`<html data-ir-layout>`（`ui/Layout.js`。他に使う所がなければ。実装時に grep）。プレビューの検査の `.bga-jump-to_link`（`scripts/preview/shoot.mjs:45`）。
- **各プレイヤーパネルの右端に、そのプレイヤーの領域へ移る小さなボタン**を置く。32×32px、下向き矢印の SVG（`chevron-down`）、`aria-label` は `Go to ${player_name}'s area`（`_()` で翻訳可能。`replace` で名前を入れる）。押すと `document.getElementById('ir-player-area-' + playerId).scrollIntoView({ behavior, block: 'start' })`（`prefers-reduced-motion` のときは `behavior: 'auto'`）。観戦者は全員分、プレイヤーは自分のパネルからは自分の領域へ。全配置で出す（xl・wide でも害がない。010 の「medium・narrow だけ」から変える。理由: パネルのボタンは浮かず、配置でレイアウトが変わらない。ただし xl・wide では領域が見えているので出さない選択も可 → 未確定事項 No.8）。
- ボタンの位置: パネルの 2 行の右端に**縦に跨って**置く（パネルの高さは 33px【実測】なので、32px のボタンが 2 行の高さに収まり、パネルは高くならない）。仕様の「1 行目の右端」からの読み替えで、1 行目だけに置くと行が 32px に伸びてパネルが約 50px になるため。390px の 2 列の格子（パネル幅 約 170px）で 2 行目の鍵 4 種（約 125px）と並ぶかをプレビューで確かめ、入らなければ 1 行目に置く（その場合は 1 行目が高くなるのを許す）→ 未確定事項 No.9。（U-17 の結果: 縦に跨る形に決めた。390×844 と 360×780 のどちらでも `.ir-pp` は高さ 33px、2 行のままで、1920×1080 でも同じ。鍵の行の隙間を 6px から 3px、ボタンとの隙間を 2px に詰めたので 360px でも収まる。ボタンの `title` は付けない。）
- スクロール位置: BGA のステータスバーが上に貼り付く（【未確認】。審査の「フッターに重なる」の文脈と 014 の前提）なら、領域の見出しがバーに隠れる。`.ir-player-area { scroll-margin-top: 96px }`（【提案】。値は Studio で測る）を付ける。
- ボタンは `button type="button"`。ツールチップは `Go to …` の文字だけ（§6）。

#### 2.4 入れ子のスクロールを無くす

実際にスクロールしうる要素（§0.1 R6 で洗い出し）と扱い:

| 要素 | 現状 | 扱い |
| --- | --- | --- |
| `.ir-zoom-scroll`（`:1378`。拡大表示の文章。タッチの長押しの拡大表示） | `overflow-y: auto` | 拡大表示を廃止する（§6）ので消える。タッチで開いたツールチップの内側だけ、最後の手段として許す（§6.5） |
| `.ir-caption`（`:1034`）、`.ir-zoom-dialog`（`:1357`） | `overflow: hidden`（スクロールしない） | `.ir-caption` はそのまま（省略記号）。`.ir-zoom-dialog` は廃止 |
| `.ir-players`（xl の右の列） | 縦に伸びるだけ（スクロール要素ではない） | そのまま。ページ全体の縦スクロールだけを使う |
| bga-jump-to のパネル | ライブラリ内 | 廃止 |

ページ全体の縦スクロールと、BGA 側のスクロール（ログなど）以外に、`#ir-root` の中にスクロールする要素を作らない。§9 の検査 6 で監視する。

### 3. 上部と案内（指摘 R3）

#### 3.1 想起の案内パネルを廃止する

**【確認】** UxPdf B.1: 「同じ情報を同じ見せ方で 2 回出さない」。想起の案内パネル（009 §3.9）は、カードの拡大画像と英語の効果文という、**カードのツールチップと同じ内容**を常設の箱で繰り返している（§0.1 R3）。009 §3.9 の目的は「日本語が読めないプレイヤーが、割り込みの想起効果（006）で何を求められているかを読める」こと。代わりに次の 2 つで満たす。

1. **表に向けた部屋のカードに「対象」の印（`ir-subject`。§5）を付ける。** 想起中は、環の中の部屋の裏向きカードが表向きになる（`notif_recallStarted` → `common.revealRoomCard`。`notifications.js:61-66`）。そのカード（`.ir-room[data-pos]` の想い出カード）に `ir-subject`（金の破線の輪郭）を付け、想起が終わるとき（`notif_phaseStarted` が `RECALL` 以外・`notif_roomCardAcquired`。現状の `hideRecall` の呼び出し箇所 `notifications.js:72`・`:93`・`:162` と同じ）に外す。再読み込みの復元は `recallFromGamedatas`（`board/view-model.js:212-224`）に `pos`（`gamedatas.rooms` の行の `pos`）を足して使う（クライアントだけの変更）。
2. **ステータスバーの文に、カード名と想起であることを入れる。** カード名はログと同じ `span.ir-log-card`（`ui/log-format.js:93`。ホバー・タップでツールチップ）にする。英語の効果文の全文はそのカードのツールチップで読める。

#### 3.2 想起のステータスバーの文（コードで確認）

- **サーバーの args に必要な値は既にある。PHP の変更は要らない。** 状態 71（`EffectChoice`）の args は `source`（出典のカード。想起効果では想い出カードのキー、例 `M015`）と `spec`（効果の識別子。想起効果は `RC-xx`。`Material/EffectCatalog.php:96-101`）を持つ（`States/EffectChoice.php` の `getArgs`、`Flow/ChoiceFlow.php:71`、`states/choice-model.js:61`。プレビューの 4p-recall の args は `"source": "M015", "spec": "RC-02"`【実測】）。想起かどうかは `spec` が `RC-` で始まるかで分かる。
- 現状の `ChoiceState.draw`（`states/ChoiceState.js:145-320`）の `setTitle` は 13 か所で、カード名を持つのは `CONFIRM_USE`（`:172`。`${card}`）だけ。
- **新しい文**: 想起の選択では、文の先頭にカード名を置く。例: `Recall ${card}: ${you} must take 1 open card(s)`。実装は、想起（`spec` が `RC-`）かどうかで文のテンプレートを切り替える（`_()` は抽出のためリテラルで書く）:

```js
const recall = choice.spec.startsWith('RC-');
bar.setTitle(recall ? _('Recall ${card}: ${you} must take ${count} open card(s)') : _('${you} must take ${count} open card(s)'), { card: cardNameHtml(choice.source), count });
```

対象は `ChoiceState.draw` の全種類（場札を取る・手札を捨てる・心光のための捨て・手札と場札の交換・同コストの交換・部屋の入れ替え・ワープ・移動の確認・分岐 など）。出うる種類は PHP の `Effects/` の想起効果から実装者が確かめ、出うる種類だけに想起の版を足す（`${card}` は 12 文字前後の名前なので、文は 60 文字以内を目安）。`cardNameHtml` は `ui/log-format.js` の `span.ir-log-card` と同じ HTML を返す関数（`ctx.texts` の英語名。`data-ir-zoom-key` を付ける。§6.8）。

- 他プレイヤーが割り込みで答えている間、答えない人の BGA 標準のタイトルは `${actplayer} must make a choice`（`EffectChoice.php` の `description`。サーバー側の定義）のままで、カード名は出ない。その人には部屋のカードの `ir-subject`・ログ（`${source_name}` が `span.ir-log-card`）・パネルの手番の強調で足りる（待っている人にはやることがない）。サーバーの変更でカード名を足す案は不要と判断した（BGA の標準の `description` に `${card}` を出すには `getArgs` の戻り値に名前を入れる必要があり、サーバーが英語名を持たない）。
- **【未確認】** `setTitle` の `args` に HTML（`span`）を渡すとそのまま描かれるか、エスケープされるか。ログは `bgaFormatText` を通して HTML を描ける（`ui/log-format.js`。010 の U-8 で Studio 確認済みの形）が、`setTitle` が同じ経路を通るかは公式の文書に書かれていない。実装者は Studio で確かめる。**描かれない場合の代案**: 同じ文書の既存の部分が使っている `span.ir-log-card` を、`#page-title` の中のテキストを走査して後から包む（委譲のツールチップが `document` で受けるので、`.ir-log-card` が `#page-title` の中にあれば動く）。

#### 3.3 候補の置き場（`ir-pick-panel`）をレールにする

- 位置は今のまま（環の上。§2.1 で `#ir-main` の先頭）。**箱をやめ**（`:904-915` の枠・背景・角丸 10px）、上端の 1px の線とラベル（`Choose an Initial Ego` / `Choose an Enhanced Ego` などを `_()` で）のレールの区画にする。ラベルは候補の置き場自身（`.ir-rail-label` を要素の先頭に）。カードは `ir-can` / `ir-picked`（§5）。
- 初期自我のドラフトは `SetupEgoPick`（状態 21）、強化自我は `ChoiceState` の `PICK_ENHANCED_EGO`（`:195-203`）。どちらも `ActionState.showCandidates`（`:295-325`）を使う。ラベルの文はこの 2 か所で渡す。

#### 3.4 ステータスバーの文の見直し

**【確認】** UxPdf A.2: バーは現在の状態を示す。長い説明文はバーに出さない。方針: (1) 盤面に見えているもの（経路の部屋の順番）は文に繰り返さない、(2) 鍵・心光はアイコン（`ui/icons.js` の `createIcon`。UI スプライトの `{wisdom}` などの小さな画像。ログと同じ `iconHtml`（`Game.js:203-226` の `bgaFormatText` が使う形）で HTML にする）、(3) 目安は 50 文字以内。アイコンの HTML を `setTitle` の `args` に渡せるかは §3.2 の【未確認】と同じ。描かれない場合は、アイコンの代わりに英語の鍵の名前（`Wisdom` など。`neededText` の現状）に戻す。

| 状態 | 今の文（英語原文） | 新しい文（`_()`） |
| --- | --- | --- |
| 21 初期自我の選択 | `${you} must choose an initial ego card` | 同じ |
| 24 人格の配置 | `${you} must place your persona in a corner room` | 同じ |
| 31 移動（経路なし） | `${you} must choose the next room to enter` | `${you} must choose the next room`（盤面の `ir-can` が答え） |
| 31 移動（払えない） | `${you} cannot afford to move` | 同じ |
| 31 移動（経路あり） | `Route: ${route}. Locks: ${paid} / ${total} paid. Still needed: ${needed}. Unused: ${items}`（実測 86 文字。`MoveState.js:250`） | `Locks: ${paid} / ${total}. Need: ${needed}`（`${needed}` は鍵のアイコンと個数。例 `Need: 2 {wisdom} 2 {will}`）。`Unused: ${items}` は余りがあるときだけ後ろに足す。**経路は盤面の順番の札（§5.5）で見えるので文に出さない** |
| 51 床アクション | `${you} must choose a floor action` | 同じ |
| 60 自己構成フェイズ | `${you} may compose, analyze, or end the compose phase` | `${you} may compose or analyze`（「終える」は赤い `End construct phase` のボタンが示す） |
| 61 自己構成（カード選択） | `${you} must choose a card to compose` / `${you} have no card you can compose` | 同じ |
| 61 自己構成（支払い） | `Compose ${card}. Energy: paid ${paid} of ${need}. Still needed: ${n} energy. Unused: ${items}`（`ComposeState.js:136-163`） | `Compose ${card}: energy ${paid} / ${need}`（足りないぶんは数字で分かる）。エネルギー不要は `Compose ${card}: no energy needed`。`Unused: ${items}` は余りがあるときだけ。`${card}` は `ir-log-card` |
| 61 Architect | `Swap with Architect: choose a constructed card, then a card in your hand` | 同じ |
| 62 精神分析（行き先） | `${you} must choose a spirit card to advance to` / `${you} cannot pay for any psychoanalysis now` | 同じ |
| 62 精神分析（支払い） | `Tier ${tier} ${branch}: Keys: ${paid} / ${total} paid. Still needed: ${needed}. Removed by the reduction: …`（`AnalysisState.js:131`） | `Tier ${tier} ${branch}: keys ${paid} / ${total}. Need: ${needed}`（アイコンつき）。`Removed by …` は余りがあるときだけ |
| 71 想起の選択 | `${you} must take ${count} open card(s)` ほか（カード名なし） | `Recall ${card}: ${you} must take ${count} open card(s)` など（§3.2。想起でない選択は今の文のまま） |
| 71 効果の確認 | `${you} may use the effect of ${card}` | 同じ（`${card}` を `ir-log-card` に） |
| 72 手札の上限 | `${you} must discard ${count} card(s) down to the hand limit` | 同じ |
| 手札を捨てて心光（フリーアクション） | `Select Memory cards to discard for Psychic Light (gain ${light})`（`FreeActionsUi.js:107`） | `Discard cards for Psychic Light (gain ${light})` |

- 翻訳の文字列は、変更したものだけ追加する（`_()` の原文を変える）。使われなくなる文は PHP の `clienttranslate` と無関係（クライアントの `_()` だけ）なので、翻訳ファイルの整理は実装 PR で `npm run lint` が通る範囲でよい。

#### 3.5 目標カードの現在値

目標カードの下の「Now: X = 2 → 2 pts」（`board/CommonArea.js:624` が `.ir-goal-badge` を作る。自分の分だけ）は、**暗い札（`rgb(255 255 255 / 70%)` 系の背景 `var(--ir-panel-bg)` の帯）をやめ**、カードの下に 12px の `--ir-ink-dim` の控えめな注記（背景なし）として残す。紙には無い補助情報だが、他のどこにも出ない値で重複ではない（010 §4 の判断を維持）。カードに重ねる（`position: absolute; bottom: 0`。`:1160-1172`）のをやめ、カードの下（`top: 100%`）に出し、`.ir-goals` に注記の行の高さ（約 16px）の余白を足す。2 行に折り返してよい。

#### 3.6 変更するファイル（U-19）

`board/Board.js`、`board/view-model.js`（`pos`）、`notifications.js`、`ui/recall-panel.js`（想起のパネルの部分。U-18 で `card-info.js` から改名した `RecallPanel`。`showRecall` / `hideRecall` を消す）、`ui/log-format.js`（`cardNameHtml` の切り出し）、`states/ChoiceState.js`、`states/MoveState.js`、`states/ComposeState.js`、`states/AnalysisState.js`、`states/TurnStates.js`、`states/FreeActionsUi.js`、`states/payment-ui.js`、`states/ActionState.js`（候補の置き場）、`board/CommonArea.js`（目標の注記）、`irevolvethereforeiam.css`、`tests/js/choice-model.test.mjs` ほか。

### 4. プレイヤーの領域とパネル（指摘 R4）

**【確認】** A.3: 標準のパネルにコンパクトな情報を置く。A.1: 関連する情報を複数の隅に散らさない。審査: 「紙のゲームが必要としない場合を除き、重複させない」。

#### 4.1 領域の構成

- **領域の箱・手番の太い枠・「Your turn / Current turn」の札を廃止**する（§0.1 R1。`.ir-player-area` の枠・背景・角丸、`.ir-turn`（`:487-491`、`board/PlayerAreas.js:204-209`）、`.ir-turn-mark`（`board/Board.js:200`））。手番は BGA 標準のパネルの強調とステータスバーで分かる（【推定】。BGA の標準のパネルは手番プレイヤーを強調する。Studio で確かめる → 未確定事項 No.3。009 §2.5・010 §3.7 の「手番の領域は枠の太さと文字で示す」の変更）。
- **自分の領域**（上から）: 見出しのレール（ミープルの形・名前・N / 20）→ 自我（初期・強化）・トークン・獲得した目標の行 → 手札のレール → 自己構成済みのレール（「Constructed」）。
- **他プレイヤー**: 見出し → 自我・トークン・目標の行 → 自己構成済みのレール。手札は出さない（枚数はパネル）。**他プレイヤーのカードの大きさは変えない**（010 No.8）。
- 観戦者・リプレイは自分の領域がなく、全員が他プレイヤーの形（010 のまま）。
- 手札と自己構成済みの区別（010 No.3）は §1.2 のとおり、ラベルの文字とマットの色合い（丸いタブはやめる）。手札の上限超過の警告は、手札のレールのラベルの横に、警告の SVG と「N / 8」を置く（`.ir-hand-info` の札・丸をやめる）。

#### 4.2 プレイヤーパネル

- 2 行（スコア・手札の枚数 ／ 鍵 4 種）は**維持**（010 §4）。アイコンは 14px 以上（現状 14px。`.ir-pp-icon` `:1195-1199`）。文字は 12px 以上（`.ir-pp` `:1174` の `12px` のまま）。
- **`title` 属性をやめ**、文字だけのツールチップ（§6）にする（`board/PlayerPanels.js:189`）。`aria-label` と `role="group"` は残す。
- パネルに**領域へ移るボタン**（§2.3）を右端に置く。390px の 2 列の格子で、パネルが 2 行に収まる（高さ 33px 以内）かをプレビューで確かめる（【実測】現状の `.ir-pp` は 4 パネルとも 33px）。

#### 4.3 領域の高さ【実測】

4p-late の自分の領域の高さ: 1920×1080 で 1190px、1366×768 で 1019px、390×844 で 1264px。手札 8 枚・自己構成済み 11 枚・強化自我・獲得した目標つき。010 の「縮小・折りたたみは作らない」（010 No.8、009 §3.11）を維持するので、高さそのものは変えず、箱と札の分（見出し・余白）だけが減る。

#### 4.4 1 つの値を 1 か所に（自己構成スコアの例外を除く）の再確認

| 値 | 場所 | 判定 |
| --- | --- | --- |
| 自己構成スコア N / 20 | パネル + 領域の見出し | 010 の例外（A.1 は得点をパネルだけに置かない）。維持 |
| 手番 | パネル（BGA 標準の強調）+ ステータスバー | 領域の札・太い枠を廃止（上）。重複が減る |
| 手札の枚数 | パネルだけ（領域には出さない） | 維持。上限超過の警告だけ手札のレールに出す（役割が違う: 動作の促し） |
| トークン 4 種 | 領域だけ（パネルには出さない） | 維持 |
| 精神階層 | 精神トラックのキューブだけ | 維持 |
| 鍵の本数 | パネルだけ | 維持 |
| 目標カードの現在の X と得点 | 目標カードの下の注記 | 維持（§3.5） |
| 想起中のカード | 部屋のカード（`ir-subject`）+ ステータスバーのカード名 | 想起の案内パネルを廃止して、箱による繰り返しを解消 |

**残る重複は、自己構成スコア（例外）だけ。** 手札のレールの警告・「Used」の帯・錠前のオーバーレイ（§7）は値の重複ではなく状態の印または可読性のための補助。

#### 4.5 変更するファイル（U-17）

`board/Board.js`、`board/PlayerAreas.js`、`board/PlayerPanels.js`、`irevolvethereforeiam.css`。

### 5. 操作の見た目（指摘 R7・C.2・E.1）

**【確認】** UxPdf C.2（014 の表のとおり）: 「何を押すのか」を迷わせない。選べる選択肢を強調する。E.1: 色にアイコン・形・模様を添える。点滅や素早い切り替えをしない。

#### 5.1 共通のクラス

カード・部屋・トークン・精神カード・目標に、同じ意味で同じクラスを使う。

| クラス | 意味 | 見た目 | 色以外の手がかり |
| --- | --- | --- | --- |
| `ir-can` | 選べる | 白（`--ir-can`）の輪郭 2px + 外側の柔らかい光（`box-shadow: 0 0 8px 2px var(--ir-can-glow)`）。マウスは `cursor: pointer` | 輪郭の有無 |
| `ir-picked` | 選択中 | 金（`--ir-pick`）の輪郭 3px + 角にチェックの SVG の印（金の小さな四角の地に `--ir-on-pick` のチェック）。選択数を持つトークンは数字の札（§5.4） | 太さ、印 |
| `ir-off` | 今は選べない | 暗くする（`filter: brightness(0.6) saturate(0.7)`。部屋はやや弱く §5.3）。押すと理由をメッセージで出す（§5.7） | 暗さ + 押したときの文 |
| `ir-subject` | 効果の発生源・問いの対象 | 金（`--ir-subject`）の破線の輪郭 2px | 破線 |

- 点滅しない。ホバーでの持ち上げもしない（bga-cards の既定の `selected-card` の `translateY(-1/5 高さ)` もなくなる）。ホバーは `ir-can` の光を少し強くするだけ（`.notouch-device` のみ）。
- クラスは `cards/selection.js` が 1 か所で付け外しし、状態を出るとき `clear()` で全部外す（003 §6.2・姉妹 MJ #46 の教訓。移動・ActionState・トークンの付け外しも同じ。今の `MoveState.clearMarks` `:180-186`、`render()` の頭の再描画）。

#### 5.2 旧クラスとの対応表と、使っているファイル

| 旧 | 新 | 使っているファイル・行 |
| --- | --- | --- |
| `.ir-card.ir-selectable`（outline 2px + ○。`css:841-859`） | `ir-can` | `cards/selection.js:10`・`:53`・`:63`、`board/PlayerAreas.js:16`・`:841`、`ui/card-info.js:27`（`SELECTION_SELECTOR`。U-18 で消える） |
| `.ir-card.ir-selected`（赤 3px + ✓。`css:861-881`） | `ir-picked` | `cards/selection.js:12`・`:51`・`:64`、`board/PlayerAreas.js:841` |
| `.ir-card.ir-pending`（青 5px + ★ + 光。`css:883-902`） | `ir-subject`（§5.6） | `board/PlayerAreas.js:63`・`:687-708`（`markHand`・`unmarkHand`）、`states/ComposeState.js:164` |
| `.ir-room.ir-room-selectable`（赤 3px。`css:359-363`） | `ir-can` | `states/MoveState.js:23`・`:238`、`states/ActionState.js:12`・`:193`・`:213` |
| `.ir-room.ir-room-undo`（cursor だけ。`css:365-367`） | 見た目なし。`ir-undo` のフック（cursor: pointer）に改名 | `states/MoveState.js:27`・`:228` |
| `.ir-room.ir-room-route::after`（赤い丸の数字。`css:369-385`） | 経路の部屋は `ir-picked` + 金の四角い札の順番（§5.5） | `states/MoveState.js:25`・`:219`、`states/ActionState.js:12`・`:201` |
| `.ir-token.ir-selectable` / `.ir-selected`（`css:690-698`）、`ir-token-pick` | `ir-can` / `ir-picked`、`ir-token-pick` は押せるフック（cursor）として残す | `board/PlayerAreas.js:47`・`:807`・`:841`・`selectionClasses` |
| `bga-cards_selectable-card` / `bga-cards_selected-card` / `bga-cards_unselectable-card`（bga-cards の既定。オレンジの破線 5px、紫の実線 5px、`contrast(0.6)`） | 上書きして無効化（§5.3） | ライブラリ。`cards/managers.js:45-64`（Manager の設定） |
| `.ir-pick-panel` の候補カード | `ir-can` / `ir-picked`（`SelectionController` が付ける） | `states/ActionState.js:272-325`（`pickSpirits`・`showCandidates`） |
| 精神カード・目標カードの選択 | `ir-can` / `ir-picked` | `states/ActionState.js:272`（`pickSpirits`）、`states/FreeActionsUi.js`（目標） |

#### 5.3 bga-cards の既定の見た目の上書きと、選べないものの暗さ

**【確認】**（bga-cards のソース。§記法の表）bga-cards 1.x は、選択モードの Stock の選べるカードの**面の要素**（`card-sides` の中）に `bga-cards_selectable-card`（5px のオレンジの破線の outline）、選べないカードに `bga-cards_unselectable-card`（`contrast(0.6)`）、選択中に `bga-cards_selected-card`（5px の紫の outline と上へ 1/5 の持ち上げ）を付ける。一方このリポジトリの `selection.js` は `ir-selectable` などを**カード全体の要素（`.ir-card`）**に付ける。結果、選べるカードには 2 つの見た目が重なる（オレンジの破線 + 白の実線）。

- **上書きの方法（推奨）**: `cards/managers.js` の `new BgaCards.Manager({…})`（`:45`）に設定を足し、ライブラリのクラスを中身のない名前に差し替える:

  ```js
  selectableCardStyle: { class: 'ir-lib-can', outlineSize: 0 },
  selectedCardStyle: { class: 'ir-lib-picked', outlineSize: 0 },
  unselectableCardStyle: { class: 'ir-lib-off' },
  ```

  `ir-lib-*` には CSS を書かない（空のクラスは `removeSelectionClassesFromElement` の `classList.remove` で問題にならない。**空文字の `class` は `classList.remove('')` が例外を出すので使わない**）。これで既定の outline・`contrast`・持ち上げが消え、見た目は `ir-can` / `ir-picked` / `ir-off` だけになる。**【未確認】** Studio で読み込まれるライブラリの版（`1.x` の最新）でも同じ設定名か。実装時にプレビュー（CDN の最新）で確かめる。
- **代案**: `.card-stock .bga-cards_selectable-card, .card-stock .bga-cards_selected-card { outline: none; transform: none }` と `.bga-cards_unselectable-card { filter: none; cursor: default }` を CSS に足す（設定が効かなかったとき）。
- **選べないカードを暗くする（`ir-off`）**: 現状は `selection.js` の `paintElement` が選べないカードには何も付けない（ライブラリ既定の `contrast(0.6)` が掛かっているのがリポジトリの意図とずれている）。新しくは、**選択モードの Stock の中の、選べないカードだけ**に `ir-off` を付ける（`SelectionController.paint` の中。`entry.ids` が `null`（全部選べる）のときは付かない）。選択モードでない Stock のカードには付けない（例: 手札だけを選ぶ状態で、自己構成済みのカードを全部暗くしない）。
- **009 §4.3（「選べない: 暗くしない」）と §3.1（部屋の「選べない部屋は暗くしない」。姉妹 MJ #46 の教訓: 暗くするクラスが外れず次の状態でも暗いままになった）を変更する。** 理由: 審査（R7・C.2）は「合法な行き先と選べない部品を一貫した見た目で伝える」ことを求める。教訓は、クラスの付け外しを 1 か所に集めて状態を出るとき必ず掃除する（上）ことで守る。**部屋は床の情報（床アクション）を読む必要があるので、暗さを弱くする**（`brightness(0.75)`）→ 未確定事項 No.5。

#### 5.4 トークンの選択（心光・万能移動鍵・自己構成・精神分析）

010 §2.4 の操作（押して数量を選ぶ）は維持し、見た目を §5.1 に合わせる:

- 押せるトークン: `ir-can`（画像の周りに白の輪郭と光）。押したぶんの個数は、トークンの角に金の長方形の札（32×32px、角丸 2px。`ir-token-badge` を再利用。数字は `--ir-on-pick`）で出し、選んだ個数が 1 以上のトークンは `ir-picked`（金の輪郭）。札を押すと 1 つ外す（現状の挙動）。
- 「+N」（`ir-token-plus`）は小さな長方形（2px）に。
- 上限に達したトークンは `ir-can` を外す（押しても何も起きない。010 のまま）。理由の文は §5.7。

#### 5.5 移動の計画

- 入れる部屋は `ir-can`、**入れない部屋は `ir-off`**（弱い暗さ）。ミープルは暗くしない（部屋の下の要素のみ。実装は `.ir-room.ir-off .ir-room-floor-box, … .ir-room-card-box` に `filter`、`.ir-room-meeples` は除く）。
- 経路の部屋は `ir-picked`（金の輪郭）+ **経路の順番を金の小さな四角い札に番号**（今の赤い丸の `content: attr(data-order)` をやめる）。`.ir-room-route::after` を、`--ir-pick` の地・`--ir-on-pick` の文字・角丸 2px・min 20px の四角に。最後の部屋は押すと 1 つ戻る（`ir-undo`。010 のまま）。
- 色だけでなく、輪郭の有無・太さ・番号の札で区別する（E.1）。

#### 5.6 `ir-pending`（自己構成するカードとして選んだ手札）は `ir-subject` に統合する

コードの使い方（`ComposeState.js:164`、`board/PlayerAreas.js:687-708`）: `markHand([cardId])` は、自己構成の支払いの画面で「今から自己構成するカード」を手札の中で印すだけで、このカードは**選べない**（捨てる手札の選択から外される）。つまり「選択」ではなく「問いの対象」（何を自己構成するかという、支払いの問いが指している対象）で、`ir-subject` の意味そのもの。`ir-picked` に統合すると「捨てるために選んだ手札」と区別が付かなくなる（同じ手札のレールに両方が並ぶ）ので、`ir-picked` ではなく `ir-subject` にする。破線と太さ（2px）で `ir-picked` の 3px 実線と区別できる。`markHand`・`unmarkHand`・`CLASS_PENDING`・`--ir-pending`・CSS の `.ir-pending` と ★ は削除する。

#### 5.7 選べないものを押したとき（D.1。無反応にしない）

**【確認】** UxPdf D.1: 無効な操作には必ず理由を出す。「何も起きない」は不可。Dialogs: `bga.dialogs.showMessage(msg, 'info')` は白い背景で画面の上に数秒出る。`'error'` は赤い背景でログにも載り、誤クリックのたびにログが汚れるので使わない（014 と同じ判断）。

- `ir-off` の要素、選べない部屋、選べないトークン（上限に達したもの）を押したら `showMessage(理由, 'info')` を出す。同じ文は 2 秒以内に繰り返さない。
- **理由の元（コードで確認）**: サーバーの args に理由はない（`MovePhase::planningData` などが持つのは候補の一覧で、選べない理由の文はない）。クライアントで求められるもの: トークンの上限（`payment-ui.js:84-90` の `tooltip`。`You have selected as many as you can use` / `You cannot use this now`）、移動で入れない部屋（`reachableRooms`（`states/move-plan.js:804`）に入らない。次の部屋でない・払えない、の区別は `move-plan.js` から求められるかを実装者が確かめる【未確認】）、手札・自己構成済みのカード（状態ごとの候補の絞り込み: レベル、使用済みなど。個別の理由の文は今ない）。**無ければ一般の文 `You cannot choose this now`**。各状態は要素に `data-ir-why`（短い英語の文。`_()`）を置いてよく、管理役（`cards/selection.js` と部屋・トークンのクリックの共通の処理）はそれを優先する。
- 呼び出し: `this.bga.dialogs.showMessage(message, 'info')`（`Game.js` の `bga` を状態クラスが持つ）。`cards/selection.js` は DOM と bga-cards に依存した層なので、メッセージの出し方はコールバック（`onBlockedClick(element)`）で受ける。Stock の `ir-off` のカードの押下は、Stock の要素（`stock.element`）への委譲のクリックで受ける（bga-cards は選べないカードのクリックを無視する）。
- **プレビューに `bga.dialogs.showMessage` の代用が要る**（現状のプレビューには `dialogs` がない【実測】）。画面の上に白い箱を 3 秒出し、`window.__irMessages` に積む（§9）。
- 同じ文の抑止は純粋関数（`ui/message-throttle.js`。`shouldShow(message, now)`）にして `tests/js/` で検証する。

#### 5.8 変更するファイル（U-19）

`cards/selection.js`、`cards/managers.js`、`board/PlayerAreas.js`、`states/MoveState.js`、`states/ActionState.js`、`states/ComposeState.js`、`states/AnalysisState.js`、`states/ChoiceState.js`、`states/payment-ui.js`、`irevolvethereforeiam.css`、`tests/js/player-areas.test.mjs`、新規 `ui/message-throttle.js` と `tests/js/message-throttle.test.mjs`。

### 6. ツールチップ（指摘 R2・B.1。作り直し）

**【確認】** UxPdf B.1: ツールチップは洞察のある情報（翻訳可能、使う時機の助言）を載せ、小さな文字・アイコンの拡大にも使う。画面と同じ情報を同じ見せ方で繰り返さない。モバイルは「タップ」で見られるようにし、**外側をタップしたら閉じる**。GameUi: BGA 標準のツールチップは対象の id が要り、タッチの挙動の説明がなく、重要な情報を載せない。

方針は 014 と同じく、`addTooltipHtml` は使わず、`ui/card-info.js` のツールチップと拡大表示を**1 つの管理役**に作り直す。理由: 部品を頻繁に描き直すので id の管理が要る・タッチの挙動を確かめられない・背の高い内容を画面に収めたい。自前なら位置と画面内の収まりをプレビューで自動に確かめられる。

#### 6.1 管理役（委譲）

- `ui/Tips.js`（新規。仮名）が `document` で `pointerover` / `pointerout` / `pointerdown` / `pointerup` / `pointercancel` / `keydown` / `scroll` を受ける（部品を描き直しても結び直しが要らない）。
- **指定**: 要素に `data-ir-tip` を付ける。カードは `data-ir-tip="card:M015"`（キーは既存の `data-ir-zoom-key` / `data-ir-key` と同じ。カードの特定は今の `targetOf`（`ui/card-info.js:126-152`）と同じ規則）。文字だけは `data-ir-tip="text"` + `data-ir-tip-text="…"`（描き直しのたびに属性を入れる）。ログ・ステータスバーのカード名（`span.ir-log-card`）は既存の `data-ir-zoom-key` で同じ扱い。
- カードの中身（名前・数値・効果文）は `ui/card-info-model.js` の `describeCard`（009 §7.4 の表と同じ。**変更しない**）と、`ui/card-info.js` の `buildInfo`（`ui/tip-content.js` へ切り出す）。

#### 6.2 出し方

| 入力 | 対象 | 動作 |
| --- | --- | --- |
| マウス | 通常の要素 | ホバー 400ms で出す |
| マウス | 選択中の要素（`ir-can` / `ir-picked`） | ホバー **700ms**（選ぶ操作の邪魔をしない） |
| タッチ | **選べない要素**（`ir-can` でも `ir-off` でもない。例: 他プレイヤーのカード、部屋、パネルの項目） | **タップ**で出す |
| タッチ | 選べる要素（`ir-can` / `ir-picked`）と `ir-off` の要素 | **長押し 500ms**（タップは選択・理由のメッセージに使う。§5.7） |
| 閉じる | — | 外側のタップ・スクロール・Esc・状態の切り替え（`onLeavingState`、`ir-can` などの付け替え）・別の要素への切り替え |

- 入力の種別は**イベントごと**の `pointerType` で判定する（マウスとタッチが両方ある端末で、どちらでも出せる）。`.touch-device` のクラスには頼らない。
- 長押しの間に指が 10px 以上動いたらキャンセル（現状の `MOVE_TOLERANCE_PX`）。長押しで出した直後の `click` は、選択として働かないよう 1 回止める（現状の `OPEN_GUARD_MS`・`suppressClick` と同じ）。Android の長押しのコンテキストメニューは出さない（現状の `contextmenu` の抑止）。
- 外側をタップして閉じるタップは、**閉じるだけでなく、タップした先の操作も通す**（読んだあとにそのまま次を選べる。タップを飲み込まない）。
- ログの中のカード名・ステータスバーのカード名にも同じ（`#ir-root` の外なので `document` で受ける。現状の `attachLog` の方式）。

#### 6.3 位置（純粋関数 `placeTip`）

`ui/tip-placement.js`（新規）に純粋関数を置き、`tests/js/tip-placement.test.mjs` で単体テストする。`ui/zoom-math.js` の `tooltipPosition`・`zoomLayout` と `tooltipImageSize` は廃止してここに統合する（`tests/js/card-info.test.mjs:10` の import も直す）。

```js
placeTip({
  anchor: { left, top, right, bottom },   // 対象の矩形（getBoundingClientRect）
  size: { width, height },                // ツールチップの希望の大きさ
  viewport: { width, height },            // window.innerWidth / innerHeight
  top: number,                            // 使える上端（BGA のステータスバー #page-title の下端。なければ 0）
  margin = 8, gap = 8,
}) // => { placement: 'right' | 'left' | 'below' | 'above' | 'fit', left, top, maxWidth, maxHeight }
```

- **対象のすぐ横**に出す。入る側を **右 → 左 → 下 → 上** の順で試す。どの側にも希望の大きさで入らないときは、**最も広い側**を選び、その側の余白に収まる `maxWidth` / `maxHeight` を返す（管理役がそれに合わせて中身を縮める。§6.4）。
- **対象を覆わない**（返す矩形は `anchor` と交差しない。左右に置くときの縦、上下に置くときの横は画面内に挟む）。
- **画面の端から 8px 以上離す**（上は `top`（ステータスバーの下端）+ 8px、下は `viewport.height` − 8px）。
- ステータスバーの下端は `document.getElementById('page-title')?.getBoundingClientRect().bottom`（今の `ui/card-info.js:383-384` と同じ）。**プレビューには `#page-title` がない**ので、§9 でステータスバーに `id="page-title"` を付ける。
- 縦のスクロールバー・iOS のツールバーのため、高さの基準は `window.innerHeight`（009 §7.2、003 §13）。

#### 6.4 背の高い内容を収める

ホバーで出したツールチップは `pointer-events: none`（スクロールできない）なので、**必ず画面に収める**（014 と同じ決定）。管理役は `placeTip` が返す `maxWidth` / `maxHeight` に合わせ、次の順に形を選ぶ:

1. 画像と文を横に並べる（見える幅が 480px 以上のとき。今の想い出カードの形）。
2. 収まらなければ、幅を最大 560px まで広げて文の行数を減らす（今の `max-width: min(560px, 92vw)`）。
3. それでも収まらなければ、**画像を縮める**。最小の幅は、想い出カード（縦長）**120px**、横長カード（床・精神・自我・目標）**180px**。ただし**卓の上の同じカードより大きいこと**（B.1 の「画面の見え方と明確に違う」）: 画像の幅は常に卓の上の同じカードの幅の 1.25 倍以上にする（部屋の床 W = 150〜213、手札 84〜128、精神・目標 105〜114 に対して。最大の希望は縦長 220、横長 300 で、現状の `tooltipImageSize` の 210・300 より縦長を少し大きくする）。1.25 倍と最小幅が両立しない画面（見える高さが 360px 未満など）では、画像を出さず**文字だけ**のツールチップに落とす（名前・数値・効果文は残る）。

【実測】現状の寸法（1366×768、見える高さ 638）: 縦長 305px、横長 423〜430px。ステータスバーが約 140px 貼り付くとすると使える高さは約 490px で、縦長は余裕があり、横長（画像 300×215 + 文）は収まるが余裕は小さい。これより低い画面では 3 の縮小が働く。

- **タッチで開いたときだけ**、最後の手段として中身のスクロールを許す（`pointer-events: auto`、`overflow-y: auto`。§2.4 の唯一の例外）。画像を最小まで縮めても文が収まらないときに限る。

#### 6.5 `title` 属性の全廃と、全画面に近い拡大表示（モーダル）の廃止

- **`title` 属性は全廃する**（§0.2 の一覧 16 か所。`aria-label` は残す）。カード以外の要素（トークン、パネルの項目、スコア、山札の枚数、目標の注記、部屋、移動のボタンなど）は、同じ仕組み・同じ見た目の**文字だけのツールチップ**にする。
- **拡大表示（`.ir-zoom*`、`ui/card-info.js` の `openZoom` ほか、選べるカードの Select ボタン）は廃止**し、タッチでも同じツールチップにする（選ぶのはカード自身をタップ）。
- **廃止で失うもの（009 §7.3 の目的との照合）**: 009 §7.3 の「Select ボタン」の目的は、長押しで拡大を開いた状態から、そのまま選ぶこと。新しい方式でも、(1) カード自身が 84px 以上の大きさでタップでき（32×32px の最小を大きく上回る）、(2) 外側のタップで閉じたときにタップが通る（§6.2）ので、拡大を読んでからカードをタップするだけで選べる。**失うものはない**。むしろ、拡大の全画面のモーダルが盤面を覆わなくなる（A.1 の「卓で見えるはずの部品をポップアップの裏に隠さない」）。
- 山札・捨札の一覧のオーバーレイ（009 §7.2 のギャラリー）は実装されていない（`ir-zoom` を使うのは `ui/card-info.js` と CSS だけ。`ui/log-format.js` は `data-ir-zoom-key` の属性名を使うのみ）【実測】。作るときは同じ管理役のツールチップを使う。

#### 6.6 ホバーで出したツールチップは邪魔をしない

- ホバーで出したものは `pointer-events: none`（クリックを奪わない）。`document.body` 直下に出す（`position: fixed`。BGA の CSS zoom・transform の祖先を避ける。009 §7.2・003 §13）。`z-index` は BGA のバーより上（現状の `10000`）。
- **対象を覆わない**・**ステータスバーを覆わない**（§6.3）。`ir-can` の選択中の要素を覆って選ぶ操作を妨げないよう、位置は常に横を優先する。

#### 6.7 見た目

BGA 標準のツールチップに近づける（ライト・ダークのテーマに従う）。`--ir-tip-bg`（`#fffdf8` / `#25252a`）、`--ir-tip-border`、`--ir-tip-text`、`--ir-tip-muted`、`--ir-tip-shadow`（`:root` と `html[data-theme="dark"]` の 2 組。§1.4 のとおり `--ir-text` からは分ける）。枠 1px、角丸 3px、影（`0 2px 8px`。ツールチップだけは浮く面なので影を許す）、本文 14px・名前 16px 太字（現状の `.ir-info`・`.ir-info-title` の値）。コントラスト: `#222` on `#fffdf8` = 15.7、`#e8e6e1` on `#25252a` = 12.2【計算】。幅は内容に合わせ最大 560px（narrow は画面幅 − 16px）。

#### 6.8 状態が変わるとき

ツールチップを開いたまま状態が変わった（状態の切り替え、`ir-can` の付け替え、通知による再描画）ときは閉じる（`bga.states` の `onLeavingState` と、`SelectionController.clear()` が呼ばれたとき、通知の処理の後。管理役に `Tips.dismiss()` を持たせ、`Game.js` と各状態の後始末から呼ぶ）。対象の要素が DOM から外れたときも閉じる（`isConnected`）。

#### 6.9 変更するファイル（U-18）

| ファイル | 変更 |
| --- | --- |
| `src/modules/js/ui/Tips.js`（新規） | 管理役 |
| `src/modules/js/ui/tip-placement.js`（新規） | `placeTip` と画像寸法の決め方（純粋関数） |
| `src/modules/js/ui/tip-content.js`（新規） | `card-info.js` の `buildInfo` を切り出す |
| `src/modules/js/ui/card-info.js` | ツールチップ・拡大表示を削除。想起の案内パネルの部分（`showRecall`・`hideRecall`）だけ U-19 まで残す |
| `src/modules/js/ui/zoom-math.js` | 削除（`placeTip` に統合） |
| `src/modules/js/Game.js` | `CardInfo` の呼び出しを `Tips` に。`attachLog` を `Tips` に |
| `board/Board.js`・`PlayerAreas.js`・`PlayerPanels.js`・`CommonArea.js` | §0.2 の 16 か所の `title` を `data-ir-tip` / `aria-label` に |
| `src/irevolvethereforeiam.css` | `.ir-zoom*`（`:1333-1412`）の削除、`.ir-tip*` の作り直し |
| `tests/js/tip-placement.test.mjs`（新規）、`tests/js/card-info.test.mjs` | `placeTip` の右・左・下・上・端 8px・バーの下・収まらないとき・対象を覆わない |

（U-18 で変更・補足）

- `ui/card-info.js` は `ui/recall-panel.js`（クラス `RecallPanel`。想起の案内パネルだけ。U-19 で廃止）に改名した。`buildInfo` は `ui/tip-content.js` へ。
- `placeTip` の戻り値に、どの側にも入らないときに選んだ側 `side` を足した（`placement` は `'fit'`）。下に置くときの上端もステータスバーの下（`top + margin`）より下にする（対象がバーの裏にあるときの下置き）。画像の幅の決め方は `tip-placement.js` の `imageWidthRange`（希望と最小）・`imageWidthSteps`（6 段階）・`imageHeight`。
- 文字だけのツールチップの付け方は `ui/dom.js` の `setTip(要素, 文)` と `tipAttrs(文)`（`data-ir-tip="text"` + `data-ir-tip-text`）。§0.2 の 16 か所に加え、ミープル（`CommonArea`）にも付けた。`Tips.dismiss()` は静的メソッド。
- 画像と文を横に並べたとき、文の欄は最小 200px（足りなければ次の小さい画像に進む）。幅は測ったあと切り上げて固定し、置いたあとに折り返しが変わらないようにした。
- 状態の切り替えは `ActionState` / `MoveState` の `onEnteringState` / `onLeavingState` と `SelectionController.clear()`、通知は `Game.setupNotifications` が各 `notif_*` を包んで処理後に `Tips.dismiss()`（BGA が包んだ関数を呼ぶかは Studio で確認）。対象が DOM から外れた・選べるかどうかが変わったときは `MutationObserver` で閉じる。`resize` でも閉じる。
- 検査 7 は幅 980px 以上でマウス、未満でタッチ（CDP）、最初のテーマだけで測る。`--no-tips` で省略、`--tip-shots` で撮影。

### 7. 読みやすさ（指摘 R5・A.5）

**【確認】** UxPdf A.5: 核となるゲームは 100% の表示で遊べる。ズームのボタンは、どうしても必要なときだけ。

#### 7.1 各配置での判定（§0.3 の実測）

| 部品 | 1920×1080（xl） | 1366×768（wide） | 390×844（narrow） | 判定 |
| --- | --- | --- | --- | --- |
| 手札・自己構成済み | 128 | 104 | 84 | 絵柄は識別できる。印刷の効果文は読めない前提（ツールチップで補う） |
| 部屋の床 | 158 | 150 | 84 | 部屋名・床アクションの印は xl・wide は読める。narrow は小さい（ツールチップ） |
| 場札 | 94 | 90 | 84 | 同上 |
| 精神・目標・自我 | 110 | 105 | 114 | 同上 |
| **部屋の裏向きカードの錠前** | **14.0px** | **13.3px** | **7.4px** | xl・wide は 12px 超で、種類（青・赤・ピンク）が見える。**narrow は 7.4px で読めない**（レベル 2 は 2 つ重なる） |

錠前は**移動の判断に必須の情報**（どの鍵が何個いるか）なので、狭い画面で 7.4px は問題。他の部品は、日本語の印刷文を読めない前提（009 の方針）でツールチップとログが英語で補う。

#### 7.2 錠前の見かけの大きさ（選択肢と推奨）

| 案 | 内容 | 長所 | 短所 |
| --- | --- | --- | --- |
| (a) | 部屋の裏向きカードの上に、**錠前のアイコンを 16px で重ねる**（UI スプライトの `lock_wisdom` / `lock_will` / `lock_emotion`（`ui/icon-names.js`）。種類とレベルは裏のキー `1B` / `2F` など（`data-ir-key` の `Rev1B` など。`ui/card-info-model.js` の `describeLock`）から求める。レベル 2 は 2 つ）。**印刷された錠前が 12px 未満になる画面**（部屋のカード幅が 68px 未満）だけに出し、xl・wide・medium では出さない | 判断に必要な情報が常に読める。見た目の追加は narrow だけ | 12 部屋ぶんの DOM が増える（部屋に 1〜2 個）。印刷の錠前と重なる（上に重ねて隠す）。選択の輪郭・番号の札との重なりに注意 |
| (b) | narrow の部屋の裏向きカードの比率を 0.5 W から 0.7 W に上げる | 部品が大きくなる | カード幅 59px でも錠前は約 10.4px（12px 未満）。床の情報を隠す・ミープルの置き場を食う。不十分 |
| (c) | ツールチップに任せる | 実装が最小 | タップを要する。移動の計画（経路の部屋ごとに必要な鍵を見比べる）で常に見えないと判断が止まる。B.1 の「情報を画面に出して、ツールチップは補助」にも反する |

**推奨: (a)。** 理由: 錠前は移動の計画の中心の情報で、画像の 7.4px は E.1 の「色だけで見分けさせない」にも反する。アイコンは色と形（本・炎・ハート）の両方を持つ。狭い画面だけに限れば、広い画面の見た目（紙どおり）を変えない。**→ 未確定事項 No.1（(a) に決定）。**

**実装（U-20）**: 錠前の一覧は純粋関数 `roomLockIcons(backKey)`（`ui/card-info-model.js`）、出す幅の判定は `needsLockOverlay(roomCardW)`（`ui/layout-math.js`。68px 未満）。`ui/Layout.js` が `#ir-root` の `data-lock-overlay`（1 / 0）を入れ、CSS が出し分ける。DOM は部屋ごとに 1 つの `span.ir-room-lock`（`aria-hidden`。読み上げは既存のツールチップ）で、`board/CommonArea.js` が部屋のカードの置き場の変化（差し替え・表向き）を `MutationObserver` で見て作り直す。表向き（想起中）のカードには `Rev` のキーが無いので空になる。位置はカードの下端から 15%（印刷の錠前のちょうど上）で、選択の輪郭（部屋の外側）・経路の番号の札（左上）・ミープル（`z-index` の別の層）とは重ならない。390×844 と 360×780 で、`4p-late` を DPR 3 で撮って切り出して確かめた（種類と個数が読める）。

#### 7.3 カード名のキャプション（設定 100 Card captions）

現状: `src/gamepreferences.jsonc` の ID 100 の既定は `1`（Off）。キャプションは `.ir-caption`（`:1023-1041`）で、**カードの下に絶対配置**（`top: 100%`）の 11px。行の隙間 8px より高いので、次の行のカードに重なりうる。

**推奨: 既定を On（2）に変え、12px にして、行に余白（約 16px）を確保する。** 理由: 審査は「カードの文字が特に小さい」と指摘した。審査者は既定の設定で見るので、既定 Off のままだと日本語の印刷だけの盤面を見る。英語名が常に見えれば、ツールチップを出さなくても識別できる（タッチでも）。高さの増加は、1 行（約 16px）× 行数。ただし**環の部屋・山札・精神トラックなど、盤面の部品の間にキャプションを置くと詰まる**ので、まず手札・自己構成済み・場札・自我・目標の行だけを On にする（環の床カードは部屋名が印刷されているので出さない）。実測の根拠が足りない（高さの変化、重なり）ので、U-20 のプレビューで確かめて決める → 未確定事項 No.2。

**U-20 の決定: 既定を On にした**（`src/gamepreferences.jsonc` の ID 100 の `default` を 2 に）。プレビュー（`4p-late`。`captions=1` と既定の両方。On は既定）で、1920×1080・1366×768・390×844 を Off と On で撮って比べた【実測】。

- 重なり: なし。目で確かめたのは、手札・自己構成済み（折り返した 2 行目の上）、場札、共通の目標（英語名と現在値の注記が 2 行になる）、自我（下のトークンの行）。最初の実装では、自我の英語名が同じ行のトークンの行に隠れたので、`.ir-player-top` の行の間を 18px 広げて直した。
- 高さの増え方（ページ全体）: 1920×1080 が 3388 → 3694px（+306）、1366×768 が 2796 → 2994px（+198）、390×844 が 4673 → 5141px（+468）。増えるのは、カードの行ごとの 18px（目標は 34px）の余白。
- 010 の「環が高さに収まる」（検査の配置の検証）: 1920×1080 で環の下端が 946px（見える高さ 950px）のまま変わらない（環の部屋・山札・精神トラックには英語名を出さないので影響しない）。全場面・全サイズ・両テーマで配置の警告は 0 件。
- 文字: 12px、`--ir-ink-dim`、`text-overflow: ellipsis`（幅の狭いカードは名前の末尾が「…」になる。全文はツールチップ）。

**既定を変えたときの既存テーブルの人の設定**: BGA は、プレイヤーが設定を選んだ値をそのプレイヤーの値として保存し、既定は未設定の人にだけ使うと考えられるが、公式文書（Options_and_preferences ページ）は 2026-11-28 に取得できず（404）、書かれているかも確認できなかった。**【未確認】**。作者が Studio で、設定を Off にしたテーブルを残したまま既定を変えたときに、その人の設定が変わらないことを確かめる（`alpha-studio-check.md` の E-6）。

#### 7.4 ズームのボタンは付けない

009 のまま（A.5。ブラウザの拡大縮小で崩れないこと）。

#### 7.5 変更するファイル（U-20）

`src/irevolvethereforeiam.css`、`board/CommonArea.js`（部屋の錠前のオーバーレイ。(a) を採るとき）、`ui/Layout.js`（`data-ir-layout` の値で出し分け）、`src/gamepreferences.jsonc`（既定を変えるとき）、`tests/js/common-area.test.mjs`。

### 8. 浮いた操作（指摘 R6）

- **bga-jump-to を廃止**（§2.3）。パネルの移動ボタンに置き換える。
- **手札のタブの独自ボタン「Discard 2 for Psychic Light」を、BGA 標準のボタンにする。** 現状は `board/PlayerAreas.js:736-745` の `showDiscardButton`（`button.ir-zone-button`、`title`、`999px` の独自の見た目）。新しくは `bga.statusBar.addActionButton(label, onClick, { color: 'secondary', destination: <手札のレールの要素> })` を使う。**【確認】** `destination` は StatusBar の文書にある（「ボタンを足す DOM 要素。指定しなければステータスバー」）。
  - `destination` は手札のレールの右端の要素（レールの中に右寄せの `.ir-rail-actions` を足す）。手札が 0 枚のときと、ターンプレイヤーでないときは出さない（現状の条件）。
  - 返ってくる `HTMLButtonElement` を保持する。**【未確認】** `removeActionButtons()` や状態の切り替えで `destination` のボタンが外れるか（文書は「タイトルバーのボタンを外す」とだけ書く）。外れる保証がないので、実装者は保持した参照で自分で `button.remove()` する（`clearDiscardButton` を残す）。Studio で、状態の遷移後にボタンが残らないことを確かめる。
  - 色は `secondary`（白。010 §2.3 の「二次的な操作」と同じ扱い。現状も Action Bar には出さない）。`title` は付けない（検査 4 に掛かる。ラベルは文字なので不要）。`tooltip` の引数は `#ir-root` の中の要素に付くので使わない（ツールチップは §6 の管理役）。
  - **代案**（`destination` が使えない・外れ方が不確かなとき）: ボタンをやめ、手札のレールの右端に、手札を捨てて心光を得るモードに入る操作を、**ステータスバーの `secondary` のボタン**（`Discard cards for Psychic Light`）として出す。010 §2.4 は「Action Bar に出さない」としたが、4 個以内（A.2）に収まる状態では許す。
- **トークンを押して数量を選ぶ操作**（`ir-token-plus`・`ir-token-badge`）は部品の操作として残し、見た目を §5.4 に合わせる。押せる要素は 32×32px 以上（トークンの画像 40px、選択数の札 32×32px）。
- 浮いた操作の最終確認は §9 の検査 2（`#ir-root` とパネルの中に `position: fixed / sticky` がない）。

変更するファイル（U-19）: `board/PlayerAreas.js`、`states/FreeActionsUi.js`、`states/ActionState.js`、`irevolvethereforeiam.css`。

（U-19 で変更・補足）

- **`setTitle` の HTML（§3.2・§3.4 の【未確認】）**: `bgaFormatText` のフック（`Game.hookLogFormat`）が `setTitle` の `args` にも効く経路は、公式の文書にもこのリポジトリのコードにも見つからなかった。そこで、**HTML を `args` に直接渡す**形にした（カード名は `ui/log-format.js` の `cardNameHtml`、鍵は `ui/status-title.js` の `textToHtml` が `{wisdom}` の記法からアイコンの HTML にする）。BGA が `args` の HTML をエスケープして描く場合に備え、`setTitleHtml` が文を出したあと `#pagemaintitletext` に `&lt;span` が見えたら `span` / `img` のタグだけ HTML に戻す（BGA が HTML を描くなら何もしない）。プレビューの `setTitle` の代用は `args` を `innerHTML` に差し込むので HTML が描かれる（BGA の実物と同じかは Studio で確認。README に記載）。§3.2 の代案（`#page-title` を後から包む）は使っていない。
- **想起の文（§3.2）**: `choice-model.js` の `Choice` に `spec` を足し、`ChoiceState.title(想起の文, 通常の文, args)` が `spec` の `RC-` で切り替える。想起の版を足したのは、場札を取る・手札を捨てる・心光のための捨て・手札と場札の交換・同コストの交換・移動の確認の 6 種（PHP の `RC-02`・`05`・`06`・`07`・`12`・`13` が出す種類。`RC-08`〜`11` は `CONFIRM_USE` で、元からカード名を持つ）。`PICK_DISCARD` の想起の版は `Recall ${card}: ${you} must discard ${count} card(s)`。想起の `args` の `activators` は使わなくなった（`recallFromGamedatas` の戻り値は `{ pos, key }`）。
- **`ir-subject` の付け外し（§3.1）**: `CommonArea.markRecallSubject(pos)` / `clearRecallSubject()`。`revealRoomCard` が表向きのカードを置き直したあとに付け、`notif_phaseStarted`（想起以外）・`notif_roomCardAcquired`・`notif_cleanup` で外す。再読み込みは `CommonArea.ready`（部屋のカードを置き終えたら解決）のあとに付ける。
- **選べない場札（§5.3）**: `ActionState.pickOpen` は、選べないカードだけの Stock も選択モード（`ids: []`）に入れて `ir-off` にする。選べる場札が 1 枚もない Stock も暗くなり、押すと理由を出す。`ir-subject` のカード（自己構成するカード）と、`ir-tapped`（使用済み。元から暗い）のカードは `ir-off` の暗さを重ねない。
- **選べない理由（§5.7）**: 管理役は `Game.watchBlockedClicks`（`#ir-root` の 1 つの委譲のクリック）で、`ir-off` を押したら `data-ir-why`（無ければ `You cannot choose this now`）を `showMessage(…, 'info')` で出す。`ir-can` / `ir-picked` を持つ子を押したときは出さない。理由の文を付けたのは、移動で入れない部屋（`MoveState.whyBlocked`: 次の部屋は払えない、それ以外は順番どおり、これ以上入れない）と、押せないトークン（`quantityTokenPick` の `tooltip` を再利用）。カードの理由は `SelectionOptions.why`（未使用。一般の文）。`cards/selection.js` はコールバックを持たず、メッセージは `Game.js` だけが出す。
- **部屋とトークンの付け外し（§5.1）**: 部屋は `selection.js` の `paintRooms` / `clearRooms`、トークンは `markElement` / `unmarkElement`、`ir-subject` は `setSubject`。経路の最後の部屋のフックは `ir-undo`（旧 `ir-room-undo`）。持っていない（`ir-zero`）トークンは、押せなくても `ir-off` にしない（元から薄い）。
- **手札のボタン（§8）**: `FreeActionsUi` が `bga.statusBar.addActionButton(…, { color: 'secondary', destination: playerAreas.handActionsElement() })` で置き、返ったボタンを保持して自分で `remove()` する（`clearDiscardButton`。`reset()`・`add()`・`drawDiscard()` の先頭で呼ぶ）。`title` / `tooltip` の引数は使わず、説明は `data-ir-tip`（`setTip`）。`PlayerAreas.showDiscardButton` / `clearDiscardButton` は廃止。検査 8 は、このボタン（BGA 標準）の角丸を許す。
- **候補の置き場（§3.3）**: `.ir-pick-panel` は `#ir-main` の先頭に `ir-rail` として入る（`ActionState.showCandidates(…, label)`。カードは中の `.ir-pick-cards`）。`:has(...)` の切り替えと `top` の grid の行は全廃した。ラベルは `Choose an Initial Ego` / `Choose an Enhanced Ego`。
- **ステータスバーの文（§3.4）**: 移動は経路を出さず `Locks: ${paid} / ${total}. Need: …`、精神分析は `keys ${paid} / ${total}`（小文字）、自己構成は `Compose ${card}: energy ${paid} / ${need}`（余りは `. Unused: …`）。移動の「経路なし」は `${you} must choose the next room`、自己構成フェイズは `${you} may compose or analyze`、心光のための捨ては `Discard cards for Psychic Light (gain ${light})`。
- **枠の置き方（§5.1・§5.5）**: `ir-can` / `ir-picked` の枠は、カード・トークン・部屋とも**外側**（`outline-offset: 2px`）に出す（内側だとカードの白い縁・bga-cards の面の子要素に隠れて見えなかった）。`--ir-can-glow` は不透明度 55%。
- **部屋の暗さ（未確定 No.5）**: `brightness(0.75) saturate(0.8)`（床・カードの箱だけ。ミープルは暗くしない）。カードの暗さは §5.1 のとおり `brightness(0.6) saturate(0.7)`。
- **目標の注記（§3.5）**: `.ir-goals` に `gap` の行間 +16px と下の余白 16px を足した（bga-cards の `.card-stock.line-stock` の `gap` に勝つため `!important`）。
- **廃止した変数（§1.4）**: `--ir-text`・`--ir-text-muted`・`--ir-panel-bg`・`--ir-panel-border`・`--ir-slot-bg`・`--ir-turn-border`・`--ir-pending`・`--ir-mat-*` を CSS から削除した（`:root` と `html[data-theme="dark"]` はツールチップ用の `--ir-tip-*` だけ）。ツールチップ内の文字色は `--ir-tip-text` / `--ir-tip-muted`。`Tips.js` の `SELECTABLE_SELECTOR` は `.ir-can`・`.ir-picked`・`.ir-off`・`.ir-token-pick` だけ。
- **プレビュー**: 検査 9 は `checks.mjs` の `runBlockedClickCheck`（部屋・カード・トークンの `ir-off` を種類ごとに 1 つ押す）。メッセージが出た画面を `<サイズ>-<テーマ>-blocked.png` に撮る。`4p-compose-pay` の固定データに `steps`（自己構成するカードと捨てる手札を選ぶ）を足した（`dump-fixtures.php` も同じ）。

### 9. 確認の道具（プレビュー）

**目的**: Studio に上げなくても、審査が指摘する観点を機械で確かめる。CI には入れない（Chrome が要るため）。

#### 9.1 プレビューを BGA の枠に近づける（U-17）

- **ステータスバーを上に貼り付ける**（BGA と同じ sticky。`.pv-statusbar { position: sticky; top: 0 }`。トップバーの下。`z-index` はゲーム領域より上）。**`id="page-title"` を付ける**（今のプレビューは `#pv-statusbar`・`#pv-title` で、`document.getElementById('page-title')`（ツールチップの位置の計算）が空になる【実測】）。BGA の標準の構造（`#page-title` の中に `#pagemaintitletext` と `#generalactions`）に寄せる。ステータスバーが貼り付くことは BGA の公式文書に書かれていない【未確認】ので、実機（Studio）で確かめる。
- **フッターを足す**（`#pv-footer`、高さ約 200px の暗い帯）。スクロール時に浮いた要素が重ならないかの確認に使う。
- **`bga.dialogs.showMessage(message, type)` の代用**: 画面の上に白い箱（`type === 'error'` は赤）を 3 秒出し、`window.__irMessages` に `{ message, type }` を積む。
- **`statusBar.addActionButton` の `destination` と `title` に対応する**（`scripts/preview/preview.js:105-134`。今は `destination` を無視して常にバーに足す）。返す要素は `HTMLButtonElement`。
- **`removeActionButtons()` が `destination` のボタンを外さない**挙動に合わせる（上の【未確認】の悪い側に寄せ、実装が自分で外すことを確かめる）。
- `bga-jump-to` を読み込まない（`scripts/preview/preview.js` の `importEsmLib` の対応）。README の説明（`.bga-jump-to_link`）を更新する。
- 触れて確かめる補助: `touch=1`（既存）、`hover` を押すスクリプト（`shoot.mjs` が使う）。

#### 9.2 自動検査（`shoot.mjs`。U-17 で土台、U-18・U-19 で足す）

警告として集計し、`--strict` で失敗にする（今の `layoutWarnings` / `overflows` と同じ扱い）。CI には入れない。

| # | 検査 | 追加の時期 |
| --- | --- | --- |
| 1 | ページの横のはみ出しがない（`scrollWidth > innerWidth` で数える。既存） | 既存 |
| 2 | `#ir-root` とパネル（`.ir-pp`）の中に `position: fixed` / `sticky` の要素がない | U-17 |
| 3 | 押せる要素（`button`・`[role="button"]`・`.ir-can`・パネルの移動ボタン）が 32×32px 以上（既存。`.ir-can` を足す。`.bga-jump-to_link` を外す） | 既存 + U-17 |
| 4 | `#ir-root` とパネルの中に `title` 属性がない（`destination` で置いた BGA 標準のボタンの `title` 引数は、ラベルが文字なので付けない） | U-18 |
| 5 | 文字が 12px 以上（`#ir-root` とパネルとツールチップの中の、テキストノードを持つ要素の `font-size`） | U-17 |
| 6 | 実際にスクロールする入れ子の要素がない（`overflow` が auto / scroll で `scrollHeight` > `clientHeight`）。タッチで開いたツールチップの内側だけ許可 | U-17 |
| 7 | ホバー・タップで出したツールチップが、画面内（端から 8px）・ステータスバーの下・対象を覆わない・画像が卓の同じカードより大きい。対象の代表: 手札・自己構成済み・部屋の床・部屋の裏向きカード・場札・精神カード・目標・自我・トークン・パネルの項目・山札の枚数。マウス（`mouse.move`）、タッチ（タップ、長押しは CDP の `Input.dispatchTouchEvent`） | U-18 |
| 8 | 角丸 3px を超える要素は、許可した部品（トークン・心光・キューブ・コマの画像の要素）だけ。許可のリストは `shoot.mjs` の定数 | U-17 |
| 9 | `ir-off` の要素を押すと `window.__irMessages` が増える（無反応でない） | U-19 |

- 検査は撮影の既存の枠組み（`measureLayout` の隣）に足す。出力は「N 件の警告」のまとめと、警告の一覧。（U-17 で変更: `--strict` は横はみ出しだけでなく、配置の検証と自動検査の警告も失敗にする。）
- `README.md` の「BGA の画面との違い」の記述（sticky にしていない、など）を更新する。

#### 9.3 変更するファイル

`scripts/preview/index.html`、`scripts/preview/preview.css`、`scripts/preview/preview.js`、`scripts/preview/shoot.mjs`、`scripts/preview/README.md`、（検査のための補助）`scripts/preview/checks.mjs`（新規。ブラウザ内で動く検査の関数）。

### 10. 実装の分け方（1 PR ずつ順に。CSS が 1 ファイルのため並行しない）

審査への申請は、U-17〜U-20 のマージと、作者の Studio での確認（受け入れ条件の最後）の後。

| ID | 内容 | 変更するファイル |
| --- | --- | --- |
| U-17 土台 | §9 のプレビューと検査（ステータスバーの貼り付け・フッター・`showMessage`・`destination`・検査 2・3・5・6・8）、§1 の視覚体系（変数・レール・箱と丸い札の廃止・記号の SVG 化・文字 12px 以上・トークンの形）、§4 の領域とパネル、§2.3 の bga-jump-to の廃止とパネルの移動ボタン | `scripts/preview/*`、`src/irevolvethereforeiam.css`、`board/Board.js`・`PlayerAreas.js`・`PlayerPanels.js`・`CommonArea.js`、`ui/icon-names.js`・`icons.js`・`Layout.js`、`Game.js`、`tests/js/player-areas.test.mjs`・`common-area.test.mjs` |
| U-18 ツールチップ | §6 全部（`title` の全廃を含む）。検査 4・7 | §6.9 の表 |
| U-19 操作 | §5 全部、§3（想起の案内パネルの廃止とステータスバー、候補の置き場のレール、ステータスバーの文）、§8 の手札のボタン。検査 9 | §3.6・§5.8・§8 の表 |
| U-20 読みやすさと仕上げ | §7（未確定事項の決定に従う）、009・010 への反映（§11 の表）、`docs/publishing/alpha-studio-check.md` の更新、受け入れ条件の確認 | §7.5 + `docs/specs/009-ui-assets.md`・`010-alpha-ui-review.md`・`docs/publishing/alpha-studio-check.md`・`docs/tasks.md` |

### 11. 009・010 との関係

本書の PR では 009・010 の本文を書き換えない。U-20 で、次の節に「011 で変更」の注記を足す（010 が 009 に対して行った方式）。

| 節 | 内容 | 011 での変更 |
| --- | --- | --- |
| 009 §2.5・010 §3.7 の手番の表示 | 手番プレイヤーの領域は枠の太さと文字（「Your turn」）で示す | 廃止（§4.1。パネルの強調とステータスバー。未確定事項 No.3） |
| 009 §3.1（選択・強調）、§4.3（選択状態） | 選べない部屋・カードは暗くしない。選べる = 2px + ○、選択中 = 3px + ✓ | §5（共通のクラス。選べないものは暗くし、押すと理由を出す） |
| 009 §3.8・010 §5.3（トークン） | 万能移動鍵は「1 + 0 used」の 1 つのチップ | §1.3（表・使用済みの 2 つの画像） |
| 009 §3.9（想起の案内パネル）、010 §3.2 の段落・§4 の表の 1 行 | 想起中の箱のパネル。xl・wide は共通の列の先頭、medium・narrow は環の上 | 廃止（§3） |
| 009 §3.10・§3.11、010 §5・§6（領域・マットとタブ） | 領域の箱、色違いのマットと丸いタブ | §1.2・§4（レール、薄いマットの色合い。丸いタブをやめる） |
| 009 §7.1・§7.2・§7.3、010 §9（ツールチップと拡大） | ホバーのツールチップ、タッチは長押しの拡大表示と Select ボタン | §6（1 つの管理役。拡大表示と Select を廃止） |
| 009 §7.4 | ツールチップの中身 | **変更なし** |
| 009 §8.7（Card captions） | 既定 Off | §7.3（既定を On に変える案。未確定事項 No.2） |
| 009 §12.2（色・タップ領域） | 色の変数、32px 以上 | §1.4（変数の作り直し）。32px 以上は維持 |
| 010 §3.7（narrow のジャンプリンク） | bga-jump-to | 廃止（§2.3。パネルの移動ボタン） |
| 010 §2.4（トークン・部品からの操作） | 領域のトークンを押して数量を選ぶ | **維持**（見た目だけ §5.4） |
| 010 §5.2・§2.4（手札のタブの独自ボタン） | `.ir-zone-button` | §8（BGA 標準のボタン） |
| 010 §11.1（プレビュー） | 構成・固定データ・BGA の枠 | §9（ステータスバーの貼り付け・フッター・検査の追加） |
| 010 の決定 No.1〜10 | 環を高さに収める、`xl`、手札と自己構成済みの区別、他プレイヤーの大きさ、背景案 B、narrow の並び など | **維持**（No.3 は見た目の手段だけ変える。§1.2） |

### 12. 指示書で決まっていなかった点と決定（作成者、2026-11-27。メインエージェントが 2026-11-28 のレビューで確認し、すべてこのとおりとした）

メインエージェントの設計を、コードと公式ドキュメントで裏付けて書いた。事実と食い違う点・設計で決まっていなかった点は、次のとおり。**別案を勝手に本文へ書き込んだのではなく、本文は設計どおりに書き、食い違いはここに出す。**

| # | 点 | 事実と本書の扱い |
| --- | --- | --- |
| 1 | 「想起の案内パネルの廃止で xl・wide の grid の切り替え（`:has(...)`）が不要になる」 | 切り替えは 5 規則（`:200-238`）で、**想起用の 2 規則（`:226-238`）だけが不要になる**。候補の置き場（`ir-pick-panel`）用の 3 規則（`:200-224`）は残る。そこで `ir-pick-panel` を `#ir-main` の先頭の子にする案を本文に採った（位置は環の上のまま。`:has` が全部不要になる）。設計は「位置は今のまま」。実装の手段の変更なので、レビューで差し戻してよい（その場合は `:has` を `ir-pick-panel` だけに簡単にする） |
| 2 | 「bga-cards の既定の選択の見た目の上書き」 | bga-cards は面の要素にクラスを付け、リポジトリは `.ir-card` にクラスを付けるので二重になっている。`selectableCardStyle` などの Manager の設定でクラス名を差し替えるのが最小（§5.3）。設定が効かない版のための代案も書いた |
| 3 | 選べないカードの暗さ | 現状の `contrast(0.6)` は bga-cards の既定で、リポジトリの意図（009 §4.3「暗くしない」）と食い違っている。本書は「暗くする」に統一し、009 §4.3・§3.1 を変更する（§5.3） |
| 4 | `ir-pending` の扱い | `ir-subject` に統合（`ir-picked` ではない）。理由は §5.6 |
| 5 | パネルの移動ボタンは「1 行目の右端」 | パネルの高さ 33px に 32px のボタンを入れるため、2 行に縦に跨る形にした（§2.3）。入らなければ 1 行目に置く |
| 6 | ステータスバーのカード名（想起） | args に `source` と `spec`（`RC-`）が既にあるので、**PHP の変更は要らない**（§3.2）。`setTitle` の args に HTML が通るかは【未確認】。通らない場合の代案を書いた |
| 7 | 想起の他プレイヤー（割り込みで答えない人）の見え方 | サーバー側の `description` はカード名を持たない。部屋の `ir-subject`・ログ・パネルで足りると判断し、サーバーは変えない（§3.2） |
| 8 | 手番の表示 | 領域の札と太い枠を廃止すると、009 §2.5・010 §3.7 の「手番の領域を枠の太さと文字で示す」を変えることになる。BGA 標準のパネルの強調で足りるかは【推定】で、Studio で確かめる（未確定事項 No.3） |
| 9 | `destination` で置いたボタンの寿命 | 公式の文書は `removeActionButtons()` の対象を書いていない【未確認】。実装が自分で外す（§8） |
| 10 | ステータスバーが貼り付くか | 公式の文書に書かれていない【未確認】。プレビューは設計どおり貼り付ける（§9）が、Studio で確かめる。`scroll-margin-top` の値も Studio で測る |
| 11 | プレビューの `#page-title` | 今のプレビューは `#pv-statusbar` で、`getElementById('page-title')` が空になるため、バーとの重なりが今の検査で見えなかった。U-17 で直す（§9.1） |
| 12 | `data-ir-layout`（`<html>`）の扱い | jump の CSS のためだけに付いている可能性が高い。廃止後に他の参照がなければ削除（実装時に grep） |
| 13 | 山札・捨札の一覧のオーバーレイ | 009 §7.2 のギャラリーは実装されていない【実測】。統合の対象はない |

## エッジケース

| 状況 | 挙動 |
| --- | --- |
| 観戦者・リプレイ | 自分の領域がなく、全員が「他プレイヤー」の形。パネルの移動ボタンは全員分。手札・候補の置き場は出ない。想起のカードの `ir-subject` と、ステータスバーの文のうち「あなたの番」でないものは BGA 標準のタイトル |
| 手札が 9 枚以上（獲得の途中） | 手札のレールに警告（SVG + 「N / 8」）。行数が増える。`ir-can` の付け方は変わらない |
| 想起中に他プレイヤーへ割り込み | 答える人のステータスバーに `Recall ${card}: …`。他の人は部屋のカードの `ir-subject`・ログ・パネルの強調で分かる |
| ツールチップを開いたまま状態が変わる | 閉じる（§6.8）。対象が DOM から外れたら閉じる |
| タッチとマウスの両方がある端末 | `pointerType` ごとに処理（§6.2）。どちらでも出せる |
| ダークテーマ | 盤面は常に暗い卓。ツールチップだけテーマに従う（§1.4・§6.7）。パネルの移動ボタンは BGA のパネルの色に従う（`currentColor`）。ライトテーマで BGA のパネルの上で読めるかは Studio で確認 |
| 手札 0 枚で手番 | 手札のレールのボタンを出さない（§8） |
| `ir-off` の要素を長押しした | ツールチップ（タップは理由のメッセージ。§6.2・§5.7） |
| 同じ文を連続で押した | 2 秒以内は出さない（§5.7） |
| 画面が低い（見える高さ 360px 未満） | ツールチップは画像を出さず文字だけ（§6.4） |
| 錠前のオーバーレイと選択の輪郭の重なり | (a) を採る場合、アイコンは輪郭の内側（部屋のカードの下端の中央）に置く（§7.2） |
| bga-cards の設定が効かない版 | CSS の代案で outline・`filter` を打ち消す（§5.3） |
| `setTitle` に HTML が通らない | 鍵の名前を英語の文字に戻し、カード名は後から `span.ir-log-card` で包む（§3.2・§3.4） |
| `destination` のボタンが状態の切り替えで残る | 実装が自分で `remove()` する。Studio で確認（§8） |

## 未確定事項

UI の好みの問題だけ。ルールの不明点は無い（あったら推測で埋めず作者に質問する）。各項目に暫定案と理由を付けた。**No.1〜11 は、メインエージェントがレビューで暫定案のとおりに決めた（2026-11-28。PR の作成・マージをメインエージェントが判断する作者の方針と、010 の未確定事項への作者回答「すべて暫定案で」に倣う）。** 作者は Studio での確認（受け入れ条件の最後）のときに変更を指示できる。

| No. | 質問 | 暫定案 | 回答 |
| --- | --- | --- | --- |
| 1 | 部屋の裏向きカードの**錠前**が狭い画面（narrow。幅 390px で約 7.4px）で小さすぎる。(a) 14〜16px のアイコンを重ねる、(b) 部屋のカードの比率を上げる、(c) ツールチップに任せる（§7.2） | (a)。印刷の錠前が 12px 未満になる画面だけ。理由: 移動の判断に必須の情報で、(b) は不足、(c) は常に見えない | 暫定案のとおり (a)（メインエージェント、2026-11-28）。U-20 で実装し、§0.3 を再実測する |
| 2 | プレイヤー設定 100 **Card captions** の既定を On に変えるか（§7.3） | On（手札・自己構成済み・場札・自我・目標の行だけ。12px、行に余白）。理由: 審査者は既定で見る。日本語の印刷が読めなくても識別できる。U-20 のプレビューで高さと重なりを確かめてから決める。重なりが解けなければ Off のまま | 暫定案のとおり On（メインエージェント、2026-11-28。姉妹 014 の Q8 も手札などに英語名のキャプションを常に出す方針）。U-20 のプレビューで重なりと高さを確かめ、解けなければ Off のままにして理由を記録する。**U-20 の結果: On にした**（重なりは解け、環は 1920×1080 で収まったまま。根拠は §7.3 の実測）。既存のテーブルの人の設定が変わらないかは【未確認】で、Studio で確認する |
| 3 | 領域の「Your turn / Current turn」の札と手番の太い枠を**廃止し、BGA 標準のパネルの強調とステータスバーだけ**にしてよいか（009 §2.5・010 §3.7 の変更。§4.1） | 廃止する。理由: 審査の「手番はパネルに」「大きな枠・札は独自のアプリ風」。Studio で標準のパネルの強調が見えることを確かめてから | 暫定案のとおり廃止（メインエージェント、2026-11-28）。Studio でパネルの強調を確かめる |
| 4 | 手札と自己構成済みの区別を、**ラベルの文字と薄いマットの色合い**（青系・琥珀系）だけにしてよいか。丸いタブ・色の濃い箱はやめる（§1.2。010 No.3 の手段の変更） | そうする。理由: 区別は残り、箱・丸の見た目だけをやめる。見分けにくければ、マットの上端の線の太さ（1px → 2px）で補う | 暫定案のとおり（メインエージェント、2026-11-28） |
| 5 | 入れない**部屋**を暗くする（`ir-off`）強さ。009 §3.1 は「暗くしない」だった（§5.3） | 弱く暗くする（`brightness(0.75)`）。理由: 床アクションの情報は読めるまま、選べない部屋が分かる | 暫定案のとおり `brightness(0.75)`（メインエージェント、2026-11-28）。値は U-19 のプレビューで調整してよい |
| 6 | 万能移動鍵のトークンを**表と使用済みの 2 つの画像**で並べる（§1.3） | そうする。理由: 紙と同じ見え方。0 個の側は薄く出して位置を固定 | 暫定案のとおり（メインエージェント、2026-11-28） |
| 7 | ツールチップの出し方（ホバー 400ms・選択中 700ms・タッチの長押し 500ms。§6.2） | この値。実機のスマホで長押しの感触を Studio で確かめる | 暫定案のとおり（メインエージェント、2026-11-28） |
| 8 | パネルの移動ボタンを**全配置で**出すか。xl・wide では領域が見えているので出さない選択もある（§2.3） | 全配置で出す。理由: 配置でパネルが変わらない。邪魔なら xl・wide だけ隠す | 暫定案のとおり全配置（メインエージェント、2026-11-28） |
| 9 | パネルの移動ボタンの位置。2 行に縦に跨る（暫定）か、1 行目の右端か（§2.3） | 縦に跨る。390px の 2 列の格子で 2 行目の鍵 4 種と並ぶか確かめて、入らなければ 1 行目 | 暫定案のとおり（メインエージェント、2026-11-28）。U-17 のプレビューで決め、PR に記録する |
| 10 | `ir-pick-panel` を `#ir-main` の先頭に移して `:has` を全廃する（設計の「位置は今のまま」の実装の手段。§12 の 1） | 移す | 暫定案のとおり移す（メインエージェント、2026-11-28） |
| 11 | 想起でないステータスバーの文の簡略化（経路を文から外す、鍵をアイコンに。§3.4） | 簡略化する。理由: 経路は盤面の番号で見える（重複）。アイコンが通らなければ英語の鍵の名前に戻す | 暫定案のとおり（メインエージェント、2026-11-28） |

## 受け入れ条件

検証方法は、プレビュー（`npm run preview:shots`、場面と大きさを添える）、自動検査（§9.2。`--strict` で警告 0）、Studio での確認（作者）。

- [x] 暗い卓の上に部品と線・ラベルだけがあり、領域の箱・丸い札・丸いタブ・手番の太い枠・「Your turn」の札がない（§1・§4）。`border-radius` が 3px を超える要素は、許可した部品（画像）だけ ※検査 8。`4p-early`・`4p-late`・`2p-setup` の 1920×1080・1366×768・390×844 【根拠】U-20 の全場面・全サイズ・両テーマの `preview:shots` で検査 8 が 0 件（#44・#46）
- [x] 12px 未満の文字がない。CSS の `content` の記号（○ ✓ ★ !）がなく、SVG になっている（§1.1）※検査 5、grep 【根拠】検査 5 が 0 件、`content` の記号の grep が 0 件（`content: ""` は枠の疑似要素と `attr(data-order)` だけ。#44）
- [x] 色の変数が §1.4 の表のとおり、廃止した変数が残っていない。暗い卓に対して文字が 4.5:1 以上 ※grep（廃止した変数の参照 0）、§1.4 の計算 【根拠】#44（変数の作り直しと 4.5:1 の計算）。廃止した変数の参照は #44・#46 で外した
- [x] 手札と自己構成済みが、レールのラベルと薄いマットの色合いで区別できる（010 No.3 の維持）。他プレイヤーのカードの大きさは変わらない（010 No.8）※`4p-late` のプレビュー 【根拠】`4p-late` の 1920×1080・1366×768・390×844（#44。U-20 でも目で確認）
- [x] 万能移動鍵が表と使用済みの 2 つの画像で個数つきに出る。0 個の側は薄く、位置が動かない（§1.3）※`4p-early`・`4p-late` 【根拠】`4p-early`・`4p-late`（#44）
- [x] bga-jump-to が読み込まれず、左の浮いたボタンがない。パネルの右端に 32×32px の移動ボタンがあり、押すと領域へスクロールする。`aria-label` が `Go to …`（§2.3）。390px の 2 列の格子でパネルが 2 行に収まる ※検査 2・3、390×844 の `4p-late`、Studio（スクロール位置とステータスバー） 【根拠】検査 2・3 が 0 件、`bga-jump-to` の grep が 0 件、390×844 の `4p-late` でパネルが 2 行（#44）。スクロール位置とステータスバーとの関係は Studio で確認（最後の項目）
- [x] `#ir-root` の中にスクロールする入れ子の要素がない（§2.4）※検査 6 【根拠】検査 6 が 0 件（#44）
- [x] 想起の案内パネルがない。想起中は部屋のカードに `ir-subject` が付き、ステータスバーに `Recall ${card}: …` が出て、カード名はホバー・タップでツールチップが出る（§3）。PHP の変更がない ※`4p-recall`、`git diff --stat src/modules/php` が空 【根拠】`4p-recall` のプレビュー、`ir-recall-panel` の grep が 0 件、`git diff --stat origin/main -- src/modules/php` が空（#46、U-20 でも確認）
- [x] 候補の置き場が箱でなくレールになり、環の上にある（§3.3）※`2p-setup` 【根拠】`2p-setup`（#46）
- [x] ステータスバーの文が §3.4 の表のとおり。移動の計画中の文が 50 文字以内 ※`4p-move-plan`、`4p-compose-pay`、`4p-analysis` 【根拠】`4p-move-plan`・`4p-compose-pay`・`4p-analysis`（#46。自己構成の支払いのカード名 M000 の不具合も #46 で修正）
- [x] 目標の現在値が暗い札でなく 12px の注記になっている（§3.5）※`4p-late` 【根拠】`4p-late`（#44。U-20 のキャプションと重ならないことも確認。§7.3）
- [x] 選べる・選択中・今は選べない・対象が、カード・部屋・トークン・精神カード・目標で同じクラス（`ir-can` / `ir-picked` / `ir-off` / `ir-subject`）と見た目になっている。旧クラスが残っていない。bga-cards の既定の見た目（オレンジの破線）が出ない（§5）※grep（`ir-selectable` ほか 0）、`4p-recall`・`4p-move-plan`・`4p-compose-pay`・`4p-analysis`・`4p-construct` 【根拠】旧クラス（`ir-selectable` ほか）の grep が 0 件、bga-cards の既定の見た目を無効にする設定を入れた（#46）。**bga-cards の設定が Studio の `1.x` で効くかは Studio で確認**（最後の項目）
- [x] 経路の順番が金の四角い札、入れない部屋が暗い。色だけでなく輪郭の有無・太さ・破線・印で区別できる。点滅しない（§5.5）※`4p-move-plan` 【根拠】`4p-move-plan`（#46）
- [x] 選べないものを押すと `showMessage(…, 'info')` が出て、同じ文は 2 秒以内に繰り返さない（§5.7）※検査 9、`tests/js/message-throttle.test.mjs` 【根拠】検査 9 が 0 件、`tests/js/message-throttle.test.mjs`（#46）。`showMessage` の見た目は Studio で確認
- [x] ツールチップが 1 つの管理役で、カードにもカード以外にも同じ仕組みで出る。マウスでもタッチでも出て、外側のタップ・スクロール・Esc・状態の切り替えで閉じる。対象を覆わず、画面の端から 8px 以上、ステータスバーの下に収まる。画像が卓の同じカードより大きい（§6）※検査 7、`tests/js/tip-placement.test.mjs`、1366×768 と 390×844 【根拠】検査 7 が 0 件、`tests/js/tip-placement.test.mjs`（#45）。タッチの長押しの感触・ステータスバーの下に収まるかは Studio で確認
- [x] `title` 属性が `#ir-root` とパネルにない。全画面の拡大表示（モーダル）と Select ボタンがない（§6.5）※検査 4、grep 【根拠】検査 4 が 0 件、`title` 属性の grep が 0 件（#45）
- [ ] 手札のレールのボタンが BGA 標準のボタンで、状態の遷移後に残らない（§8）※`4p-construct`・`4p-move-plan` のプレビュー、Studio 【根拠】`4p-construct`・`4p-move-plan` のプレビューでは、手札のレールのボタンが状態を出ると消える（#46）。BGA の実物で `removeActionButtons` との関係と残らないことを **Studio で確認**
- [x] 部屋の裏向きカードの錠前が、全配置で見かけ 12px 以上（narrow は §7.2 の (a) の錠前のアイコンで読める）（§7）※§0.3 の再実測 【根拠】U-20（本 PR）。390×844・360×780 の錠前のオーバーレイを DPR 3 で切り出して確認（§0.3 の再測定、§7.2）。単体テスト `roomLockIcons`・`needsLockOverlay`
- [x] 100% の表示で遊べる（核となる情報が拡大なしで読める）。ズームのボタンがない（§7.4）※1920×1080・1366×768・390×844 【根拠】1920×1080・1366×768・390×844（U-20 でキャプションを含めて確認。ズームのボタンはない）
- [x] 360〜2560px のどの幅でもページが横スクロールしない。デスクトップ・ワイド・モバイルでレイアウトが崩れない ※検査 1、`preview:shots` の全場面・全サイズ 【根拠】検査 1 相当（横のはみ出し）が、U-20 の全場面・全サイズ（360〜2560px）・両テーマの 540 枚で 0 件
- [x] `npm run lint` と `npm run test:js` が通る 【根拠】U-20 で `npm run lint` と `npm run test:js`（233 件）が通る
- [x] 009・010 に「011 で変更」の注記があり、`docs/publishing/alpha-studio-check.md` が更新されている（U-20） 【根拠】U-20（本 PR）。009 の冒頭と §2.5・§3.1・§3.8〜§3.11・§4.3・§7・§8.7・§12.2、010 の §2.4・§3.2・§3.7・§4〜§6・§9・§11.1 に注記を足した。`alpha-studio-check.md` を 011 に合わせて更新した
- [ ] 作者が Studio で確認した（Express Start の 2〜4 人、ライト・ダーク、実機のスマホ。特に: ステータスバーの貼り付き、パネルの強調、`destination` のボタンの寿命、`setTitle` の HTML、ツールチップのタッチ、ライトテーマでの BGA 側の欄、`showMessage` の見た目、bga-cards の選択の見た目、想起の割り込み側の破線、錠前のオーバーレイ、Card captions の既定の変更が既存テーブルの設定を変えないこと）。**Studio で確認**（作者）。確認項目は `docs/publishing/alpha-studio-check.md`。

## ガイドラインの節と本書の対応

節番号と要点は公式 PDF（UxPdf）による。【確認】2026-11-27。審査が挙げた各節の要点の表は、姉妹の仕様書 014「審査が挙げたガイドラインの節」を出典として引用する（014 は同じ審査者の同じ節を同日に確認している）。本書は A.3・B.1・C.4（アンカー）・D.1 を PDF の本文で直接読み直した。

| 節 | 要点（014 の表を出典。A.3・B.1・C.4 のアンカー・D.1 は PDF を確認） | 本書の対応 |
| --- | --- | --- |
| A.1 Layout arrangement | 遊び場を全端末で中央に置く。操作しない余白を周りに残す。卓で見えるはずの部品をポップアップの裏に隠さない。スコア・手番・目的はパネルだけに置かない。関連する情報を 3 隅に散らさない。縦スクロールは副次的な情報（他プレイヤーなど）に使う | §2、§4 |
| A.2 Action Bar | バーは現在の状態を示す。盤上の部品の操作をバーのボタンで置き換えない。主な操作を中央、取り消し・戻るを右に離す。ボタンは最大 4 個 | §3.4、§8（ボタンの色・並びは 010 §2 のまま） |
| A.3 Player panels | 4 人でもスマホで画面の 1/4 を超えない。**設定・題名・ラウンド数などの重複した情報を置かない。主な部品へ移る HTML のアンカー（ショートカット）は置いてよい**（PDF で確認） | §2.3、§4.2 |
| A.5 Clarity & Zoom | 核となるゲームは 100% の表示で遊べる。ズームのボタンは、どうしても必要なときだけ | §7 |
| B.1 Tooltips | 画面にある情報を同じ見せ方で繰り返さない。小さな文字・アイコンの拡大や、使う時機の助言を載せる。**モバイルでは外をタップすると閉じる**（PDF で確認） | §3.1、§6 |
| C.2 Highlights & Glowing auras | プレイヤーが「何を押すのか」を迷わない。選べる選択肢を強調し、確定する前に結果を見せる | §5 |
| C.4 Sizing & responsiveness | 中央寄せ、流動的な幅、盤の周りの余白。デスクトップは盤全体を見せる。モバイルは副次的な要素を主な操作の下に積む。100% の表示で読める。32×32px 未満の操作要素を作らない。**縦のレイアウトにはアンカー・ジャンプのリンクを置く**（PDF で確認） | §2、§5、§7 |
| E.1 Design for all | 色にアイコン・形・模様を添える。コントラスト比 4.5:1 以上。ボタン・アイコン・操作要素すべてにラベル。点滅や素早い切り替えをしない | §1、§5 |
| E.3 Tap targets | 操作要素は最小 32×32px、目標 40〜44px。ボタン・コマ・カードの間に間隔 | §1.3、§2.3、§5.4 |
| D.1 Errors and invalid messages（審査は挙げていないが参照） | 無効な操作には必ず理由を出す。「何も起きない」は不可。短い揺れ・暗さ・ツールチップ・平文のメッセージ。色だけに頼らない（PDF で確認） | §5.3、§5.7 |
