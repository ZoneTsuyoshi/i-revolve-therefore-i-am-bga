# 002: カードデータ仕様

| 項目 | 内容 |
| --- | --- |
| ステータス | Approved |
| 対応タスク | T-002 |
| 作成日 | 2026-10-20 |
| 関連仕様書 | 001（ゲームルール仕様。並行して作成中）、003（BGA プロジェクト構成）、008（カード効果の実装方針） |

## 目的

『我廻る、故に我あり』の全カード（想い出カード 99 種 144 枚、床カード 12 枚、精神カード 9 枚、初期自我カード 8 枚、強化自我カード 5 枚、目標カード 7 枚）とトークンのデータを、実装がそのまま定数化できる形で確定させる。**印刷物（カード画像・ルールブック）を正**とし、サイト側の `card.csv` は下敷きとして使って、食い違いは 2026-10-20 にサイト側で修正済み（「16. `card.csv` と印刷物の差分（修正済み）」に履歴を残す）。`CLAUDE.md` の用語集で保留していた英語識別子もここで決める。

## スコープ

- 対象: データ項目の定義、列挙値と英語識別子、全カードとトークンの一覧、`card.csv` の列の意味、`src/` への取り込み方針、`card.csv` と印刷物の差分の修正履歴、カード個別の FAQ 裁定の転記、判断できない点の洗い出し。
- 対象外: ゲームの進行手順・勝利条件・人数別の除外枚数（仕様書 001）、効果文の機械可読な構造化（タイミング・条件・効果量の粒度。仕様書 008）、ファイル構成・クラス名（仕様書 003）。効果文を本書の言葉で解釈し直すこともしない。解釈が必要な点は「未確定事項」として作者に質問する。

## 仕様

### 1. 根拠と読み方

- **印刷物が正**。想い出カード 99 種の表面、裏面 6 種（`Rev1B` 〜 `Rev2H`）、床カード 12 枚と裏面 2 種、精神カード 9 枚と裏面、初期自我 8 枚、強化自我 5 枚、目標 7 枚、トークン画像、鍵・錠前などのアイコンを、すべて画像で開いて確認した（`data/cards/`、`data/tokens/`、`data/icons/`）。ルールブックのページは下部のローマ数字で示す（p.I 内容物、p.II セットアップ、p.IV 移動、p.VI 自己構成とカードの見方、p.VII 精神分析、p.VIII ゲームサマリー）。
- `card.csv`（サイト側 `i-revolve-therefore-i-am/data/card.csv`。2026-10-20 のサイト側コミット `62cfdc91` で印刷物に合わせて修正済み。ヘッダを除き 119 行）は、想い出カード 99 行、初期自我 8 行、強化自我 5 行、目標 7 行からなる。`n_card` の合計は 144 で、p.I の内容物（レベル 1 が 120 枚、レベル 2 が 24 枚）と一致する。**床カードと精神カードは `card.csv` に無い**ため、本書が初出のデータになる。
- 印刷と食い違っていた `card.csv` の 14 件は、作者の了承（rule-questions Q-15）を受けて 2026-10-20 にサイト側で修正済みで、現在の差分は 0 件（16 節に修正履歴）。本書の表は印刷に従い、修正後の `card.csv` と全件一致することを確認した。
- 想い出カード 1 枚ごとの枚数（`n_card`）は印刷物からは確認できない（総数 144、レベル別 120 / 24 のみ p.I で確認）。枚数は `card.csv` の値を正とする（作者確認済み。rule-questions Q-33、未確定事項 No.1）。
- 効果文の日本語は印刷どおり。アイコンは 2 節の凡例で角括弧表記に置き換えた。句読点・全角半角は画像に合わせた（`1/2/3` のような半角の区切りは印刷どおり）。
- FAQ は `faq.csv`（ヘッダを 1 行目とした行番号。ヘッダを含め 41 行、データ 40 行）のうち、`card_ids` や `add_display_ids` にカードが挙がる行を 15 節に転記した。

### 2. 表記の凡例

効果文中のアイコンは次の角括弧表記で記す。`data/icons/` に画像があるものは画像名を添えた。

| 表記 | アイコン | 画像 | 備考 |
| --- | --- | --- | --- |
| `[心光]` | 青い円盤に白い花（心光トークン） | `data/tokens/tokenHR` | p.I |
| `[山札]` | 想い出カードから矢印が出る（+1 付き）。想い出の山札から引いて手札に加える | `data/icons/draw@4x` | p.VIII |
| `[場札]` | 点線で囲んだ 6 枚から矢印が出る（+1 付き）。想い出の場札から選んで手札に加える | `data/icons/opendraw@4x` | p.VIII |
| `[エネルギー]` | 紫の筆跡（自己構成エネルギー。コスト表記に使う） | `data/icons/cost@4x` | p.VI |
| `[知恵鍵]` `[意志鍵]` `[感情鍵]` `[万能鍵]` | 鍵のアイコン（5 節） | `key_book` / `key_fire` / `key_heart` / `key_general` | p.VI |
| `[万能移動鍵]` | 万能移動鍵トークン（表面） | `data/tokens/tokenM` | p.I、p.IV |
| `[精神]` | 「精神」と書かれた精神カードのアイコン | — | p.II、O6 |
| `[床]` | 「床」と書かれた床カードのアイコン | — | 想起効果の ALT 表記にも使う |

効果の種類のアイコン（文頭に付く）は、砂時計 = ターン開始時、歯車 = 起動、∞ = 常時、稲妻 = 即時、手 = 保有時（p.VI）。本書の表では識別子（3.3）で示し、文頭には書かない。ただし砂時計付きの効果（M052〜M057、M072）は、印刷で文中にも「ターン開始時、」と書かれているため、文に含めた。

### 3. 識別子の規則と列挙

#### 3.1 識別子の規則

| 対象 | 規則 | 例 |
| --- | --- | --- |
| 想い出カード ID | `M` + 3 桁。印刷の `No.` の番号（= `card.csv` の `card_id`、画像 `memory/N`）と同じ | `M001`、`M099` |
| 床カード ID | `F` + 2 桁。画像 `floor/F1`〜`F12` の番号（= 部屋の ID）と同じ | `F01`、`F12` |
| 精神カード ID | `R` + 階層 + 分岐。画像 `analysis/R*` の名前を大文字にした | `R1A`、`R2`、`R3C`、`R5` |
| 初期自我 / 強化自我 / 目標の ID | カード右上などに印刷された ID（`I01`〜`I08`、`E01`〜`E05`、`O001`〜`O007`）。`card.csv` の `I1` などとは桁数が違うので注意 | `I03`、`E05`、`O007` |
| `name_id` | 英語名案の `snake_case`（記号・アクセントは除く）。想い出カード・自我カード・床カードでは一意 | `abandoned_idea`、`deja_vu` |
| 列挙値 | `UPPER_SNAKE_CASE` | `WISDOM`、`TURN_START` |
| 錠前・裏面 | 画像名の `Rev` + レベル + `B` / `F` / `H` | `Rev1B`、`Rev2H` |
| 配列のキー・項目名 | `snake_case`（4 節） | `energy`、`effect_kind` |

英語名案と英語文は UI 用の翻訳原文の**案**であり、必須ではない。後続タスク（UI・翻訳）で確定する。英語識別子（`name_id` と列挙値）はコードの参照名としてここで確定させる。ただし `name_id` の綴りは英語名案が改まったときにだけ見直す（その場合も仕様書の改訂で行う）。

#### 3.2 鍵（`KeyType`）

| 日本語 | 識別子 | 鍵のアイコン | 錠前のアイコン | 色 |
| --- | --- | --- | --- | --- |
| 知恵 | `WISDOM` | `key_book` | `lock_book` | 青（本） |
| 意志 | `WILL` | `key_fire` | `lock_fire` | 金（炎・松明） |
| 感情 | `EMOTION` | `key_heart` | `lock_heart` | ピンク（ハート） |
| 万能 | `WILD` | `key_general` | （錠前なし） | 灰（歯車形の頭） |

根拠は 5 節。万能鍵は他の 3 種のいずれの鍵のコストにも使える（p.VI）。

#### 3.3 効果の種類（`EffectKind`）

| 日本語 | 識別子 | アイコン | 意味（p.VI） |
| --- | --- | --- | --- |
| ターン開始時 | `TURN_START` | 砂時計 | ターン開始時に発動する（移動フェイズより前に解決。p.IV） |
| 起動 | `ACTIVATED` | 歯車 | 特定の条件を満たしたときに（ターン中何回でも）発動する |
| 常時 | `CONSTANT` | ∞ | 常に発動する |
| 即時 | `INSTANT` | 稲妻 | 自己構成時、一度だけ発動する |
| 保有時 | `HELD` | 手 | 手札に保有しているときに発動する |
| （なし） | `NONE` | なし | 効果文が無いカード（M001〜M051、M082〜M084、M091〜M093）。想起効果のみを持つ |

#### 3.4 想起効果の識別子（`RecallEffect`）

想起効果はカード下部のアイコンで示される。p.VIII の「想起効果」一覧と画像から次の 13 種に分けた。本書では識別子と対応アイコン・意味（p.VIII の注釈）までを定め、処理は 008 で決める。

| 識別子 | アイコン | 意味（p.VIII） | 該当する想い出カード |
| --- | --- | --- | --- |
| `DRAW_1` | `[山札]`（+1） | 想い出の山札から 1 枚引き、手札に加える | M001〜M012、M040〜M042、M073〜M075、M082〜M084、M097〜M099（24 種） |
| `OPEN_DRAW_1` | `[場札]`（+1） | 想い出の場札から 1 枚選び、手札に加える | M015、M018、M021、M045、M062〜M063、M078、M090、M093（9 種） |
| `LIGHT_1` | `[心光]`（+1） | 心光を 1 つ得る | M013、M016、M019、M043、M058〜M059、M076、M088、M091（9 種） |
| `NEXT_COMPOSE_DISCOUNT_1` | `[エネルギー]`（-1） | （想起効果）このターンで次の自己構成アクションにおけるエネルギー -1 | M014、M017、M020、M044、M060〜M061、M077、M089、M092（9 種） |
| `DISCARD_FOR_LIGHT` | 想い出 -X ⇒ `[心光]` +X | 手札から想い出カードを好きな枚数捨て、その枚数だけ心光を得る | M022、M025、M028、M046、M070、M079、M085（7 種） |
| `SWAP_HAND_FIELD` | 場札 ⇄ X | 手札の想い出カードと想い出の場札を好きな枚数入れ替える | M024、M027、M030、M048、M072、M081、M087（7 種） |
| `SWAP_SAME_COST` | 想い出 ⇄（= `[エネルギー]`） | 自己構成済みの想い出カード 1 枚と同じコストの手札の想い出カードを交換してもよい | M023、M026、M029、M047、M071、M080、M086（7 種） |
| `ALT_ANALYSIS` | [床] ALT ⇒ 精神分析 | 床アクションの代わりに精神分析アクションを行ってもよい | M031、M034、M037、M049、M052、M055、M064、M067（8 種） |
| `ALT_COMPOSE` | [床] ALT ⇒ 自己構成 | 床アクションの代わりに自己構成アクションを行ってもよい | M032、M035、M038、M050、M053、M056、M065、M068（8 種） |
| `ALT_ANALYSIS_DISCOUNT_1` | [床] ALT ⇒ 精神分析（-1） | 床アクションの代わりに、コスト -1 で精神分析アクションを行ってもよい | M094（1 種） |
| `ALT_COMPOSE_DISCOUNT_1` | [床] ALT ⇒ 自己構成（-1） | 床アクションの代わりに、エネルギー -1 で自己構成アクションを行ってもよい | M095（1 種） |
| `MOVE_NEXT_LV1` | 人格 → レベル 1 の部屋 ⇒ 場札 +1 | 次のレベル 1 の部屋に移動してもよい。そうした場合、場札を 1 枚獲得し、移動した部屋で床アクションを行う | M033、M036、M039、M051、M054、M057、M066、M069（8 種） |
| `MOVE_NEXT_LV2` | 人格 → レベル 2 の部屋 ⇒ 場札 +1 | 次のレベル 2 の部屋に移動してもよい。そうした場合、場札を 1 枚獲得し、移動した部屋で床アクションを行う | M096（1 種） |

「[床] ALT」の `[床]` は床カードのアイコン。FAQ 行 7・14 により、床アクションを代替する想起効果は手番プレイヤーのみ発動できる（001・008 で扱う）。`MOVE_NEXT_LV1` の対象は FAQ 行 8・9・13・23・33 に、`MOVE_NEXT_LV2` が M096 のみにあることは FAQ の `recall_card_ids` 列（`33,36,39,51,54,57,66,69,96`）に整合する。

