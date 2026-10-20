# 003: BGA プロジェクト構成・技術方針

| 項目 | 内容 |
| --- | --- |
| ステータス | Approved |
| 対応タスク | T-003 |
| 作成日 | 2026-10-20 |
| 関連仕様書 | 001（ゲームルール。並行作成中）、002（カードデータ。並行作成中）。後続: T-004（CI lint）、T-005（Studio 同期）、T-006（状態遷移）、T-007（データモデル・通知）、T-008（カード効果）、T-009（UI・アセット） |

## 目的

『我廻る、故に我あり』を BGA Studio のプロジェクト `irevolvethereforeiam` として実装するにあたり、`src/` に何を置くか、BGA フレームワークのどの機能を使うか、PHP・JS・CSS をどう書くかを決める。同じ作者・同じ運用で先行する姉妹プロジェクト（minimum-journey-bga、project-edgeworth-bga）の技術方針と実装を土台にし、このゲーム固有の差分（手番の割り込み、順番に回す秘密ドラフト、非公開情報の多さ、日本語が印刷された画像）に合わせて調整する。後続の CI・同期・状態遷移・データモデル・UI の各仕様書はこの仕様書に依存する。

## スコープ

- 対象: `src/` のファイル構成、PHP の名前空間とクラス構成、独自メソッドの接頭辞、状態機械の実装方式の方針、クライアント（JS・CSS）の構成、翻訳、非公開情報の扱いの原則、ゲームオプション・プレイヤー設定・`gameinfos` の候補、アセット生成物の扱いの方針、テスト方針、姉妹プロジェクトとの流用関係、作者側の準備事項
- 対象外: ルール（001）、カードデータ（002）、状態の一覧と遷移の詳細（006）、DB スキーマ・通知の一覧（007）、カード効果の実装方式（008）、画面レイアウトとスプライトの詳細（009）、CI・同期ワークフローの実装（004・005）。この仕様書ではコードもワークフローも作らない

## 記法と調査の前提

- **【確認】** は BGA 公式ドキュメント（`https://en.doc.boardgamearena.com/` 配下）で確認した事実。URL を添える。
- **【提案】** は本仕様書の提案。公式ドキュメントでは確認していない。
- **【未確認】** は確認を試みたが判断できなかった事項。「未確定事項」にまとめる。
- **【姉妹】** は姉妹プロジェクトの仕様書・実装・コミット履歴から取り込んだ判断や教訓。出典を書く。
- 調査日は 2026-10-20。ページは WebFetch（本文を要約して返すツール）で取得したため要約を介した確認であり、Game.php など長いページは要約が全文を網羅していない箇所がある。コード例の細部は実装時に各 URL を再読する。

姉妹プロジェクトの略称と、主な参照 URL:

| 略称 | 内容 |
| --- | --- |
| 姉妹 MJ | `minimum-journey-bga`。仕様書 003 は `../minimum-journey-bga/docs/specs/003-bga-architecture.md`。実装済みで、`src/` と fix コミットが教訓の源 |
| 姉妹 PE | `project-edgeworth-bga`。仕様書 004 は `../project-edgeworth-bga/docs/specs/004-bga-architecture.md`（最新。2026-10-19 作成） |
| Studio | <https://en.doc.boardgamearena.com/Studio> |
| FileRef | <https://en.doc.boardgamearena.com/Studio_file_reference> |
| GamePhp | <https://en.doc.boardgamearena.com/Main_game_logic:_Game.php> |
| StateDir | <https://en.doc.boardgamearena.com/State_classes:_State_directory> |
| StatesInc | <https://en.doc.boardgamearena.com/Your_game_state_machine:_states.inc.php> |
| GameJs | <https://en.doc.boardgamearena.com/Game_interface_logic:_Game.js> |
| GameJsOld | <https://en.doc.boardgamearena.com/Game_interface_logic:_yourgamename.js> |
| Actions | <https://en.doc.boardgamearena.com/Players_actions:_yourgamename.action.php> |
| Options | <https://en.doc.boardgamearena.com/Options_and_preferences:_gameoptions.json,_gamepreferences.json> |
| Stats | <https://en.doc.boardgamearena.com/Game_statistics:_stats.json> |
| GameInfos | <https://en.doc.boardgamearena.com/Game_meta-information:_gameinfos.jsonc> |
| Translations | <https://en.doc.boardgamearena.com/Translations> |
| Zombie | <https://en.doc.boardgamearena.com/Zombie_Mode> |
| TsScss | <https://en.doc.boardgamearena.com/Using_Typescript_and_Scss> |
| Deck / ItemManager | <https://en.doc.boardgamearena.com/Deck> / <https://en.doc.boardgamearena.com/ItemManager> |
| BgaCards / BgaAnimations / BgaZoom | <https://en.doc.boardgamearena.com/BgaCards> / <https://en.doc.boardgamearena.com/BgaAnimations> / <https://en.doc.boardgamearena.com/BgaZoom> |
| Img | <https://en.doc.boardgamearena.com/Game_art:_img_directory> |
| Checklist | <https://en.doc.boardgamearena.com/Pre-release_checklist> |

## 仕様

### 1. 方針の要約

| 項目 | 決定 | 姉妹との差 |
| --- | --- | --- |
| BGA プロジェクト名 | `irevolvethereforeiam`（作成済み・変更不可） | — |
| ファイル構成 | 現行構成のみ（`modules/php/Game.php`、状態クラス、`modules/js/Game.js`、JSONC 設定）。非推奨ファイルは作らない | 同じ |
| PHP | 8.4、全ファイル `declare(strict_types=1);` | 同じ |
| 状態機械 | 状態クラス方式（1 状態 1 クラス） | 同じ |
| 独自メソッドの接頭辞 | **`ir`**（§4.3） | MJ `mj`、PE `pe` から変更 |
| クライアント | 素の JS（ES モジュール）＋ JSDoc ＋ `tsc --checkJs`。**最初から複数ファイルに分割** | PE と同じ。MJ は単一ファイル（約 2,800 行まで肥大） |
| CSS | 素の CSS 1 ファイル（`irevolvethereforeiam.css`）、クラス接頭辞 `ir-` | 同じ（接頭辞のみ変更） |
| カードの永続化 | 自前のテーブルとリポジトリ。Deck・ItemManager は使わない | 同じ |
| 公式ライブラリ | bga-cards・bga-animations は採用。bga-zoom は回廊の拡縮に採用候補。bga-score-sheet は終了時の得点内訳の候補 | MJ は前者のみ、PE は bga-zoom も採用 |
| 非公開情報 | 手札・裏向きカード・秘密ドラフトは `_private` と `notify->player` を原則とし、観戦者・リプレイに漏らさない（§5.4） | 同じ原則。カードの識別子の付け方を追加（§5.4） |
| 他プレイヤーの割り込み（想起） | 順番に処理する ACTIVE_PLAYER 状態＋`changeActivePlayer`（§5.2） | 新規。姉妹は同時選択（MULTIPLE_ACTIVE_PLAYER）が中心 |
| undo | 使わない（`db_undo_support` は false） | 同じ |
| スプライト | **生成物をコミットする**（§9） | 姉妹は CI で生成してコミットしない。このリポジトリでは CI から `data/` を使えないため変更 |

### 2. BGA の現行標準ファイル構成（確認した事実）

- **【確認】** 現行標準は `modules/php/Game.php`（主ロジック）、`modules/php/` 配下の追加クラス、状態クラス、`modules/js/Game.js`。`material.inc.php`、`states.inc.php`、`<game>.view.php`、`<game>_<game>.tpl`、`<game>.action.php` は非推奨。PHP コンポーネント Deck と JS コンポーネント Stock も非推奨。FileRef（<https://en.doc.boardgamearena.com/Studio_file_reference>）、Actions（action.php は autowired action を使えば不要）、Deck（<https://en.doc.boardgamearena.com/Deck>）。
- **【確認】** `modules/` はバージョン管理され本番にも出る。`misc/` は本番に出ない Studio 用で合計 1 MB まで。それ以外の場所に置いた追加の `.js` / `.php` は本番に出ない。FileRef。
- **【確認】** 変更後に必要な操作: `dbmodel.sql` はゲームの再起動（本番公開後は移行が必要）、`gameoptions` / `gamepreferences` / `stats` はコントロールパネルでの特別な再読込、`Game.js` は F5、CSS と `img/` は強制再読込（Ctrl+F5）。FileRef。`gameinfos.jsonc` の再読込は姉妹 MJ 003 が GameInfos で確認した「Reload game informations」。
- **【確認】** PHP は 8.4。Dojo は非推奨（「avoid at all cost」）。公式の共有 JS 部品として bga-cards / bga-animations / bga-zoom / bga-autofit / bga-score-sheet / bga-dice が挙がる。Studio（<https://en.doc.boardgamearena.com/Studio>）。
- **【確認】** TypeScript は Studio ページ上では「対応・推奨とは書かれておらず、cookbook への案内のみ」。TsScss（<https://en.doc.boardgamearena.com/Using_Typescript_and_Scss>）は、TS のソースを **プロジェクトの `src/ts/`** に置き、Rollup で `modules/js/Game.js` を生成し、`src/` も一緒にアップロードする方式。ビルド成果物をバージョン管理に入れるかは書かれていない。同ページは初心者に勧めないとも述べる。
- **【確認】** `notify->all` の `_private`、`notify->player`、`i18n`、`NotificationMessage`、`changeActivePlayer`、`setPlayersMultiactive` などの API は GamePhp（<https://en.doc.boardgamearena.com/Main_game_logic:_Game.php>）。

