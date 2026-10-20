# 007: データモデル・通知の設計

| 項目 | 内容 |
| --- | --- |
| ステータス | Approved |
| 対応タスク | T-007 |
| 作成日 | 2026-10-20 |
| 関連仕様書 | 001（ゲームルール）、002（カードデータ）、003（BGA プロジェクト構成・技術方針）、006（状態遷移）、009（UI・アセット）。並行: 008（カード効果の実装方針） |

## 目的

DB スキーマ（`dbmodel.sql`）、グローバル値、プレイヤー値、カードの所在の表現、非公開情報の守り方、`getAllDatas` の内容、通知の一覧、統計、セットアップのデータ生成を決める。実装者がこの仕様書と 006 だけで、データ層（`Data/`）、セットアップ（`Setup/`）、通知（`Flow/Notification`）を書けるようにする。006 が「各所の 007 への申し送り」として残した項目（効果解決スタックの保存形式、保留中の選択、ターン内カウンタ、終局情報、進行度の最大値など）と、009 §17 の gamedatas 要件をすべて受ける。

## スコープ

- 対象: テーブル定義とインデックス、カード ID の採番、カードの所在（location）の体系、トークン・部屋・ミープル・精神トラック・自我・目標の持ち方、グローバル値とプレイヤー値、`TurnContext`（ターン内の入れ物）、効果解決スタックの保存形式、公開・非公開の区分、`getAllDatas`、通知の一覧と引数とログ文言（英語原文）、翻訳の扱い、セットアップのデータ生成、整合性の制約と `Data/` 層の構成、統計の候補
- 対象外: 状態の一覧と遷移・`getArgs` の中身（→ 006）、個々のカード効果の処理・frame の `kind` ごとのパラメータ・`choice_kind` の完成・トリガーの定義（→ 008。007 は「入れ物」だけを決める。§15 に境界を列挙）、画面・クライアントの描画（→ 009）、CI・同期（→ 004・005）。この仕様書ではコードも設定ファイルも作らない（§3 の SQL は仕様としての案）

## 仕様

### 0. 記法と根拠

- 「001 §N」「002 §N」「003 §N」「006 §N」「009 §N」は各仕様書の節。「Q-NN」は [rule-questions.md](../development/rule-questions.md) の質問番号。
- **【確認】** は BGA 公式ドキュメントで確認した事実（URL を添える）。**【提案】** は本仕様書の提案。**【未確認】** は公式から判断できず、§16「Studio で検証する点」に挙げる事項。**【姉妹】** は姉妹プロジェクトの実績。**【Studio】** は Studio の初期ファイル（2026-10-20 取得）で確認した事実。
- 調査日は 2026-10-20。公式ページは WebFetch（本文を要約して返すツール）で取得したため、要約を介した確認である。Game.php のページは長く、末尾の約 28,000 文字は要約が読み切れていない。

| 略称 | URL |
| --- | --- |
| DbModel | <https://en.doc.boardgamearena.com/Game_database_model:_dbmodel.sql> |
| GamePhp | <https://en.doc.boardgamearena.com/Main_game_logic:_Game.php> |
| Stats | <https://en.doc.boardgamearena.com/Game_statistics:_stats.json> |
| StateDir | <https://en.doc.boardgamearena.com/State_classes:_State_directory> |
| Translations | <https://en.doc.boardgamearena.com/Translations> |
| 姉妹 PE 010 | `../project-edgeworth-bga/docs/specs/010-data-model.md` と `src/dbmodel.sql`、`src/modules/php/Data/` |
| 姉妹 MJ 007 | `../minimum-journey-bga/docs/specs/007-data-model.md` と実装 |

### 1. BGA の仕組みの確認結果

| No. | 事実 | 出典 |
| --- | --- | --- |
| D1 | `dbmodel.sql` は `CREATE TABLE IF NOT EXISTS`・`ENGINE=InnoDB DEFAULT CHARSET=utf8mb4` を使い、全テーブルに主キーが要る。コードと同じ行にコメントを書かない（行全体が無効になりうる）。動的なデータだけを持ち、静的な性質は PHP のマスターデータに置く。文字列の翻訳対象は数値の識別子で持つ | DbModel |
| D2 | `player` テーブルには `ALTER TABLE` で列を足してよい。フレームワークが作った列は変えない。`global`・`stats`・`gamelog` と `bga_` で始まるテーブルは変更も直接アクセスもしない | DbModel |
| D3 | 列名はテーブル名と同じにしない（リプレイ機能が正規表現で置換するため）。ゲーム中にスキーマは変えられない。リリース後の変更は `upgradeTableDb()` で行う。DB 全体は 64 MB 未満に保つ | DbModel |
| D4 | `$this->bga->globals` に `set(name, value)` / `get(name, default, class)` / `getAll(...names)` / `delete` / `has` / `inc(name, delta)`。値は JSON で保存され、キーは 50 文字以内。関数と循環参照は保存できない | GamePhp |
| D5 | `getAllDatas` は連想配列を返す。キー `players` はプレイヤーテーブル用に予約されている。引数は魔法の引数 `int $currentPlayerId`（または `$currentPlayerNo`）だけ | GamePhp |
| D6 | `notify->all(type, message, args)` は全員と観戦者へ、`notify->player(playerId, type, message, args)` は卓の 1 人へ（観戦者には送れない）。`notify->all` の args の `_private`（プレイヤー ID をキーにした連想配列）は、その本人にだけ別の args（または `NotificationMessage`）を渡す。`_merge_private` を併用すると `args` に直接マージされる。通知はアクションの終わりにまとめて送られ、例外で破棄される。合計 128 KB まで。翻訳対象の引数は `i18n` に列挙する | GamePhp |
| D7 | `playerScore` / `playerScoreAux` は `PlayerCounter` で、`inc($playerId, $delta)`・`set($playerId, $value)` を持つ | GamePhp |
| D8 | 統計は `stats.jsonc` の `table` / `player`。型は `int` / `float` / `bool`。ID は 10 以上で各節の中で一意、公開後は変更も再利用もしない。名前は英数字。表示順は配列の順。`value_labels` で数値をラベルに置き換えられる。`setupNewGame` で `init` する（未初期化は「-」）。（テーブル統計 + プレイヤー統計 × 人数）は約 930 以下 | Stats |
| D9 | 乱数は `bga_rand(min, max)` または `random_int()`。`rand()` / `mt_rand()` / SQL の `RAND()` は使わない。DB 操作は `DbQuery` / `getCollectionFromDB` などで、プレイヤー由来の文字列は `escapeStringForDB()` で包む。変更はリクエストの終わりにトランザクションとして確定する | GamePhp（003 §4.4） |
| D10 | ACTIVE_PLAYER 状態の `getArgs` の戻り値は `_private` の外が全員に公開される。`getArgs` では DB を書かない | StateDir（006 B7） |
| D11 | 【Studio】初期ファイルの `Game.php` は `$this->bga->counterFactory->createPlayerCounter('energy')`・`initDb`・`fillResult` を使う例を持つ。通知の `addDecorator` で `player_name`・`card_name` を自動補完する例もある | Studio 初期ファイル |

#### 1.1 【未確認】のまま設計に影響する点

| No. | 事項 | 設計上の扱い |
| --- | --- | --- |
| U-1 | `PlayerCounter` / `TableCounter` のページ（`counterFactory`）は公式ドキュメントの URL を特定できず、API を確認できていない | 使わない（§7.1 T-3）。スコアの `playerScore` だけ D7 の範囲で使う |
| U-2 | `notify->all` の `_private` が観戦者・リプレイでどう扱われるか。姉妹 PE は未確認のため使わなかった | 本書は 003 §5.4 の方針（1 通知にまとめる）に従い、カードを引く系の通知だけに使う。V-1 で検証し、だめなら `notify->all` + `notify->player` の 2 通知に分ける（§10.1） |
| U-3 | `CHECK` 制約が Studio の DB で効くか | 依存しない。主キー・`UNIQUE` と `Data/` 層の検査で守る（§12） |
| U-4 | `getAllDatas` のキー `players` に `player` の追加列が含まれるか | 追加列は `players` に頼らず、別キー `playerData` で返す（§8） |
| U-5 | `player` テーブルへの索引追加（`ALTER TABLE ... ADD KEY`）が許されるか | `player` には列だけを足す（D2）。索引は新規テーブルにだけ張る |
| U-6 | BGA が卓の席順（`player_no`）を開始時にランダム化するか | ランダム化されると仮定して `player_no` をそのまま手番順にする（§7.1 T-8）。V-7 で確認 |

### 2. 永続データの全体像

| 分類 | データ | 保存先 | 公開範囲 |
| --- | --- | --- | --- |
| 想い出カード 144 枚 | 種類（M 番号）、所在、順序、横向き、表向き | `memory_card` | 所在ごと（§4.2） |
| 部屋 12 | 位置 → 床カード（位置がレベルを決める） | `room` | 公開 |
| 精神カード 7 枚 | 階層、並び、心光トークンの有無 | `spirit_card` | 公開 |
| 自我カード 13 枚 | 初期自我 8・強化自我 5 の所在と持ち主 | `ego_card` | 所在ごと（§5.3） |
| 目標カード 7 枚 | 場に並ぶ枠、持ち主 | `objective_card` | 公開 |
| 効果解決スタック | frame の列、保留中の選択（frame の状態） | `effect_frame` | サーバー内部。args・通知で必要分だけ |
| プレイヤーごとの値 | 人格の位置、精神カード、トークン 5 種 | `player` の追加列 | 公開（トークンの個数も公開） |
| ゲーム全体の値 | ターンプレイヤー、ターン番号、フェイズ、戻り先、終局情報、進行度、`TurnContext` | `bga_globals`（`$this->bga->globals`） | サーバー内部。公開する値だけ args・通知・`getAllDatas` |
| 得点 | `player_score`（勝者 1、他 0。終局時のみ書く） | `player`（標準列） | 公開 |
| 派生値（保存しない） | 自己構成スコア、各山の枚数、手札の枚数、鍵の本数、精神階層、目標の現在値 | 必要なときに計算 | §7.4 |

保存しない（派生値にする）理由は二重管理を避けるため。例: 自己構成スコアは自己構成済みカード・目標・I08 の下のカードから計算する唯一の関数 `ScoreCalculator` を正とし、列に複製しない。

### 3. `dbmodel.sql`【提案】

テーブルは 6 つ（`memory_card`、`room`、`spirit_card`、`ego_card`、`objective_card`、`effect_frame`）と `player` の追加列。列名は D3 のため、テーブルごとに接頭辞を付ける。静的な定義（コスト、鍵、スコア、効果、裏面の錠前）は持たず、`Material/`（002）を `card_type` などで引く。