#### 3.5 想起効果のグループ（`recall_group`）

`card.csv` の `otype` 列は想起効果の大分類に対応する（4 節）。本書では識別子を次のとおり対応させる。

| `otype` | 識別子 | 内容 | 想起効果（3.4） |
| --- | --- | --- | --- |
| `c` | `DRAW` | 山札から引く | `DRAW_1` |
| `s` | `GAIN` | 獲得（心光・次の自己構成の割引・場札） | `LIGHT_1`、`NEXT_COMPOSE_DISCOUNT_1`、`OPEN_DRAW_1` |
| `w` | `EXCHANGE` | 交換・変換 | `DISCARD_FOR_LIGHT`、`SWAP_SAME_COST`、`SWAP_HAND_FIELD` |
| `a` | `ALTERNATE` | 床アクションの代替・移動 | `ALT_ANALYSIS`、`ALT_COMPOSE`、`MOVE_NEXT_LV1` |
| `a2` | `ALTERNATE_DISCOUNT` | 床アクションの代替（-1） | `ALT_ANALYSIS_DISCOUNT_1`、`ALT_COMPOSE_DISCOUNT_1`、`MOVE_NEXT_LV2` |

この対応は、`otype` と画像のアイコンが全 99 種で矛盾しないことから決めた。作者の了承を得ている（rule-questions Q-33、未確定事項 No.2）。`recall_group` の名前は本書の案。

### 4. `card.csv` の列の意味

ヘッダは `card_id, lv2_id, card_name, id_name, n_card, original_id, level, engine, e1, e2, cost, r, otype, score, condition, effect`。画像との突き合わせで次のとおり解釈した。作者の確認を得た列は rule-questions Q-33 に記録している。

| 列 | 意味 | 想い出カードでの値 | 確度 |
| --- | --- | --- | --- |
| `card_id` | カード番号。想い出は 1〜99（印刷の `No.`）。自我・目標は `I1`〜`I8`、`E1`〜`E5`、`O1`〜`O7` | 数字 | 確定 |
| `lv2_id` | レベル 2 想い出カードの通し番号 | レベル 2（No.85〜99）のみ 1〜15 | 確定 |
| `card_name` | 想い出カードの名前（`No.` なし） | 名前 | 確定 |
| `id_name` | 想い出は `No.N 名前`。自我・目標では**名前そのもの**がここに入る（`card_name` は空） | | 確定 |
| `n_card` | その番号のカードの枚数 | 1〜4（合計 144） | 確定（作者確認済み。Q-33） |
| `original_id` | 原本マスターの通し番号。同一効果・同一レイアウトで鍵の種類だけ違う 3 枚が同じ値を共有する（例: No.1〜3 = 1、No.73〜75 = 47）。欠番がある | 数字 | 作者了承（Q-33）。実装では使わない |
| `level` | 想い出カードのレベル（1 / 2） | 1 / 2 | 確定 |
| `engine` | 鍵の型の分類。値は `1`（81 種）、`任意1`（12 種）、`異種2`（3 種）、`任意2`（3 種）。`e1`・`e2` から導けるので冗長 | | 作者了承（Q-33）。実装では使わない |
| `e1`、`e2` | 自己構成したときに使える鍵。カード右の鍵アイコンを上から並べた順（`e1` が上）。`任意` = 万能鍵（`card.csv` の表記は「任意」のまま。作者の判断でそのままにしてある） | 知恵 / 感情 / 意志 / 任意 | 確定（全 99 種を画像と照合。修正後も同じ） |
| `cost` | 自己構成エネルギー（左上の紫の数字） | 2〜7 | 確定（全件一致） |
| `r` | 裏面の錠前（移動コスト）の種類。`知恵` = `B`（本）、`意志` = `F`（炎）、`感情` = `H`（ハート）。レベル 2 は同じ種類の錠前が 2 つ | 知恵 / 感情 / 意志 | 確定（作者確認済み。Q-33、5.3） |
| `otype` | 想起効果の大分類（3.5） | `c` / `s` / `w` / `a` / `a2` | 確定（全件で画像と矛盾なし） |
| `score` | 自己構成スコア（右上の結晶の数字） | 1〜4 | 確定（全件一致） |
| `condition` | 想い出カードでは効果の種類（ターン開始時 / 起動 / 常時 / 即時 / 保有時）。強化自我でも同じ使い方。**目標カードでは達成条件** | | 確定 |
| `effect` | 効果文。目標カードでは SCORE 欄の説明。アイコンは `[心光]` などの角括弧に置き換えてある | | 確定（2026-10-20 の修正後は印刷と一致。16 節） |

想起効果の具体的な内容（`DRAW_1` など）は `card.csv` に無く、カード下部のアイコンにしか無い。本書の 6 節が初出になる。

### 5. 鍵と錠前

#### 5.1 鍵のアイコンとの対応（確定）

- ルールブック p.VI は鍵を「意志（金色の松明）、知恵（青い本）、感情（ピンクのハート）、万能（灰色の歯車形）」と並べている。画像名との対応は `key_fire` = 意志、`key_book` = 知恵、`key_heart` = 感情、`key_general` = 万能。
- 想い出カード 99 種すべてについて、右の鍵アイコンの色・形を画像で読み取り、`card.csv` の `e1`・`e2` と照合して**全件一致**した（M040〜M051 と M091〜M093 は万能鍵、M082〜M090 と M094〜M099 は 2 本）。
- 目標カード O003 の `MAX( 金, 青, ピンク )` と O007 の `( 金, 青, ピンク )` は、意志・知恵・感情の順で 3 種の鍵を並べている（p.VI の並びと一致）。

#### 5.2 鍵の本数

| 構成 | 該当 |
| --- | --- |
| 1 本（`WISDOM` / `EMOTION` / `WILL`） | M001〜M039、M052〜M081 |
| 1 本（`WILD`） | M040〜M051 |
| 2 本（異なる種類） | M082〜M090（M082 `WISDOM`+`EMOTION`、M083 `EMOTION`+`WILL`、M084 `WILL`+`WISDOM`、M085 `WISDOM`+`EMOTION`、M086 `EMOTION`+`WILL`、M087 `WILL`+`WISDOM`、M088 `WISDOM`+`WILL`、M089 `EMOTION`+`WISDOM`、M090 `WILL`+`EMOTION`） |
| 2 本（同じ種類） | M094〜M099（M094・M097 `WISDOM`、M095・M098 `EMOTION`、M096・M099 `WILL`） |
| 2 本（`WILD` + `WILD`） | M091〜M093 |

p.VI により、複数の鍵が描かれたカードの鍵は、複数の移動に分割して使うことはできない（連続した部屋を移動するときは同時に使ったとみなせる。p.IV）。精神分析では複数のコストを同時に支払えるが、移動や他のアクションと分割はできない（p.VII）。処理は 001 で扱う。

#### 5.3 想い出カードの裏面（錠前）

| 画像 | 内容 | 枚数（`card.csv` の `r` 列より） |
| --- | --- | --- |
| `Rev1B` | レベル 1、青い本の錠前 × 1（`WISDOM`） | 40 |
| `Rev1F` | レベル 1、金の炎の錠前 × 1（`WILL`） | 40 |
| `Rev1H` | レベル 1、ピンクのハートの錠前 × 1（`EMOTION`） | 40 |
| `Rev2B` | レベル 2、青い本の錠前 × 2 | 8 |
| `Rev2F` | レベル 2、金の炎の錠前 × 2 | 8 |
| `Rev2H` | レベル 2、ピンクのハートの錠前 × 2 | 8 |

- レベル 1 と レベル 2 の裏面は、背景の大理石模様の金色の筋でも見分けられる（レベル 2 は金の筋が入る）。
- 移動コストは、部屋の想い出カードの裏面の錠前 1 つ（レベル 2 は 2 つ）で、同じ種類の鍵（または万能鍵）で支払う（p.IV）。
- **各カードがどの裏面かは、印刷物で確認できない**（表面と裏面が別の画像で、1 枚のカードの表裏が分かる資料が無い）。`card.csv` の `r` 列を裏面とする。根拠は、`r` が `e1` と無関係な値を取ること（M040〜M051 は `e1` が万能だが `r` は知恵 / 感情 / 意志）、レベル 1 が 40 / 40 / 40、レベル 2 が 8 / 8 / 8 と均等に分かれること、p.II のセットアップ図で ARES が感情、DIONYSUS が意志の錠前（p.IV の例でも同じ組み合わせ）と読めること。この推定は作者に確認し、了承された（rule-questions Q-33、未確定事項 No.3）。
- 鍵と錠前の対応は、`lock_book` = 知恵、`lock_fire` = 意志、`lock_heart` = 感情（鍵と同じ絵柄）。

### 6. 想い出カード（99 種・144 枚）

枚数の合計は 144（レベル 1 が 84 種 120 枚、レベル 2 が 15 種 24 枚）。表の列は次のとおり。

| 列 | 内容 |
| --- | --- |
| 番号 | `M` + 印刷の `No.`（2 節） |
| 名前（印刷） | カード名。画像と `card.csv` が全件一致 |
| 枚 | `n_card`（作者確認済み） |
| Lv | レベル。1 は銀（I）、2 は金（II）の枠（四隅の部屋のみ。p.VI） |
| E | 自己構成エネルギー（左上の数字） |
| 鍵 | 右の鍵アイコン（上から順）。識別子は 3.2 |
| 裏面 | 裏面の画像名（5.3。作者確認済み） |
| SC | 自己構成スコア（右上の結晶の数字） |
| 効果の種類 | 3.3 |
| 想起効果 | 3.4 |

#### 6.1 データ（M001〜M039）