### 3. `src/` の構成【提案】

`src/` の中身が BGA 側プロジェクトフォルダ `/irevolvethereforeiam/` と 1 対 1 に対応する（CLAUDE.md「BGA 実装の方針」）。Studio にだけあるファイル（`_ide_helper.php`、`bga-framework.d.ts`、各 README など）は `src/` に取り込まず、同期で消さない既定とする（005 で確定）。

```text
src/
├── LICENCE_BGA                          # Studio の初期ファイル（取り込む）
├── gameinfos.jsonc                      # メタ情報（§8）
├── gameoptions.jsonc                    # ゲームオプション（§7）
├── gamepreferences.jsonc                # プレイヤー設定（§7。初版は空に近い）
├── stats.jsonc                          # 統計（定義は 007）
├── dbmodel.sql                          # DB スキーマ（007）
├── irevolvethereforeiam.css             # スタイル（1 ファイル）
├── modules/
│   ├── php/
│   │   ├── Game.php                     # 名前空間は §4.1。薄く保つ
│   │   ├── Material/                    # 不変のマスターデータ（カード定義、列挙）。002 の生成物
│   │   ├── Data/                        # DB アクセス（Database、リポジトリ、グローバル値、プレイヤー値）
│   │   ├── Effects/                     # カード効果（想い出 99 種ほか。方式は 008）
│   │   ├── Flow/                        # フェイズごとのルール計算、回廊、勝利判定、通知の組み立て
│   │   ├── Setup/                       # setupNewGame の処理（オプション解釈、配置、配札）
│   │   ├── Stats/                       # 統計の記録
│   │   └── States/                      # 状態クラス（006 で一覧を確定）
│   └── js/
│       ├── Game.js                      # 入口（Game クラスを export）。他ファイルを import する（§6.1）
│       ├── states/                      # 状態ごとのクライアント側クラス
│       ├── board/                       # 回廊（12 の部屋）・精神トラックの描画と選択
│       ├── cards/                       # 手札・自己構成済みエリア・場札などのカード表示（bga-cards の薄いラッパ）
│       └── ui/                          # プレイヤーパネル、通知、ダイアログ、定数
└── img/                                 # サブディレクトリなし。スプライト（コミットする生成物）とアイコン類
```

- ファイル名・クラス名・名前空間はプロジェクト名 `irevolvethereforeiam` に従う。CSS は `irevolvethereforeiam.css`、`gameinfos.jsonc` などの JSONC は拡張子 `.jsonc`（姉妹 MJ 003 が 2026-10-14、姉妹 PE が 2026-10-20 に Studio の初期ファイルで確認）。
- **`Board/` ディレクトリは作らない**【提案】。姉妹 PE にある六角形盤面の計算に相当するものは、本ゲームでは 12 の部屋が環状に並ぶだけで、時計回りの移動・通過した部屋の数え上げ・全部屋入室（刹那の追憶）の判定は `Flow/` の小さなクラス（例: `Corridor`）で足りる。大きくなったら 006 で切り出す。
- `Effects/` は 008 が方式を決めるまで空のディレクトリ予約とする。想い出カード 99 種の効果を `Game.php` や状態クラスに書かない原則だけをここで決める（姉妹 PE 004 §3 を踏襲）。
- 非推奨ファイル（`states.inc.php`、`material.inc.php`、`*.action.php`、`*.view.php`、`.tpl`）は作らない。Studio の初期ファイルに含まれていた場合は、初回同期の前に確認して取り込まない（姉妹 MJ・PE、このゲームの初期ファイル（2026-10-20 確認）のいずれにも含まれていなかった）。
- TypeScript テンプレート一式（`package.json`、`rollup.config.mjs`、`tsconfig.json`、`src-disabled/`、`jsconfig.json`）は `src/` に取り込まない。TsScss は `src/ts` を前提にしており、本リポジトリの `src/` と衝突する（姉妹 PE 004 §3）。
- Studio の初期ファイルには、サンプルの状態クラス（姉妹の初期ファイルでは `PlayerTurn` = 10、`NextPlayer` = 90、`EndScore` = 98）が入っている。**同名で上書きしない状態クラスは Studio に残り、状態 ID が衝突して Express start が失敗する**。姉妹 MJ では実際に失敗し、同期で削除する対象に加えて直した（姉妹 MJ #35）。このゲームの初期ファイルも `PlayerTurn` = 10、`NextPlayer` = 90、`EndScore` = 98 だった（2026-10-20 確認）。削除リストを 005 で作る前提にし、状態クラスの名前と ID は 006 で雛形と照合する。
- リポジトリ直下（`src/` の外）に置くもの【姉妹】: `composer.json`、`package.json`、`phpstan.neon.dist`、`phpcs.xml.dist`、`eslint.config.mjs`、`stylelint.config.mjs`、`tsconfig.json`、`tests/`、`scripts/`、`tools/bga/`（Studio のスタブ。ライセンス不明のためコミットしない）。

### 4. サーバー側（PHP）

#### 4.1 名前空間と PHP のバージョン

- PHP は **8.4**。**【確認】** Studio（<https://en.doc.boardgamearena.com/Studio>）の Software Versions。CI の PHP も 8.4、`composer.json` の `config.platform.php` は `8.4.0`（姉妹 PE 004 §6.2 を踏襲）。
- 名前空間は `Bga\Games\<プロジェクト名>` で、追加クラスは PSR-4 に従えば自動で読み込まれ、サブフォルダは同名のサブ名前空間にする。**【確認】** GamePhp。
- **大文字小文字は Studio の初期ファイルで確定する**。姉妹 MJ の初期ファイルは `Bga\Games\MinimumJourney`、姉妹 PE は `Bga\Games\projectedgeworth`（すべて小文字）で、姉妹 PE は暫定案の `ProjectEdgeworth` を使って実装したあとで全面置換する手戻りが出た（姉妹 PE #35）。**教訓: 名前空間を決めるまで PHP クラスを書き始めない。****このゲームの名前空間は `Bga\Games\IRevolveThereforeIAm`（大文字小文字混在）で確定**。2026-10-20 にメインエージェントが Studio の初期ファイルを SFTP で読み取り取得して確認した（`modules/php/Game.php`、`States/*.php`。未確定事項 No.1 は解決）。姉妹 PE の小文字とは異なるので、雛形どおりに書く。
- `Game` クラスは `\Bga\GameFramework\Table` を継承する。状態クラスは `Bga\Games\<名前空間>\States` に置き、`\Bga\GameFramework\States\GameState` を継承する。

#### 4.2 層構成

| ディレクトリ | 役割 | 例（名前は 006〜008 で確定） |
| --- | --- | --- |
| `Material/` | 不変のマスターデータ。想い出カード 99 種（144 枚）、床 12、精神 9、初期自我 8、強化自我 5、目標 7 の定義と列挙。識別子は 002 に従う。`card.csv` からの取り込み方法と差分検査は 002 | `CardDefinitions`、`EffectTiming`（ターン開始時 / 起動 / 常時 / 即時 / 保有時）、`KeyType` |
| `Data/` | DB アクセス。`Database` インターフェース（BGA 実装とテスト用 PDO 実装の差し替え点）、リポジトリ、グローバル値、プレイヤー値 | `BgaDatabase`、`CardRepository`、`GlobalValues`、`PlayerValues` |
| `Effects/` | カード効果。方式は 008 | 008 で確定 |
| `Flow/` | 5 フェイズごとのルール計算、回廊の移動、コスト支払い、勝利判定（3 種と全員敗北）、通知の組み立て。**フレームワークを直接呼ばない** | `MovePhase`、`RecallPhase`、`ConstructPhase`、`Corridor`、`WinChecker`、`StateId`、`Notification` |
| `Setup/` | `setupNewGame` の本体。オプションの解釈、床の配置、配札、初期自我の山の準備 | `GameSetup` |
| `Stats/` | 統計の記録。BGA の統計 API を `StatStore` インターフェースで隠し、テストでは配列実装に差し替える | `StatsRecorder` |
| `States/` | 状態クラス（フレームワークとの接点） | 006 |

- 乱数・プレイヤー名の取得のように BGA に依存するものは、`Flow/`・`Setup/` のクラスにコンストラクタまたはメソッド引数で注入する。これでフレームワーク無しの PHPUnit で動かせる（姉妹 MJ・PE の実績）。
- `Game.php` は、フレームワークが要求するメソッド（`setupNewGame`、`getAllDatas`、`getGameProgression`、`upgradeTableDb`）、依存の組み立て、通知送信・例外生成の共通ヘルパー、`debug_` メソッドに限る。**【確認】** `debug_` で始まる `Game.php` のメソッドが Studio のデバッグメニューに出る（姉妹 MJ 003 が Practical_debugging で確認）。
- `getAllDatas` は非公開情報（他人の手札、部屋の裏向きの想い出カードの識別子、強化自我の山、山札の順序、未確定の選択）を含めない。本人分だけを返す。**【確認】** `getAllDatas` は魔法引数 `$currentPlayerId` を受け取れる（GamePhp）。
- 状態クラスの責務は「入場時の準備（`onEnteringState`）」「表示用の引数（`getArgs`）」「プレイヤーの操作（`act*`）」「離脱時の自動行動（`zombie`）」だけ。ルール判定は `Flow/`・`Effects/` に委ねる。`getArgs` では DB を書かない（姉妹 MJ・PE の実績）。