```sql
CREATE TABLE IF NOT EXISTS `memory_card` (
  `card_id` SMALLINT UNSIGNED NOT NULL,
  `card_type` SMALLINT UNSIGNED NOT NULL,
  `card_loc` VARCHAR(12) NOT NULL,
  `card_loc_arg` INT NOT NULL DEFAULT 0,
  `card_pos` INT NOT NULL DEFAULT 0,
  `card_tapped` TINYINT(1) NOT NULL DEFAULT 0,
  `card_face_up` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`card_id`),
  KEY `idx_card_loc` (`card_loc`, `card_loc_arg`, `card_pos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `room` (
  `room_pos` TINYINT UNSIGNED NOT NULL,
  `room_floor` TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (`room_pos`),
  UNIQUE KEY `uq_room_floor` (`room_floor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `spirit_card` (
  `spirit_id` VARCHAR(3) NOT NULL,
  `spirit_tier` TINYINT UNSIGNED NOT NULL,
  `spirit_slot` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `spirit_light` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`spirit_id`),
  UNIQUE KEY `uq_spirit_place` (`spirit_tier`, `spirit_slot`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ego_card` (
  `ego_id` VARCHAR(3) NOT NULL,
  `ego_loc` VARCHAR(10) NOT NULL,
  `ego_owner` INT UNSIGNED NULL DEFAULT NULL,
  `ego_pos` SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`ego_id`),
  KEY `idx_ego_loc` (`ego_loc`, `ego_owner`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `objective_card` (
  `obj_id` TINYINT UNSIGNED NOT NULL,
  `obj_state` VARCHAR(6) NOT NULL,
  `obj_slot` TINYINT UNSIGNED NULL DEFAULT NULL,
  `obj_owner` INT UNSIGNED NULL DEFAULT NULL,
  `obj_claim_turn` INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`obj_id`),
  KEY `idx_obj_owner` (`obj_owner`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `effect_frame` (
  `frame_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `frame_order` INT NOT NULL,
  `frame_parent` INT UNSIGNED NULL DEFAULT NULL,
  `frame_kind` VARCHAR(24) NOT NULL,
  `frame_owner` INT UNSIGNED NOT NULL,
  `frame_source` VARCHAR(8) NULL DEFAULT NULL,
  `frame_spec` VARCHAR(12) NULL DEFAULT NULL,
  `frame_op` VARCHAR(24) NULL DEFAULT NULL,
  `frame_step` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `frame_group` INT UNSIGNED NULL DEFAULT NULL,
  `frame_origin` VARCHAR(12) NOT NULL DEFAULT '',
  `frame_unit` TINYINT(1) NOT NULL DEFAULT 0,
  `frame_cancel_to` SMALLINT UNSIGNED NULL DEFAULT NULL,
  `frame_status` VARCHAR(7) NOT NULL DEFAULT 'READY',
  `frame_params` TEXT NULL DEFAULT NULL,
  `frame_payload` TEXT NULL DEFAULT NULL,
  `frame_choice` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`frame_id`),
  KEY `idx_frame_order` (`frame_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

ALTER TABLE `player` ADD `player_persona` TINYINT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE `player` ADD `player_spirit` VARCHAR(3) NULL DEFAULT NULL;
ALTER TABLE `player` ADD `player_light` INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE `player` ADD `player_wild_up` INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE `player` ADD `player_wild_used` INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE `player` ADD `player_compose_tok` INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE `player` ADD `player_analysis_tok` INT UNSIGNED NOT NULL DEFAULT 0;
```

- **カードを 1 テーブルにまとめない理由**【提案 T-1】: 想い出カードは 144 の個体を区別し、所在・順序・向きを頻繁に動かす。他の種類（床・精神・自我・目標）は枚数が 4〜13 で、種類ごとに状態の性質が違う（床は位置、精神は心光の有無、自我は秘密の所在、目標は枠と持ち主）。共通の `card` 表にすると使わない列が増え、公開範囲の規則も混ざる。003 §4.5 の「同じ表か別の小さな表かは 007 で決める」に対する決定。
- **Deck / ItemManager を使わない**: 003 §4.5 のとおり。DB へのアクセスは `Database` インターフェース（BGA 実装と PHPUnit 用 PDO 実装）の裏に隠す。
- **`room_floor` と `spirit` / `ego` / `objective` の識別子**: 床は 1〜12（002 §7 の `F01`〜`F12`）、精神は `R1A` のような 3 文字（`R2`・`R4`・`R5` は 2 文字）、自我は `I01`・`E01`、目標は 1〜7（`O001` の数字部分）を DB に保存する。
- **`player_*` の追加列は 7 列**。`player_score` は標準列で、終局時だけ書く（§7.1）。

### 4. 想い出カード（`memory_card`）

#### 4.1 列

| 列 | 意味 |
| --- | --- |
| `card_id` | DB 上の個体 ID。**推測できない採番**（§4.3）。クライアントにも見える表示用 ID を兼ねる |
| `card_type` | 種類 = 002 の M 番号の数値部分（1〜99）。同じ種類の複数枚（`n_card` が 2〜4）は同じ値を持つ。**非公開のカードの `card_type` は、持ち主以外にも、ID と対にして送らない** |
| `card_loc`, `card_loc_arg` | 所在と所在の引数（§4.2） |
| `card_pos` | 所在内の順序（§4.4） |
| `card_tapped` | 横向き（使用済み）。`TABLEAU` のカードだけが 1 になりうる（001 §6-1、§13 a） |
| `card_face_up` | 表向き。`ROOM` の想起で公開した間だけ 1（001 §7）。他の所在では 0 |

#### 4.2 所在の一覧（`card_loc`）

| `card_loc` | `card_loc_arg` | `card_pos` | 公開範囲 | 備考 |
| --- | --- | --- | --- | --- |
| `DECK1` | 0 | 山の順序（昇順。最小が一番上） | 枚数と一番上の**裏面のキー**のみ（Q-44） | レベル 1 の山札。想い出の山札 |
| `DECK2` | 0 | 同上 | 同上 | レベル 2 の山札 |
| `DISC1` | 0 | 捨てた順（昇順。最大が一番上） | **全員に ID と種類を公開** | レベル 1 の捨札（001 §11-3、Q-23） |
| `DISC2` | 0 | 同上 | 同上 | レベル 2 の捨札 |
| `OUT` | 0 | 0 | 非公開（誰も見ない） | セットアップで除外されたカード（2 人 52 枚、3 人 26 枚。001 §2-2）。枚数も送らない |
| `OPEN` | 場札の枠（0〜5） | 0 | 公開（ID と種類） | 想い出の場札。取られた枠は行が無く、空き枠になる（009 §3.2） |
| `ROOM` | 部屋の位置（1〜12） | 0 | 裏向き: ID と裏面のキーのみ。`card_face_up = 1` の間は ID と種類 | 部屋の想い出カード。部屋あたり 0〜1 枚（補充の途中は 0 枚） |
| `HAND` | 持ち主のプレイヤー ID | 0 | 本人: ID と種類。他人・観戦者: 枚数のみ | 手札 |
| `TABLEAU` | 持ち主のプレイヤー ID | 自己構成した順（1 から） | 公開（ID と種類と横向き） | 自己構成済みエリア。「N 枚目」は I01 が数える |
| `UNDER_FLOOR` | **部屋の位置**（1〜12） | 置いた順 | 公開（確定。008 R8-15、Q-64） | I04（論理学者）の「床カードの下」。**位置に付ける**ので床の入れ替え（M055〜M057）では動かない（008 R8-11(b)・Q-60 で確定。R-1・R-2） |
| `UNDER_CARD` | 親の `card_id`（自己構成済みカード） | 置いた順 | 持ち主: ID と種類。他人: 枚数のみ（確定。R-3） | I08（主人公）の「自己構成したカードの下に裏向き」。1 枚につき自己構成スコア +1。親が場を離れたとき（I03 で捨札へ、`SWAP_SAME_COST` で手札へ）は下のカードを各レベルの捨札へ移す（008 R8-10(d)・R8-17(c)、Q-59・Q-66 で確定。R-3） |

- 所在はこの 11 種で完結する。008 が新たな置き場を必要とするとき（例: 効果で一時的に脇に置く）は、007 に所在を追加する（`card_loc` は `VARCHAR(12)` で足りる）。
- **山札の順序の規約**: 引くのは `ORDER BY card_pos ASC LIMIT n`。山札に戻す操作は無い（001 §14-2、Q-23 で捨札を戻さない）。捨札の一番上は `card_pos` の最大値。`card_pos` は所在ごとに `MAX + 1` で採番する（`MemoryCardRepository::nextPos`）。
- **手札の順序は持たない**。並び順はクライアントが決める（009 §3.10）。

#### 4.3 ID の採番【提案 T-4】

003 §5.4: 「DB の ID をカード番号順に採番しない」。

1. 144 枚の種類リスト（002 の `n_card` を展開、例: `[1, 1, 1, 2, ...]`）を作る。
2. `random_int` ベースの Fisher-Yates（003 §4.4）でシャッフルし、先頭から `card_id = 1, 2, ..., 144` を振る。これで `card_id` と `card_type` の対応は卓ごとにランダムで、ID から種類は推測できない。
3. 山札の順序 `card_pos` は ID とは別に、レベルごとにもう一度シャッフルして割り当てる（ID の大小が山の順序と相関しない）。
4. 値の範囲は 1〜144（`SMALLINT UNSIGNED`）。既知の種類の枚数など公開情報以外の手がかりは、ID にも `card_pos` にも含まれない。

この対応表（`card_id` → `card_type`）が漏れる経路は §9 で網羅する。**通知・`getAllDatas`・`getArgs` に、非公開のカードの `card_id` と `card_type` を同時に載せない**ことが原則で、載せるときは「そのカードがその受信者に公開された瞬間」だけ。

#### 4.4 データアクセス `MemoryCardRepository`【提案】

| メソッド | 内容 |
| --- | --- |
| `create(array $cards)` | セットアップで 144 行を作る |
| `topBack(int $level): ?string` | 山の一番上の裏面のキー（`1B` など）。空なら null。**山の一番上の `card_type` は返さずに、`Material` の `back` を返す** |
| `drawFromDeck(int $level, int $n): list<int>` | 引けるだけ引く。足りなければ引けた分を返す（山札切れは呼び出し側が判定。001 §14-2） |
| `move(int $cardId, string $loc, int $arg = 0)` | 所在の変更。`card_pos` は所在の規約に従って採番。`TABLEAU` 以外へ移すと `card_tapped = 0`、`ROOM` 以外へ移すと `card_face_up = 0` |
| `inLoc(string $loc, int $arg): list<MemoryCard>` | 所在の一覧。公開範囲の判定は呼ばず、`PublicView` が行う |
| `count(string $loc, int $arg = 0): int` | 枚数。手札の枚数などの公開情報 |
| `setTapped(list<int> $ids, bool $tapped)` | 横向きの更新 |
| `setFaceUp(int $cardId, bool $up)` | 想起の公開 |
| `swapRoomCards(int $posA, int $posB)` | `ROOM` の 2 枚の `card_loc_arg` を入れ替える（M052〜M054。人格のいる部屋も可）。券面は誰も見ない |
| `swapTableauHand(int $tableauId, int $handId)` | `SWAP_SAME_COST`（OP-09）。手札から出るカードは、交換相手の `card_pos` と `card_tapped` を引き継ぐ（008 R8-10(b)）。出ていくカードは手札へ（`card_tapped = 0`）。下のカードは `releaseUnder` |
| `releaseUnder(int $parentId)` | 親の `UNDER_CARD` を各レベルの捨札へ移す（`card_pos` は捨てた順）。移したカードは全員に公開される（N15 `UNDER_RELEASED`） |

### 5. 部屋・精神トラック・自我・目標

#### 5.1 部屋（`room`）

- 12 行。`room_pos` は巡り順の位置 1〜12（001 §3）、`room_floor` は床カード ID。位置 1・4・7・10 がレベル 2 の位置（四隅）で、レベル 2 の床カード（F01・F04・F07・F10）が入る。位置のレベルは固定で、床の入れ替え（M055〜M057）はレベル 1 の位置同士でだけ起きる（002 §6.4）ので、床のレベルと位置のレベルは常に一致する。
- ゲームオプション 100「回廊の配置」: 値 1（ID 順、既定）は `room_pos = room_floor`。値 2（ランダム）は、レベル 2 の床 4 枚を位置 1・4・7・10 にシャッフルして置き、レベル 1 の床 8 枚を残りの 8 位置にシャッフルして置く（001 §4 手順 1、Q-17）。
- **部屋の想い出カードは「位置」に付ける**（`memory_card.card_loc = 'ROOM'`, `card_loc_arg = 位置`）。床の入れ替え（M055〜M057）で床だけが動き、裏向きのカードは位置に残る。カードが床に付いて動くのかは**位置に残る**（008 R8-11(b)・Q-60 で確定。R-1）。008 の確定した規定は「床だけが入れ替わり、想い出カード・床の下のカード（`UNDER_FLOOR`）・人格は位置に残る」で、本書もそれに合わせる。DB の操作は `room_floor` の入れ替えだけで済む。`room_floor` は `UNIQUE` なので、入れ替えは一時値 0 を挟む 3 手順で行う。M052〜M054 の想い出カードの入れ替えは `memory_card.card_loc_arg` の入れ替え（`swapRoomCards`）。
- 人格（ミープル）の位置は `player_persona`（0 = 未配置、1〜12）。同じ位置に複数人がいてよい。
- 周遊ボーナスのトークンの山は持たない（上限なし。001 U-3、Q-19、009 §3.8）。

#### 5.2 精神トラック（`spirit_card`、`player_spirit`）

- セットアップで使う 7 枚だけを行にする（001 §4 手順 5）。第一階層 a〜c のうち 2 枚、第三階層 a〜c のうち 2 枚、第二・四・五階層の各 1 枚。使わない 2 枚は行を作らず、公開もしない（誰も見ない）。
- `spirit_tier` は 1〜5、`spirit_slot` は同じ階層の並び（0 または 1。第一・第三階層が 2 枚）。`UNIQUE (tier, slot)`。表示の並びは (tier, slot) の昇順で固定し、a〜c のどれが使われているかは `spirit_id` で分かる。
- `spirit_light` は精神カード上の心光トークンの有無（第一〜第四階層は 1、第五階層は 0。001 §4 手順 6）。先に進んだプレイヤーが獲得すると 0 になり、戻らない（001 §10-3、Q-27）。**到着時の有無の保存**: 階層を上げる処理は、`spirit_light` を 0 にする前に読んで `dest_had_light` として EV-06 の `frame_payload` に残す（M058 の条件は心光の獲得の後に評価されるため。008 CD-09）。N24 の `light_taken` と同じ値。
- 各プレイヤーの位置は `player_spirit`（NULL = 階層 0 の開始位置）。**キューブの位置はこの列だけ**で、`spirit_card` に持たない。精神階層は `player_spirit` の `spirit_tier`（NULL なら 0）で、列に複製しない。

#### 5.3 自我カード（`ego_card`）

13 行（初期自我 `I01`〜`I08`、強化自我 `E01`〜`E05`）。`ego_loc`:

| `ego_loc` | `ego_owner` | 意味 | 公開範囲 |
| --- | --- | --- | --- |
| `OUT` | NULL | ゲームから除外（初期自我のうち抜き出されなかった分と、ドラフトの最後の 1 枚。強化自我のうち抜き出されなかった分） | 非公開（枚数も送らない） |
| `PACK` | NULL | 初期自我のドラフトで回している山（人数 + 1 枚から選ばれた残り）。`ego_pos` は表示順 | **現在の選択者にだけ**中身を見せる（`getArgs` の `_private`。006 の 21） |
| `PICKED` | 選んだプレイヤー | ドラフトで選ばれ、まだ公開されていない初期自我 | 本人だけ（全員が選び終えるまで） |
| `ENH_DECK` | NULL | 強化自我の山（人数分。第三階層の横） | **枚数のみ**。取るときの候補は本人の `_private` |
| `OWNED` | 獲得したプレイヤー | 公開された初期自我・獲得した強化自我 | 公開 |

- ドラフトの進行は DB から導ける（`PACK` の枚数、`PICKED` の持ち主）。逆順の何番目かは `setup_step`（§7.2）で持つ。
- 強化自我の獲得（001 §10-4、Q-08）は `ENH_DECK` → `OWNED` の更新 1 回。候補は `ENH_DECK` の全行（本人にだけ見せる）。1 人 1 枚、人数分あるので第三階層に着いた全員が取れる。

#### 5.4 目標カード（`objective_card`）

7 行。`obj_state`: `OUT`（使わない）/ `OPEN`（場に並ぶ）/ `OWNED`（獲得済み）。`obj_slot` は共通の場の枠（0 始まり。2・3・4 人で 3・4・5 枠。009 §3.6）。獲得しても枠は空き枠として残る（位置が詰まらない）。`obj_owner` は獲得者、`obj_claim_turn` は獲得したターン番号（統計と整合性の検査用）。

- 目標の「現在の X と得点」は保存しない（毎回計算。§11、Q-13）。

### 6. 効果解決スタック（`effect_frame`）

006 §7 の frame を保存する。列の意味は 006 §7.1 の項目に対応する。frame の `kind` ごとの `params`・`choice` の中身は 008 が決める（007 は入れ物）。

| 列 | 意味 |
| --- | --- |
| `frame_id` | frame の ID（`AUTO_INCREMENT`。連続とは限らない。D3 に準じる） |
| `frame_order` | **実行順**。小さいほうが先に実行される。符号付き整数 |
| `frame_parent` | この frame を積んだ親の `frame_id`。親が消えても子は残る（ログとデバッグ用。外部キーは張らない） |
| `frame_kind` | 006 §7.1 の `TURN_START_EFFECT`、`RECALL_ACTIVATION`、`ACQUIRE_ROOM_CARD`、`MOVE_END_TRIGGER`、`DRAW`、`GAIN_FIELD`、`LIGHT`、`DISCARD`、`SET_TURN_DISCOUNT`、`COMPOSE`、`ANALYZE`、`TRIGGERED`、`ORDER_EFFECTS`、`HAND_LIMIT_CHECK`、`END_CHECK`、および 008 §1.2 が足す `OP`（原子操作 1 つ）と `SPEC_RUN`（`EffectSpec` の操作列の実行）。006 の `DRAW`・`GAIN_FIELD`・`LIGHT`・`DISCARD`・`SET_TURN_DISCOUNT` は `OP` の別名で、保存するときは `frame_kind = 'OP'` + `frame_op` |
| `frame_owner` | 効果の持ち主（選択者）。常にプレイヤー ID |
| `frame_source` | 出典の識別子。`M052`・`F04`・`R3A`・`I05`・`E03` のような 002 の ID。出典が無い frame は NULL。個体の `card_id` は持たない（同名の自己構成は 1 人 1 枚のため、持ち主 + 出典で一意。001 §9-1） |
| `frame_origin` | 008 §6.6 の `FLOOR` / `SPIRIT_CARD` / `TOUR` / `RECALL` / `BONUS` / `EFFECT` / `EXTRA`（獲得の出どころ。M085〜M087・I02 の反応条件）。子の frame に伝播させない（008 T8-9）ので、積むたびに指定する。獲得に関係しない frame は空文字 |
| `frame_unit` | 手札上限を確認する単位の根か（006 §8） |
| `frame_cancel_to` | `actCancel` されたときの戻り先の状態 ID。NULL なら frame を捨てる（006 §5.3） |
| `frame_status` | `READY`（実行待ち）/ `WAITING`（プレイヤーの入力待ち。保留中の選択。008 §10.1 の `WAITING_CHOICE`）。008 の `DONE` は行を削除するので持たない |
| `frame_params` | 種類ごとのパラメータ（JSON）。回数、枚数、割引、割り当て済みの情報など。**公開してはならない値を入れない**ものとし、args・通知には `PublicView` が選んで出す |
| `frame_choice` | `WAITING` のときの選択の定義（JSON）。`choice_kind`、公開できる候補、`skippable` など（006 §7.4）。非公開の候補（強化自我の山の全カードなど）は**ここに保存せず**、状態の `getArgs` が DB（`ego_card`）から再構成して `_private` に入れる |
| `frame_spec` | `EffectSpec` の ID（`M058.1`、`RC-04`、`F12`、`R2` など。008 §2.1 の `spec_id`）。`SPEC_RUN` の frame が持つ。`once_used` のキーにも使う。それ以外は NULL |
| `frame_op` | `OP` frame の原子操作の名前（`GAIN_LIGHT`、`DISCARD_HAND` など。008 §3.1 の `op`）。`OP` 以外は NULL |
| `frame_step` | `SPEC_RUN` の操作列の現在位置（0 始まり。008 の `step`）。`SEQ` / `CHOOSE_ONE` / `REPEAT` の入れ子の位置は 008 が `frame_params` に持たせる |
| `frame_group` | `ORDER_EFFECTS` で同時に誘発した効果の集合の ID（008 の `group_id`）。同じイベントから積んだ frame に同じ値。集合が無ければ NULL。値は積む側が決める（その時点の `MIN(frame_id)` など） |
| `frame_payload` | イベントのペイロード（JSON。008 §4.1）。解決の開始時の条件の再評価に使う。例: EV-06 の `dest_had_light`（到着前の心光の有無。§5.2）、EV-02 の `key_kinds_paid`・`wild_tokens_used`・`entered_count`。`frame_params` と同じく**公開してはならない値を入れない** |

- **保留中の選択は frame 自身が持つ**ので、「1 人が複数の保留を持つ」（姉妹 MJ #41、003 §5.2）は frame が複数あることで自然に満たされる。`WAITING` にできるのは**実行順が先頭の frame だけ**（70 が先頭から処理するため）。後ろの frame は `READY` のまま待つ。
- **積み方**: 実行順の先頭に挿入する。`pushFront(array $frames)` は、現在の `MIN(frame_order)` を m として、列の i 番目（0 始まり、n 個）に `m - n + i` を振る（空のときは 0 から）。これで、列は与えた順に先頭から実行され、再採番は不要。`pushBack` は `MAX + 1` から振る（スタックの底に積むのは、ターン開始時などの初期投入だけ）。
- **取り出し**: `peek()` = `ORDER BY frame_order ASC LIMIT 1`。`complete(frameId)` = 行の削除。`replace(frameId, array $frames)` = その frame を、その位置に新しい列で置き換える（親が子に展開するとき）。
- **戻り先 `resume`** は frame ではなくグローバル値 1 つ（§7.2。入れ子にならない。006 §7.3）。
- **終局**: `GameEnd` に入るとき `clear()` で全行を削除する（006 §9.2）。
- **安全弁**: 1 回の 70 の入場で処理する frame は 500 件まで（006 §7.2）。`effect_frame` の行数が 200 を超えたら例外（暴走の検出。通常は 20 件程度）。
- 再読み込み・再接続: 現在の状態（71 など）は DB のスタックと `resume` から再構成する（006 §7.3）。クライアントは `getAllDatas` ではなく**状態の args** で入力待ちを復元する（§8.3）。
- `choice_kind` は 008 §6.4 の 14 種（006 §7.4 の 13 種 + `PICK_SPIRIT_BRANCH`）。`frame_choice` の中身は 008 が決める。`ORDER_EFFECTS` の候補は同じ `frame_group` の frame から作る。008 §10.1 の項目との対応は §15。

### 7. プレイヤー値・グローバル値・ターン内の入れ物

#### 7.1 `player` の追加列

| 列 | 型 | 意味 | 書く場所 | 公開範囲 |
| --- | --- | --- | --- | --- |
| `player_persona` | 0〜12 | 人格の位置。0 = 未配置（セットアップ 24 より前） | 24、31、70（想起の移動、ワープ） | 公開 |
| `player_spirit` | `R1A` 等 / NULL | 精神カード。NULL = 階層 0 | 62、70（M088） | 公開 |
| `player_light` | ≥ 0 | 心光トークンの数 | 各所 | 公開 |
| `player_wild_up` | ≥ 0 | 万能移動鍵トークン（表。使用可能） | 各所 | 公開 |
| `player_wild_used` | ≥ 0 | 万能移動鍵トークン（裏。使用済み） | 31、80 | 公開 |
| `player_compose_tok` | ≥ 0 | 自己構成トークン | 各所 | 公開 |
| `player_analysis_tok` | ≥ 0 | 精神分析トークン | 各所 | 公開 |

- **手番順は `player_no`（BGA の席順）をそのまま使う**【提案 T-8】。専用の列を作らない。001 §4 手順 12・Q-18 は「スタートプレイヤーはランダム、手番順は席順」。BGA は卓の開始時に席順をランダム化する（【未確認】U-6、V-7）ので、`player_no = 1` を 1 番手（スタートプレイヤー）とすれば Q-18 を満たす。ランダム化されていなかった場合は、`setupNewGame` で `bga_rand` による回転（先頭の人を選んで席順を回す）を別に決める。ドラフトとミープル配置の「逆順」は `player_no` の降順（006 の 20・23）。
- **トークンは 5 つの列**（心光、万能移動鍵の表・裏、自己構成、精神分析）。上限なし（001 U-3、Q-19）。負にしない（§12）。万能移動鍵の「表 + 裏」は表示用に両方を送る（009 §3.8）。
- **`player_score`（標準列）**【提案 T-7】: ゲーム中は 0 のままにし、`GameEnd`（95）で勝者 1、他 0、全員敗北なら全員 0 を書く（001 §15、006 §9.3）。進行中の自己構成スコアは `player_score` ではなく、派生値として送る（009 §6）。`player_score_aux` は使わない。
- **心光・初期自我・強化自我・自己構成済みカード・手札・目標の持ち主は `player` に列を足さない**。それぞれ `ego_card.ego_owner`、`memory_card`、`objective_card.obj_owner` が正（二重管理を避ける）。
- `player` 行を丸ごと JSON にして返さない。`PlayerValues` のアクセサを通す（姉妹 PE 010 §12.2）。

#### 7.2 グローバル値（`$this->bga->globals`）

D4 の API に保存する。キーは snake_case で 50 文字以内。「書く状態」は 006 の状態 ID。

| キー | 型 | 意味 | 初期値 | 書く状態 | 公開 |
| --- | --- | --- | --- | --- | --- |
| `turn_player` | int | ターンプレイヤーのプレイヤー ID。割り込み中は `getActivePlayerId()` と異なる（006 §3.2、003 §5.2） | 0（25 で 1 番手） | 25、82 | 公開 |
| `turn_no` | int | ターン番号（1 から。全員の手番を通して数える） | 0 | 30 | 公開 |
| `phase` | string | 現在のフェイズ: `SETUP_EGO` / `SETUP_PERSONA` / `TURN_START` / `MOVE` / `RECALL` / `FLOOR` / `CONSTRUCT` / `CLEANUP` / `TURN_END` / `END` | `SETUP_EGO` | 20、23、30、31、40、50、60、80、81、95 | 公開 |
| `resume` | int | 効果解決スタックが空になったときの戻り先の状態 ID（006 §7.3）。入れ子にならないので 1 つ | 0 | 31、40、50、60、30 など「70 を呼ぶ側」 | 内部 |
| `setup_step` | int | セットアップの進み具合: ドラフトで選び終えた人数、続けて配置し終えた人数 | 0 | 20〜24 | 内部 |
| `turn` | object | `TurnContext`（§7.3）。ターンごとに 30 が初期化し、82 が破棄する | `{}` | 各所 | 内部（一部を公開） |
| `end_reason` | string / null | `FRAGMENTS` / `INSTANT_MEMORY` / `APEX_OF_PSYCHE` / `DECK_OUT`（006 §9.2） | null | 勝利判定の成立時、80 | 公開（終局後） |
| `winner_id` | int / null | 勝者。全員敗北なら null | null | 同上 | 公開（終局後） |
| `deck_out` | int[] | 山札切れが成立したレベル（1 または 2）。「引けるだけ引いてから終わる」ため、その frame / アクションの終了時の判定まで保持（001 §14-2、006 §9.1） | `[]` | 山札を引く・補充する処理 | 内部 |
| `deck1_initial` / `deck2_initial` | int | セットアップ終了時の山札の枚数（レベル 1 は部屋・場札・初期手札を配った後）。006 §11 の進行度の式 | — | setup | 内部 |
| `progress_high_water` | int | 進行度の最大値（単調増加。006 §11） | 0 | 82 | 内部 |

- ゲームオプション（回廊の配置）は `tableOptions->get(100)` から読み、導出値は `room` 表に書くので、グローバル値に複製しない。
- 人数は `COUNT(player)` で求める。保存しない。

#### 7.3 `TurnContext`（ターン内の入れ物）【提案 T-5】

グローバル値 `turn` に JSON で保存する。1 つの値にまとめる理由は、ターン開始時に一括で初期化し、ターン終了時に一括で破棄する（006 §5.6 の 82、FAQ 行 10「このターンは途切れる」）ためと、フィールドの追加が 008 の判断で `dbmodel.sql` の変更なしにできるため。型付きのアクセサ `TurnContext`（値オブジェクト）を `Data/` に置き、**ここに無いキーを読み書きしない**。

| フィールド | 型 | 意味 | 読み書きする主な規則 |
| --- | --- | --- | --- |
| `start_pos` | int | ターン開始時の人格の位置 | 刹那の追憶（開始時の部屋は 1 周して再入室するまで数えない。001 §14-1） |
| `entered_rooms` | int[] | このターンに**入室した**部屋の位置（重複なし。入室順。想起効果による移動も含む。ワープ・スキップは含まない） | 刹那の追憶（相異なる 12 か所。001 §14-1） |
| `rooms_entered_count` | int | 入室の回数（同じ部屋への再入室も別に数える。移動と想起効果の移動の合計。ワープ・スキップは含まない） | CD-01（M067〜M069、O001） |
| `lv2_entries_count` | int | レベル 2 の部屋への入室回数 | CD-05（M064〜M066 の「初めて」） |
| `skipped_rooms` | int[] | このターンに**スキップした**レベル 2 の部屋の位置 | 008 は使わない（スキップは数えない。R8-13）。統計・通知用の予備 |
| `move_steps` | int | このターンの移動で進んだ位置の数（スキップは 1 と数える） | 008 は使わない（R8-13）。予備 |
| `move_summary` | object | 移動フェイズの支払いの要約: `key_kinds_paid`（支払いで使った鍵の種類の集合。手札・自己構成済みの非万能鍵の印刷された種類。心光・万能移動鍵トークン・MD-06 の万能鍵は含めない。FAQ 行 19・20、008 T8-4）、`wild_tokens_used`（使った万能移動鍵トークンの数）、`light_spent`（心光を払った回数。統計用） | M061（CD-02）、I06（CD-03）。EV-02 のペイロードの元 |
| `lights_gained` | int | このターンに獲得した心光の数 | O005（1 ターンに 4 つ以上）。精神カード上の心光・床・効果・フリーアクションすべてを数える |
| `analysis_actions_done` | int | このターンに実行した精神分析アクションの回数（階層を上げたときだけ、`via = ACTION` のみ数える。FAQ 行 28・39） | O006 |
| `compose_actions_done` | int | このターンに完了した自己構成アクションの回数（`ctx` が `PHASE` / `TOKEN` / `FLOOR`。ボーナス・M089 の無料の自己構成は含まない） | CD-12（M068） |
| `phase_compose_used` | bool | 自己構成フェイズの自己構成アクション（1 回）を使ったか | 60 の `actStartCompose`（001 U-4、Q-02） |
| `objective_claimed` | int / null | このターンに獲得した目標カードの `obj_id`（1 ターン 1 枚。001 §11-2） | 6 `actClaimObjective`、81 の入室判定（O004） |
| `turn_compose_discount` | int | このターンの自己構成エネルギー割引（POSEIDON の -1。ターン限りで累積しない） | OP-22（006 §5.3 の `SET_TURN_DISCOUNT`） |
| `next_compose_discount` | int | 「このターンの次の自己構成」の割引の合計（想起 `NEXT_COMPOSE_DISCOUNT_1`、M080）。次の自己構成で消費 | 008 |
| `floor_alternatives` | object[] | 想起で得た床アクションの代替の選択肢 `{kind: ANALYSIS / COMPOSE, discount, source}`（006 §5.2、§5.3）。同種は割引が最大のものだけを残す（FAQ 行 12）。記録のたびに N33 で通知する | OP-24、50 |
| `recall` | object / null | 想起フェイズの情報: `room_pos`（フェイズ開始時の部屋。008 の `recall_origin_room`）、`card_id`（公開した想い出カード）、`activators`（発動者の列。プレイヤー ID。ターンプレイヤー先頭の時計回り）、`counts`（発動者ごとの発動回数。M062 で 2） | 40、`ACQUIRE_ROOM_CARD`（獲得するのは開始時の部屋のカード。FAQ 行 9） |
| `once_used` | string[] | `ONCE_PER_TURN` の効果の使用済みの印。キーは `"<player_id>:<spec_id>"`（`spec_id` は 008 の `EffectSpec` ID。例: `2310001:M061.1`、`2310001:I07.1`）。持ち主 + 効果で一意にする | FAQ 行 25、Q-14、M061、M064〜M069、I06、I07 |
| `temp_effects` | object[] | 「このターン」限りの一時効果の汎用の入れ物: `{key, owner, source, params}`（FAQ 行 10）。008 が `key` の意味を決める | 82 が破棄 |

- 「心光の行使の回数」「精神分析・自己構成の支払いの内訳」は、**1 回の `act` の中で検証が完結する**（006 §2 の方針 4）ため、**リクエストをまたいで保存しない**。ただし統計と通知のために `move_summary.light_spent` を持つ。
- **カウンタのリセット**: 30 `TurnStart` が `TurnContext` を新規に作って保存し、82 `NextTurn` が破棄（空オブジェクトに戻す）する。ターンプレイヤー以外が発動した「このターン」限りの効果もこの入れ物で管理され、ターン終了で失効する（FAQ 行 10）。
- 横向き（使用済み）はカード行（`memory_card.card_tapped`）が正で、`TurnContext` に複製しない。M095 の「このターン未使用」は `card_tapped = 0` で判定する（横向きは 80 の a でしか戻らない）。
- **公開する部分**: `entered_rooms`・`rooms_entered_count`・`skipped_rooms`・`objective_claimed`・`lights_gained`・`analysis_actions_done`・`turn_compose_discount`・`next_compose_discount`・`recall` は、公開された操作から誰でも数えられる情報なので、`getAllDatas` の `turn` に送ってよい（§8）。`once_used`・`temp_effects`・`move_summary`・`lv2_entries_count` はサーバー内部（フロントが必要とするぶんは args に出す）。`floor_alternatives` は記録のたびに N33 で通知し、床アクションの状態の args にも出す。

#### 7.3.1 008 §10.2 のターン内カウンタとの対応

008 §10.2 の 15 項目はすべて `TurnContext` に対応する（名前は 007 が 008 に合わせた）。

| 008 §10.2 | `TurnContext` | 備考 |
| --- | --- | --- |
| `entered_rooms` | `entered_rooms` | 同じ |
| `rooms_entered_count` | `rooms_entered_count` | 007 に追加 |
| `lv2_entries_count` | `lv2_entries_count` | 007 に追加 |
| `lights_gained` | `lights_gained` | 同じ |
| `analysis_actions_done` | `analysis_actions_done` | 旧名 `analysis_done` |
| `objective_claimed`（真偽） | `objective_claimed`（`obj_id` / null） | null かどうかで真偽を表す |
| `compose_actions_done` | `compose_actions_done` | 旧名 `compose_count` |
| `phase_compose_used` | `phase_compose_used` | 同じ |
| `turn_compose_discount` | `turn_compose_discount` | 旧名 `turn_discount` |
| `next_compose_discount` | `next_compose_discount` | 同じ |
| `floor_alternatives`（`kind`、`discount`） | `floor_alternatives` | `kind` は `ANALYSIS` / `COMPOSE`。`source` を追加 |
| `move_wild_tokens_used` | `move_summary.wild_tokens_used` | |
| `move_key_kinds_paid` | `move_summary.key_kinds_paid` | |
| `once_used`（spec_id の集合） | `once_used` | キーは `"<player_id>:<spec_id>"` |
| `recall_origin_room` | `recall.room_pos` | |

#### 7.4 派生値（保存しない）

| 値 | 計算 | 公開 |
| --- | --- | --- |
| 自己構成スコア | `Σ` 自己構成済みカードのスコア + `Σ` 獲得した目標カードの現在の得点 + I08 の `UNDER_CARD` の枚数（各 +1）。目標の得点は X を数え直して得点表から引く（Q-13） | 公開（全員分） |
| 手札の枚数 | `COUNT(HAND, player)` | 公開 |
| 鍵の本数 | 自己構成済みカードの `keys` を種類ごとに数える（`WISDOM`・`WILL`・`EMOTION`・`WILD`） | 公開 |
| 精神階層 | `player_spirit` の階層（NULL = 0） | 公開 |
| 各山・捨札の枚数 | `COUNT` | 公開（山札は枚数のみ。一番上の裏面は §8） |
| 目標の現在の X と得点（未獲得のもの） | `ObjectiveEvaluator` が本人視点で計算 | 本人のみ（009 §3.6） |
| 目標の「獲得可能」 | 条件の評価（`TurnContext` と現在の状態）。FAQ 行 3 | 本人のみ（ターンプレイヤーの視点） |
| 「場札・部屋の補充が必要か」 | `OPEN` の空き枠、`ROOM` のカード無しの位置 | 内部 |
| 進行度 | 006 §11 の式（`progress_high_water` のみ保存） | 内部 |

### 8. 公開・非公開の区分と `getAllDatas`

#### 8.1 視点ごとの違い

| 項目 | 本人 | 他プレイヤー | 観戦者・リプレイ |
| --- | --- | --- | --- |
| 手札 | ID と種類 | 枚数のみ | 全員分、枚数のみ |
| `ROOM` の裏向きカード | ID と裏面のキー | 同左 | 同左 |
| `ROOM` の表向きカード（想起中） | ID と種類 | 同左 | 同左 |
| 山札（レベル 1・2） | 枚数と一番上の裏面のキー | 同左 | 同左 |
| 捨札、場札、自己構成済み、`UNDER_FLOOR` | ID と種類（公開） | 同左 | 同左 |
| `UNDER_CARD`（I08） | ID と種類（確定。R-3） | 枚数のみ | 枚数のみ |
| 初期自我 | 自分の分は、公開後は全員。ドラフト中の `PICKED` は本人だけ | 公開前は見えない | 公開前は見えない |
| `PACK`（ドラフトの回ってきた山） | 現在の選択者の `getArgs` の `_private` だけ | 見えない（枚数のみ） | 見えない |
| 強化自我の山 | 枚数のみ。取るとき候補は本人の `_private` | 枚数のみ | 枚数のみ |
| 獲得した強化自我、目標、トークン、精神カード、キューブ、ミープル | 公開 | 公開 | 公開 |
| 目標の現在の X と得点 | 自分の視点の値 | 他人の値は送らない | 送らない |
| 効果解決スタック（`effect_frame`） | 送らない（現在の状態の args が必要分を出す） | 送らない | 送らない |
| `TurnContext` | 公開部分のみ（§7.3） | 同左 | 同左 |

非公開情報は**本人の分（手札、`PICKED`、`UNDER_CARD`、目標の X）を除いて誰にも含まれない**。観戦者は `$currentPlayerId` が卓のプレイヤーに含まれないことで判定し、本人の分は空にする。

#### 8.2 戻り値の構造【提案】

数値は整数で送る（003 §6.1）。キーは camelCase。`players` キーはフレームワーク用なので、独自の値は `playerData` に入れる（U-4）。`decks` の `top` は裏面のキー（`1B`・`1F`・`1H`・`2B`・`2F`・`2H`。002 §5.3 の `Rev` を除いた形）で、空なら null。

```json
{
  "catalog": { "memory": [], "floor": [], "spirit": [], "ego": [], "objective": [] },
  "turnPlayer": 2310001,
  "turnNo": 3,
  "phase": "MOVE",
  "turn": { "enteredRooms": [2, 3], "roomsEnteredCount": 2, "skippedRooms": [], "objectiveClaimed": null,
            "lightsGained": 0, "analysisActionsDone": 0, "turnComposeDiscount": 0,
            "nextComposeDiscount": 0, "recall": null },
  "rooms": [
    { "pos": 1, "floor": 1, "level": 2,
      "card": { "id": 57, "back": "2B", "faceUp": false },
      "under": [{ "id": 12, "type": 33 }] }
  ],
  "open": [{ "slot": 0, "id": 101, "type": 14 }],
  "decks": { "1": { "count": 52, "top": "1F" }, "2": { "count": 20, "top": "2H" } },
  "discards": { "1": [{ "id": 8, "type": 3 }], "2": [] },
  "spirit": [{ "id": "R1A", "tier": 1, "slot": 0, "light": true }],
  "enhDeckCount": 3,
  "objectives": [{ "id": 2, "slot": 0, "owner": null }],
  "playerData": {
    "2310001": {
      "persona": 4, "spirit": "R1A", "tier": 1,
      "tokens": { "light": 3, "wildUp": 1, "wildUsed": 0, "compose": 0, "analysis": 1 },
      "handCount": 5, "score": 7,
      "keys": { "WISDOM": 2, "WILL": 0, "EMOTION": 1, "WILD": 0 },
      "composed": [{ "id": 44, "type": 5, "tapped": false, "underCount": 0 }],
      "initialEgo": "I05", "enhancedEgo": null, "objectives": [3]
    }
  },
  "myHand": [{ "id": 22, "type": 61, "energy": 3 }],
  "myUnder": [{ "host": 44, "cards": [{ "id": 90, "type": 70 }] }],
  "myPickedEgo": null,
  "myObjectiveStatus": [{ "id": 2, "x": 1, "points": 1, "claimable": false }],
  "gameEnd": null
}
```

| キー | 内容 | 補足 |
| --- | --- | --- |
| `catalog` | 静的なカタログ（想い出・床・精神・自我・目標の数値データと識別子）。009 §17 の要件 | 識別子と数値だけ（秘密情報ではない）。英語の効果文は含めない（009 の `texts.js` の担当）。**カタログに `card_id` と `card_type` の対応は含まれない**ので、カタログを送っても非公開の対応は漏れない |
| `rooms` | 12 部屋の床・レベル・カード。裏向きのカードは `{id, back, faceUp: false}`、想起で公開中は `{id, back, faceUp: true, type}`。`under` は `UNDER_FLOOR` の公開カード | 想い出カードの無い位置は `card: null` |
| `open` | 場札。取られた枠は含めない | |
| `decks` | 山札の枚数と一番上の裏面のキー（Q-44）。**二枚目以降の錠前と山の順序は送らない** | |
| `discards` | 捨札の ID と種類（捨てた順）。最大 144 件で数 KB | 公開情報 |
| `spirit` | 使う 7 枚の精神カード | キューブの位置は `playerData.tier/spirit` |
| `enhDeckCount` | 強化自我の山の枚数のみ | |
| `playerData` | プレイヤーごとの公開値。`handCount`、`score`、`keys` は派生値。`composed` は `tapped` と、I08 の下の枚数 `underCount` を含む | 初期自我は**公開後**（`OWNED`）のみ。ドラフト中・公開前は null |
| `myHand`、`myUnder`、`myPickedEgo`、`myObjectiveStatus` | 本人の分だけ。観戦者には空配列 / null。`myHand[].energy` は手札のカードの現在の自己構成エネルギー（M098 込み。008 MD-02）。自己構成済みカードの現在のエネルギーは印刷の値と同じ（M098 は手札でだけ働く）なので `catalog` から引く | `myObjectiveStatus` は未獲得の目標の現在の X と得点、ターンプレイヤーのときは `claimable` |
| `gameEnd` | 終局後だけ: `reason`、`winnerId`、各人の内訳（§10 の `gameEnded` と同じ） | それ以外は null |

#### 8.3 args との役割分担

- **`getAllDatas`** は静的でない盤面の全体を一度に返す。**入力待ちの内容（ドラフトの候補、強化自我の候補、移動可能な部屋、支払い可能な資源）は含めない**。006 の `getArgs`（公開部分と `_private`）が現在の状態から再構成する。
- 窓の中の保留（`effect_frame` の `WAITING`）も同じ。再読み込み後は、現在の状態（71 など）の `getArgs` が DB から選択の定義を作る。
- `getAllDatas` は `memory_card` や `player` を丸ごと読んで整形しない。専用の読み出し `PublicView` を唯一の入口にする（姉妹 PE 010 §12.2）。

### 9. 非公開情報の守り方

| 情報 | 漏れる経路 | 防ぎ方 |
| --- | --- | --- |
| 山札の順序と中身（`card_type`） | `getAllDatas`、args、通知、ログ文 | 山札の一覧を作るコードを書かない（`inLoc('DECK1')` を `PublicView` から呼ばない）。送るのは枚数と、一番上の `back`（`MemoryCardRepository::topBack`）だけ。**二枚目以降の `back` を返す API は作らない**（Q-44）。引いたカードは、引いた本人にだけ ID と種類を送る |
| 手札の内訳 | カードを引く・獲得する通知 | 他人には枚数のみ（`cardsDrawn` の公開部分）。**ID も送らない**（ID が既に別の通知で公開された種類と結びついていても、ID の移動が追跡されるため） |
| 部屋の裏向きカードの種類 | `getAllDatas`、通知、args | 裏向きのカードの `card_type` は送らない。送るのは `card_id` と `back`（裏面のキー）だけ。想起で表向きにした瞬間に初めて `type` を送る（`recallStarted`）。**`card_id` は種類と無関係なランダム採番**（§4.3）なので、ID だけで種類は推測できない |
| 山から部屋・場札への移動 | 補充の通知 | 部屋への補充（`roomsRefilled`）は `card_id` と `back` だけ。場札への補充（`openRefilled`）は表向きなので `type` も送る |
| 手札から部屋・場札への移動（I05 の入れ替え、`SWAP_HAND_FIELD`） | 通知 | 部屋への移動は `card_id` だけ（種類は本人にだけ `_private`）。場札への移動は表向きになるので公開 |
| 部屋の想い出カードの入れ替え（M052〜M054） | 通知 | 入れ替えた 2 つの位置と、入れ替え後の各位置の `card_id` と `back` だけ（N28 の `after`）。`type` は送らない。`card_id` は種類と無関係なので、位置が変わっても券面は分からない |
| I08 の親が場を離れたときの下のカード | 通知 | 捨札に移った時点で全員に公開される（N15 の `UNDER_RELEASED`。ID と種類）。それまでは他人には枚数のみ |
| 手札のカードの現在のエネルギー（M098） | args、通知 | `myHand[].energy` と N35 は本人だけ。他人には送らない（M098 を持つこと自体が手札の情報） |
| ドラフトの山（`PACK`）と選択（`PICKED`） | `getArgs` の公開部分、通知 | 山の中身は選択者の `_private` だけ。選択の通知は「選んだ」ことだけを公開し、どのカードかは本人の `_private`。全員が選び終えたら `initialEgosRevealed` で一斉公開。最後に残った 1 枚は除外され、公開しない |
| 強化自我の候補 | `getArgs` の公開部分、通知 | `ENH_DECK` の全カードは本人の `_private` だけ。獲得したカードは公開（`enhancedEgoObtained`） |
| I08 の下のカード | `getAllDatas`、通知 | 他人には枚数のみ（確定。R-3）。持ち主には `_private` |
| 目標の現在の X | `getAllDatas`、通知 | 本人にだけ（`objectiveStatus` は `notify->player`） |
| 効果解決スタックの `params`、`choice` | `getArgs`、通知 | `PublicView` を通した必要分だけ。frame の JSON をそのまま送るコードを書かない |
| `deck_out` フラグ | args | 内部のみ。公開するのは `deckOut` 通知（山札切れが確定したとき） |

- **カードの ID と種類を結びつける通知の規則**【提案】: 通知の args に `card_id` と `type` が同居してよいのは、その受信者にそのカードが**すでに公開されている**か、**この通知で公開される**場合だけ。例外なく `Notification` クラスの 1 か所（`cardRef(MemoryCard, viewer)`）で作り、各所で組み立てない。
- 検証: PHPUnit で `PublicView` の出力（観戦者・他人の視点）に、非公開カードの `card_type` が含まれないことをテストする（003 §10）。Studio では観戦者・リプレイで確認する（V-2）。

### 10. 通知

#### 10.1 共通の約束

- 送信は `$this->bga->notify->all()`（全員と観戦者）、`$this->bga->notify->player()`（本人のみ。卓のプレイヤーに限る）。通知はアクションが正常に終わった時点でまとめて送られ、例外で破棄される（D6）。GAME 状態の連鎖で複数の通知を出しても、リクエストの終わりに順序どおり届く。
- クライアントは `notif_<通知名>` ハンドラを持つ。**画面の更新は通知と `getAllDatas` だけで行う**（リプレイ・観戦で崩れないため。003 §5.4）。
- 通知名は camelCase、args のキーは snake_case（BGA のログ置換の規約に合わせる）。`getAllDatas` のキーは camelCase（§8.2）。
- ログ文言は英語の原文を `clienttranslate()` で包み、リテラルで書く（003 §7.1）。**文言は `Flow/Notification` の 1 か所に集める**（翻訳の抽出のため）。画面だけを更新しログに出さない通知はメッセージを空文字 `''` にする。
- プレイヤー名は `${player_name}`（`player_id` を同じ args に入れる）。カード名・部屋名・目標名などの翻訳可能な引数は `${card_name}` のように**英語名の文字列**を渡し、`i18n` に引数名を列挙する（D6、Translations）。カードの `card_name` は 009 §9 の形式「No.N 英語名」とし、クライアントのロジックで使う識別子は別の引数 `card_type` に持つ。
- **カードの参照 `cardRef`**: `{id, type}`（公開されている場合）、`{id}`（非公開）、`{id, back}`（部屋や山の裏向き）。
- **山の同期 `decks`**: 山札を動かす通知（`cardsDrawn`、`roomsRefilled`、`openRefilled`、`setupDealt` 相当）は、args に `decks`（`{"1": {"count", "top"}, "2": {...}}`）を含める。一番上の裏面を更新するため（Q-44、009 §3.3）。山札を動かさない通知には含めない。
- **`_private` の使い方**【提案 T-6】: 「全員向けの公開部分」+「本人だけが知る券面」を 1 つにまとめられる場合は `notify->all` の `_private`（003 §5.4）を使う。対象は `cardsDrawn`、`initialEgoPicked`、`cardsAttached`、`roomCardSwappedWithHand` の 4 つだけ。Studio で `_private` が観戦者・リプレイで漏れないことを確認し（V-1）、だめな場合は同じ内容を `notify->all`（公開部分）+ `notify->player`（本人の券面）に分け、後者のログ文言を `''` にする。
- 128 KB の上限（D6）に対し、1 件は 2 KB 以内を目安にする。移動で 24 部屋まで進む場合も、`roomEntered` を 1 部屋ごとに送るので 1 件は小さい。
- 通知の大量送信を避けるため、1 つの `act` で同じ種類の通知が複数必要なとき（例: 手札上限で 3 枚捨てる）は 1 通知の配列にまとめる。

#### 10.2 通知の一覧

「全員」は `notify->all`（観戦者を含む）、「本人」は `notify->player`、「全員+本人」は `notify->all` の `_private`。`reason` などの列挙は英語の識別子。`player_name` は `i18n` に入れない。

| No. | 通知名 | 送り先 | args（`player_id`、`player_name` は省略） | ログ文言（英語原文） | 効果・クライアントでの処理 |
| --- | --- | --- | --- | --- | --- |
| N01 | `initialEgoPicked` | 全員+本人 | 公開: `pack_size`（残りの山の枚数）。`_private[本人]`: `ego_id` | `${player_name} chooses an initial ego card` | 21。本人は選んだカードを確認。他人は選んだことだけ。次の人へ |
| N02 | `initialEgosRevealed` | 全員 | `egos`（`[{player_id, ego_id}]`） | `All players reveal their initial ego cards` | 22。一斉公開。最後の 1 枚は除外（公開しない） |
| N03 | `personaPlaced` | 全員 | `pos`、`room_name`（i18n） | `${player_name} places their persona in ${room_name}` | 24。ミープルを置く |
| N04 | `turnStarted` | 全員 | `turn_no` | `''` | 30。ターンプレイヤーの強調。`TurnContext` の公開部分を初期化（`entered_rooms` など） |
| N05 | `phaseStarted` | 全員 | `phase`、`phase_name`（i18n） | `${phase_name} phase` | 31、40、50、60、80 の入場。フェイズの表示（ログの区切り） |
| N06 | `roomEntered` | 全員 | `from_pos`、`pos`、`room_name`（i18n）、`step`（この移動での順番）、`payment`（`{tapped: [card_id], discarded: [cardRef], light, wild_tokens}`）、`entered_count` | `${player_name} enters ${room_name}` | 31。1 部屋ずつ送る（12 部屋目で勝利が成立したらそこで止まる。006 §5.1）。支払った資源の変化は N14〜N16 が続く |
| N07 | `roomSkipped` | 全員 | `pos`、`room_name`（i18n） | `${player_name} skips ${room_name}` | 31。スキップしたレベル 2 の部屋。周遊ボーナスは N16 が続く |
| N08 | `personaMoved` | 全員 | `from_pos`、`pos`、`room_name`（i18n）、`reason`（`RECALL` / `WARP`）、`counts_as_entry`（入室に数えるか） | `${player_name} moves to ${room_name}` | 想起効果による移動（Q-06）、M072 のワープ。ミープルを動かす。`entered_rooms` に加えるかは `counts_as_entry` |
| N09 | `recallStarted` | 全員 | `pos`、`room_name`（i18n）、`card`（`cardRef` = 種類つき）、`card_name`（i18n）、`activators`（発動者の ID の列）、`counts`（発動者ごとの発動回数 `{player_id: 回数}`。M062 でターンプレイヤーが 2） | `${player_name} recalls ${card_name} in ${room_name}` | 40。部屋のカードを表向きにして公開し、想起中の案内パネル（009 §3.9）を出す |
| N10 | `floorActionChosen` | 全員 | `kind`（床アクション名または `ALT_*` の識別子）、`action_name`（i18n） | `${player_name} performs ${action_name}` | 50・51。選んだ床アクション・代替 |
| N11 | `effectActivated` | 全員 | `source`（002 の ID）、`source_name`（i18n。カード名）、`spec_id`（008 の `EffectSpec` ID。省略可）、`nth` / `times`（想起効果の何回目 / 発動回数。M062 の 2 回目は `nth = 2`。それ以外は省略） | `${player_name} activates ${source_name}` | 想起効果・起動効果・ターン開始時の効果の発動。結果は N12 以降が続く。008 が使う発動の共通部品 |
| N12 | `cardsDrawn` | 全員+本人 | 公開: `count`、`level`（引いた山）、`reason`（`FLOOR` / `RECALL` / `EFFECT` / `ANALYSIS_BONUS` / `SETUP`）、`decks`、`hand_count`。`_private[本人]`: `cards`（`[cardRef]`） | `${player_name} draws ${count} memory card(s)` | 山札から手札へ。他人には枚数だけ（ID を載せない） |
| N13 | `cardsGained` | 全員 | `cards`（`[cardRef]` = 種類つき）、`source`（`OPEN` / `UNDER_FLOOR`）、`from_slots`（OPEN の枠）または `pos` と `room_name`（i18n。床の下の部屋の位置）、`hand_count`、`reason` | OPEN: `${player_name} takes ${count} memory card(s) from the open cards`、UNDER_FLOOR: `${player_name} takes ${count} memory card(s) from under ${room_name}` | 場札・床の下から手札へ。表向きの情報なので種類を公開 |
| N14 | `roomCardAcquired` | 全員 | `pos`、`card`（`cardRef` = 種類つき）、`card_name`（i18n）、`hand_count` | `${player_name} acquires ${card_name}` | 想起の最後の獲得（ターンプレイヤーのみ。Q-04）。公開済みのカードが手札へ。この後、部屋は空（補充は N29） |
| N15 | `cardsDiscarded` | 全員 | `cards`（`[cardRef]` = 種類つき）、`reason`（`COST` / `LIGHT` / `HAND_LIMIT` / `EFFECT` / `FLOOR` / `UNDER_RELEASED`）、`pile_counts`（`{"1", "2"}`）、`hand_count` | `LIGHT`: `${player_name} discards ${count} memory card(s) for inner light`、`HAND_LIMIT`: `${player_name} discards ${count} memory card(s) (hand limit)`、`EFFECT` / `FLOOR`: `${player_name} discards ${count} memory card(s)`、`COST`: `''`（支払いは N06・N24・N25 のログ）、`UNDER_RELEASED`: `''`（親の自己構成済みカードが場を離れ、I08 の下のカードが捨札へ。N17・N26・N18 のログが先に出る） | 手札から捨札へ。**ID と種類を公開**（捨札は表向き。Q-23）。レベルは `Material` から分かる |
| N16 | `tokensChanged` | 全員 | `delta`（`{light, wild_up, wild_used, compose, analysis}` の 0 でないもの）、`totals`（同じキーの変更後の全値）、`reason`（`TOUR_BONUS` / `FLOOR` / `RECALL` / `ANALYSIS` / `EFFECT` / `DISCARD_LIGHT` / `SETUP` / `PAYMENT` / `CLEANUP` / `TURN_START`）、`origin`（心光の獲得の出どころ。008 §6.6 の `FLOOR` / `SPIRIT_CARD` / `TOUR` / `RECALL` / `BONUS` / `EFFECT` / `EXTRA`。心光以外・減少では省略）、`token_name`（i18n）、`amount` | 増: `${player_name} gains ${amount} ${token_name}`、減: `${player_name} spends ${amount} ${token_name}`（`SETUP` / `CLEANUP` / `PAYMENT` で見せたくない場合は `''`） | トークンの増減。万能移動鍵の表 → 裏（`wild_up` -1、`wild_used` +1）も 1 通知。N17 とは別に、**1 つの `act` の中で同じプレイヤー・同じ `reason` の増減は 1 通知にまとめる** |
| N17 | `cardsTapped` | 全員 | `card_ids`、`tapped`（bool） | `''` | 自己構成済みカードを横向きにする / 戻す（支払い）。N06・N24・N25 の `payment.tapped` と同じものを指す。クリーンアップの戻しは N28 |
| N18 | `cardComposed` | 全員 | `card`（`cardRef` = 種類つき）、`card_name`（i18n）、`context`（`PHASE` / `TOKEN` / `FLOOR` / `BONUS_LV1` / `BONUS_ANY` / `EFFECT` / `ARCHITECT`）、`payment`（`{discarded: [cardRef], light, energy}`）、`tableau_count`、`hand_count` | `${player_name} composes ${card_name}` | 61。手札から自己構成済みエリアへ。N15・N16 の `PAYMENT` が続く。I03 の入れ替えは `ARCHITECT` |
| N19 | `cardsAttached` | 全員+本人 | 公開: `host_id`、`count`、`hand_count`。`_private[本人]`: `cards`（`[cardRef]`） | `${player_name} puts ${count} memory card(s) under ${card_name}` | I08（主人公）の追加支払い。他人には枚数のみ（確定。R-3）。自己構成スコアが +count |
| N20 | `cardsPlacedUnderFloor` | 全員 | `pos`、`room_name`（i18n）、`cards`（`[cardRef]` = 種類つき）、`hand_count` | `${player_name} places ${count} memory card(s) under ${room_name}` | I04（論理学者）。公開（確定。R-2）。取り出しは N13（`UNDER_FLOOR`） |
| N21 | `scoreUpdated` | 全員 | `scores`（`{player_id: {total, memory, objective}}`。変化した人だけ） | `''` | 自己構成スコアの変化。プレイヤーパネルのカウンター（009 §6） |
| N22 | `objectiveClaimed` | 全員 | `obj_id`、`objective_name`（i18n）、`points` | `${player_name} claims ${objective_name}` | 目標の獲得（Q-12、Q-48）。共通の場の枠が空き、獲得者の領域へ |
| N23 | `objectiveStatus` | 本人 | `statuses`（`[{obj_id, x, points, claimable}]`）。ターンプレイヤー以外は `claimable` が常に false | `''` | 未獲得の目標の現在の X・得点（009 §3.6）。**自己構成スコアや条件に関わる状態を変えた `act` の終わりごと**に、全プレイヤーに本人用を 1 通ずつ送る |
| N24 | `spiritAdvanced` | 全員 | `from_spirit`（NULL = 開始位置）、`to_spirit`、`tier`、`via`（`ACTION` / `EFFECT`。M088 は `EFFECT` で `payment` は null、`analysis_count` は増えない）、`light_taken`（到着した精神カード上の心光があったか = 008 の `dest_had_light`）、`payment`（N06 と同形）、`analysis_count` | `${player_name} advances to tier ${tier}` | 62。キューブを動かす。心光の獲得は N16（`reason: ANALYSIS`）。階層 5 なら次に `gameEnded` |
| N25 | `enhancedEgoObtained` | 全員 | `ego_id`、`ego_name`（i18n）、`deck_count` | `${player_name} obtains the enhanced ego ${ego_name}` | 第三階層到着の選択後。獲得した強化自我は公開（Q-08） |
| N26 | `cardsExchanged` | 全員 | `kind`（`HAND_OPEN` / `TABLEAU_HAND`）、`pairs`（`[{out: cardRef, in: cardRef, slot?}]` = 種類つき）、`hand_count` | `${player_name} exchanges ${count} memory card(s)` | 想起の `SWAP_HAND_FIELD`・`SWAP_SAME_COST`。交換後に場札・自己構成済みへ置かれたカードも、受け取った手札のカードも、いずれも公開された種類で渡す（手札に入ったカードは持ち主が知っている）。他人の手札に入るカードは場札・自己構成済みの表向きのものなので公開情報。`SWAP_SAME_COST` では手札から出るカードが相手の並びと向きを引き継ぐ（008 R8-10(b)）。自己構成として扱わない（N18 を出さない） |
| N27 | `roomCardSwappedWithHand` | 全員+本人 | 公開: `pos`、`room_card_id`（部屋から手札へ）、`hand_card_id`（手札から部屋へ。裏向き）、`back`（部屋に置かれたカードの裏面のキー）。`_private[本人]`: 双方の `cardRef`（種類つき） | `${player_name} swaps a memory card in hand with the one in ${room_name}` | I05（起業家）。部屋のカードを手札へ。置かれたカードは裏向きで、他人には `id` と `back` だけ。**手札へ移るカードの `type` は本人だけ** |
| N28 | `roomsSwapped` | 全員 | `kind`（`CARD` / `FLOOR`）、`pos_a`、`pos_b`、`room_a`・`room_b`（i18n）、`after`（入れ替え後の状態。`CARD`: `[{pos, card_id, back}]`、`FLOOR`: `[{pos, floor_id}]`） | CARD: `${player_name} swaps the memory cards of ${room_a} and ${room_b}`、FLOOR: `${player_name} swaps the floors of ${room_a} and ${room_b}` | M052〜M057。`CARD` は `card_id` と `back` だけを入れ替える（裏向き。誰も券面を見ない。`type` は送らない）。`FLOOR` は `room_floor` だけを入れ替える（想い出カード・床の下は位置に残る。R-1 → 008 R8-11(b)）。人格のいる部屋も対象（008 R8-11(a)） |
| N29 | `cleanup` | 全員 | `untapped`（戻した `card_ids`）、`wild_restored`（裏から表へ戻した万能移動鍵の数）、`rooms`（補充した部屋: `[{pos, card_id, back}]`）、`open`（補充した場札: `[cardRef]` = 種類つき。`slot` 付き）、`decks` | `${player_name} cleans up` | 80。a〜d を 1 通知にまとめる。補充分の山札は `roomsRefilled` / `openRefilled` に相当（部屋は裏向きで `id`・`back` のみ、場札は種類つき） |
| N30 | `deckOut` | 全員 | `level`（1 または 2） | `The level ${level} memory deck has run out` | 山札切れが確定したとき（引けるだけ引いた後の判定の時点。001 §14-2）。続けて `gameEnded` |
| N31 | `gameEnded` | 全員 | `reason`（`FRAGMENTS` / `INSTANT_MEMORY` / `APEX_OF_PSYCHE` / `DECK_OUT`）、`winner_id`（全員敗北なら null）、`results`（`[{player_id, memory_score, objective_score, attached_score, total, tier, rooms_entered}]`） | `FRAGMENTS`: `${player_name} wins by gathering fragments`、`INSTANT_MEMORY`: `${player_name} wins by a fleeting recollection`、`APEX_OF_PSYCHE`: `${player_name} wins by reaching the apex of the psyche`、`DECK_OUT`: `The memory deck ran out. Everyone loses` | 95。勝者・全員敗北の明示（Q-25）と得点内訳。`gameEnd` を `getAllDatas` に載せる |
| N32 | `decksChanged` | 全員 | `decks` | `''` | 山札の中身が変わるが他の通知に載らない場合の予備（008 の効果が山を直接動かすとき） |
| N33 | `floorAlternativeRecorded` | 全員 | `kind`（`ANALYSIS` / `COMPOSE`）、`discount`、`source`（002 の ID）、`alternatives`（記録後の全件 `[{kind, discount}]`） | `${player_name} may perform ${action_name} instead of a floor action` | 想起効果の床アクションの代替の記録（OP-24。ターンプレイヤーのみ）。同種は割引最大の 1 件にまとまる。`action_name`（i18n）は N10 と同じ表記 |
| N34 | `composeDiscountChanged` | 全員 | `turn_compose_discount`（POSEIDON のこのターンの軽減の現在の合計）、`next_compose_discount`（次の自己構成の軽減の現在の合計）、`source`（省略可） | `${player_name} reduces composition energy` | OP-22・OP-23、次の自己構成アクションの完了による消費（合計 0 になる）。クライアントは合計値で表示を置き換える |
| N35 | `handEnergyUpdated` | 本人 | `energies`（`[{card_id, energy}]`。手札のカードの現在の自己構成エネルギー、M098 込み） | `''` | M098 を手札に持つ本人にだけ、手札または自己構成済みの感情鍵が変わった `act` の終わりに送る（008 MD-02）。持っていなければ送らない |

- 通知のうち本人向けは **N23・N35 の 2 種と、N01・N12・N19・N27 の `_private`** だけ。他は全員向け。
- **進行度**は通知しない（BGA が `getGameProgression` で更新する）。`player_score` も通知しない（終局時のみ書く）。
- N11（`effectActivated`）は 008 が使う共通の発動通知。個々の効果の結果は N12〜N35 の部品の組み合わせで表し、**効果ごとの専用の通知を作らない**ことを 007/008 の約束とする（姉妹 PE 010 §10.2 の部品方式）。部品で表せない場合は 007 に通知を追加する。

#### 10.3 公開通知に載せてはいけない項目

| 項目 | 理由 |
| --- | --- |
| N12 の公開部分に引いたカードの `cardRef`（ID を含む） | 手札は枚数のみ公開 |
| N01 の公開部分に選んだ初期自我の ID | 全員が選び終えるまで非公開（N02 で一斉公開） |
| N19・N27 の公開部分に I08 の下のカード・手札から部屋に置いたカードの種類 | 持ち主だけの情報 |
| N06・N07・N29 の `rooms` に補充した裏向きカードの `type` | 部屋のカードは想起まで裏向き |
| N28 の `after` に `type` | 部屋の想い出カードは想起まで裏向き。入れ替えても券面は誰も知らない |
| N29 の `decks.top` 以外の山の情報（二枚目以降の `back`、枚数以外の順序） | Q-44 は一番上のみ |
| N23 に他人の目標の X | 009 §3.6 は自分の値のみ |
| 全ての通知に `getArgs` の `_private` の中身 | 本人以外に漏れる |

#### 10.4 流れの例（1 手番の通知）

1. N04 `turnStarted` → N05 `phaseStarted`（MOVE）
2. ターンプレイヤーが `actMove`（4 → 5 → 7）: N06（5）→ N15 / N17 / N16（支払い）→ N07（6 をスキップ）→ N16（周遊ボーナス）→ N06（7）→ N16（周遊ボーナス）。続けて N23（目標の状況）
3. N05（RECALL）→ N09 `recallStarted` → 発動者ごとの N11 と結果（N12 / N16 など）→ N14 `roomCardAcquired` → 手札上限なら N15（`HAND_LIMIT`）
4. N05（FLOOR）→ N10 → 結果（N12 / N13 / N16）
5. N05（CONSTRUCT）→ 61 で N18 `cardComposed` + N15 / N16（支払い）+ N21 `scoreUpdated` → 80 の N29 `cleanup`

### 11. 目標の評価とスコア

- `ScoreCalculator`（`Flow/`）が自己構成スコアを返す唯一の関数。`WinChecker`（006 §9）は、スコアが変わりうる箇所の直後にこれを呼ぶ。
- `ObjectiveEvaluator`（`Flow/`）は、目標 1 枚ごとに「条件を満たすか」「X」「得点（002 §10 の得点表）」を返す。入力は `TurnContext`、`memory_card`、`player` の列。**O001 の X は表と裏の万能移動鍵を合わせた数**（Q-13）、O004 は 81 でのみ条件を判定する（006 §6.3）。
- 目標の得点を `player` や `objective_card` に保存しない。X の変動でスコアが下がりうる（Q-13）ため、毎回計算する。
- `scoreUpdated`（N21）は、`ScoreCalculator` の結果が前回の通知から変わったプレイヤーについてだけ、1 つの `act` の終わりに送る（前回値は 1 リクエスト内のメモリで比較し、保存しない。リロード後は `getAllDatas` が再計算する）。

### 12. 整合性の制約と `Data/` 層

#### 12.1 制約と守る場所

「DB」は `dbmodel.sql` のキー、「Data」は `Data/` 層の検査、「Test」は PHPUnit、「Debug」は `Game.php` の `debug_assertInvariants`（Studio のデバッグメニュー）。

| 制約 | 守る場所 |
| --- | --- |
| 想い出カードは 144 枚で、各カードは 1 か所にだけある | DB（`card_id` が主キー）、Test、Debug |
| 手札・自己構成済み・`UNDER_*` の `card_loc_arg` は存在するプレイヤー / 床 / 親カードを指す | Data（`move` が検査）、Debug |
| 部屋の位置あたり `ROOM` のカードは 0〜1 枚 | Data（`move` が 2 枚目を例外）、Debug |
| 場札の枠あたり `OPEN` のカードは 0〜1 枚 | Data、Debug |
| `card_tapped = 1` は `TABLEAU` だけ、`card_face_up = 1` は `ROOM` だけ | Data（`move` が自動で 0 に戻す）、Debug |
| 手札は 8 枚以下（上限の確認の単位の外） | Data は検査しない（獲得で一時的に超える。001 §12）。006 の `HAND_LIMIT_CHECK` が守る。Debug は `phase = CONSTRUCT` 以降の手札を検査 |
| 同名（同じ `card_type`）の自己構成済みは 1 人 1 枚 | Data（`compose` が検査）、Test（001 §9-1） |
| 床カードは 12 枚が 12 位置に 1 回ずつ。位置 1・4・7・10 のレベル 2 は F01・F04・F07・F10 | DB（`room_pos` が主キー、`room_floor` が `UNIQUE`）、Data（セットアップ）、Test |
| 精神カードの `(tier, slot)` は一意。1 人の `player_spirit` は `spirit_card` に存在するか NULL | DB（`UNIQUE`）、Data |
| 次の階層へしか進めない（階層は 1 つずつ上がる） | Data（`advanceSpirit` が検査。001 §10-1） |
| トークンは負にならない | Data（`PlayerValues::add*` が負なら例外） |
| 強化自我は 1 人 1 枚まで | Data（`EgoRepository::obtain`、Test）。第三階層に着いたときだけ |
| 目標は 1 ターンに 1 枚まで。獲得済みは手放さない | Data（`ObjectiveRepository::claim` が `TurnContext.objective_claimed` を検査）、Test |
| `effect_frame` に `WAITING` の frame は先頭の 1 件まで | Data（`markWaiting`）、Debug |
| 終局後は `effect_frame` が空 | Data（`clear`）、Test |
| 公開 args・通知・`getAllDatas` に非公開情報が入らない | コードレビュー（§9）、Test（`PublicView`） |

- すべての書き込みは `Data/` 層のリポジトリ経由で行い、`Game.php` や状態クラスから生の SQL を書かない（003 §4.2）。
- 1 リクエストは 1 つのトランザクション（D9）。検査に失敗したら例外で全体を巻き戻す（通知も送られない）。
- `CHECK` 制約には依存しない（U-3）。

#### 12.2 `Data/` 層の構成【提案】

| クラス | 役割 |
| --- | --- |
| `Database`（インターフェース）、`BgaDatabase` | DB 操作の口（`execute`・`rows`・`value`・`lastInsertId`）。PHPUnit では PDO 実装に差し替える（003 §4.2） |
| `MemoryCardRepository` | §4.4 |
| `RoomRepository` | `room` の読み書き。床の入れ替え（`swapFloors`）、位置からのレベル判定 |
| `SpiritRepository` | `spirit_card` の読み書き、心光トークンの取得（先着）、プレイヤーの階層更新 |
| `EgoRepository` | ドラフトの山、選択、公開、強化自我の獲得（§5.3） |
| `ObjectiveRepository` | 場の枠、獲得 |
| `EffectStack` | §6 の積み方・取り出し・保留 |
| `GlobalValues` | §7.2 のアクセサ（`$this->bga->globals`）。初期値を 1 か所に持つ |
| `TurnContext` | §7.3 の値オブジェクトと、`GlobalValues` への保存・読み出し |
| `PlayerValues` | §7.1 のアクセサ。`player` 行を丸ごと JSON にして返さない |
| `PublicView` | `getAllDatas` と `getArgs` の公開部分を作る**唯一の入口**（視点ごとの出し分け。§8、§9） |
| `Material/` との境界 | カタログ（`CardDefinitions`）を ID で引く。DB に静的な定義を持たない（D1） |

### 13. ゲーム開始時の初期化（`setupNewGame`）

`Setup/GameSetup` が次の順に DB を作る（001 §4 の手順に対応）。乱数はすべて `random_int` ベース（D9）で、`GameSetup` に注入して PHPUnit で種付きに差し替える。

1. **想い出カード 144 枚**（§4.3）: 種類リストを作ってシャッフルし、`card_id` 1〜144 を振る。レベル別に分け、それぞれもう一度シャッフルして `card_pos` を割り当てる。
2. **人数別の除外**（001 §2-2）: レベル 1 の山から 2 人: 52 枚、3 人: 26 枚、4 人: 0 枚を `OUT` に移す。
3. **部屋**（手順 1・3）: ゲームオプション 100 に従って `room` を作る。レベル 1 の山から 8 枚、レベル 2 の山から 4 枚を引き、それぞれ `ROOM` に裏向きで置く。
4. **場札**（手順 4）: レベル 1 の山から 6 枚を引き、`OPEN` の枠 0〜5 に置く。
5. **精神カード**（手順 5・6）: 第一・第三階層は a〜c から 2 枚を無作為に選び、`spirit_slot` を 0・1 に割り当てる。第二・四・五階層は各 1 枚。`spirit_light = 1`（第一〜第四階層）。
6. **強化自我**（手順 7）: 5 枚から人数分を `ENH_DECK` に（`ego_pos` はシャッフル順）、残りは `OUT`。
7. **目標**（手順 8）: 7 枚から 2・3・4 人で 3・4・5 枚を `OPEN`（枠 0 から）、残りは `OUT`。
8. **初期自我の山**（手順 13 の準備）: 8 枚から人数 + 1 枚を `PACK` に（`ego_pos` で表示順を決める）、残りは `OUT`。
9. **プレイヤー**（手順 10・11・12）: `player_persona = 0`、`player_spirit = NULL`。全員に `player_light = 1`・`player_wild_up = 1`。さらに手番順（`player_no` 昇順）で、1 番手: 想い出カード 2 枚、2 番手: 3 枚 + 精神分析トークン 1、3 番手: 4 枚 + 自己構成トークン 1、4 番手: 5 枚 + 心光 +1・自己構成トークン 1（001 §4 の表、Q-21）。引くのは**部屋・場札を置いた後の**レベル 1 の山から。
10. **グローバル値**: §7.2 の初期値。`deck1_initial`・`deck2_initial` は 9 の後の山札の枚数。`phase = SETUP_EGO`、`setup_step = 0`。
11. **統計**を `init`（§14）。
12. 状態 20 へ（ターンプレイヤーはドラフトとミープル配置が終わってから 25 で決める。006 §3.2）。

- 初期自我のドラフトとミープルの配置は、ここでは行わない（006 の 20〜25 の状態で進める）。
- **除外した `OUT` のカードは行として残す**。枚数の整合（144 枚）を検査できるため。クライアントには存在を送らない。

### 14. 統計（`stats.jsonc`）の候補【提案】

D8 のとおり、ID は 10 以上、各節の中で一意、公開後は変更も再利用もしない。名称は英語の原文（自動で翻訳対象）。`setupNewGame` で全統計を `init`（0）する。記録は `Stats/StatsRecorder`（`StatStore` インターフェースで BGA の API を隠す。003 §4.2）を通し、通知の送信と同じ場所で記録する。

テーブル統計（ID 10〜19）:

| キー | ID | 名称 | 型 | 記録タイミング |
| --- | --- | --- | --- | --- |
| `turns_played` | 10 | Number of turns | int | 82 |
| `end_reason` | 11 | Game end condition | int | 95。`value_labels` で `0` = -、`1` = Fragments、`2` = Fleeting recollection、`3` = Apex of the psyche、`4` = Deck ran out |
| `max_rooms_in_turn` | 12 | Most rooms entered in a turn | int | 31 の終了時（最大値） |

プレイヤー統計（ID 20〜）:

| キー | ID | 名称 | 型 | 記録タイミング |
| --- | --- | --- | --- | --- |
| `final_score` | 20 | Composition score | int | 95（自己構成スコア） |
| `memory_score` | 21 | Score: memory cards | int | 95（想い出カード + I08 の下） |
| `objective_score` | 22 | Score: goal cards | int | 95 |
| `final_tier` | 23 | Final psyche tier | int | 95 |
| `turns_taken` | 24 | Turns taken | int | 82 |
| `rooms_entered` | 25 | Rooms entered | int | N06 |
| `rooms_skipped` | 26 | Rooms skipped | int | N07 |
| `cards_drawn` | 27 | Memory cards drawn | int | N12 |
| `cards_composed` | 28 | Memory cards composed | int | N18 |
| `analysis_done` | 29 | Analyses performed | int | N24 |
| `light_gained` | 30 | Inner light gained | int | N16（増） |
| `light_spent` | 31 | Inner light spent | int | N16（減） |
| `cards_discarded_for_light` | 32 | Cards discarded for inner light | int | N15（`LIGHT`） |
| `cards_discarded_limit` | 33 | Cards discarded for hand limit | int | N15（`HAND_LIMIT`） |
| `wild_tokens_used` | 34 | Wild move keys used | int | N06 の `payment` |
| `compose_tokens_used` | 35 | Compose tokens used | int | 60 |
| `analysis_tokens_used` | 36 | Analysis tokens used | int | 60 |
| `goals_claimed` | 37 | Goal cards claimed | int | N22 |
| `key_wisdom` | 38 | Wisdom keys (final) | int | 95（自己構成済みの鍵の本数） |
| `key_will` | 39 | Will keys (final) | int | 95 |
| `key_emotion` | 40 | Emotion keys (final) | int | 95 |
| `key_wild` | 41 | Wild keys (final) | int | 95 |
| `recall_effects` | 42 | Recall effects activated | int | N11（想起） |
| `activated_effects` | 43 | Card effects activated | int | N11（想起以外） |

- テーブル統計 3 + プレイヤー統計 24 × 4 人 = 99（上限 930 に対して余裕。D8）。
- どの統計を残すかは作者の好みに依存する（未確定 B 欄 T-12）。

### 15. 008（カード効果）との境界

007 は**入れ物**、008 は**意味**を決める。008 §10（007 への要求）の全項目に対応済み（照合は 2026-10-20、008 の同日版と）。接続点を次に列挙し、続けて 008 §10 の項目ごとの対応表を置く。

| No. | 接続点 | 007 が決めること | 008 が決めること |
| --- | --- | --- | --- |
| 1 | 効果解決スタック | `effect_frame` の列（`frame_spec`・`frame_op`・`frame_step`・`frame_group`・`frame_payload` を含む）・積み方・取り出し・保留の保存（§6） | `frame_params` と `frame_choice` の JSON の形、`SPEC_RUN` の `step` の入れ子の持ち方、`ORDER_EFFECTS` の衝突判定 |
| 2 | 出典 `frame_source` | 002 の ID（`M052`・`F04`・`R3A`・`I05` など）を `VARCHAR(8)` で持つ。効果の ID は `frame_spec` | 出典ごとの処理の登録（`EffectCatalog`） |
| 3 | ターン内のカウンタ | `TurnContext` のフィールド（§7.3、008 §10.2 の全 15 項目）とその初期化・破棄 | 各フィールドの更新タイミング |
| 4 | 1 ターン 1 回の効果 | `once_used`（`"<player_id>:<spec_id>"`） | 使用済みにする条件（Q-14、FAQ 行 25、R8-16） |
| 5 | 「このターン」の一時効果 | `temp_effects` の汎用の入れ物とターン終了での破棄 | `key` の種類と意味（008 の効果は専用フィールドで足りるため、現状は使わない見込み） |
| 6 | トリガー | frame を積む手段（`pushFront`）、`frame_origin`、イベントのペイロード `frame_payload` | イベント（EV-01〜08）の定義、トリガーの発火条件、反応する効果の探索 |
| 7 | 自己構成済みカードの状態 | 横向き `card_tapped`、順序 `card_pos`、I08 の `UNDER_CARD`、`swapTableauHand`・`releaseUnder` | 鍵の使用可否、M095 の追加の横向き、I03 の入れ替え、M071 の想起効果の使用 |
| 8 | 手札上限の単位 | `frame_unit` の保存 | 効果ごとの `unit` の境界（008 §6.9） |
| 9 | 部屋の入れ替え・ワープ | `room_floor` の入れ替え、`swapRoomCards`、`player_persona` の更新、N08・N28 | M052〜M057・M072・I05 の処理、入室数への算入（M072 は入室済みと見なさない） |
| 10 | 新しいカードの置き場 | 所在の追加（§4.2 の備考）。008 は新しい所在を要求していない | 必要な場合に 007 へ追加を要求 |
| 11 | 通知 | 部品の通知（N11〜N35）と `Notification` の規則 | 効果の結果を部品の組み合わせで表す。部品で表せない場合は 007 に追加を要求 |
| 12 | 想起の `recall` | `TurnContext.recall`（開始時の部屋、公開したカード、発動者、回数） | 回数の増減（M062）、発動の可否 |
| 13 | 床アクションの代替 | `floor_alternatives` の保存、「同種は割引最大のみ」、N33 | 代替の識別子ごとの処理（`ANALYSIS` / `COMPOSE`。N10 の `ALT_*` は通知上の名前） |

#### 15.1 008 §10 の項目ごとの対応

| 008 の項目 | 007 の対応 |
| --- | --- |
| §10.1 `spec_id` / `step` / `op` / `payload` / `group_id` | `frame_spec` / `frame_step` / `frame_op` / `frame_payload` / `frame_group`（§3、§6） |
| §10.1 `state`（`READY` / `WAITING_CHOICE` / `DONE`） | `frame_status`（`READY` / `WAITING`）。`DONE` は行の削除（`complete`） |
| §10.1 `kind` に `OP`・`SPEC_RUN` を追加 | `frame_kind`（`VARCHAR(24)`）に追加。006 の `DRAW` 等は `OP` の別名 |
| §10.1 frame ごとの `pending_choice`、複数の保留 | `frame_choice`。frame が複数あることで満たす（§6） |
| §10.1 `resume` | グローバル値 `resume`（§7.2） |
| §10.2 ターン内カウンタ 15 項目 | §7.3.1 の対応表 |
| §10.3 `tapped`、I08 の `extra_under` | `memory_card.card_tapped`、`UNDER_CARD`（本人以外には枚数のみ）。親が場を離れたら捨札（R8-17・R8-10(d)） |
| §10.3 各部屋（位置・現在の床・現在の想い出カード・`floor_under`） | `room`（`room_pos`・`room_floor`）、`memory_card` の `ROOM` と `UNDER_FLOOR`（引数はどちらも位置）。位置と床 ID を固定しない |
| §10.3 人格の位置、トークン（表 / 使用済み） | `player_persona`、`player_wild_up` / `player_wild_used`（O001 は合計） |
| §10.3 精神カード上の心光と `dest_had_light` | `spirit_light` と、EV-06 の `frame_payload` の `dest_had_light`（§5.2） |
| §10.3 目標・自我の持ち主と山、初期自我の `kind` | `obj_owner`、`ego_owner` と `ego_loc`。`kind` は `EffectCatalog` にあり DB に持たない |
| §10.4 部屋の想い出カードの入れ替え後も券面は非公開 | N28 `after` は `card_id` と `back` のみ（§9、§10.3） |
| §10.4 I05 は本人にだけ通知 | N27 の `_private` |
| §10.4 I08 の下は枚数だけ公開、I04 の床の下は公開 | `UNDER_CARD`（N19）と `UNDER_FLOOR`（N20）。区別して持つ |
| §10.4 強化自我の候補は private args | `ENH_DECK` から `getArgs` の `_private` で再構成（§5.3） |
| §10.5 効果の発動（出典の ID） | N11 |
| §10.5 心光の獲得と `origin` | N16 の `origin` |
| §10.5 部屋の想い出カードの入れ替え / 床カードの入れ替え | N28（`CARD` / `FLOOR`、`after`） |
| §10.5 ワープ | N08（`reason = WARP`） |
| §10.5 床の下への配置と取得 | N20、N13（`source = UNDER_FLOOR`） |
| §10.5 I08 の追加支払い | N19 |
| §10.5 M095 で横向きにしたカード | N17、N18 の `payment` |
| §10.5 想起効果の発動回数（M062） | N09 の `counts`、N11 の `nth` / `times` |
| §10.5 床アクションの代替の記録 | N33 |
| §10.5 次の自己構成の軽減と POSEIDON の軽減（現在の合計） | N34 |
| §10.5 階層の上昇（`via`） | N24 の `via` |
| §10.5 目標の現在の X と得点（本人の値） | N23 |
| §10.5 手札上限の捨て | N15（`HAND_LIMIT`） |
| §10.5 自己構成済み / 手札のカードの現在のエネルギー（M098） | `getAllDatas` の `myHand[].energy`、N35。自己構成済みは印刷の値と同じ |

### 16. Studio で検証する点

実装の最初の PR で Studio 上で検証する。だめだった場合の代替案を併記する。

| No. | 事項 | 代替案 | 回答 |
| --- | --- | --- | --- |
| V-1 | `notify->all` の `_private` が観戦者・リプレイで他人の値を見せないか（N01・N12・N19・N27） | 公開部分は `notify->all`、本人の券面は `notify->player` に分ける（§10.1） | 実装時に確認 |
| V-2 | 観戦者・リプレイで、部屋の裏向きカードの `type`、他人の手札の ID、ドラフトの山、山札の二枚目以降の `back` が見えない | 原因の通知・args を `PublicView` の修正で塞ぐ | 実装時に確認 |
| V-3 | `getAllDatas` の `players` に `player` の追加列が混ざらないか。`playerData` のキーで足りること | `players` を触らず独自のキーを使う（本書の方針） | 実装時に確認 |
| V-4 | `bga_globals` に保存する JSON（`turn`）のサイズ制限（公式に記述なし。数 KB の見込み） | `TurnContext` を専用テーブルに移す（`dbmodel.sql` の変更のみ） | 実装時に確認 |
| V-5 | `effect_frame` の `frame_order` が負の値でも `ORDER BY` が期待どおりに働くか、`AUTO_INCREMENT` が連続しなくても問題ないか | 符号なしにして、積むたびに再採番する | 実装時に確認 |
| V-6 | 1 リクエストの通知の合計が 128 KB に収まるか（24 部屋の移動 + 想起 + 床アクション） | `roomEntered` を 1 通知の配列にまとめる | 実装時に確認 |
| V-7 | BGA が開始時に席順をランダム化するか（`player_no = 1` がランダムな人か）。再戦時に維持されるか（`disable_player_order_swap_on_rematch`） | `bga_rand` で先頭を決め、`player_no` を回した手番順の列を持つ | 実装時に確認 |
| V-8 | `$this->bga->playerScore` を終局までほとんど書かないとき、プレイヤーパネルの標準のスコア欄が 0 になる動作（009 §6 は独自カウンターで見せる） | 009 の技術判断 No.9 で決める | 実装時に確認 |
| V-9 | 全員 0 点の同順位で結果画面がどう表示されるか（006 V-8 と共通） | `losers_not_ranked` を `false` に戻し、ログで明示する | 実装時に確認 |

### 17. 他の仕様書への申し送り

| 仕様書 | 引き継ぐ前提 |
| --- | --- |
| 006（状態遷移） | 本書の `effect_frame`・`resume`・`TurnContext` が 006 §15 の「007 への申し送り」を満たす。006 の args の `_private` の中身（ドラフトの候補、強化自我の候補）は `ego_card` の `PACK` / `ENH_DECK` から再構成する（§5.3）。006 の枠組みは変えないが、本文には 008 §13 のとおり frame の `kind` への `OP`・`SPEC_RUN` と `choice_kind` への `PICK_SPIRIT_BRANCH` の追加が入る（`frame_kind` は文字列なので 007 は変更不要） |
| 008（カード効果） | §15 の境界表と 008 §10 の対応表（全項目に対応済み）。frame の種類・パラメータ、トリガー、`TurnContext` の更新タイミング、効果の通知の組み立てを 008 で決める |
| 009（UI） | gamedatas の形（§8.2）と通知の一覧（§10.2）。`catalog` はカタログのみで、英語の効果文は 009 の `texts.js`。山の一番上の裏面は `decks.top` と通知の `decks` で更新する（Q-44）。`myObjectiveStatus` と N23 で未獲得の目標の現在値を出す。ログのカード名は `card_name`（「No.N 英語名」）を `i18n` で翻訳する |
| 004（CI） | `stats.jsonc` の JSONC 検査（ID の一意性、10 以上）。`dbmodel.sql` のコメントがコードと同じ行にないことの検査（D1） |
| 実装タスク | `Data/` の `Database` インターフェースとリポジトリ、`PublicView`、`Notification`、`GameSetup`、`StatsRecorder`。PHPUnit で §9・§12 を検査 |

## エッジケース

| 状況 | 挙動 |
| --- | --- |
| 山札から引く枚数が残りより多い | `drawFromDeck` は引けた分を返し、`deck_out` にレベルを追加する。判定は frame / アクションの終了時（001 §14-2、006 §9.1）。山札が 0 枚になっただけでは立てない |
| 山札が空のとき `decks.top` | null。空き枠（009 §3.3） |
| 部屋の補充でレベル 2 の山が足りない | 補充できるだけ補充し、`deck_out` に 2 を追加。続けて N30・N31（001 §13、§14-2） |
| 想起の途中で部屋の `ROOM` のカードが 0 枚（獲得後） | 次の補充（80）まで 0 枚。`rooms[].card = null`（009 §3.1 の破線の空き枠） |
| 同じ位置に複数のプレイヤーがいる | `player_persona` が同じ値。制約なし（001 §6） |
| 同じ `card_type` を複数枚、手札に持つ | 可。`memory_card` に別行（`card_id` は別）。自己構成は 1 人 1 枚だけ（001 §9-1） |
| 横向きのカードが自己構成済みエリアを離れる（I03 で捨札へ、`SWAP_SAME_COST`） | I03 で捨札へ: `move` が `card_tapped` を 0 に戻す。`SWAP_SAME_COST`: 手札から出るカードが相手の `card_pos` と `card_tapped` を引き継ぎ（008 R8-10(b)）、出ていくカードは縦向きで手札へ。どちらも I08 の `UNDER_CARD` は各レベルの捨札へ（`releaseUnder`。R-3 → 008 R8-10(d)・R8-17(c)） |
| 床の入れ替えで `UNDER_FLOOR` のカード | 位置に付くので動かない（`card_loc_arg` は部屋の位置。008 R8-11(b)・Q-60 で確定） |
| ドラフト中の再読み込み | 候補は `getArgs` の `_private` から。選択済みなら `myPickedEgo` が `getAllDatas` に入る。待機の表示は公開部分 |
| 第三階層の強化自我の選択中の再読み込み | 71 の `getArgs` が `ENH_DECK` から候補を再構成。`effect_frame` の `WAITING` が選択の定義を持つ |
| 観戦者・リプレイ | `myHand` などは空。`playerData` は全員を同じ形で返す。手札は枚数のみ |
| プレイヤーが離脱（zombie） | その人の手札・自己構成済みはそのまま残る。006 §10 の動作で進む。DB に特別な状態は持たない |
| 強化自我の山が人数分あるので 0 枚にならない | 通常は起きない。0 枚なら空き枠（009 §3.5）で、獲得は例外ではなく不発 |
| 勝者がいる終局で `player_score` | 勝者 1、他 0。全員敗北は全員 0（001 §15） |
| 通知の合計が 128 KB を超えそう | `roomEntered` などを配列にまとめる（V-6）。`discards` の全件は `getAllDatas` だけで送る（通知は追加分のみ） |

## 未確定事項

### A. ルールの質問（作者の回答が必要）

質問は rule-questions.md の第 4 弾（Q-50〜Q-69）に記録した。作者は全件を暫定案で了承した（2026-10-20）。

| No. | 質問 | 暫定案 | 根拠 | 回答 |
| --- | --- | --- | --- | --- |
| R-1 | **008 R8-11(b) に統合（確定）。** 床の入れ替え（M055〜M057）で、部屋の裏向きの想い出カードは床と一緒に動くか、位置に残るか（「部屋 = 床カード + その上の想い出カード」） | 位置に残る（床だけが入れ替わる。効果文が「床」の入れ替えと書いており、想い出カードの入れ替え M052〜M054 が別にあるため）。DB は `room_floor` の入れ替えだけで済む | 001 §3、002 §6.4 | 008 R8-11(b)（Q-60）として暫定案のとおり（2026-10-20） |
| R-2 | **008 R8-15(c)(d)・R8-11(b) に統合（確定）。** I04（論理学者）が床カードの下に置いた想い出カードは、表向き（全員が見える）か裏向きか。床の入れ替えで一緒に動くか | 表向きの公開情報。支払いで捨てるカードは捨札で公開されるため。位置に付き、床の入れ替えでは動かない（008 R8-11(b) に合わせる） | 002 §9 の I04、FAQ 行 33 | 008 R8-15（Q-64）・R8-11(b)（Q-60）として暫定案のとおり（2026-10-20） |
| R-3 | **008 R8-10(d)・R8-17(c) に統合（確定）。** I08（主人公）が自己構成したカードの下に置いた想い出カードは、持ち主以外も中身を見られるか（枚数は公開）。親の自己構成済みカードが場を離れるとき（I03 で捨札へ、`SWAP_SAME_COST` で手札へ）、下のカードはどうなるか | 中身は持ち主だけが見られる（裏向きの印刷どおり）。親が場を離れたら、下のカードは各レベルの捨札へ（自己構成スコア +1 も失われる） | 002 §9 の I08、FAQ 行 31〜32 | 008 R8-10(d)（Q-59）・R8-17(c)（Q-66）として暫定案のとおり（2026-10-20） |

### B. 技術判断（メインエージェントが判断する）

| No. | 判断 | 暫定案 | 理由 | 回答 |
| --- | --- | --- | --- | --- |
| T-1 | カードのテーブルの分け方 | 想い出カードは `memory_card`（144 行）、他は種類ごとの小さな表（`room`・`spirit_card`・`ego_card`・`objective_card`）。共通の `card` 表にしない | 状態の性質（所在・位置・心光・秘密の所在・枠）が種類ごとに違う。公開範囲の規則が混ざらない（§3） | メインエージェントの判断で暫定案のとおり（2026-10-20） |
| T-2 | 効果解決スタックの保存先 | 専用テーブル `effect_frame`（グローバル値の JSON にしない） | 実行順を `ORDER BY` で扱い、先頭挿入を再採番なしで行える。1 件ずつの更新が小さく、保留中の選択を frame の状態にできる（§6） | メインエージェントの判断で暫定案のとおり（2026-10-20） |
| T-3 | プレイヤーの値の持ち方 | `player` の追加列 + `PlayerValues`（`PlayerCounter` を使わない） | `Database` インターフェースの裏に隠して PHPUnit で検査できる。`PlayerCounter` のドキュメントを確認できていない（U-1）。Studio の例では使えるので、将来、カウンターの通知機能が欲しければ置き換えられる | メインエージェントの判断で暫定案のとおり（2026-10-20） |
| T-4 | カード ID の採番 | 種類リストをシャッフルして 1〜144 を振り、山の順序は別にシャッフル | 003 §5.4 の「番号順にしない」を満たす最小の実装。ID から種類・山の順序が推測できない（§4.3） | メインエージェントの判断で暫定案のとおり（2026-10-20） |
| T-5 | ターン内の入れ物 | グローバル値 1 つ（`turn`、JSON）の `TurnContext` | ターン開始での一括初期化・ターン終了での一括破棄が簡単で、008 がフィールドを足しても `dbmodel.sql` を変えずに済む。サイズ制限は V-4 で確認 | メインエージェントの判断で暫定案のとおり（2026-10-20） |
| T-6 | `_private` の使用範囲 | 4 つの通知（N01・N12・N19・N27）に限る。V-1 で検証し、だめなら 2 通知に分ける | 003 §5.4 の方針に従いつつ、姉妹 PE の「未確認」を踏まえて範囲を絞る | メインエージェントの判断で暫定案のとおり（2026-10-20） |
| T-7 | `player_score` の扱い | 終局まで 0。終局時に勝者 1・他 0 | 001 §15、006 §9.3、009 §6 | メインエージェントの判断で暫定案のとおり（2026-10-20） |
| T-8 | 手番順 | `player_no`（BGA の席順）をそのまま使い、専用の列を作らない | Q-18 の「ランダム」を BGA の席順のランダム化で満たす。V-7 で確認 | メインエージェントの判断で暫定案のとおり（2026-10-20） |
| T-9 | 部屋の想い出カードと床の下のカードの付き方 | どちらも位置に付ける（`ROOM`・`UNDER_FLOOR` の引数 = 位置）。床の入れ替えでは動かない（R-1。008 R8-11(b)・Q-60 で確定） | 入れ替えは `room_floor` だけで済み、DB の操作が小さい | メインエージェントの判断で暫定案のとおり（2026-10-20） |
| T-10 | 目標の現在値の送り方 | サーバーが計算し、`getAllDatas` の `myObjectiveStatus` と通知 N23（本人宛て）で送る | クライアントは `TurnContext` の一部（内部値）を持たない。条件の判定をサーバーに一本化する（009 §3.6 の要求） | メインエージェントの判断で暫定案のとおり（2026-10-20） |
| T-11 | 山札の一番上の裏面 | 山を動かす通知に `decks`（枚数 + 一番上の `back`）を含める。一番上以外の `back` を返す API は作らない | Q-44（一番上のみ公開）と 009 §3.3 | メインエージェントの判断で暫定案のとおり（2026-10-20） |
| T-12 | 統計の項目 | §14 の候補 | 作者の好みで増減してよい | メインエージェントの判断で暫定案のとおり（2026-10-20） |
| T-13 | 想い出カードの `OUT` を行として残す | 残す（クライアントには送らない） | 144 枚の整合を検査できる | メインエージェントの判断で暫定案のとおり（2026-10-20） |
| T-14 | 通知の部品方式 | 効果ごとの専用通知を作らず、部品（N11〜N35）の組み合わせで表す | 通知の数の爆発を避ける。008 と同じ約束（§10.2 の注記） | メインエージェントの判断で暫定案のとおり（2026-10-20） |

## 受け入れ条件

- [ ] `dbmodel.sql` の案が全テーブル・全列について意味・型・公開範囲を説明しており、コメントがコードと同じ行になく、全テーブルに主キーがある
- [ ] 想い出カードの所在が一覧され、山札・捨札・場札・部屋・手札・自己構成済み・床の下・カードの下・除外の公開範囲が定義されている
- [ ] カード ID の採番が番号順でなく、ID から種類・山の順序が推測できないことが説明されている
- [ ] 効果解決スタックの保存形式（実行順、積み方、取り出し、保留中の選択、`resume`）が 006 §7 と矛盾なく定義されている
- [ ] グローバル値、プレイヤー値、`TurnContext` が、006 §15 の 007 への申し送りと 002 の効果が必要とする項目を網羅している
- [ ] `getAllDatas` が視点（本人・他人・観戦者）ごとに何を返すかを表と JSON で示し、非公開情報を含まない
- [ ] 山札は枚数と一番上の裏面のみ、強化自我の山は枚数のみ、手札は枚数のみ（Q-44、003 §5.4）が通知と `getAllDatas` の両方で守られる
- [ ] 通知の一覧が、名前・送り先・args・ログ文言（英語原文）・翻訳の扱いを持ち、山の一番上の裏面の更新を含む
- [ ] セットアップのデータ生成が人数別の除外・配置・配札を 001 §4 と一致して定義している
- [ ] 統計の候補が ID・型・記録タイミングつきで挙がっている
- [ ] 008 との境界が接続点ごとに列挙されている
- [ ] 未確定事項がルールの質問と技術判断に分けて書かれている