| 番号 | 名前（印刷） | 英語名案 | `name_id` | 枚 | Lv | E | 鍵（上から） | 裏面 | SC | 効果の種類 | 想起効果 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| M001 | 捨てられたイデア | Abandoned Idea | `abandoned_idea` | 1 | 1 | 2 | `WISDOM` | `Rev1B` | 1 | `NONE` | `DRAW_1` |
| M002 | 既視感 | Deja Vu | `deja_vu` | 1 | 1 | 2 | `EMOTION` | `Rev1H` | 1 | `NONE` | `DRAW_1` |
| M003 | 荒廃 | Desolation | `desolation` | 1 | 1 | 2 | `WILL` | `Rev1F` | 1 | `NONE` | `DRAW_1` |
| M004 | 解かれぬ構造 | Unsolved Structure | `unsolved_structure` | 1 | 1 | 2 | `WISDOM` | `Rev1B` | 1 | `NONE` | `DRAW_1` |
| M005 | 肖像 | Portrait | `portrait` | 1 | 1 | 2 | `EMOTION` | `Rev1H` | 1 | `NONE` | `DRAW_1` |
| M006 | 自由の代償 | Price of Freedom | `price_of_freedom` | 1 | 1 | 2 | `WILL` | `Rev1F` | 1 | `NONE` | `DRAW_1` |
| M007 | 色褪せた写真 | Faded Photograph | `faded_photograph` | 1 | 1 | 2 | `WISDOM` | `Rev1B` | 1 | `NONE` | `DRAW_1` |
| M008 | 予知夢 | Prophetic Dream | `prophetic_dream` | 1 | 1 | 2 | `EMOTION` | `Rev1H` | 1 | `NONE` | `DRAW_1` |
| M009 | 届かなかった想い | Unreached Feelings | `unreached_feelings` | 1 | 1 | 2 | `WILL` | `Rev1F` | 1 | `NONE` | `DRAW_1` |
| M010 | 閉ざされた論理 | Closed Logic | `closed_logic` | 1 | 1 | 2 | `WISDOM` | `Rev1B` | 1 | `NONE` | `DRAW_1` |
| M011 | 涙の輝き | Glitter of Tears | `glitter_of_tears` | 1 | 1 | 2 | `EMOTION` | `Rev1H` | 1 | `NONE` | `DRAW_1` |
| M012 | 虚ろな情報 | Hollow Information | `hollow_information` | 1 | 1 | 2 | `WILL` | `Rev1F` | 1 | `NONE` | `DRAW_1` |
| M013 | 問い | The Question | `the_question` | 1 | 1 | 2 | `WISDOM` | `Rev1B` | 1 | `NONE` | `LIGHT_1` |
| M014 | 叡智の末路 | Fate of Wisdom | `fate_of_wisdom` | 1 | 1 | 2 | `EMOTION` | `Rev1H` | 1 | `NONE` | `NEXT_COMPOSE_DISCOUNT_1` |
| M015 | 意識の縁 | Edge of Consciousness | `edge_of_consciousness` | 1 | 1 | 2 | `WILL` | `Rev1F` | 1 | `NONE` | `OPEN_DRAW_1` |
| M016 | 想い出の忘却 | Forgetting Memories | `forgetting_memories` | 1 | 1 | 2 | `WISDOM` | `Rev1B` | 1 | `NONE` | `LIGHT_1` |
| M017 | 重圧 | Pressure | `pressure` | 1 | 1 | 2 | `EMOTION` | `Rev1H` | 1 | `NONE` | `NEXT_COMPOSE_DISCOUNT_1` |
| M018 | 強大な障壁 | Mighty Barrier | `mighty_barrier` | 1 | 1 | 2 | `WILL` | `Rev1F` | 1 | `NONE` | `OPEN_DRAW_1` |
| M019 | 理解の限界 | Limit of Understanding | `limit_of_understanding` | 1 | 1 | 2 | `WISDOM` | `Rev1B` | 1 | `NONE` | `LIGHT_1` |
| M020 | 幼き日の恐怖 | Childhood Fear | `childhood_fear` | 1 | 1 | 2 | `EMOTION` | `Rev1H` | 1 | `NONE` | `NEXT_COMPOSE_DISCOUNT_1` |
| M021 | 自己の数式 | Formula of the Self | `formula_of_the_self` | 1 | 1 | 2 | `WILL` | `Rev1F` | 1 | `NONE` | `OPEN_DRAW_1` |
| M022 | 理性の自壊 | Collapse of Reason | `collapse_of_reason` | 1 | 1 | 2 | `WISDOM` | `Rev1B` | 1 | `NONE` | `DISCARD_FOR_LIGHT` |
| M023 | 憂鬱 | Melancholy | `melancholy` | 1 | 1 | 2 | `EMOTION` | `Rev1H` | 1 | `NONE` | `SWAP_SAME_COST` |
| M024 | 正義の重み | Weight of Justice | `weight_of_justice` | 1 | 1 | 2 | `WILL` | `Rev1F` | 1 | `NONE` | `SWAP_HAND_FIELD` |
| M025 | 連続性の崩壊 | Broken Continuity | `broken_continuity` | 1 | 1 | 2 | `WISDOM` | `Rev1B` | 1 | `NONE` | `DISCARD_FOR_LIGHT` |
| M026 | 忘れられた約束 | Forgotten Promise | `forgotten_promise` | 1 | 1 | 2 | `EMOTION` | `Rev1H` | 1 | `NONE` | `SWAP_SAME_COST` |
| M027 | 別れの背中 | Back of Parting | `back_of_parting` | 1 | 1 | 2 | `WILL` | `Rev1F` | 1 | `NONE` | `SWAP_HAND_FIELD` |
| M028 | 歪曲する記録 | Distorted Record | `distorted_record` | 1 | 1 | 2 | `WISDOM` | `Rev1B` | 1 | `NONE` | `DISCARD_FOR_LIGHT` |
| M029 | 古代の美学 | Ancient Aesthetics | `ancient_aesthetics` | 1 | 1 | 2 | `EMOTION` | `Rev1H` | 1 | `NONE` | `SWAP_SAME_COST` |
| M030 | 矛盾を抱いた決断 | Contradictory Decision | `contradictory_decision` | 1 | 1 | 2 | `WILL` | `Rev1F` | 1 | `NONE` | `SWAP_HAND_FIELD` |
| M031 | 曖昧な記憶 | Vague Memory | `vague_memory` | 1 | 1 | 2 | `WISDOM` | `Rev1B` | 1 | `NONE` | `ALT_ANALYSIS` |
| M032 | 宙吊りの罪悪感 | Suspended Guilt | `suspended_guilt` | 1 | 1 | 2 | `EMOTION` | `Rev1H` | 1 | `NONE` | `ALT_COMPOSE` |
| M033 | 零れ落ちる星屑 | Spilling Stardust | `spilling_stardust` | 1 | 1 | 2 | `WILL` | `Rev1F` | 1 | `NONE` | `MOVE_NEXT_LV1` |
| M034 | 思考の鍵 | Key of Thought | `key_of_thought` | 1 | 1 | 2 | `WISDOM` | `Rev1B` | 1 | `NONE` | `ALT_ANALYSIS` |
| M035 | 巣立ち | Leaving the Nest | `leaving_the_nest` | 1 | 1 | 2 | `EMOTION` | `Rev1H` | 1 | `NONE` | `ALT_COMPOSE` |
| M036 | 反復する日常 | Repeating Routine | `repeating_routine` | 1 | 1 | 2 | `WILL` | `Rev1F` | 1 | `NONE` | `MOVE_NEXT_LV1` |
| M037 | こだまする問い | Echoing Question | `echoing_question` | 1 | 1 | 2 | `WISDOM` | `Rev1B` | 1 | `NONE` | `ALT_ANALYSIS` |
| M038 | 虚無の囁き | Whisper of the Void | `whisper_of_the_void` | 1 | 1 | 2 | `EMOTION` | `Rev1H` | 1 | `NONE` | `ALT_COMPOSE` |
| M039 | 錯覚の牢獄 | Prison of Illusion | `prison_of_illusion` | 1 | 1 | 2 | `WILL` | `Rev1F` | 1 | `NONE` | `MOVE_NEXT_LV1` |

#### 6.2 データ（M040〜M084）

| 番号 | 名前（印刷） | 英語名案 | `name_id` | 枚 | Lv | E | 鍵（上から） | 裏面 | SC | 効果の種類 | 想起効果 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| M040 | 日記 | Diary | `diary` | 1 | 1 | 3 | `WILD` | `Rev1B` | 2 | `NONE` | `DRAW_1` |
| M041 | 裏切りの果て | End of Betrayal | `end_of_betrayal` | 1 | 1 | 3 | `WILD` | `Rev1H` | 2 | `NONE` | `DRAW_1` |
| M042 | 意見の錯綜 | Tangled Opinions | `tangled_opinions` | 1 | 1 | 3 | `WILD` | `Rev1F` | 2 | `NONE` | `DRAW_1` |
| M043 | アーカイブ | Archive | `archive` | 1 | 1 | 3 | `WILD` | `Rev1B` | 2 | `NONE` | `LIGHT_1` |
| M044 | 嫉妬の茨 | Thorns of Jealousy | `thorns_of_jealousy` | 1 | 1 | 3 | `WILD` | `Rev1H` | 2 | `NONE` | `NEXT_COMPOSE_DISCOUNT_1` |
| M045 | 革新の予感 | Premonition of Innovation | `premonition_of_innovation` | 1 | 1 | 3 | `WILD` | `Rev1F` | 2 | `NONE` | `OPEN_DRAW_1` |
| M046 | 迷宮の起点 | Origin of the Labyrinth | `origin_of_the_labyrinth` | 1 | 1 | 3 | `WILD` | `Rev1B` | 2 | `NONE` | `DISCARD_FOR_LIGHT` |
| M047 | 消えた日常 | Vanished Routine | `vanished_routine` | 1 | 1 | 3 | `WILD` | `Rev1H` | 2 | `NONE` | `SWAP_SAME_COST` |
| M048 | 存在の彼岸 | Beyond Existence | `beyond_existence` | 1 | 1 | 3 | `WILD` | `Rev1F` | 2 | `NONE` | `SWAP_HAND_FIELD` |
| M049 | エイドスの残響 | Echo of Eidos | `echo_of_eidos` | 1 | 1 | 3 | `WILD` | `Rev1B` | 2 | `NONE` | `ALT_ANALYSIS` |
| M050 | 涙の味 | Taste of Tears | `taste_of_tears` | 1 | 1 | 3 | `WILD` | `Rev1H` | 2 | `NONE` | `ALT_COMPOSE` |
| M051 | 螺旋の指南 | Spiral Guidance | `spiral_guidance` | 1 | 1 | 3 | `WILD` | `Rev1F` | 2 | `NONE` | `MOVE_NEXT_LV1` |
| M052 | 選択と未来 | Choice and Future | `choice_and_future` | 1 | 1 | 3 | `WISDOM` | `Rev1B` | 2 | `TURN_START` | `ALT_ANALYSIS` |
| M053 | 主観と客観 | Subjective and Objective | `subjective_and_objective` | 1 | 1 | 3 | `EMOTION` | `Rev1H` | 2 | `TURN_START` | `ALT_COMPOSE` |
| M054 | 自己像 | Self-Image | `self_image` | 1 | 1 | 3 | `WILL` | `Rev1F` | 2 | `TURN_START` | `MOVE_NEXT_LV1` |
| M055 | 記憶の目録 | Catalog of Memories | `catalog_of_memories` | 1 | 1 | 3 | `WISDOM` | `Rev1B` | 2 | `TURN_START` | `ALT_ANALYSIS` |
| M056 | 正義の境界線 | Boundary of Justice | `boundary_of_justice` | 1 | 1 | 3 | `EMOTION` | `Rev1H` | 2 | `TURN_START` | `ALT_COMPOSE` |
| M057 | 封印された選択肢 | Sealed Choice | `sealed_choice` | 1 | 1 | 3 | `WILL` | `Rev1F` | 2 | `TURN_START` | `MOVE_NEXT_LV1` |
| M058 | 観測前夜 | Eve of Observation | `eve_of_observation` | 2 | 1 | 3 | `WISDOM` | `Rev1B` | 2 | `ACTIVATED` | `LIGHT_1` |
| M059 | 矛盾した公理 | Contradictory Axiom | `contradictory_axiom` | 2 | 1 | 3 | `WISDOM` | `Rev1B` | 2 | `CONSTANT` | `LIGHT_1` |
| M060 | あの日の残像 | Afterimage of That Day | `afterimage_of_that_day` | 2 | 1 | 3 | `EMOTION` | `Rev1H` | 2 | `CONSTANT` | `NEXT_COMPOSE_DISCOUNT_1` |
| M061 | 没頭する刻 | Moment of Immersion | `moment_of_immersion` | 2 | 1 | 3 | `EMOTION` | `Rev1H` | 2 | `ACTIVATED` | `NEXT_COMPOSE_DISCOUNT_1` |
| M062 | 自己矛盾 | Self-Contradiction | `self_contradiction` | 2 | 1 | 3 | `WILL` | `Rev1F` | 2 | `ACTIVATED` | `OPEN_DRAW_1` |
| M063 | 閉じたアルバム | Closed Album | `closed_album` | 2 | 1 | 3 | `WILL` | `Rev1F` | 2 | `CONSTANT` | `OPEN_DRAW_1` |
| M064 | 瞬間の保存 | Preserved Moment | `preserved_moment` | 1 | 1 | 3 | `WISDOM` | `Rev1B` | 2 | `ACTIVATED` | `ALT_ANALYSIS` |
| M065 | 思惟の渦中 | Amid Contemplation | `amid_contemplation` | 1 | 1 | 3 | `EMOTION` | `Rev1H` | 2 | `ACTIVATED` | `ALT_COMPOSE` |
| M066 | 偉大なる指導者 | Great Leader | `great_leader` | 1 | 1 | 3 | `WILL` | `Rev1F` | 2 | `ACTIVATED` | `MOVE_NEXT_LV1` |
| M067 | 偉人のひらめき | Great Mind's Inspiration | `great_minds_inspiration` | 3 | 1 | 3 | `WISDOM` | `Rev1B` | 2 | `ACTIVATED` | `ALT_ANALYSIS` |
| M068 | 夏至の午後 | Summer Solstice Afternoon | `summer_solstice_afternoon` | 3 | 1 | 3 | `EMOTION` | `Rev1H` | 2 | `ACTIVATED` | `ALT_COMPOSE` |
| M069 | 希望の篝火 | Bonfire of Hope | `bonfire_of_hope` | 3 | 1 | 3 | `WILL` | `Rev1F` | 2 | `ACTIVATED` | `MOVE_NEXT_LV1` |
| M070 | 終端の構造 | Structure of the Terminus | `structure_of_the_terminus` | 2 | 1 | 4 | `WISDOM` | `Rev1B` | 2 | `CONSTANT` | `DISCARD_FOR_LIGHT` |
| M071 | 子守唄 | Lullaby | `lullaby` | 2 | 1 | 4 | `EMOTION` | `Rev1H` | 2 | `ACTIVATED` | `SWAP_SAME_COST` |
| M072 | 鏡の向こう | Beyond the Mirror | `beyond_the_mirror` | 2 | 1 | 4 | `WILL` | `Rev1F` | 2 | `TURN_START` | `SWAP_HAND_FIELD` |
| M073 | 断絶した思考の輪 | Severed Ring of Thought | `severed_ring_of_thought` | 2 | 1 | 4 | `WISDOM` | `Rev1B` | 2 | `ACTIVATED` | `DRAW_1` |
| M074 | 心の修復 | Mending the Heart | `mending_the_heart` | 2 | 1 | 4 | `EMOTION` | `Rev1H` | 2 | `ACTIVATED` | `DRAW_1` |
| M075 | 友情の証 | Proof of Friendship | `proof_of_friendship` | 2 | 1 | 4 | `WILL` | `Rev1F` | 2 | `ACTIVATED` | `DRAW_1` |
| M076 | 昇華 | Sublimation | `sublimation` | 4 | 1 | 4 | `WISDOM` | `Rev1B` | 2 | `ACTIVATED` | `LIGHT_1` |
| M077 | 眠りの瞬間 | Moment of Sleep | `moment_of_sleep` | 4 | 1 | 4 | `EMOTION` | `Rev1H` | 2 | `CONSTANT` | `NEXT_COMPOSE_DISCOUNT_1` |
| M078 | 夕暮れの約束 | Twilight Promise | `twilight_promise` | 4 | 1 | 4 | `WILL` | `Rev1F` | 2 | `ACTIVATED` | `OPEN_DRAW_1` |
| M079 | 過去への扉 | Door to the Past | `door_to_the_past` | 3 | 1 | 4 | `WISDOM` | `Rev1B` | 2 | `ACTIVATED` | `DISCARD_FOR_LIGHT` |
| M080 | 赦しの言葉 | Words of Forgiveness | `words_of_forgiveness` | 3 | 1 | 4 | `EMOTION` | `Rev1H` | 2 | `ACTIVATED` | `SWAP_SAME_COST` |
| M081 | 無名の憧れ | Nameless Longing | `nameless_longing` | 3 | 1 | 4 | `WILL` | `Rev1F` | 2 | `ACTIVATED` | `SWAP_HAND_FIELD` |
| M082 | 有限と無限の間 | Between Finite and Infinite | `between_finite_and_infinite` | 2 | 1 | 4 | `WISDOM`, `EMOTION` | `Rev1B` | 1 | `NONE` | `DRAW_1` |
| M083 | 愛の誓い | Vow of Love | `vow_of_love` | 2 | 1 | 4 | `EMOTION`, `WILL` | `Rev1H` | 1 | `NONE` | `DRAW_1` |
| M084 | 走り続けた脚 | Legs That Kept Running | `legs_that_kept_running` | 2 | 1 | 4 | `WILL`, `WISDOM` | `Rev1F` | 1 | `NONE` | `DRAW_1` |