**状態クラス方式の採否: 採用**。**【確認】** StateDir（<https://en.doc.boardgamearena.com/State_classes:_State_directory>）: 状態は `GameState` を継承したクラス。コンストラクタ引数は `id`・`type` が必須で、`name`・`description`・`descriptionMyTurn`・`transitions`・`updateGameProgression`・`initialPrivate` が任意。`type` は `ACTIVE_PLAYER` / `MULTIPLE_ACTIVE_PLAYER` / `PRIVATE` / `GAME`。ID は正の整数で 1 と 99 は予約、稼働中のゲームで変えると壊れる。`onEnteringState` などが返すクラス名・状態 ID（int）・遷移名で遷移でき、その場合 `nextState` は呼ばない。アクションは `act*` メソッドに `#[PossibleAction]`、非 GAME 状態には `zombie` が必須。少なくとも 1 つの状態で `updateGameProgression: true` を指定する。理由は、(a) 公式が現行標準として示している、(b) 姉妹 MJ・PE が実装して動いている（MJ は 26 状態）、(c) 本ゲームは割り込みと秘密選択が多く、`getArgs` / `zombie` を状態ごとに同居させたほうが保守しやすい。状態 ID は `Flow/StateId.php` の定数にまとめる（StateDir が名前付き定数を推奨）。

#### 4.3 独自メソッドの接頭辞

フレームワークのクラス（`Table`、`GameState` など）を継承するクラスに足す独自メソッドには接頭辞を付ける。同名のメソッドがあると Studio で致命的エラーになり、開発用スタブ（`_ide_helper.php`）が古いため PHPStan では検出できない。姉妹 MJ で 2026-10-15 に `Game::sendNotifications` が `Table::sendNotifications` と衝突し、Express start が失敗した（姉妹 MJ #34）。**【確認】** GamePhp には、予約名や衝突についての記述はない（公式は `act` 接頭辞のアクション自動配線を説明するのみ）。したがって接頭辞の運用は本リポジトリの規約である。

| 候補 | 長所 | 短所 |
| --- | --- | --- |
| **`ir`（採用）** | プロジェクト名 `irevolvethereforeiam` の先頭。姉妹（`mj`、`pe`）と同じ 2 文字で打ちやすい。`ir` + 大文字で始まる名前は、このゲームの Studio 初期ファイルのスタブ（`_ide_helper.php`、2026-10-20 取得、371 関数）に 1 つも無い | `is...`（`isRealtime` など）と並べると 1 文字違いで読み間違えやすい |
| `irt` | 3 文字で衝突しにくい。CSS の接頭辞に使う「トリグラム」（Checklist）と揃えられる | 呼び出しが長くなる。PHP と CSS で揃える利点はあまり大きくない |
| `iri` | 「I Revolve I」。読みやすい | 意味が分かりにくい。`iri` は日本語のローマ字の語頭と紛らわしい |
| `rtia` | 頭文字（Revolve Therefore I Am）に近い | 4 文字で長い。プロジェクト名と結びつかない |
| `ita` | 頭文字（I Therefore Am）で衝突しにくい | `item` など一般の語と紛らわしく、意味が分かりにくい |

決定: **`ir`**（例: `irSendNotifications`、`irRecord`、`irSeatOrder`）。接頭辞の直後は大文字（camelCase）。

- 対象は `Game.php` だけでなく、**状態クラスの private / protected メソッドも含む**（姉妹 MJ の `mjRecord` の実績）。フレームワークを継承しないクラス（`Flow/`・`Effects/`・`Data/` など）には適用しない。
- フレームワークが定めるメソッド（`setupNewGame`、`getAllDatas`、`getGameProgression`、`upgradeTableDb`、`onEnteringState`、`getArgs`、`zombie`、`act*`）と `debug_` で始まるメソッドは例外として許可リストに入れる。
- 機械的な検査【姉妹】: 姉妹 PE は CI に `pe` 接頭辞の検査を入れた（姉妹 PE `scripts/lint/check-method-prefix.mjs`、同 #20）。004 で、これを `ir` 用に流用する。
- JS 側（`Game` クラスは `this.bga` を受け取るだけで、フレームワークのクラスを継承しない。**【確認】** GameJs）には接頭辞の必須化はしない。ただし CSS のクラス名・変数名は `ir-` を付ける（Checklist の CSS 接頭辞の勧め。**【確認】**）。

#### 4.4 コーディング規約

- 全 PHP ファイルに `declare(strict_types=1);`。型宣言、`enum`、属性を積極的に使う。新規プロジェクトでは `self::` ではなく `$this->` を使う（姉妹 MJ 003 が Migration ガイドで確認）。
- 規約は PSR-12（行長 140 文字）、静的解析は PHPStan level 5 から開始（姉妹の設定）。具体は 004。
- 識別子は 002 で決める。クラス・メソッドは PSR-1、DB の列名は `snake_case`。
- 乱数は `bga_rand(min, max)` または `random_int()`。`rand()` / `mt_rand()` は使わない。**【確認】** GamePhp。配列の抽選・シャッフルも `random_int` ベースのヘルパーを使う。
- 例外: プレイヤーの誤操作は `UserException`（メッセージは `clienttranslate` で包んだ英語。赤字で表示され、バグとして記録されない）、クライアントが送ってはいけない入力は `SystemException`。例外を投げるとそのリクエスト内の DB 変更と通知は破棄される。**【確認】** GamePhp。
- **アクションの引数**: 配列は `#[IntArrayParam]` などの型付き属性で受ける。空配列はリクエストに載らず、引数が未指定になって `BadMethodCallException` で落ちる。姉妹 MJ でブラックホール発生機の `[Do not exclude]` が実際に落ちた（姉妹 MJ #44。`IntArrayParam(max: 2)` を付けて `?array` の null を空配列扱いにして直した）。
- すべてのソースファイルの冒頭に著作権表記を付ける（Checklist。**【確認】**）。表記は「© だんじり協奏曲 (Danjiri Concerto)」、連絡先は <https://x.com/danjiriconcerto>（姉妹 PE 004 未確定事項 No.9 の作者回答）。

#### 4.5 Deck コンポーネントを使うか

**使わない（自前のテーブルとリポジトリ）**。**【確認】** Deck は非推奨で、後継として ItemManager が示されている（<https://en.doc.boardgamearena.com/Deck>、<https://en.doc.boardgamearena.com/ItemManager>）。ItemManager は採用しない。姉妹 MJ・PE が評価し、`dbmodel.sql` の自前スキーマ＋`Database` インターフェースのほうが PHPUnit（結合テストを含む）で検証しやすいと判断した（姉妹 PE 004 §6.1）。本ゲームはカードのほかにも、床の配置、人格の位置、トークン、精神トラック、保留中の割り込み（§5.2）などの永続データが多い。スキーマの詳細は 007。

方針だけ決める【提案】:

- カード 1 枚 = 1 行（想い出 144 枚の個体を区別する）。場所（山札・捨札・手札・部屋・場札・自己構成済み・除外など）、場所の引数（プレイヤー ID や部屋番号）、順序、向き（使用済みの横向き）を持つ。
- 初期自我・強化自我・目標・精神カードは配置が少ないので同じ表か別の小さな表かを 007 で決める。

### 5. 状態機械・割り込み・非公開情報の方針

状態の一覧と遷移は 006。ここでは方式を決める。

#### 5.1 手番の骨格

- 通常の手番（移動 → 想起 → 床アクション → 自己構成 → クリーンアップ）は、ターンプレイヤーを `ACTIVE_PLAYER` の状態で進める。結果の計算だけの状態は `GAME` 型にする。
- **【確認】** ACTIVE_PLAYER 状態では、入場前にアクティブプレイヤーを設定する必要があり、状態の途中で変えられない（StateDir）。変更するときは `$this->gamestate->changeActivePlayer($playerId)` を呼び、同じアクションの中で状態遷移を行う（GamePhp）。
- **進行度**: `getGameProgression()` を実装する（Checklist で必須。**【確認】**）。公式はまた、ELO の計算と投了条件（2 人戦の非トーナメントで 50% 以上）に使われると説明している（GamePhp）。式は 006（勝利条件への近さ：精神トラックの最高階層、自己構成スコアの最大、全部屋入室の進み具合、想い出の山札の残りなど）。
- **終了**: 最終処理は専用の `GAME` 状態で行い、その後に終了する。**【確認】** `stGameEnd` / `argGameEnd` は `Game.php` でオーバーライドしない（しないとゲームが「Recording game results」で止まる。StateDir）。最後の状態は 99 の代わりに `GameResult::individualRanking(...)` を返せる（姉妹 PE 004 §5.4）。
- **undo は使わない**。**【確認】** `undoSavepoint()` は multiactive 状態、および次が multiactive の場合は使えず、保存点も 1 つだけ（GamePhp）。本ゲームは非公開情報（手札のドロー、秘密の確認）が絡み、undo で隠れた情報が漏れる。`gameinfos` の `db_undo_support` は false。

#### 5.2 想起フェイズ（他プレイヤーの割り込み）

ルール上の要点（詳細は 001。ここでは技術選択に必要な範囲）: 想起フェイズでは、ターンプレイヤーが自分の部屋の裏向きの想い出カードを表向きにし、その部屋にいる全プレイヤーが想起効果を発動する。床アクションを代替する効果はターンプレイヤーだけが発動できる。複数プレイヤーの発動順を決める必要があるときは、ターンプレイヤーから時計回りに発動する（ルールブック「想起フェイズ」の節）。