#### 6.3 データ（M085〜M099、レベル 2）

| 番号 | 名前（印刷） | 英語名案 | `name_id` | 枚 | Lv | E | 鍵（上から） | 裏面 | SC | 効果の種類 | 想起効果 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| M085 | 照らすもの | That Which Illuminates | `that_which_illuminates` | 2 | 2 | 5 | `WISDOM`, `EMOTION` | `Rev2B` | 3 | `ACTIVATED` | `DISCARD_FOR_LIGHT` |
| M086 | 旋律のしぶき | Spray of Melody | `spray_of_melody` | 2 | 2 | 5 | `EMOTION`, `WILL` | `Rev2H` | 3 | `ACTIVATED` | `SWAP_SAME_COST` |
| M087 | 勇気の残り香 | Lingering Scent of Courage | `lingering_scent_of_courage` | 2 | 2 | 5 | `WILL`, `WISDOM` | `Rev2F` | 3 | `ACTIVATED` | `SWAP_HAND_FIELD` |
| M088 | 知識の方舟 | Ark of Knowledge | `ark_of_knowledge` | 2 | 2 | 5 | `WISDOM`, `WILL` | `Rev2B` | 3 | `INSTANT` | `LIGHT_1` |
| M089 | 好きぴ | Crush | `crush` | 2 | 2 | 6 | `EMOTION`, `WISDOM` | `Rev2H` | 2 | `INSTANT` | `NEXT_COMPOSE_DISCOUNT_1` |
| M090 | 絆の糸 | Thread of Bonds | `thread_of_bonds` | 2 | 2 | 6 | `WILL`, `EMOTION` | `Rev2F` | 3 | `INSTANT` | `OPEN_DRAW_1` |
| M091 | 無限回廊の地図 | Map of the Infinite Cloister | `map_of_the_infinite_cloister` | 1 | 2 | 5 | `WILD`, `WILD` | `Rev2B` | 3 | `NONE` | `LIGHT_1` |
| M092 | 勝利の瞬間 | Moment of Victory | `moment_of_victory` | 1 | 2 | 5 | `WILD`, `WILD` | `Rev2H` | 3 | `NONE` | `NEXT_COMPOSE_DISCOUNT_1` |
| M093 | 彼方への越境 | Crossing Beyond | `crossing_beyond` | 1 | 2 | 5 | `WILD`, `WILD` | `Rev2F` | 3 | `NONE` | `OPEN_DRAW_1` |
| M094 | 知識の迷宮 | Labyrinth of Knowledge | `labyrinth_of_knowledge` | 2 | 2 | 5 | `WISDOM`, `WISDOM` | `Rev2B` | 3 | `CONSTANT` | `ALT_ANALYSIS_DISCOUNT_1` |
| M095 | 歓喜の残滓 | Remnants of Joy | `remnants_of_joy` | 2 | 2 | 6 | `EMOTION`, `EMOTION` | `Rev2H` | 3 | `CONSTANT` | `ALT_COMPOSE_DISCOUNT_1` |
| M096 | 存在の多面性 | Many Facets of Existence | `many_facets_of_existence` | 2 | 2 | 6 | `WILL`, `WILL` | `Rev2F` | 3 | `CONSTANT` | `MOVE_NEXT_LV2` |
| M097 | 文字の恩恵 | Blessing of Letters | `blessing_of_letters` | 1 | 2 | 7 | `WISDOM`, `WISDOM` | `Rev2B` | 4 | `INSTANT` | `DRAW_1` |
| M098 | 母の手の温もり | Warmth of Mother's Hand | `warmth_of_mothers_hand` | 1 | 2 | 7 | `EMOTION`, `EMOTION` | `Rev2H` | 4 | `HELD` | `DRAW_1` |
| M099 | 繋いできた願い | Wishes Passed Down | `wishes_passed_down` | 1 | 2 | 7 | `WILL`, `WILL` | `Rev2F` | 4 | `INSTANT` | `DRAW_1` |

#### 6.4 効果文（印刷どおり）

効果のあるカード 42 種。効果文の無い 57 種（M001〜M051、M082〜M084、M091〜M093）は、想起効果のみを持つ。「関連 FAQ 行」は 15 節の行番号（`faq.csv` のヘッダを 1 行目とした行番号）。

| 番号 | 名前 | 効果の種類 | 効果文（印刷どおり） | 関連 FAQ 行 |
| --- | --- | --- | --- | --- |
| M052 | 選択と未来 | `TURN_START` | ターン開始時、回廊上の2箇所の同レベルの想い出カードを入れ替えてもよい | — |
| M053 | 主観と客観 | `TURN_START` | ターン開始時、回廊上の2箇所の同レベルの想い出カードを入れ替えてもよい | — |
| M054 | 自己像 | `TURN_START` | ターン開始時、回廊上の2箇所の同レベルの想い出カードを入れ替えてもよい | — |
| M055 | 記憶の目録 | `TURN_START` | ターン開始時、回廊上の2箇所のレベル1の[床]を入れ替えてもよい | — |
| M056 | 正義の境界線 | `TURN_START` | ターン開始時、回廊上の2箇所のレベル1の[床]を入れ替えてもよい | — |
| M057 | 封印された選択肢 | `TURN_START` | ターン開始時、回廊上の2箇所のレベル1の[床]を入れ替えてもよい | — |
| M058 | 観測前夜 | `ACTIVATED` | [心光]が配置されていない[精神]を精神分析したとき、[心光]+1 | 11 |
| M059 | 矛盾した公理 | `CONSTANT` | 精神分析時、1/2/3/4/5回目の心光の行使では[心光]を1/1/2/2/3個支払えばよい | 15、16、17 |
| M060 | あの日の残像 | `CONSTANT` | レベル2の想い出カードを自己構成するとき、[エネルギー]-1 | 18 |
| M061 | 没頭する刻 | `ACTIVATED` | 移動フェイズで移動コストとして3種類全ての鍵([意志鍵]、[知恵鍵]、[感情鍵])を使用していれば、移動フェイズの終了時に[山札]+1 | 19、20 |
| M062 | 自己矛盾 | `ACTIVATED` | 他の人格がいる部屋で移動フェイズを終えたとき、想起効果を2回発動できる | 12、13、14 |
| M063 | 閉じたアルバム | `CONSTANT` | 移動フェイズで、1/2/3/4/5回目の心光の行使は[心光]を1/1/2/2/3個支払えばよい | 15、17 |
| M064 | 瞬間の保存 | `ACTIVATED` | 移動フェイズで、このターンに初めてレベル2の部屋に入る場合、[万能鍵] | 21、22 |
| M065 | 思惟の渦中 | `ACTIVATED` | 移動フェイズで、このターンに初めてレベル2の部屋に入る場合、[万能鍵] | 21、22 |
| M066 | 偉大なる指導者 | `ACTIVATED` | 移動フェイズで、このターンに初めてレベル2の部屋に入る場合、[万能鍵] | 21、22、23 |
| M067 | 偉人のひらめき | `ACTIVATED` | 1ターンに4部屋以上移動したら[心光]+1 | 23、24、25 |
| M068 | 夏至の午後 | `ACTIVATED` | 1ターンに4部屋以上移動していれば、そのターン内で最初の自己構成時の[エネルギー]-1 | 24、25 |
| M069 | 希望の篝火 | `ACTIVATED` | 1ターンに4歩以上移動したら[場札]+1 | 24、25 |
| M070 | 終端の構造 | `CONSTANT` | 自己構成時、1/2/3/4/5回目の心光の行使は[心光]を1/1/2/2/3支払えばよい | 15、16、17 |
| M071 | 子守唄 | `ACTIVATED` | 自己構成時、自己構成したカードの想起効果を使用してもよい | 14 |
| M072 | 鏡の向こう | `TURN_START` | ターン開始時、好きな部屋にワープして入室する。ワープではその部屋に「入室済み」とはみなされない | — |
| M073 | 断絶した思考の輪 | `ACTIVATED` | [知恵鍵]を持つ想い出カードを自己構成するたび、[山札]+1 | 24 |
| M074 | 心の修復 | `ACTIVATED` | [感情鍵]を持つ想い出カードを自己構成するたび、[山札]+1 | 24 |
| M075 | 友情の証 | `ACTIVATED` | [意志鍵]を持つ想い出カードを自己構成するたび、[山札]+1 | 24 |
| M076 | 昇華 | `ACTIVATED` | [知恵鍵]を持つ想い出カードを自己構成するたび、[心光]+1 | 24 |
| M077 | 眠りの瞬間 | `CONSTANT` | [感情鍵]を持つ想い出カードを自己構成するとき、[エネルギー]-1 | 24 |
| M078 | 夕暮れの約束 | `ACTIVATED` | [意志鍵]を持つ想い出カードを自己構成するたび、[場札]+1 | 24 |
| M079 | 過去への扉 | `ACTIVATED` | 精神分析階層を上げたとき、[心光]+1 | 24、26 |
| M080 | 赦しの言葉 | `ACTIVATED` | 精神分析階層を上げたとき、そのターン内の次の自己構成における[エネルギー]-1 | 24、26 |
| M081 | 無名の憧れ | `ACTIVATED` | 精神分析階層を上げたとき、[場札]+1 | 24、26 |
| M085 | 照らすもの | `ACTIVATED` | [床]の効果で[心光]獲得時、追加で[心光]+1 | 27 |
| M086 | 旋律のしぶき | `ACTIVATED` | [床]の効果で想い出の山札からカードを獲得するとき、追加で[山札]+1 | 27 |
| M087 | 勇気の残り香 | `ACTIVATED` | [床]の効果で想い出の場札からカードを獲得するとき、追加で[場札]+1 | 27 |
| M088 | 知識の方舟 | `INSTANT` | 精神分析階層を1つ上げる。ただし、この効果で第五階層に上げることはできない | 28 |
| M089 | 好きぴ | `INSTANT` | 自己構成エネルギーを支払わずにレベル1想い出カードを1枚自己構成してもよい | 18 |
| M090 | 絆の糸 | `INSTANT` | 自己構成済みカード3枚につき[万能移動鍵]+1 | — |
| M094 | 知識の迷宮 | `CONSTANT` | 精神分析時、[知恵鍵]は全て[万能鍵]として扱う | 29 |
| M095 | 歓喜の残滓 | `CONSTANT` | 自己構成時、[感情鍵]を持つこのターン未使用の自己構成済みカードを好きな枚数横向きにする。X枚横向きにしたら[エネルギー]-X | 30、31 |
| M096 | 存在の多面性 | `CONSTANT` | 移動フェイズ中、[意志鍵]は全て[万能鍵]として扱う | 20、29 |
| M097 | 文字の恩恵 | `INSTANT` | 自己構成済みの[知恵鍵]1つにつき、[心光]+1 | — |
| M098 | 母の手の温もり | `HELD` | 自己構成済みの[感情鍵]1つにつき、このカードの[エネルギー]-1 | 18、31、32 |
| M099 | 繋いできた願い | `INSTANT` | 自己構成済みの[意志鍵]1つにつき、[場札]+1 | — |

英語文は案としても付けない（後続タスクで確定。必須ではない）。

#### 6.5 備考

- 起動の効果の発動回数: ルールブック p.VI は「起動: 特定の条件を満たしたときに（ターン中何回でも）発動する」とする。FAQ 行 25 は、M067 の効果は 1 ターンに最大 1 回までとする（M067 は 8 部屋移動しても 2 回発動しない）。「ターン中何回でも」の意味は、効果文に「たび」とあれば条件を満たすたび、それ以外は条件を満たしたときに 1 回とする（作者了承。rule-questions Q-14、未確定事項 No.9）。効果ごとの個別の決定は 008 の担当。
- M064・M065・M066: 効果の内容は同一で、名前が異なるため重複して自己構成できる（FAQ 行 21）。M066 の「万能鍵」は移動コストが 1 減るのと同等で、万能移動鍵トークンは獲得しない（FAQ 行 22。作者了承。rule-questions Q-15、未確定事項 No.10）。`card.csv` の効果文は 2026-10-20 に `[万能鍵]` へ修正済み。
- M067・M068・M069: 移動した部屋数を数える。M069 は印刷が「4 歩」だが、M067・M068 は「4 部屋」。同じ意味（移動した部屋数）とする（作者了承。rule-questions Q-35、未確定事項 No.8）。
- M098 は「保有時」の効果で、手札にあるときのエネルギーが自己構成済みの `EMOTION` 鍵の数だけ減る。FAQ 行 32 のとおり、建築家（I03）で自己構成済みのカードを捨てると、捨てる処理が先に行われるため、その時点のエネルギーが変わる。
- M082〜M084・M091〜M093: 効果文が無い（想起効果のみ）。M091〜M093 はレベル 2 で鍵が 2 本とも万能。
- 精神カード R2 と R4 の精神分析ボーナスで、想い出カードを自己構成する（FAQ 行 18、38）。

### 7. 床カード（12 枚）

床カードは部屋の土台で、裏面は `Frev1`（レベル 1）と `Frev2`（レベル 2）。**部屋名は 12 の固定。** 画像は横長（1007 × 723）。

| 番号 | 画像 | 部屋名 | `name_id` | Lv | 床アクション | 周遊ボーナス | 初回配置の位置 |
| --- | --- | --- | --- | --- | --- | --- | --- |
| F01 | `floor/F1` | DEMETER | `demeter` | 2 | `[山札]`+3（想い出の山札から 3 枚引く） | 精神分析トークン（`tokenR`） | 左上（位置 1） |
| F02 | `floor/F2` | HERMES | `hermes` | 1 | 精神分析アクション | — | 上段 左から 2 番目 |
| F03 | `floor/F3` | ATHENA | `athena` | 1 | `[山札]`+3 のあと、手札を 1 枚捨てる | — | 上段 左から 3 番目 |
| F04 | `floor/F4` | POSEIDON | `poseidon` | 2 | 自己構成アクション。このターン中、自己構成時のエネルギーは常に -1 | 心光トークン（`tokenHR`） | 右上（角） |
| F05 | `floor/F5` | ARES | `ares` | 1 | `[場札]`+1、`[心光]`+1 | — | 右列 上から 2 番目 |
| F06 | `floor/F6` | DIONYSUS | `dionysus` | 1 | 精神分析アクション | — | 右列 上から 3 番目 |
| F07 | `floor/F7` | HESTIA | `hestia` | 2 | `[場札]`+1、`[山札]`+1、`[心光]`+1 | 万能移動鍵トークン（`tokenM`） | 右下（角） |
| F08 | `floor/F8` | APOLLO | `apollo` | 1 | `[山札]`+3 のあと、手札を 1 枚捨てる | — | 下段 右から 2 番目 |
| F09 | `floor/F9` | APHRODITE | `aphrodite` | 1 | `[場札]`+2 / `[心光]`+2（`/` は左右いずれか） | — | 下段 右から 3 番目 |
| F10 | `floor/F10` | HADES | `hades` | 2 | 精神分析アクション、`[心光]`+1 | 自己構成トークン（`tokenC`） | 左下（角） |
| F11 | `floor/F11` | ARTEMIS | `artemis` | 1 | `[場札]`+1、`[心光]`+1 | — | 左列 下から 2 番目 |
| F12 | `floor/F12` | HEPHAESTUS | `hephaestus` | 1 | 自己構成アクション（エネルギー -1 で）/ `[山札]`+2（`/` は左右いずれか） | — | 左列 下から 3 番目 |

- 根拠: 画像 `floor/F1`〜`F12`、p.I（内容物）、p.II 手順 1・9（セットアップ）、p.VIII（床アクション・想起効果の一覧）。床アクションのアイコンは p.VIII の凡例と照合した（`[山札]` +3 に続く空の札のアイコン = 「手札を 1 枚捨てる」、ブラシ = 自己構成アクション、脳 = 精神分析アクション）。
- **レベル 2（四隅）は DEMETER、POSEIDON、HESTIA、HADES の 4 枚**。四隅にあるカードにだけ、周遊ボーナスの足跡のアイコンが印刷される。周遊ボーナスに対応するトークンは、四隅のレベル 2 の部屋の近くにまとめて置く（p.II 手順 9）。
- **初回配置**: p.II 手順 1 は、初めてのゲームでは左上から ID 順に回廊へ置く。上の表の「位置」は、回廊を時計回りに数えた順（上段を左から右、右列を上から下、下段を右から左、左列を下から上）。p.II の配置図（上段 DEMETER / HERMES / ATHENA / POSEIDON、右列 ARES / DIONYSUS、下段右から HESTIA / APOLLO / APHRODITE / HADES、左列 下から ARTEMIS / HEPHAESTUS）と一致する。ID 順に置くと、レベル 2 が四隅（位置 1・4・7・10）に自然に入る。
- 慣れたゲームでは、レベルごとにシャッフルし、レベル 2 を四隅に、レベル 1 をそれ以外の 8 箇所に配置する（p.II 手順 1）。この場合、`position` は実行時に決まる。
- DEMETER が `[山札]`+3 だけで、手札を捨てるアイコンが無いことを画像で確認した（ATHENA と APOLLO には捨てるアイコンがある）。
- FAQ 行 27 は、床カードの効果で心光を獲得できる部屋として「APHRODITE, ARES, ARTEMIS, **HERA**, HADES」と挙げていた（修正前の原文）が、HERA という部屋は無い。HESTIA（心光 +1 を持つ）の誤記だった。作者の了承（rule-questions Q-15、未確定事項 No.5）を受け、2026-10-20 にサイト側の `faq.csv` を「HESTIA」に修正済み（あわせて「ARES」の閉じ引用符も修正）。

### 8. 精神カード（9 枚）

精神トラックは第一〜第五階層。セットアップでは、階層ごとにシャッフルし、第一 / 二 / 三 / 四 / 五階層ごとに 2 / 1 / 2 / 1 / 1 枚を表向きで並べる（p.II 手順 5）。第一・第三階層は a〜c の 3 枚のうち 2 枚を使う。第一〜第四階層の各カードの上に心光トークンを置く（手順 6）。裏面は `R1rev`〜`R5rev`（階層ごと）。画像は横長（1007 × 723）。

| 番号 | 画像 | 階層 | 分岐 | 精神分析コスト | 精神分析ボーナス | 心光トークン |
| --- | --- | --- | --- | --- | --- | --- |
| R1A | `analysis/R1a` | 1 | a | `WISDOM` × 2 | `[山札]`+1 | あり |
| R1B | `analysis/R1b` | 1 | b | `WILL` × 2 | `[山札]`+1 | あり |
| R1C | `analysis/R1c` | 1 | c | `EMOTION` × 2 | `[山札]`+1 | あり |
| R2 | `analysis/R2` | 2 | — | `WISDOM` × 1、`EMOTION` × 1、`WILL` × 1 | `[エネルギー]`を支払わずにレベル 1 の想い出カードを 1 枚自己構成してもよい | あり |
| R3A | `analysis/R3a` | 3 | a | `WISDOM` × 3 | 強化自我（強化自我カードの表を秘密裏に確認して 1 枚獲得する。p.VII の例） | あり |
| R3B | `analysis/R3b` | 3 | b | `WILL` × 3 | 強化自我 | あり |
| R3C | `analysis/R3c` | 3 | c | `EMOTION` × 3 | 強化自我 | あり |
| R4 | `analysis/R4` | 4 | — | `WISDOM` × 2、`EMOTION` × 2、`WILL` × 2 | `[エネルギー]`を支払わずに想い出カードを 1 枚自己構成してもよい | あり |
| R5 | `analysis/R5` | 5 | — | `WISDOM` × 4、`EMOTION` × 4、`WILL` × 4 | 【心理の極致】即座にゲームに勝利する | なし |

- コストは錠前のアイコン（`lock_book` = 知恵、`lock_fire` = 意志、`lock_heart` = 感情。p.VII）。精神分析ボーナスは進んだ先のカードで即時に得る（p.VII）。
- R2 のボーナスは「レベル 1 の想い出カード」（印刷）、R4 は「想い出カード」（レベルの限定が印刷に無い）。FAQ 行 18 は、第四階層ボーナスとしてレベル 2 の M091 を自己構成する例を挙げており、R4 はレベルを問わないことと整合する。
- 階層の分岐: 第三階層が 2 つに分かれ、鍵を多く持つ側のルートに進む例が p.VII にある。進行ルール（どの分岐からどこへ進めるか）は 001 で扱う。R3 の「強化自我」ボーナスの帯は金色で、文章は書かれていない。
- 第五階層の「心理の極致」が勝利条件の 1 つ（p.VII）。

### 9. 自我カード（初期自我 8 枚・強化自我 5 枚）

裏面は `Irev`（初期自我）と `Erev`（強化自我）。初期自我は印刷の ID `I01`〜`I08`、強化自我は `E01`〜`E05`。名前は画像の上部（パズル形の枠）に印刷される。効果の種類は初期自我には印刷されない（画像上に種類のアイコンが無い。I08 に自己構成アクションの図中の歯車アイコンがあるだけ）。強化自我の `E01`・`E02` は `card.csv` で常時、`E03`〜`E05` はターン開始時（印刷では砂時計が `E03`〜`E05` の図に描かれる）。