| 案 | 内容 | 長所 | 短所 |
| --- | --- | --- | --- |
| **A. 順番に処理（採用案）** | 想起フェイズを `GAME` 型で開始し、発動できるプレイヤーを時計回りに並べる。1 人ずつ `changeActivePlayer` で ACTIVE_PLAYER の状態（例: `RecallEffect`）に入れ、解決後に次の人へ。全員済んだら床アクションへ戻る | ルールが「ターンプレイヤーから時計回り」と順序を決めており、そのまま表せる。同時選択のための非アクティブ・再選択（姉妹の案 B）の不確実性を避けられる。最終的な効果の解決順が一意に決まる | 順番待ちの人は何もできない。効果の発動が任意かどうかで状態が増える |
| B. 全員同時 | `MULTIPLE_ACTIVE_PLAYER` で発動する人を同時にアクティブにして一斉処理 | 待ち時間が短い | 順序の依存（場札獲得など）を結果の計算側で解く必要がある。ルールは順序を決めている |
| C. private parallel states | 複数ステップの選択を人ごとに並行 | 1 人の複数ステップの入力に向く | 公式が「新フレームワークでは未検証」と注記している（StateDir）。採用しない |

方針【提案】:

- **採用案は A**。ターンプレイヤーの ID は DB（`GlobalValues`）に保持し、割り込みの間は `changeActivePlayer` で他のプレイヤーに移るため、`getActivePlayerId()` をターンプレイヤーとして使わない。割り込みが終わったらターンプレイヤーに戻す（`changeActivePlayer` ＋遷移を同じ処理の中で）。
- 割り込みで選択が要る効果（例: 「場札を獲得する」）は、解決前に保留中の項目を DB に持つ（007）。**同じプレイヤーが複数の保留を持てる**ように、1 つのスロットを上書きしない。姉妹 MJ では、1 人が同時解決の効果を 2 つ持つと後の効果で要求が上書きされ、残りが選ばれないまま進む不具合があった（姉妹 MJ #41）。先頭から 1 つずつ解決する設計にする。
- **効果が自動で完結する場合（選択がない場合）は、状態を経由せず `GAME` 状態の中で解決して通知だけ送る**。割り込み状態に入る人は、選択が実際に必要な人に限る。
- アナログのルール上は同時でも順序に影響しない効果（全員が単純に値を得る）は、A のなかでまとめて 1 回の通知にしてよい（006 で個別に判断）。
- 他プレイヤーの割り込みが **任意（発動しない選択ができる）** かどうかは 001 が決める。任意なら、割り込み状態の `actSkip` と `zombie`（スキップ）を持たせる。
- MULTIPLE_ACTIVE_PLAYER は、本当に同時が要る状況（例: 全員が同時に確認して選ぶ効果がカードにあった場合）に限って使う。使う場合は次の姉妹の教訓を守る。
  - **【確認】** 入場時（`onEnteringState`）に `setPlayersMultiactive` / `setAllPlayersMultiactive` を呼ぶ。入場前にアクティブにしてはいけない（StatesInc）。
  - **【確認】** JS では、multiactive の状態で `onEnteringState` の時点ではまだアクティブか分からない。UI は `onUpdateActionButtons` または状態クラスの `onPlayerActivationChange(args, isCurrentPlayerActive)` で作る（StateDir、GameJs）。姉妹 MJ では、`onEnteringState` で UI を作っていたため、リロードするまで選択の UI が出ない不具合があった（姉妹 MJ #41）。
  - 選び終えた人の「選び直し」を `#[CheckAction(false)]` で受ける方式（姉妹の案 B）は、**公式ドキュメントに記述がない**（姉妹 PE 004 §5.2）。姉妹 MJ は実装して運用している（#40 以降の fix 群が案 A への切り替えなしに続いている）が、明示的な確認の記録はない。クライアントの `performAction` にも `{ checkAction: false }` が要る（姉妹 MJ の `Game.js`）。このゲームで必要になったら最初に Studio で検証する。

#### 5.3 初期自我の秘密ドラフト

ルール上の要点（詳細は 001）: 初期自我カードをシャッフルして「プレイ人数 + 1 枚」を抜き出し、プレイ順序と逆順に、秘密裏に 1 枚選んで残りを次のプレイヤーに回す。全員が選び終えたら一斉に公開する（ルールブック「セットアップ」の 13）。

- 回す手順が順序依存なので、**1 人ずつ ACTIVE_PLAYER の状態で選ぶ**（同時ではない）。
- 候補（回ってきた山）は、ACTIVE_PLAYER 状態の `getArgs` を **`_private`（プレイヤー ID をキー）に入れる**。**【確認】** ACTIVE_PLAYER 状態の `getArgs` の戻り値は、`_private` の外は全員に公開される（StateDir）。公開部分には「誰が選択中か」「山の枚数」だけを入れる。
- 選択は `notify->player` で本人にだけ確認を返す。公開側には「選んだ」ことだけを知らせ、どのカードかは全員の選択が終わるまで通知しない。全員が選び終えた後に `notify->all` で一斉に公開する。
- 選んだ初期自我と山の中身は、DB に保存する。リロード・再接続では `getArgs` と `getAllDatas`（本人分のみ）から復元する。
- 強化自我（第三階層到達時に獲得する）の「秘密裏に確認して選ぶ」も同じ仕組み（本人だけに候補を `_private` で見せる）。

#### 5.4 非公開情報の原則

| 情報 | 本人 | 他プレイヤー・観戦者 |
| --- | --- | --- |
| 手札の想い出カード | 券面を見せる | 枚数だけ |
| 部屋の裏向きの想い出カード | 同じ（誰も券面を知らない） | 見えるのは裏面の情報（レベルと移動コストの錠前の種類）だけ。**カードの識別子（カード番号）は送らない** |
| 想い出の山札・強化自我の山 | 見えない | 枚数だけ |
| ドラフトの回ってきた山、強化自我の候補 | 券面を見せる | 見せない |
| 表向きの情報（自己構成済みカード、場札、床、精神トラック、トークン） | 見せる | 見せる |

- **`getAllDatas` と `getArgs` の公開部分には非公開情報を入れない**。**【確認】** `notify->all` は観戦者にも届くため非公開情報を載せない。本人だけへは `notify->player`（観戦者には送れない）または `notify->all` の `_private`（プレイヤー ID をキー）を使う（GamePhp）。
- 公開と本人向けを 1 つの通知で出したいとき（例: 「A がカードを 1 枚引いた」＋本人には券面）は `_private` を使うと 1 通知にまとめられる。姉妹 MJ・PE は `notify->all` と `notify->player` を別々に呼んでいた。どちらでもよい。リプレイで再生順が崩れないよう、まとめるほうを優先する【提案】。
- **カードの DB ID は、カード番号の順に採番しない**【提案】。部屋の裏向きカードの ID をクライアントが受け取る場合、ID がカード番号順だと識別子が推測できる。ID はシャッフル後の順に採番するか、クライアントへは識別子と無関係な表示用の ID を送る（姉妹 MJ 009 §5 は、裏向きのカードの ID も送らず枚数だけにした）。
- 手札から場へ出した瞬間（自己構成など）は、公開通知にカード番号を載せる。ドローしたカードは、本人への通知にだけカード番号を載せる。
- クライアントは通知だけで画面を更新する。リプレイ・観戦で崩れないようにする（姉妹 PE 004 §5.4）。**【確認】** 通知は成功したアクションの最後にまとめて送られ、例外で破棄される。通知の容量は 128K まで。HTML を入れない（GamePhp）。
- 検証: Studio で、観戦者として入った画面とリプレイで非公開情報が見えないことを確認する（Checklist。**【確認】**）。

#### 5.5 離脱（zombie）

- **【確認】** 非 GAME 状態のすべてに `zombie(int $playerId)`（`$args` も受け取れる）を実装する。`getCurrentPlayerId()` を使わず、渡された `$playerId` を使う（StateDir、Zombie: <https://en.doc.boardgamearena.com/Zombie_Mode>）。
- **【確認】** 推奨はレベル 1（ランダムの合法手）または 2。ルールを参照せず、ゲームの精神に沿った無難な行動を取る。**ゲームを早期に終わらせない**（2 人戦でも）。
- 本ゲームの既定【提案】（具体は 006）: 移動・自己構成は「何もしない」で手番を終える（できるなら最低コストの移動に進める）、割り込みの想起効果は「スキップ」、ドラフトは先頭の 1 枚、強化自我はランダム。**移動で全部屋入室などの即時勝利にならない**ように、離脱者の行動は無難なパスを基本にする。
- 全員が離脱した場合の扱いは BGA の標準に任せる。

### 6. クライアント側（JS・CSS）

#### 6.1 TypeScript か JavaScript か

**採用: 素の JS（ES モジュール）＋ JSDoc ＋ `// @ts-check`（`tsc --noEmit --checkJs`）。TypeScript とビルドは使わない。ファイルは最初から分割する。**