| 番号 | 画像 | 名前 | 英語名案 | `name_id` | 効果の種類（`card.csv`） | 効果文（印刷どおり） |
| --- | --- | --- | --- | --- | --- | --- |
| I01 | `ability/I1` | 巨匠 | Maestro | `maestro` | — | 自己構成済みカードが1/3/5/7/9枚目になったとき、想い出の山札を1枚引いて手札に加える |
| I02 | `ability/I2` | 領事 | Consul | `consul` | — | 精神分析トラックで心光を獲得したとき、追加で心光を1つ獲得する |
| I03 | `ability/I3` | 建築家 | Architect | `architect` | — | 自己構成アクション時、自己構成をする代わりに以下を行っても良い：自己構成済みカードを1枚捨札にし、捨てたカードの自己構成エネルギーより2まで大きい想い出カードを、エネルギーを支払わずに自己構成する |
| I04 | `ability/I4` | 論理学者 | Logician | `logician` | — | 精神分析コストとして想い出カードを支払った場合、現在自分がいる部屋から反時計回りに床カードの下に1枚ずつコストとして支払った想い出カードを置く。床カードの下に想い出カードが置かれている部屋に自分が止まった場合、その想い出カードを自分の手札に加える |
| I05 | `ability/I5` | 起業家 | Entrepreneur | `entrepreneur` | — | ターン開始時、どの人格もいない回廊上の想い出カードと手札の同レベルの想い出カードを1枚入れ替えてもよい |
| I06 | `ability/I6` | 擁護者 | Advocate | `advocate` | — | 移動フェイズで万能移動鍵トークンを1つも使わなかった場合、移動フェイズ終了時に想い出の山札からカードを1枚引いて手札に加える |
| I07 | `ability/I7` | 冒険家 | Adventurer | `adventurer` | — | 各ターンに初めて他の人格がいる部屋を通過したとき、想い出の山札から1枚引いて手札に加える |
| I08 | `ability/I8` | 主人公 | Protagonist | `protagonist` | — | 自己構成をするとき、想い出カードを2枚まで追加で支払ってもよい。そうした場合、追加で支払った想い出カードを自己構成したカードの下に裏向きで置く。それらの想い出カード1枚につき自己構成スコア+1 |
| E01 | `enhancement/E1` | 頑張り屋な | Diligent | `diligent` | `CONSTANT` | 自己構成時、自己構成エネルギーを1軽減する。 |
| E02 | `enhancement/E2` | 素直な | Candid | `candid` | `CONSTANT` | 精神分析を行うとき、精神分析コストを1軽減する。 |
| E03 | `enhancement/E3` | 照れ屋な | Shy | `shy` | `TURN_START` | ターン開始時、想い出の山札から1枚引いて手札に加える。 |
| E04 | `enhancement/E4` | 気まぐれな | Capricious | `capricious` | `TURN_START` | ターン開始時、心光を獲得する。 |
| E05 | `enhancement/E5` | 真面目な | Earnest | `earnest` | `TURN_START` | ターン開始時、万能移動鍵トークンを獲得する。 |

- 初期自我の効果の種類は `card.csv` でも空欄。実装での種類の割り当ては 008 が効果文から行う（I05 = ターン開始時、I01・I06・I07 = 起動、I02・I08 = 常時、I03 = 起動 など。作者了承。rule-questions Q-34、未確定事項 No.7）。
- 強化自我は第三階層到達時に獲得し、セットアップでプレイ人数分を抜き出して第三階層の精神カードの横に裏向きの山として置く（p.II 手順 7）。獲得時は表を秘密裏に確認する（p.VII）。
- 初期自我の名前は 8 つの職業のシルエット、強化自我は鳥のシルエット。名前は画像で `card.csv` と全件一致。
- 効果文の細部の食い違い（I03・I05・I06）は 2026-10-20 にサイト側で修正済み（16 節）。I02 の「精神分析階層で／トラックで」の字句の違いは意味が同じなので修正していない。

### 10. 目標カード（7 枚）

裏面は `Orev`。表は左半分が CONDITION（黒地）、右半分が SCORE（淡黄地）。獲得条件を満たしたとき、1 ターンに 1 枚まで獲得でき、自己構成スコアを加える（p.VI）。セットアップは、2 / 3 / 4 人プレイで 3 / 4 / 5 枚を表向きで並べる（p.II 手順 8）。

SCORE 欄は X の値から得点を引く表で、結晶の中の数字が得点。全カードで得点は 1 / 2 / 3 / 4 の 4 段階。

| 番号 | 画像 | 名前（`card.csv`） | `name_id` | CONDITION（獲得条件） | X の定義 | SCORE（X の範囲 → 得点） |
| --- | --- | --- | --- | --- | --- | --- |
| O001 | `objective/O1` | 万能移動鍵の目標 | `wild_move_key_goal` | 1 ターンに 6 部屋以上入室 | 万能移動鍵トークンの数 | 1 → 1、2〜3 → 2、4〜5 → 3、6 以上 → 4 |
| O002 | `objective/O2` | Lv2自己構成の目標 | `level2_composed_goal` | レベル 2 の想い出カードを 2 枚以上自己構成 | 自己構成済みのレベル 2 想い出カードの枚数 | 1 → 1、2 → 2、3 → 3、4 以上 → 4 |
| O003 | `objective/O3` | 同一エンジンの目標 | `single_engine_goal` | 自己構成済みカードの最多鍵の本数が 4 本以上（`MAX(WILL, WISDOM, EMOTION)` ≧ 4） | 自己構成済みカードの最多鍵の本数 | 1 → 1、2〜3 → 2、4〜5 → 3、6 以上 → 4 |
| O004 | `objective/O4` | 手札の目標 | `hand_goal` | ターン終了時の手札が 6 枚以上 | 手札枚数 | 1〜2 → 1、3〜4 → 2、5〜6 → 3、7 以上 → 4 |
| O005 | `objective/O5` | 心光の目標 | `light_goal` | 1 ターンに心光を 4 つ以上獲得 | 心光保有数 | 1 → 1、2〜3 → 2、4〜5 → 3、6 以上 → 4 |
| O006 | `objective/O6` | 精神分析階層の目標 | `analysis_floor_goal` | 1 ターンに精神分析を 2 回以上実行 | 精神分析階層 | 1 → 1、2 → 2、3 → 3、4 以上 → 4 |
| O007 | `objective/O7` | 多種エンジンの目標 | `multi_engine_goal` | 自己構成済みカードに知恵鍵・意志鍵・感情鍵・万能鍵が全て存在 | 知恵鍵・意志鍵・感情鍵のセットの数 | 1 → 1、2 → 2、3 → 3、4 以上 → 4 |

- `name_id` の `goal` は、`card.csv` の名前の「〜の目標」に対応する英語名案（「万能移動鍵の目標」= Wild Move Key Goal など）。
- 画像の確認: O001 の CONDITION は回廊の図に矢印と「≧6」、SCORE は万能移動鍵トークンの図。O005 の CONDITION は心光に矢印（獲得）と「≧4」。O006 の CONDITION は脳アイコンと「≧2」（精神分析の実行）。O007 の CONDITION は 4 種類の鍵の図。
- FAQ 行 3 により、目標カードは条件を満たしているタイミングであれば、ターン内のいつでも獲得できる（「1 ターンに 6 部屋以上入室」のようなターン内カウントは、ターン内でリセットされない）。一方、O007 のような現在の状態を条件とするカードは、条件が揃っているときしか獲得できない（建築家で自己構成済みカードを捨てることがあるため）。FAQ 行 23（O001）、28・39（O006）、29（O007）も参照。
- 得点の表が `card.csv` と印刷で食い違っていたカード（O002、O007）は 2026-10-20 にサイト側で修正済み（16 節）。
- X は獲得後もスコア計算のたびに数え直す。O001 の X は表向きと使用済みを合わせた保有数とする。O002 の X = 1 の得点は印刷どおり 1 とする。印刷にも FAQ にも無いため作者に確認し、了承された（rule-questions Q-13、未確定事項 No.6）。

### 11. トークン

p.I（内容物）によるトークンは 60 個。

| トークン | 識別子 | 個数 | 画像 | 備考 |
| --- | --- | --- | --- | --- |
| 心光 | `LIGHT` | 20 | `tokens/tokenHR` | 青い円盤に白い花。精神カード上（第一〜第四階層）に置かれる分と、プレイヤーが持つ分がある |
| 万能移動鍵 | `WILD_MOVE_KEY` | 16 | `tokens/tokenM`（表）、`tokens/tokenMrev`（使用済み。「USED」） | 両面トークン。裏返して使用済みにし、クリーンアップで回復する（p.IV、p.VIII）。表には扉のような小さな刻印が付く |
| 自己構成 | `COMPOSE_TOKEN` | 24 個（自己構成と精神分析の合計） | `tokens/tokenC`（ブラシ） | 追加の自己構成アクションを行う。保有上限はない（p.VI） |
| 精神分析 | `ANALYSIS_TOKEN` | （同上） | `tokens/tokenR`（脳に虫眼鏡） | 追加の精神分析アクションを行う。保有上限はない（p.VII） |

- 自己構成トークンと精神分析トークンは、p.I で「自己構成 / 精神分析（24 個）」と 1 つの括りにされ、2 つのトークンの間に ⇄ の記号が付く。万能移動鍵トークンも ⇄（表 ⇄ USED）で括られているため、**両面のトークン**（表が自己構成、裏が精神分析）の可能性があるが、p.VI・p.VII は「トークンが足りない場合、適宜代替すること」と書き、トークンの数に限りがあることを前提にしている。画像は 2 つ（`tokenC`、`tokenR`）のみで、両面かどうかは読み取れない。作者に確認し、種類別の上限は設けず、24 個は参考情報とする（保有上限もない）ことになった（rule-questions Q-19、未確定事項 No.4）。
- 心光 20 個のうち、セットアップで精神カードの上に置く数は第一〜第四階層の 6 枚（第一・第三階層は 2 枚ずつ使うため。p.II 手順 6）。
- 周遊ボーナス用のトークン（精神分析・心光・自己構成・万能移動鍵）の初期の山は、四隅の部屋の近くに置く（p.II 手順 9）。
- 「心光の行使」で必要な心光はアクション・フェイズごとにリセットされる（p.VI）。処理は 001 で扱う。

### 12. データ項目の定義

実装が持つデータ項目の名前と型（`snake_case`）。配置は 003 に従う。

| 対象 | 項目 | 型 | 説明 |
| --- | --- | --- | --- |
| 想い出カード | `id` | 文字列 | `M001`〜`M099` |
| | `name_ja` / `name_en` / `name_id` | 文字列 | 名前（印刷）/ 英語名案 / 識別子 |
| | `count` | 整数 | 枚数 |
| | `level` | 整数 | 1 / 2 |
| | `energy` | 整数 | 自己構成エネルギー |
| | `keys` | 配列（`KeyType`） | 鍵。上から順 |
| | `back` | 文字列 | `Rev1B` など |
| | `score` | 整数 | 自己構成スコア |
| | `effect_kind` | `EffectKind` | 効果の種類 |
| | `effect_text_ja` | 文字列 | 効果文（6.4）。無いカードは空 |
| | `recall` | `RecallEffect` | 想起効果 |
| 床カード | `id`、`name_id`、`level`、`position_default`、`action`（008 で構造化）、`bonus`（`NONE` / `ANALYSIS_TOKEN` / `LIGHT` / `COMPOSE_TOKEN` / `WILD_MOVE_KEY`） | | 7 節 |
| 精神カード | `id`、`floor`（階層）、`branch`（`A`/`B`/`C`/なし）、`cost`（`KeyType` ごとの本数）、`bonus`（008 で構造化）、`has_light` | | 8 節 |
| 自我カード | `id`、`name_ja`、`name_id`、`kind`（`INITIAL` / `ENHANCEMENT`）、`effect_kind`、`effect_text_ja` | | 9 節 |
| 目標カード | `id`、`name_id`、`condition_text_ja`、`score_table`（X の下限 → 得点の組） | | 10 節 |

効果文の構造化（タイミング・条件・効果量を機械可読にする粒度）は 008 の担当。本書は、日本語の効果文と、想起効果・効果の種類の識別子までを定める。

### 13. `src/` への取り込み方針

`card.csv` はサイト側の別リポジトリにあり、CI（GitHub Actions）からは読めない。`data/`（画像）も Git 管理外で CI に無い。次の案を比較した。

| 案 | 内容 | 長所 | 短所 |
| --- | --- | --- | --- |
| A | ローカルで `card.csv` から PHP の定数ファイルを生成してコミットする。CI は生成物と本仕様書の表の整合のみを検査する | CSV の原本（サイト側）の更新を取り込める | 床・精神カードは CSV に無く、手書きになる。（印刷との差分は修正済みのため補正は不要） |
| B | 本仕様書の表（6〜10 節）を原本とし、そこから生成物を作る。CI でも仕様書を読んで再生成して差分を検査できる | CI で完結する。印刷が正という方針と一致 | 仕様書の表の書式を安定させる必要がある。`card.csv` の更新は手で仕様書に反映 |
| C | 手書きの PHP 定数ファイルのみ。仕様書との整合は人がレビューする | 仕組みが要らない | 144 枚の転記ミスを検出できない |

**採用案（作者了承。rule-questions Q-37、未確定事項 No.12）: A を土台にし、B の検査を足す。** 具体的には次のとおり。