| 案 | 長所 | 短所 |
| --- | --- | --- |
| A. 素の JS を分割（採用） | `src/` が BGA と 1 対 1。ビルド不要で、手元の試験同期にもビルド工程がいらない。Node だけで ESLint と型チェックが回る | 型の厳密さは JSDoc 頼み。分割のたびに `import` の動作確認が要る |
| B. TypeScript を Rollup で `Game.js` にバンドル（TsScss 方式） | 型が書きやすい | TS のソースを置く `src/ts` が本リポジトリの `src/`（BGA と 1 対 1）と衝突する。CD にビルドと成果物の扱い（コミットするか）の決定が要る |

- **【確認】** `modules/js/Game.js` は `export class Game` を export し、コンストラクタは `constructor(bga)`。状態クラスは `this.bga.states.register('<状態名>', new StateClass(this, this.bga))` で登録する。別ファイルのクラスは `export class X {...}` と書き、Game.js の先頭で `import { X } from "./X.js";` と読み込む。bga-cards などは `const BgaCards = await importEsmLib('bga-cards', '1.x');`（GameJs、BgaCards、BgaAnimations）。
- 姉妹 MJ は単一の `Game.js` が約 2,800 行になった。姉妹 PE は最初から分割した（004 §4）。本ゲームも、手札・自己構成済みエリア・回廊・精神トラック・各種の選択 UI が加わるため、最初から分割する。
- 分割の単位【提案】: 状態ごとのクラスは `states/<状態名>.js`、回廊と精神トラックは `board/`、カード表示は `cards/`。`Game.js` は `setup`、状態クラスの登録、通知の購読だけを持つ。
- **【未確認】（最初の UI 実装 PR で確認する方針で確定。rule-questions Q-37）** 本番用の minify（Checklist の "Use minified JS"。**【確認】** デプロイ時の JS は terser で minify される。GameJsOld「Javascript minimization」）で、相対 `import` が動くか。姉妹 PE 004 §4 も未確認のまま残している（姉妹 PE は実装・同期済みだが、確認の記録は見つからない）。**最初の UI の実装 PR で、Studio の「Use minified JS」で確認する**。だめなら TsScss の方式（Rollup で `Game.js` 1 本にバンドル）に切り替える（未確定事項 No.3、rule-questions Q-37）。
- クライアントの数値は文字列で届くことがある。**【確認】** BgaCards も「PHP が整数を文字列で送ることがあり、整数フィールドで sort すると問題になる」と注意している。JS で `int()` に通す関数を 1 つ用意し、ID と枚数は必ず通す（姉妹 MJ の `Game.js`）。
- ESLint のグローバルに `importEsmLib`、`getLibUrl`、`_`、`$` を足す設定は姉妹の `eslint.config.mjs` を流用する。`bga-framework.d.ts` は `tools/bga/` にローカルで置き、コミットしない（姉妹 MJ・PE）。
- JS の単体テストは、**生成物（スプライトの配置表など）に依存させない**。姉妹 PE で、テストが生成物の `sprites.js` に依存していたため、依存しないように直した（姉妹 PE #41）。

#### 6.2 公式ライブラリの採否

| ライブラリ | 採否 | 理由と注意 |
| --- | --- | --- |
| bga-animations（1.x） | **採用** | bga-cards の前提。`animationsActive: () => this.bga.gameui.bgaAnimationsActive()` で生成する（BgaAnimations）。**注意（姉妹 MJ #43）**: アニメーション中のカードは `body` 直下の animation-surface へ移されるため、カードの大きさを決める CSS 変数は `.ir-scope` の中だけでなく `:root` に定義する |
| bga-cards（1.x） | **採用** | 想い出カードは枚数が多く、山札・手札・場札・部屋・自己構成済みと場所が多いので、`CardManager` と `LineStock` / `HandStock` / `SlotStock` / `Deck` を使う。`setupFrontDiv` / `setupBackDiv` でスプライトを指定し、`isCardVisible` は `() => true` を基本にして山札だけ裏面にする（BgaCards が既定の `card.type` を「直感に反する」としている。**【確認】**）。**注意（姉妹 MJ #36・#40）**: bga-cards のカードの面は `--bga-cards_card-width` / `-height`（`CardManager` の `cardWidth`・`cardHeight`）の大きさで描かれるので、縮小表示では実寸の変数で上書きする。山札の枠（`--width` / `--height`）も同様に `!important` で上書きする。bga-cards の CSS は後から注入されるので、読み込み順に頼らず詳細度を上げる |
| bga-zoom（1.x） | **採用候補** | 12 の部屋が並ぶ回廊は幅が大きく、スマートフォンでは全体を縮小したい。`autoZoom` で目標の幅に自動で縮小する（BgaZoom。**【確認】**）。姉妹 PE が採用。ただし CSS の `zoom` を使うため、祖先の `zoom` / `transform` で `position: fixed` の基準がずれる。カードの拡大表示のようなオーバーレイは `body` 直下に出す（姉妹 MJ #52）。最終決定は 009 |
| bga-score-sheet | **候補** | 終了時の得点内訳（想い出カードのスコア、目標カードのスコア）を表にする。009 で判断 |
| bga-autofit | 候補 | カード名やボタンの文字の自動縮小に使えるが、カード名は画像に印刷されている（§9）。009 で判断 |
| bga-dice | 不採用 | サイコロを使わない |
| Stock（JS）、Deck（PHP）、Dojo | 不採用 | 非推奨。**【確認】** Studio |

- カードの拡大表示は、本ゲームでもほぼ必須（カードの効果文が細かい）。姉妹 MJ で、スマートフォンでの拡大表示に不具合が重なった（姉妹 MJ #39・#52）。教訓: (1) 高さの上限は `100vh` ではなく `window.innerHeight` から決める（iOS では `100vh` がツールバーを含み下が切れる）、(2) 拡大表示は `body` 直下に出す、(3) 拡大がステータスバー（`#page-title`）のボタンを隠さない位置にする、(4) 選べるカードの拡大をタップしたらそのカードを選んで閉じる。
- 選択状態の CSS クラスは、状態を出るときに必ず外す。姉妹 MJ では、選べない山札の暗い表示のクラスが外れず、次の状態でも暗いままだった（姉妹 MJ #46）。選択状態を 1 つの関数で決め、離脱時に全体を掃除する。

#### 6.3 CSS

- 素の CSS 1 ファイル `irevolvethereforeiam.css`。**【確認】** Checklist は CSS 接頭辞（クラス名の衝突を避けるため）、ダークモードへの対応、中央寄せ、非自明な画像のツールチップを求める。クラスと CSS 変数は `ir-` 接頭辞。配色は CSS 変数で持ち、ダークモードに対応する。
- 複数ファイルへの `@import` が使えるかは**【未確認】**（姉妹 PE と同じ）。1 ファイルのままセクションコメントで整理する。
- SCSS は使わない（ビルド不要の方針。TsScss の SCSS は Sass のビルドが要る）。
- カードの大きさは基準幅から計算した CSS 変数 1 組（例: `--ir-w` / `--ir-h`）で決め、`:root` で定義する（姉妹 MJ #43）。
- スマートフォン: 手札・自己構成済みエリアのカードが多くなるので、折りたたみや横スクロールを避け、基準幅で縮小して全体を見せることを基本にする（姉妹 MJ #37・#42・#49 で、`cqw` に頼った大きさ指定やカードの縮小表示の分岐が不具合を繰り返した。縮小表示を別のモードにせず、全員同じ大きさにする）。詳細は 009。

### 7. 翻訳・ゲームオプション・プレイヤー設定

#### 7.1 翻訳

- UI 文言は **英語を原文**とし、BGA の翻訳機構で日本語化する（CLAUDE.md、BGA の標準作法）。**【確認】** Translations（<https://en.doc.boardgamearena.com/Translations>）: 英語だけで開発する。サーバーは英語の文字列を送り、翻訳はクライアントで行う。コミュニティによる翻訳はベータ入り後。
- PHP: 状態の `description` / `descriptionMyTurn`、通知のログ文、`UserException` のメッセージは `clienttranslate('...')` で包む。**変数は渡さない**（抽出ツールはリテラルしか拾わない）。置換は単引用符の `${name}` で行う。`gameoptions` / `gamepreferences` / `stats` の名前は自動で翻訳対象になるので `totranslate` / `clienttranslate` は不要。
- JS: 画面に出す文字列は `_()` で包む。`_()` に変数を渡すなら、その英語の文字列が PHP の `clienttranslate` か JS の `_()` のどこかに必ずリテラルで現れていること。**オブジェクトのコンストラクタの中で `_()` を呼ばない**（`setup` 以降で呼ぶ）。
- 翻訳可能な引数（カード名など）は、通知の `i18n` 配列に列挙する。翻訳されても壊れないよう、クライアントのロジックで使うキーとは別の名前にする（例: `card_name` と `card_name_translated`）。**【確認】** Translations。
- 文を断片から組み立てない（複数形は `(s)` などの 1 つの形に）、時制は現在形、ボタン・タイトルの末尾にピリオドを付けない。
- 日本語訳は BGA の翻訳ツールで行い、訳ファイルはリポジトリに置かない（姉妹 MJ 003 が確認）。
- **画像に日本語が印刷されている（確定: 案 1。作者回答 2026-10-20、rule-questions Q-16）**。想い出カードの画像（`data/cards/memory/*.png`）にはカード名（例「No.1 捨てられたイデア」）と、効果を示す印刷が含まれる。BGA のカードを画像のスプライトで表示すると、英語の UI でも日本語が見え、翻訳機構は使えない。方針案:
  1. **案 1（確定）**: 英語版のカード画像は存在しない。画像は加工せず、そのまま使い、カードにマウスを乗せたとき・タップで開く拡大表示・ログに、002 が定める**英語のカード名と英語の効果文**を HTML で出す。カードの識別と効果の理解は英語のテキストで補う。
  2. 案 2: 画像のテキスト部分を切り取る（または白紙の枠にする）加工をして、カード名と効果文を HTML で重ねる。多言語（翻訳機構）に最も忠実だが、レイアウトの作り込みと、画像の改変について作者の了承が必要。
  3. 案 3: 日本語のままにする。`language_dependency` は設定しないが、非日本語話者には使いづらい。
  案 1 で確定した（rule-questions Q-16、未確定事項 No.2）。002 が英語のカード名と効果文を保持し、009 は画像を加工せずにスプライトへ切り出す。

#### 7.2 ゲームオプション（`gameoptions.jsonc`）

**【確認】** 形式（Options: <https://en.doc.boardgamearena.com/Options_and_preferences:_gameoptions.json,_gamepreferences.json>）: オプション ID を文字列のキーにした JSON オブジェクト。ID は 100 番台（200〜299 は BGA 共通オプション用の予約）。各オプションは `name`、`values`（値のキーは文字列）、`default`（省略時は最初の値）、`level`（`base` / `major` / `additional`。ファンシーロビーの表示にだけ影響）、`displaycondition`（`minplayers` / `maxplayers` / `otheroption` / `otheroptionisnot`）、`startcondition`、`notdisplayedmessage`、値側の `tmdisplay` / `description` / `beta` / `alpha` などを持つ。値がちょうど 2 つで yes/no・on/off などのときは、チェックボックスで表示される。`gamestartonly` は使わない（公式が禁止）。PHP は `$this->bga->tableOptions->get(int $optionId): ?int`（古いテーブルでは null）、JS は `this.bga.tableOptions.get(optionId)`（未定義だと例外）。オプションの値のキーは一度公開したら変えない。

候補【提案】（ルールが決める内容は 001 に従う。001 のオプション節が確定したら、ID と値のキーをそれに合わせる）:

| ID | 名前（原文） | 値（キー: 名前） | 初期値 | 備考 |
| --- | --- | --- | --- | --- |
| 100 | Floor layout（ゲームオプション「回廊の配置」。標準＝ID 順を既定 / ランダム。rule-questions Q-17 で確定） | 1: Fixed order (by ID) / 2: Random | 1 | 床カード（ID 1〜12 の DEMETER〜HEPHAESTUS）の回廊への配置。ルールブックは「レベル 2 の床カードを四隅に、レベル 1 の床カードをそれ以外の 8 箇所に」と定めるが、レベル内の並びの決め方は 001 が決める。ID 順を既定、値 2 をランダムとする（001・Q-17） |
| 101 | Show enhancement-ego deck count | — | — | 候補にとどめる。非公開情報なので、枚数を公開するかは 001 が決める。初版は作らない |

- 初版は 100 のみ（Q-17 で作ると確定）。ほかに、ルールに無い独自の選択（例: 初期自我のドラフトを使わず固定の割り当てにする入門用のオプション）は、作者が望まない限り作らない。
- ゲーム開始時に確定するランダムな配置は、`setupNewGame` で 1 度だけ決めて DB に保存する（再読込・リプレイで同じになる）。**【確認】** `tableOptions->set()` はランダムオプションを、コードが選んだ値に置き換える（GamePhp）。
- 値の `tmdisplay` は初期値以外に付け、ロビーのテーブル説明に出す。`level` は `base`（姉妹 PE の暫定案と同じ）。

#### 7.3 プレイヤー設定（`gamepreferences.jsonc`）

**【確認】** ID は 100〜199（オプションの ID とは無関係）。各設定は `name`、`values`、各値に `cssPref`（`<html>` に付くクラス）、`needReload`、`default`。PHP は `$this->userPreferences->get($playerId, $prefId)`、JS は `this.bga.userPreferences.get(100)`。`cssPref` の変更は再読込後に反映されるので `needReload` を true にする（Options、GameJs）。

初版は**空に近い**【提案】（姉妹 MJ・PE と同じ）。候補:

| ID | 名前（原文） | 値 | 用途 |
| --- | --- | --- | --- |
| 100 | Card effect text | 1: Show on hover/tap / 2: Always show below cards | §7.1 の案 1 で、英語の効果文をどこに出すか。画像が日本語の間は、効果文を常に出したい人がいる |
| 101 | Compact hand | 1: Off / 2: On | 手札が多いときの重ね表示。009 で必要か判断 |

### 8. `gameinfos.jsonc` の値

**【確認】** GameInfos（<https://en.doc.boardgamearena.com/Game_meta-information:_gameinfos.jsonc>）に書かれているフィールド: `players`、`suggest_player_number`（ELO の係数にも関わる。null 可）、`not_recommend_player_number`、`player_colors`、`losers_not_ranked`、`tie_breaker_description` / `tie_breaker_split`、`fast/medium/slow_additional_time`（ベータ入りで自動調整され、以降は管理者が変更）、`disable_player_order_swap_on_rematch`。`designer` / `artist` / `year` / `tags` / `presentation` などは**もう読まれず、Game Metadata Manager で管理する**。`estimated_duration`、`bgg_id`、`db_undo_support`、`game_interface_width`、`favorite_colors_support`、`is_coop`、`solo_mode_ranked`、`exception_on_warning` は、このページでは説明されていない（姉妹の初期ファイルには存在する。**【未確認】** の扱いとし、初期ファイルの既定を基本にする）。

| キー | 値 | 根拠・備考 |
| --- | --- | --- |
| `game_name` | `"I Revolve, Therefore I Am"` | `docs/publishing/game-description.md`（英語の題名） |
| `publisher` / `publisher_website` / `publisher_bgg_id` | `Danjiri Concerto` / `https://danjiri-concerto.github.io/index.html` / `0` | 姉妹 PE 004 §8 の作者回答（2026-10-19）。出版社の BGG ID は未取得 |
| `bgg_id` | `0`（承認待ち） | 2026-10-20 に BGG へ申請済み。承認後に作者が BGG_ID を伝え、エージェントが設定する（`docs/tasks.md`、`docs/publishing/game-description.md`） |
| `players` | `[2, 3, 4]` | README（2〜4 人）。想い出の山札はレベル 1 を人数に応じて除外する（ルールブックのセットアップ 2。詳細は 001） |
| `suggest_player_number` | `null`（確定） | ルールブックに推奨人数の記載がない（姉妹 PE と同じ扱い）。未確定事項 No.5、rule-questions Q-37 |
| `not_recommend_player_number` | `null` | 同上 |
| `estimated_duration` | `45` | README の 30〜60 分の中央値。ロビーの初期表示にだけ使われ、のちに実測に置き換わる（姉妹 PE と同じ考え方） |
| `fast_additional_time` / `medium_…` / `slow_…` | 初期ファイルのまま（大きめ） | **【確認】** ベータ入りで自動調整され、以後の変更は管理者の対応が要る。アルファ申請前に見直す |
| `tie_breaker_description` | 001 が決める | ルールブックに同点の規定があるか 001 で確認。無ければ `""`（同順位）。**【確認】** 同点処理に `player_score_aux` を使う（Checklist） |
| `losers_not_ranked` | `false` | **【確認】** 2 人戦・同点が多いゲームには使わないよう公式が注意している |
| `is_coop` | `0` | 対戦ゲーム。ただし「全員敗北」がある点は、全員 0 点の同順位で表す（未確定事項 No.4、rule-questions Q-25） |
| `db_undo_support` | `false` | §5.1 |
| `game_interface_width.min` | `740`（初期ファイルのまま。rule-questions Q-37） | 回廊と手札の幅の扱いは 009。bga-zoom の自動縮小で対応する想定 |
| `player_colors` | 駒の色（ミープルとキューブ画像）に合わせた 4 色（赤・青・緑・黄）に絞る | `data/piecies/meeple/meeple_{r,b,g,y}`。具体的な 16 進数は 009 で決める |
| `favorite_colors_support` | `false`（確定） | 駒の画像が 4 色固定のため、プレイヤーの好みの色に合わせにくい。**【確認】** 公式は、色が盤面と結びつくゲームでは使わないよう案内している（GamePhp）。未確定事項 No.5、rule-questions Q-37 |
| `language_dependency` | `false` | 言語依存のルール・文章がない（カードの印刷文は §7.1 の別問題） |
| `exception_on_warning` | 初期ファイルのまま | — |

- `gameinfos.jsonc` を変更したら、コントロールパネルの「Reload game informations」が必要（姉妹 MJ 003）。同期後の手動操作の一覧は 005。

### 9. アセットの生成とコミットの方針

詳細は 009。ここでは前提と方針だけを決める。