1. 生成スクリプト（ローカルで実行。場所と言語は 004 / 003 で決める。例: `tools/generate-material`）が `card.csv` を読み、想い出・自我・目標のデータを PHP の定数ファイル（配置は 003 に従う。仮に `src/modules/php/Material/` の下）に書き出す。
2. `card.csv` と印刷物の差分は 2026-10-20 にサイト側で解消した（16 節。現在 0 件）ため、補正テーブルは設けない。今後サイト側で差分が生じたら、`card.csv` の訂正を優先する（`faq-workflow.md` の運用）。ただし NFC への正規化は生成スクリプトでも行う（保険）。
3. 床・精神カードと、想起効果（`recall`）、裏面（`back`）は `card.csv` に無いため、本書の表から手で（または表を読むスクリプトで）作る。
4. 生成物の先頭に「自動生成。`card.csv` と仕様書 002 から生成。手で編集しない」と書き、コミットする。
5. CI では、生成物の数値（枚数の合計 144、レベル別 120 / 24、レベル別 ID の連番、`name_id` の一意性、鍵・コスト・スコアが本書の表と一致）を検査する。検査は仕様書の Markdown の表を読むか、同じ内容を JSON に書き出したものを `docs/` に置いて読む（どちらにするかは 004 で決める）。

定数ファイルの形式（PHP の配列か、クラス定数か）、JS 側へのデータの渡し方、翻訳用の文言（`clienttranslate`）の置き場所は 003・009 で決める。本書は項目と識別子のみを定める。

### 14. 英語識別子と英語名案

想い出カードの英語名案と `name_id` は 6.1〜6.3 の表に載せた。床カードの部屋名は印刷が英語（ギリシャ神話の神名）なので、そのまま `name_id`（小文字）にした。英語名案は翻訳の原文の案であり、確定ではない（後続タスクで変更してよい）。識別子と英語名案の案は作者の了承を得ている（rule-questions Q-36、未確定事項 No.11）。

英語の用語の案（仕様・コードの識別子と対応させる。UI の確定は後続タスク）:

| 日本語 | 英語案 | 識別子など |
| --- | --- | --- |
| 人格 | persona | `persona` |
| 回廊 | cloister | `cloister` |
| 部屋 | room | `room` |
| 床カード | floor card | `floor` |
| 想い出カード | memory card | `memory` |
| 場札 / 山札 / 捨札 | open cards / deck / discard | `open`、`deck`、`discard` |
| 自己構成 / 自己構成済みカード | compose / composed card | `compose`、`composed` |
| 精神トラック / 精神カード / 精神分析 | spirit track / spirit card / analysis | `analysis`（精神カードと精神分析の識別子の頭に使う） |
| 鍵（知恵・意志・感情・万能） | key（wisdom / will / emotion / wild） | `WISDOM`、`WILL`、`EMOTION`、`WILD` |
| 心光 | light（inner light） | `LIGHT` |
| 万能移動鍵トークン | wild move key | `WILD_MOVE_KEY` |
| 初期自我 / 強化自我 | initial ego / enhanced ego | `INITIAL` / `ENHANCEMENT` |
| 目標カード | goal card | `goal` |
| 想起効果 | recall effect | `recall` |
| 周遊ボーナス / 精神分析ボーナス | tour bonus / analysis bonus | `bonus` |

### 15. FAQ（カード個別の裁定）

`faq.csv`（サイト側、ヘッダを 1 行目とした行番号。データ 40 行）のうち、`card_ids` と `add_display_ids` にカードが挙がる行の要約。回答の全文は `faq.csv` を参照する。001（ルール）・008（効果）の担当の裁定も、カード個別のものは備考として転記した。

| カード | 行 | 質問 → 回答（要約） |
| --- | --- | --- |
| 全般 | 2 | 手札上限 8 枚のとき、獲得したあとに捨ててよい。一時的な超過は許容されるが、次のアクションや効果の適用の前に 8 枚にする |
| 目標全般（O001〜） | 3 | 目標カードは、条件を満たしているタイミングであればターン内のいつでも獲得できる。ターン内カウントの条件はリセットされない。現在の状態を条件とするもの（O007）は、揃っているときしか獲得できない |
| M032 | 7 | 床アクションを代替する想起効果は、手番プレイヤー以外は発動できない（p.V） |
| M036 | 8 | 「次のレベル 1 の部屋」は、間のレベル 2 の部屋を通過する扱いになり、その周遊ボーナスも得られる |
| M039 | 9 | 想起効果で次の部屋に移動しても、移動先の想い出カードは表向きにしない。想起は想起フェイズ開始時にいた部屋、床アクションは床アクション開始時にいる部屋が対象 |
| M014 | 10 | 想起効果「このターンで次の自己構成のエネルギー -1」は、手番プレイヤーのターンが終わると途切れる。他のプレイヤーは使えない |
| M058、I02 | 11 | `M058` の心光獲得に、領事（I02）の追加獲得は適用されない。領事は精神分析トラック（精神カード）上の心光を獲得したときのみ |
| M031、M062 | 12 | `M062` で床アクションの代わりの精神分析を 2 回行うことはできない（代替した結果は床アクション扱いになるため） |
| M042、M062 | 13 | `M062` で、次のレベル 1 の部屋への移動を 2 回続けられる（2 つ先まで）。1 回目の移動先の床アクションを 2 回目の想起効果が代替する |
| M034、M062、M071 | 14 | 自己構成フェイズの前のフリーアクションで自己構成したカードの、床アクションを代替する想起効果は、床アクションフェイズが終わっているため使えない |
| M059、M063、M070 | 15 | `M059` は 6 回目の心光の行使について記載が無いため、6 個支払う |
| M059、M070 | 16 | 自己構成のフリーアクションでも、心光の行使のカウントはアクション・フェイズごとにリセットされ、1 回目は 1 個でよい |
| M059、M063、M070 | 17 | 2 回目の心光の行使を、カード効果を適用せずに 2 個支払うことはできない（1 個） |
| M060、M089、M098、M091 | 18 | エネルギーが 0 未満になることはない。エネルギーを負にして代わりにリソースを得ることはできない |
| M061 | 19 | 心光の行使や万能移動鍵で払った鍵は「その種類の鍵を使用した」に数えない。数えられるのは、手札・自己構成済みカードによる非万能鍵での支払いのみ |
| M061、M096、M003、M082 | 20 | `M096`（意志鍵を万能鍵として扱う）がある場合、意志鍵は意志鍵・万能鍵のどちらとして見てもよい。M082 で知恵・感情、M003 で意志を払っていれば `M061` の条件を満たす |
| M064〜M066 | 21 | `M064` と `M065` の効果は同一だが、名前が異なるため両方自己構成できて重複する |
| M064〜M066 | 22 | `M066` の「万能鍵」は、レベル 2 の部屋に入室する際の移動コストを 1 減らすのと同等で、万能移動鍵トークンの獲得ではない |
| M067〜M069、O001、M033 | 23 | `M067` などのカウントはターン内。想起効果による移動も数える（想起効果の移動で 4 部屋目になれば心光を獲得できる） |
| M067〜M069、M073〜M081 | 24 | `M068` と `M069` の効果は同時に条件を満たしたとき、2 つとも発動し、順序も自由に選べる |
| M067〜M069 | 25 | `M067` の効果は 1 ターンに最大 1 回まで（8 部屋移動しても 2 回発動しない） |
| M079〜M081 | 26 | 精神分析階層を上げたときの効果と精神分析ボーナスは、適用順序を自由に選べる（場札を獲得してからボーナスで自己構成してもよい） |
| M085〜M087 | 27 | 床カードの効果で心光を獲得する部屋（APHRODITE、ARES、ARTEMIS、HESTIA（旧「HERA」の誤記を 2026-10-20 に修正済み）、HADES）のみが対象。HERMES の精神分析アクション後に進んだカード上の心光の獲得は対象外 |
| M088、O006 | 28 | 精神分析アクション以外（`M088` など）で精神分析階層を上げても「精神分析を実行した」にならない（O006 の条件を満たさない） |
| M094、M096、O007 | 29 | `M096` の効果で移動フェイズ中は意志鍵が万能鍵とみなせるため、O007 の条件を満たし、移動フェイズ中でも目標カードを獲得できる |
| M095、M040 | 30 | `M095` で横向きにできるのは `EMOTION` 鍵を持つ自己構成済みカードのみ（`M040` など万能鍵のカードは不可） |
| M095、M098 | 31 | `M095` では、横向きにしたカードの枚数だけでエネルギーの軽減数が決まる（`M098` を横向きにしても 2 は減らない） |
| M098、I03、M002 | 32 | 建築家（I03）で `M098` を自己構成するとき、自己構成済みの `EMOTION` 鍵を持つカードを捨てると、捨てる処理が先に行われ、手札の `M098` のエネルギーが増える（M002 を捨てると 5 になり、自己構成できない） |
| I04、M051 | 33 | 論理学者（I04）で床の下に置かれた想い出カードは、移動した先の部屋でも手札に加えなければならない。手札上限 8 枚を超えるなら、代わりに何かを捨てる |
| I05 | 34 | 起業家（I05）で回廊に置く手札のカードは裏向きにして置く |
| I05 | 35 | 起業家で交換した想い出カードのある部屋に止まった場合、通常どおり想起効果を発動できる |
| I07 | 36 | 冒険家（I07）の「通過」は、他プレイヤーのいる部屋に止まっただけでは発動しない（止まらずに通り過ぎたときのみ） |
| I07、M033 | 37 | 想起効果による移動も「止まった」扱いで、「通過」にならない。冒険家は発動しない |
| I08、R2、R4 | 38 | 精神分析ボーナスの自己構成（コストを支払わない）でも、主人公（I08）の追加支払いはできる |
| O006 | 39 | 精神分析トークンの支払いで階層を上げなかった場合は「精神分析を実行した」とは言えない（階層を上げて初めて実行したことになる） |
| 全般（レベル 2 の想い出カード） | 40 | 「いつでも想い出カード 2 枚を捨てれば心光を獲得できる」で、レベル 2 の想い出カードを捨てた場合は 2 枚分として数える（レベル 2 を 1 枚捨てるだけで心光を 1 つ獲得できる） |
| 全般（山札切れ） | 41 | レベル 2 の想い出カードの山札が切れた場合も、レベル 1 の山札切れと同じく全プレイヤーの敗北（001 の範囲） |

行 4（効果の傾向）、5（トークンの空撃ち不可）、6（場札 0 枚のとき山札から獲得できない）、41（レベル 2 の山札切れ）は、カード個別ではない一般的な裁定なので、001 で扱う。行 40（レベル 2 は 2 枚分）は、捨てるカードの数え方としてエッジケースに反映した。行 4 は、知恵は精神分析・心光獲得、感情は自己構成、意志は移動・場札獲得の効果・想起効果が多い傾向を述べており、3.5 の大分類と矛盾しない（M013〜M015 は知恵 = 心光、感情 = エネルギー、意志 = 場札）。

### 16. `card.csv` と印刷物の差分（修正済み）

**2026-10-20 にサイト側で修正済み（コミット `62cfdc91`）。現在の差分は 0 件。** 作者が差分 14 件をすべて了承し（rule-questions Q-15）、サイト側の `card.csv` を印刷物に合わせて修正・公開した。あわせて想い出カード名を NFC に正規化した。修正後の `card.csv` と本書の 6 節の表（名前・枚数・レベル・エネルギー・鍵・裏面・スコア・効果の種類）が全 99 種で一致すること、O002・O007 の得点表が 1 / 2 / 3 / 4 になっていることを機械的に確認した。

修正前の画像との突き合わせでは、**名前・レベル・エネルギー・鍵・スコア・効果の種類・枚数の合計は全件一致**しており、差分は効果文の字句の違いと得点表の違いだった。

#### 修正履歴