- `data/` は原本で、Git 管理外。リポジトリ直下の作業ツリーにだけあり、**`.worktrees/` 配下のワークツリーにも CI にも存在しない**（CLAUDE.md の絶対ルール 6）。
- **方針【提案】: スプライトなどの生成物は、ローカルで `data/` から生成して `src/img/` にコミットする**。姉妹 MJ・PE は「生成物をコミットせず、CI が `data/` から生成する」方式だが、それは姉妹の `data/` が CI から使える前提であり、このリポジトリでは CLAUDE.md により不可（`docs/tasks.md` T-009 も「生成物をコミットする前提」）。
- コミットした生成物の整合は、CI では **原本なしで検査できる範囲**に限る【提案】: 1 枚の大きさの上限（BGA の 2 MB / 4 MB の食い違いのうち小さいほうの 2 MB を上限に使う。下記）、スプライトの縦横 4096 px 以下、`src/img/` 直下のファイル数、命名規則、生成時に同時に出力するマニフェスト（配置表。リポジトリに置く）と実ファイルの整合。原本と生成物の一致の検査はローカルだけで行う。
- **【確認】** 画像の制約: Img（<https://en.doc.boardgamearena.com/Game_art:_img_directory>）は「1 枚 2 MB まで」「画像ファイルは十数個まで」「スプライトは 4096×4096 以下（モバイルで失敗しうる）」「WebP 推奨」「ファイル名に空白・括弧を使わない」「ルート直下の画像はすべてプリロードされる」。Checklist は「1 枚 4 MB まで、総量 15 MB まで」。両者は食い違ったままで（姉妹 PE 004 §9 と同じ）、厳しい 2 MB・十数個を上限として扱う。箱絵などのメタ画像は `img/` に置かず Game Metadata Manager で登録する（Img。**【確認】**。005 の同期対象外）。
- カード画像は想い出カード 99 種（約 722×1006 px の原本）のほか、床 12、精神 9、初期自我 8、強化自我 5、目標 7、裏面（想い出の裏はレベル 1 / 2 × 錠前 3 種 = 6 種、各種の裏）。スプライトに詰める縮小サイズと枚数の割り振りは 009 が決める。
- 生成物をコミットする運用の注意: 生成は決定的にし、`data/` の原本か生成スクリプトが変わったときだけ再生成する（バイナリの差分が溜まるため）。スクリプトは姉妹 MJ の `scripts/build-sprites.mjs`（`sharp` のバージョン固定）を土台にする。
- 画像を `img/` に置いた後、Studio では強制再読込が必要（FileRef。**【確認】**）。

### 10. テスト方針