| No. | カード | 修正前の `card.csv` | 印刷物（修正後の `card.csv`） | 備考 |
| --- | --- | --- | --- | --- |
| 1 | M059 | `…1/1/2/2/3支払えばよい` | `…1/1/2/2/3個支払えばよい` | 「個」が抜けていた（M070 は印刷も「個」なし） |
| 2 | M063 | `…1/1/2/2/3支払えばよい` | `…1/1/2/2/3個支払えばよい` | 同上 |
| 3 | M064 | `…入る場合、[移動コスト]-1` | `…入る場合、[万能鍵]`（歯車形の頭の灰色の鍵のアイコン） | `[移動コスト]-1` というアイコンは存在しない。FAQ 行 22 は「万能鍵が発生する」と説明 |
| 4 | M065 | 同上 | 同上 | 同上 |
| 5 | M066 | 同上 | 同上 | 同上 |
| 6 | M072 | `ターン開始時、好きな部屋にワープする。…` | `ターン開始時、好きな部屋にワープして入室する。…` | 「して入室」が抜けていた |
| 7 | M089 | `構成エネルギーを支払わずに[Lv1の想い出カード]を1枚自己構成してもよい` | `自己構成エネルギーを支払わずにレベル1想い出カードを1枚自己構成してもよい` | 「自己」が抜けていた。`[Lv1の想い出カード]` は印刷ではアイコンではなく文字 |
| 8 | M094 | `…[知恵鍵]は全て[任意鍵]として扱う` | `…[知恵鍵]は全て[万能鍵]として扱う` | 「任意鍵」と表記していた（ルールブック p.VI は「万能」） |
| 9 | M096 | `…[意志鍵]は全て[任意鍵]として扱う` | `…[意志鍵]は全て[万能鍵]として扱う` | 同上 |
| 10 | I03 | `…捨てたカードの[構成エネルギー]より2まで大きい想い出カードを1枚自己構成する` | `…捨てたカードの[構成エネルギー]より2まで大きい想い出カードを、エネルギーを支払わずに自己構成する` | 「エネルギーを支払わずに」が抜けていた（画像の図にも「エネルギーを支払わない」記号がある） |
| 11 | I05 | `ターン開始時、手札とどの人格もいない回廊上の想い出カードと…` | `ターン開始時、どの人格もいない回廊上の想い出カードと…` | 「手札と」が余計だった。FAQ 行 34 とも整合しなかった |
| 12 | I06 | `移動フェイズで[万能鍵トークン]を1つも使わなかった場合、[想い出の山札]+1` | `移動フェイズで[万能移動鍵トークン]を1つも使わなかった場合、移動フェイズ終了時に[想い出の山札]+1` | 「万能移動鍵」と「移動フェイズ終了時」が抜けていた（引くタイミング） |
| 13 | O002 | `2/3/4+につき2/3/4`（X ＝ 2 から得点） | `1/2/3/4+につき1/2/3/4`（X: 1 → 1、2 → 2、3 → 3、4+ → 4） | 印刷の表は X = 1 の列から始まり、得点は 1/2/3/4 |
| 14 | O007 | `1/2/3+なら2/3/4` | `1/2/3/4+なら1/2/3/4`（X: 1 → 1、2 → 2、3 → 3、4+ → 4） | 印刷の表は X = 1〜4+ の 4 段階で、得点は 1/2/3/4 |

- `e1`・`e2` 列の「任意」（= 万能鍵）は、作者の判断で修正していない。本書は `WILD` に読み替える。
- 想い出カード名の NFD（濁点付きの文字を「基底文字 + 結合濁点（U+3099）」で書いていた。M001 捨てられたイデア、M010 閉ざされた論理、M043 アーカイブ、M049 エイドスの残響）は、同日に NFC へ正規化済み。修正後は全件が NFC であることを確認した。

#### 修正後も残る字句の違い（意味は同じ。差分に数えない）

- アイコンの表記: `card.csv` は `[想い出の山札]+1`、`[想い出の山]+1`（M061）、`[想い出の場札]+1`、`[床カード]`・`[床カード表面]`（M055〜M057、M085〜M087）、`[精神カード]`（M058）、`[万能移動鍵トークン]`（M090）などと書き、印刷はアイコン（`[山札]`、`[場札]`、`[床]`、`[精神]`、`[万能移動鍵]`）。本書は 2 節の凡例に統一した。
- 名称: `card.csv` の `[構成エネルギー]`・`[自己構成エネルギー]` は、印刷ではアイコン `[エネルギー]`（紫の筆跡）。O001 の `[任意移動鍵トークン数]` は、印刷では万能移動鍵トークンの図。I02 は `card.csv` が「精神分析階層で」、印刷が「精神分析トラックで」。
- M055〜M057 は、`card.csv` が「2箇所のLv1の…」、印刷が「回廊上の2箇所のレベル1の…」。M095 は `card.csv` が「X枚横向きにしたら、」（読点あり）、印刷は読点なし。
- 強化自我 `E01`〜`E05` は印刷が「…軽減する。」のように句点で終わり、`card.csv` は `-1` などの短い表記。意味は同じ。
- 初期自我の `card.csv` は、`condition` 列（効果の種類）が空。印刷にも種類のアイコンは無い。

`card.csv` に無い内容（本書で初めて確定）: 床カード 12 枚、精神カード 9 枚、想起効果の詳細、裏面の対応、トークン、英語識別子。

## エッジケース

- **効果文の無いカード**: M001〜M051、M082〜M084、M091〜M093 は効果の種類が `NONE`。想起効果のみで、自己構成しても何も起こらない（鍵とスコアだけが効く）。
- **鍵が 2 本あるカード**: 2 本の鍵は分割して別の移動に使えない（p.IV）。M082〜M090、M094〜M099。M094 は精神分析時に `WISDOM` が `WILD` 扱いになる。M096 は移動フェイズ中に `WILL` が `WILD` 扱い（FAQ 行 20・29）。
- **万能鍵と目標カード**: O007 は万能鍵を含む 4 種類が自己構成済みカードにあることを条件にする。M096 の効果で一時的に満たせる（FAQ 行 29）。
- **想い出カードの手札上限**: 8 枚（p.VIII）。獲得後に超過した分は捨てる（FAQ 行 2）。
- **同名カードの自己構成**: 同名の想い出カードは複数自己構成できない（p.VI、p.VIII）。M064〜M066 のように名前が違えば、同じ効果でも両方可能（FAQ 行 21）。
- **レベル 2 の想い出カードを捨てるとき**、エネルギー 2 つ分（p.VI）、「いつでも 2 枚捨てて心光」でも 2 枚分（FAQ 行 40）に数える。データ上は `level` で判定する。

## 未確定事項

画像・ルールブック・FAQ から判断できなかった点を、推測で確定させず作者に質問した。**12 件すべて 2026-10-20 に回答済み**（暫定案のとおり。記録は rule-questions 第 2 弾の Q-13・14・15・19・33〜37）。

| No. | 質問 | 暫定案 | 根拠 | 回答 |
| --- | --- | --- | --- | --- |
| 1 | 想い出カードの枚数（`n_card`）は、印刷物から 1 枚ごとの枚数が確認できない（総数 144、レベル別 120 / 24 のみ p.I で確認）。`card.csv` の枚数（1〜4）は正しいか。 | `card.csv` の値を正とする | p.I、`card.csv` の `n_card` | 暫定案のとおり（2026-10-20、Q-33） |
| 2 | `card.csv` の `otype` 列（`c` / `s` / `w` / `a` / `a2`）は、想起効果の大分類（山札 / 獲得 / 交換 / 床アクション代替 / 代替(-1)）という解釈でよいか。`engine`・`original_id` 列は実装で使わなくてよいか。 | 解釈のとおり。`engine`・`original_id` は使わない | 全 99 種で `otype` と画像のアイコンが矛盾しない | 暫定案のとおり（2026-10-20、Q-33） |
| 3 | 想い出カードの裏面（錠前）の種類は、`card.csv` の `r` 列でよいか（印刷物から 1 枚ごとの表裏の対応が確認できない）。 | `r` 列を裏面の錠前（知恵 = `B`、意志 = `F`、感情 = `H`）とする | レベル 1 が 40 / 40 / 40、レベル 2 が 8 / 8 / 8 で均等。p.II の ARES・DIONYSUS の錠前と整合 | 暫定案のとおり（2026-10-20、Q-33） |
| 4 | 自己構成トークンと精神分析トークンは、別々のトークンが 12 個ずつか、両面のトークンが 24 個か。BGA 上で供給に上限を設けるか。 | 種類別の上限は設けず（保有上限もない。p.VI・p.VII）、24 個は参考情報とする | p.I（⇄ の記号）、画像は `tokenC`・`tokenR` の 2 つのみ | 暫定案のとおり（2026-10-20、Q-19） |
| 5 | FAQ 行 27 の回答に「HERA」とあるが、HERA という部屋は無い。心光を獲得する部屋は ARES、ARTEMIS、APHRODITE、HADES、HESTIA の 5 つだが、HESTIA の誤記でよいか。サイト側の `faq.csv` を直すか。 | HESTIA の誤記。サイト側の修正は作者に確認 | 床カード F01〜F12 | 暫定案のとおり（2026-10-20、Q-15）。サイト側の `faq.csv` は HESTIA に修正済み |
| 6 | 目標カードの X を数える時点はいつか（獲得時か、スコア計算のたびか）。O001 の X に、使用済み（裏面）の万能移動鍵トークンは含むか。O002 の X = 1 の得点（印刷の表にある）はいつ使われるか。 | 獲得後もスコア計算のたびに数え直す。O001 は表向きと使用済みを合わせた保有数。X = 1 の得点は印刷どおり 1 | O001・O002 の SCORE 欄、FAQ 行 3 | 暫定案のとおり（2026-10-20、Q-13） |
| 7 | 初期自我カードの効果の種類（ターン開始時 / 起動 / 常時 / 即時 / 保有時）は、印刷にも `card.csv` にも無い。実装でどう分類するか。 | 008 で効果文から割り当てる（I05 = ターン開始時、I01・I06・I07 = 起動、I02・I08 = 常時、I03 = 起動 など） | 印刷に種類のアイコンが無い | 暫定案のとおり（2026-10-20、Q-34） |
| 8 | M067・M068 は「4 部屋以上移動」、M069 は「4 歩以上移動」と印刷される。同じ意味（移動した部屋数）か。 | 同じ意味（部屋数） | M067〜M069 の効果文、FAQ 行 23〜25 | 暫定案のとおり（2026-10-20、Q-35） |
| 9 | 起動の効果の発動回数。p.VI は「ターン中何回でも」発動するとあるが、FAQ 行 25 は M067 を 1 ターン 1 回までとする。効果ごとに「条件を満たすたび」と「ターン 1 回」のどちらかを決める基準は何か。 | 効果文に「たび」とあれば条件を満たすたび、それ以外は条件を満たしたときに 1 回。008 で個別に決める | p.VI、FAQ 行 24・25 | 暫定案のとおり（2026-10-20、Q-14） |
| 10 | M064〜M066 の「万能鍵」アイコンの効果の意味（移動コストの 1 つ分を万能鍵で払える扱いか、コストが 1 減る扱いか）。FAQ 行 22 は「移動コストが 1 減ることと同等」とする。`card.csv` の `[移動コスト]-1` に直してよいか。 | 移動コスト -1 と同等（FAQ 行 22）。`card.csv` の字句の修正は作者に確認 | M064〜M066 の画像、FAQ 行 21・22 | 暫定案のとおり（2026-10-20、Q-15）。`card.csv` は `[万能鍵]` に修正済み |
| 11 | 英語識別子（`name_id`、列挙値）の綴りと、英語名案（想い出カード 99 種、自我カードなど）はこの案でよいか。 | 案のとおり。英語名案は後続タスクで変更してよい | 3 節、6 節 | 暫定案のとおり（2026-10-20、Q-36） |
| 12 | `src/` への取り込み方針（13 節）の案 A + B の検査でよいか。 | 暫定案のとおり。ファイル構成・形式は 003 / 004 で確定する | 13 節 | 暫定案のとおり（2026-10-20、Q-37） |

## 受け入れ条件

- [ ] 想い出カード 99 種（M001〜M099、144 枚）が、名前・英語名案・`name_id`・枚数・レベル・エネルギー・鍵・裏面・スコア・効果の種類・想起効果とともにすべて載っている。
- [ ] 効果のある 42 種の効果文が印刷どおりに載っている。
- [ ] 床カード 12 枚（部屋名・レベル・床アクション・周遊ボーナス・初回配置の位置）が載っている。
- [ ] 精神カード 9 枚（階層・分岐・コスト・ボーナス）が載っている。
- [ ] 初期自我 8 枚・強化自我 5 枚（名前・効果）、目標カード 7 枚（CONDITION・X・SCORE の段階表）が載っている。
- [ ] 鍵 4 種とアイコン画像の対応、裏面 6 種と錠前の対応が定義されている。
- [ ] トークンの種類と個数が載っている。
- [ ] 英語識別子の規則と全カードの識別子が定義されている。
- [ ] `card.csv` の列の意味、`card.csv` と印刷物の差分、FAQ（カード個別）の転記が載っている。
- [ ] `src/` への取り込み方針が書かれ、決めきれなかった点が未確定事項に挙がり、回答が記録されている。
- [ ] `npx -y markdownlint-cli2 --config <設定> "**/*.md"` がエラーなしで通る。