- **純粋なルール計算を PHPUnit で検査する**（姉妹 MJ・PE の実績）。対象: `Flow/` の回廊の移動（時計回り、通過した部屋の数え上げ、全部屋入室）、移動コストの支払い（万能鍵・心光の行使）、自己構成と精神分析のコスト、勝利判定（3 種と全員敗北）、想起フェイズの発動順（ターンプレイヤーから時計回り）、`Setup/`（人数別の除外枚数、配札、床の配置）、`Material/` の整合（`card.csv` との差分は 002）。ルールブックの例をテストベクタにする。
- 結合テスト（MariaDB）: 姉妹と同じく Docker（`php:8.4-cli` ＋ `mariadb`）で `Data/` と `Setup/` を実 DB で検査する。BGA のフレームワーク（`Table`）は使わない。CI での実行有無は 004 が決める。
- **非公開情報の漏れの検査をテストに入れる**: `getAllDatas` の他人の手札・部屋の裏向きカードの識別子、`getArgs` の公開部分、通知の公開側の引数に、非公開の値が含まれないこと（姉妹 MJ の `BoardViewTest` を踏襲）。
- BGA 上でしか確認できないこと（状態遷移、割り込みの順序と `changeActivePlayer`、通知、非公開情報の漏れ、zombie、リロード復元、観戦、リプレイ、minify した JS）は Studio での手動テスト表で確認する。表は 006 以降の仕様書に含める。
- JS はテストを書かない（ESLint と `tsc --checkJs`）。回廊の座標計算のように PHP と二重実装になる純粋関数は、Node の簡単なスクリプトで検査する（姉妹 PE の `tests/js/geometry.test.mjs`）。
- 配置【姉妹】: `tests/Unit/`、`tests/Integration/`、`tests/Support/`（BGA スタブ、配列版の `globals`、種付き乱数、PDO 版の `Database`）、`tests/js/`、`tests/Dockerfile`、`tests/run-integration.sh`。名前空間は `IRevolveTests\`（暫定）。PHPUnit は姉妹と同じ系列から始め、004 でバージョンを再確認する。

### 11. 後続タスクへの申し送り

| タスク | 引き継ぐ前提 |
| --- | --- |
| T-004（CI lint） | PHP 8.4。Markdown / PHP（`php -l`、PHPCS、PHPStan）/ JS（ESLint、`tsc --checkJs`）/ CSS（Stylelint）/ JSONC の検査。**`ir` 接頭辞の検査**（§4.3）と、`strict_types` の検査。`src/img/` の上限検査（原本なし。§9）。`tools/bga/` のスタブはコミットしない（PHPStan 用に Studio から取得する手順が要る） |
| T-005（同期） | `src/` のみを `/irevolvethereforeiam/` へ SFTP 同期（ポート 2022、SSH 鍵認証。姉妹 MJ 003 §6、姉妹 PE 006）。**Studio の雛形の状態クラスを削除対象に入れる**（§3、姉妹 MJ #35）。`src/img/` の生成物はコミット済みなので、CI で生成する工程は要らない。同期後の手動操作（`dbmodel.sql` は再起動、`gameinfos` / `gameoptions` / `stats` は再読込、CSS・画像は強制再読込） |
| T-006（状態遷移） | §5 の方針（想起は順番の ACTIVE_PLAYER、秘密ドラフトは順番、`_private`、zombie、進行度）。名前空間の確定後に状態クラス名を決める。雛形の状態 ID（10・90・98）と衝突しないこと |
| T-007（データモデル） | カードは自前テーブル（個体 144 枚）。保留中の割り込みはプレイヤーごとに複数持てる。ターンプレイヤーの ID を別に持つ。非公開情報の扱い（§5.4）。ID の採番はカード番号順にしない |
| T-008（カード効果） | `Effects/` の予約。効果を `Game.php` や状態クラスに書かない。割り込みが要る効果と自動で完結する効果の区別（§5.2） |
| T-009（UI・アセット） | §6 のライブラリの採否の最終決定。英語テキストの出し方（§7.1）。スプライトの生成物をコミットする運用（§9）。スマートフォンのカード拡大の教訓（§6.2）。minify した JS の確認 |

### 12. 作者側の準備事項

| 項目 | 内容 | 時期 |
| --- | --- | --- |
| Studio の初期ファイルの取得 | Studio の `irevolvethereforeiam` フォルダの内容をダウンロードし、名前空間の大文字小文字、サンプルの状態クラス名と ID、非推奨ファイルの有無を確認する。姉妹では作者の作業（または作者の許可を得たエージェントの読み取り）で取得した（姉妹 MJ 003 未確定事項 B の No.3、姉妹 PE 006 §7）。リポジトリの外に置く（ライセンス不明のスタブを含むため） | 済（2026-10-20。メインエージェントが SFTP で読み取り取得し、§4.1 に反映） |
| SSH 鍵の登録・GitHub の Secrets / Variables | 姉妹と同じ鍵（`~/.ssh/bga_studio_rsa`、ユーザー `ZoneTsuyoshi`）で `/irevolvethereforeiam` に SFTP 接続できることを確認済み（2026-10-20）。GitHub の Secrets / Variables の登録は 005 で決める | T-005 の導入時 |
| BGG_ID | 承認後にエージェントへ伝える | 承認後 |
| BGA のライセンス確認 | 作者が権利者であることを BGA に示す（Pre-release checklist）。`docs/tasks.md` に既出 | アルファ申請まで |

### 13. 姉妹プロジェクトの実装から取り込んだ教訓

| 教訓 | 出典 | 反映先 |
| --- | --- | --- |
| フレームワークの既存メソッドと衝突する独自メソッドは Studio でしか分からない | 姉妹 MJ #34 | §4.3（`ir` 接頭辞と検査） |
| 名前空間の大文字小文字は Studio の雛形で決まる。実装後に直すと全面置換になる | 姉妹 PE #35 | §4.1 |
| 雛形の状態クラスが Studio に残ると ID が衝突して Express start が失敗する | 姉妹 MJ #35 | §3、§11 |
| 空配列のアクション引数が未指定になって落ちる | 姉妹 MJ #44 | §4.4 |
| multiactive の UI は `onEnteringState` ではなく `onPlayerActivationChange` で作る | 姉妹 MJ #41 | §5.2 |
| 1 人が複数の保留を持つと上書きで失われる | 姉妹 MJ #41 | §5.2 |
| 非アクティブな人のアクションには、サーバー（`CheckAction(false)`）とクライアント（`checkAction: false`）の両方が要る | 姉妹 MJ `Game.js`・`PrepChoice.php` | §5.2 |
| bga-cards のカードと山札の大きさは CSS 変数の上書きが必要（`:root` に定義、`!important`、読み込み順に頼らない） | 姉妹 MJ #36・#40・#43 | §6.2 |
| カードの拡大表示は `body` 直下、`window.innerHeight` 基準、ステータスバーを隠さない | 姉妹 MJ #39・#52 | §6.2 |
| 選択状態のクラスは離脱時に必ず掃除する | 姉妹 MJ #46 | §6.2 |
| 単一の `Game.js` は 2,800 行になる | 姉妹 MJ、姉妹 PE 004 §4 | §6.1 |
| JS のテストを生成物に依存させない | 姉妹 PE #41 | §6.1 |
| 非公開情報はカード番号だけでなく ID の採番からも漏れうる（姉妹は裏向きのカードの ID を送らなかった） | 姉妹 MJ 009 | §5.4 |

### 14. 公式ドキュメントと姉妹の仕様書の食い違い・更新

2026-10-20 に公式ドキュメントを再確認した結果。食い違いがあれば公式を正とする。

- **画像の上限**: Checklist は「1 枚 4 MB / 総量 15 MB」、Img は「1 枚 2 MB / 十数個」。姉妹 PE 004 §9 と同じ食い違いが残っている。厳しいほうを採る。
- **`gameinfos` のフィールド**: `estimated_duration`、`bgg_id`、`db_undo_support`、`game_interface_width`、`favorite_colors_support`、`is_coop`、`solo_mode_ranked`、`exception_on_warning` は GameInfos ページに説明がない（姉妹 PE と同じ）。`designer` など 7 項目は Game Metadata Manager に移った。
- **zombie**: Zombie ページは状態クラスの `zombie(int $playerId, array $args)` を示し、Pre-release checklist は旧来の `zombieTurn()` を挙げる。状態クラス方式では `zombie` を実装すればよい（StateDir は非 GAME 状態で必須と明記）。姉妹の実装（`zombie(int $playerId)`）と矛盾しない。
- **通知**: 姉妹の仕様書は `notify->all` と `notify->player` の使い分けだけを書いていたが、GamePhp には `notify->all` の `_private`（本人向けの内容と引数を 1 通知にまとめられる）と `NotificationMessage`、`alwaysMergePrivate()` がある。本仕様書は §5.4 でこれを選択肢に加えた。
- **JS の分割**: 姉妹 MJ 003 は「複数ファイルに分けられるか記述なし」としていたが、GameJs にはその方法（`export class` ＋ 先頭の `import`）が書かれている。姉妹 PE が既に反映。minify 時の動作は未確認のまま。
- **TypeScript**: Studio ページは対応を明言せず、TsScss が公式の方法。TS のソースを `src/ts`（プロジェクトの `src/`）に置く前提なので、本リポジトリの `src/` と 1 対 1 の方針とは両立しない。素の JS を継続する理由はここにある（姉妹と同じ）。
- **スプライトの扱い（姉妹との方針の違い）**: 姉妹 MJ・PE は生成物をコミットしないが、本リポジトリは CLAUDE.md により生成物をコミットする（§9）。公式の食い違いではなく、リポジトリの運用の違い。
- 変わっていない点: PHP 8.4、現行ファイル構成と非推奨ファイルの一覧、Deck / Stock の非推奨と ItemManager、オプションの ID 範囲、`bga_rand`、`gamestartonly` の禁止、multiactive での undo 不可。

## エッジケース

- Studio の雛形が想定と違う（名前空間が小文字、非推奨ファイルが含まれる、状態の ID が異なる）場合は、雛形の実際の内容に合わせて §3・§4.1 を修正し、機械的な置換で揃える（姉妹 PE #35）。
- 想起で、ターンプレイヤー以外の発動者がいない（同じ部屋にだれもいない）場合は、割り込みの状態に入らずに通常の手番に戻る。
- 割り込みの途中で発動者が離脱（zombie）した場合は、その人の想起効果を「スキップ」として扱い、次の人へ進む。ターンプレイヤー自身が離脱した場合の割り込みの扱いは 006 で決める。
- 割り込みの途中で即時勝利（刹那の追憶・断片の結集・心理の極致）が成立した場合、その時点で終了する。ターンプレイヤー以外の発動で成立する場合を含む（001 が決める。複数が同時に満たした場合の扱いも 001）。
- レベル 2 の想い出カードの山札切れによる全員敗北（rule-questions Q-24。ルールは 001）は、BGA の順位では全員が同点になる。全員 0 点の同順位とし、ログと終了メッセージで明示する（rule-questions Q-25、未確定事項 No.4）。
- 名前空間・オプション ID・統計 ID・状態 ID は、一度公開したら変えない。
- JSON 設定が不正だと Studio の再読込で初めて分かる。004 の JSONC 検査で防ぐ。
- Studio の初期ファイルに非推奨ファイルが含まれていた場合は、状態クラスと二重定義にならないよう、初回同期の前に確認する。

## 未確定事項

ルールブックや公式ドキュメントから判断できなかった点。推測で確定させず、作者への質問として列挙する。

| No. | 質問 | 暫定案 | 回答 |
| --- | --- | --- | --- |
| 1 | Studio の初期ファイル（作者の作業）に含まれる名前空間の大文字小文字、クラス名、状態クラス名と ID、非推奨ファイルの有無。取得して確認してよいか | 名前空間は小文字の `Bga\Games\irevolvethereforeiam`。初期ファイルを取得して確認し、違えば本仕様書を直す。確定まで PHP クラスは書かない | 解決（2026-10-20。メインエージェントが SFTP で読み取り取得。名前空間は `Bga\Games\IRevolveThereforeIAm`、状態は 10・90・98、非推奨ファイルなし） |
| 2 | 想い出カードなどの画像には日本語のカード名・文が印刷されている。BGA（英語の UI）での表示をどうするか。案 1: 画像をそのまま使い、英語のカード名・効果文を HTML のツールチップ・拡大表示・ログで補う。案 2: 画像のテキスト部分を切り取り、HTML で重ねる。案 3: 日本語のまま。英語の印刷版（英語のカード画像）はあるか | 案 1（画像は無加工）。英語のテキストは 002 で保持する。英語版の画像があれば差し替えを検討 | 案 1 で確定（2026-10-20、Q-16）。英語版のカード画像は存在しない。画像は加工せずに使い、英語のカード名・効果文は 002 のデータからツールチップ・拡大表示・ログで補う |
| 3 | `modules/js/` の相対 `import` が minify した JS でも動くか（公式に記述がない） | 最初の UI の実装 PR で Studio の「Use minified JS」で確認する。だめなら Rollup で 1 本にバンドル（TsScss 方式）に切り替える | 暫定案のとおり（2026-10-20、Q-37）。最初の UI 実装 PR で確認する |
| 4 | 全員敗北（レベル 2 の想い出カードの山札切れなど）と BGA の順位の表現。BGA は全員同点だと全員を同順位（共同 1 位）に表示する | 全員 0 点・同順位とし、ログと終了時のメッセージで「全員の敗北」を明示する。ほかに良い表現があれば 006・007 で再検討 | 了承（2026-10-20、Q-25）。全員 0 点の同順位、ログと終了メッセージで明示。レベル 2 の想い出カードの山札切れも全員敗北（Q-24） |
| 5 | 推奨人数（`suggest_player_number`）はあるか。ルールブックに記載がない。また、プレイヤーの好みの色（`favorite_colors_support`）を使うか（駒の画像は 4 色固定） | `suggest_player_number` は `null`。`favorite_colors_support` は `false`、`player_colors` は駒の 4 色 | 暫定案のとおり（2026-10-20、Q-37） |
| 6 | 独自メソッドの接頭辞を `ir` にしてよいか | `ir`（§4.3）。ほかの候補は `irt`・`iri`・`rtia`・`ita` | `ir` で確定（2026-10-20、Q-37） |
| 7 | 床カードの配置オプション（ID 順に固定 / ランダム）を作るか。ルール上の決め方は 001 が決める | 001 に従う。001 が配置を固定するなら、オプションは作らない | 作る（2026-10-20、Q-17）。ゲームオプション「回廊の配置」（標準＝ID 順を既定 / ランダム）。001 で確定 |
| 8 | bga-zoom の採否と回廊の最小幅（`game_interface_width.min`） | 採用候補。`740` のまま。009 で最終決定 | 暫定案のとおり（2026-10-20、Q-37） |
| 9 | 想起フェイズの割り込みを「順番に処理（ACTIVE_PLAYER＋`changeActivePlayer`）」とすることに、ルール上の支障はないか（同時に決めるべき効果が無いか。001 と全カードの確認が必要） | 順番に処理。001・002 の確定後に 006 で全カードの効果を確認し、同時選択が必要な効果が見つかれば、その状態だけ MULTIPLE_ACTIVE_PLAYER にする | 暫定案のとおり（2026-10-20、Q-37） |
| 10 | スプライトなどの生成物をコミットする方針（姉妹は CI で生成してコミットしない）。リポジトリの容量が増えることを許容するか | コミットする。生成は決定的にし、原本が変わったときだけ再生成する | 暫定案のとおり（2026-10-20、Q-37） |

## 受け入れ条件

- [ ] 各【確認】の記述に公式ドキュメントの URL が添えられている
- [ ] 確認できなかった事項が【未確認】として「未確定事項」に挙がっている
- [ ] `src/` の構成が BGA 側のパス `/irevolvethereforeiam/` と 1 対 1 で対応している
- [ ] 状態クラス方式の採否、PHP のバージョン、Deck を使わない方針、モジュール分割が書かれている
- [ ] 独自メソッドの接頭辞が候補の比較とともに 1 つに決まっている
- [ ] クライアントの言語（JS ＋ JSDoc）、ビルドの要否、公式ライブラリの採否、CSS 方針が書かれている
- [ ] 翻訳の使い分け、非公開情報の原則、想起フェイズの割り込みの方式が書かれている
- [ ] ゲームオプション・プレイヤー設定の候補と、`gameinfos` の主な値が書かれている
- [ ] 生成物のコミット方針、テスト方針、004・005 への申し送り、作者側の準備事項が書かれている
- [ ] 姉妹プロジェクトから取り込んだ教訓と、公式ドキュメントとの食い違いが書かれている
- [ ] 「未確定事項」に作者が回答し、回答に合わせて更新されたうえでステータスが Approved になる
