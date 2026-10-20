# ゲーム紹介文（英語）と BGG 登録の手順

BoardGameGeek（BGG）のゲーム登録と、BGA の Game Metadata Manager の説明文に使う英語の紹介文。ルールの内容はルールブック（`data/rulebook/rulebook_web.pdf`）と公式ページ <https://danjiri-concerto.github.io/i-revolve-therefore-i-am/index.html> に基づく。

BGG に未登録であることを作者が確認したうえで、2026-10-20 に作者が申請済み。承認後に BGG_ID をここと `src/gameinfos.jsonc` に記録する。

| 項目 | 値 |
| --- | --- |
| BGG_ID | 承認待ち（2026-10-20 申請） |

## 登録の手順

1. BGG にログインし、メニューの **Community → Contribute → Board Game** から登録画面を開く（公式の手順: [BGG Guide to Game Submissions](https://boardgamegeek.com/wiki/page/BGG_Guide_to_Game_Submissions)、[Guide for Designers and Publishers](https://boardgamegeek.com/wiki/page/Guide_for_Designers_and_Publishers)）。
2. 下の「基本情報」「説明文」「カテゴリーとメカニクス」を入力する。日本語の別名（Alternate name）は『我廻る、故に我あり』。
3. デザイナー・出版社（だんじり協奏曲）は MINIMUM JOURNEY / PROJECT EDGEWORTH の登録で作った項目を選ぶ。まだ承認待ちで検索に出ない場合は、同じ英語表記で入力する（表記ゆれで二重登録にならないよう注意）。
4. 申請後、BGG の管理者の承認を待つ。承認には数週間かかることがある。承認後のページの URL（`boardgamegeek.com/boardgame/<数字>/...`）の数字が BGG_ID。エージェントに伝えてもらえれば `src/gameinfos.jsonc` とこのファイルに記録する。
5. 承認後、ゲームのページに箱絵（`data/box/box@4x.png`）をアップロードする（画像は承認後に別途追加する仕組み）。

## 短い説明（Short description）

BGG の短い説明欄用（85 文字以内。下の文は 77 文字）。

```text
Circle a ring of rooms, build your self from memories, and win in an instant.
```

## 説明文（Description）

```text
You were once someone. Now you wander the Psycho Cloister, a labyrinth of the mind that defines who you are. Many personalities live inside it, each circling the rooms in turn. When thought reaches its peak, an ego is established and the cycle of reincarnation ends. But only one ego can be established, and establishing yours means losing every other.

In I Revolve, Therefore I Am, each player guides a personality around a ring of twelve rooms, always clockwise. Every room is a floor card with a face-down Memory card on top, and entering a room costs keys, shown on the back of its Memory card. Pay with keys from your constructed Memory cards, discarded cards from your hand, Psychic Light, or Omni-Move Key tokens. The four Level 2 corner rooms may be skipped, and entering or skipping one earns its Tour Bonus.

Each turn has five phases: move, recall, floor action, construction, and cleanup. After you move, the Memory card in your room is flipped. Everyone standing in that room triggers its recall effect, although only the active player may use an effect that replaces the floor action, and then you take the Memory card into your hand. Next you take the floor action printed on the room, such as gaining cards and tokens, constructing a Memory card, or climbing the Mind Track.

The engine-building happens in two places. By Constructing, you pay energy by discarding cards from your hand or spending Psychic Light, then lay a Memory card in your constructed area. Its keys can pay for later moves and Psychoanalysis, and its effects keep working for you. By Psychoanalysis, you pay with keys to climb the Mind Track one tier at a time, earning bonuses along the way, including a secret Enhanced Ego card on the third tier. Your Initial Ego card, chosen in a secret draft, gives you a power of your own from the start.

There are three ways to win, and each takes effect immediately. Fleeting Recollection: enter every room in a single turn; the room you start in does not count, so you must go all the way around. Gathered Fragments: reach a total Construction Score of 20 or more from your Memory cards and Goal cards. Pinnacle of the Mind: climb all the way to the fifth tier of the Mind Track. Whichever path you choose, watch your rivals closely and be careful with the Memory deck: if it runs out, nobody wins.

Will you remain here, or cross over into the beyond? This is your one chance to prove that you are yourself.
```

## 用語の英訳案

説明文中の表記を一貫させるための対応表。ゲーム内の英語識別子は仕様書 002（カードデータ仕様）で確定する。この表は紹介文用の案で、確定後に合わせて直す。

| 日本語 | 英語（案） |
| --- | --- |
| 我廻る、故に我あり | I Revolve, Therefore I Am |
| 精神回廊（サイコ・クロイスター） | Psycho Cloister |
| 人格 | personality |
| 回廊 / 部屋 | ring (cloister) / room |
| 床カード / 床アクション | floor card / floor action |
| 想い出カード / 想い出の山札 | Memory card / Memory deck |
| 自己構成 / 自己構成済みカード | Construction (to construct) / constructed card |
| 自己構成スコア | Construction Score |
| 精神分析 | Psychoanalysis |
| 精神トラック / 階層 | Mind Track / tier |
| 鍵 | key |
| 心光 | Psychic Light |
| 万能移動鍵トークン | Omni-Move Key token |
| 想起効果 | recall effect |
| 周遊ボーナス | Tour Bonus |
| 初期自我 / 強化自我 | Initial Ego / Enhanced Ego |
| 目標カード | Goal card |
| 刹那の追憶 / 断片の結集 / 心理の極致 | Fleeting Recollection / Gathered Fragments / Pinnacle of the Mind |

## 基本情報

| 項目 | 値 |
| --- | --- |
| 名前 | I Revolve, Therefore I Am |
| 別名 | 我廻る、故に我あり |
| 人数 | 2–4 |
| 対象年齢 | 10+ |
| プレイ時間 | 30–60 分 |
| 発売年 | 2025 |
| 複雑度 | 公式ページ記載の 2.6 / 5（作者の自己申告値。BGG の Weight はユーザー投票で決まるため入力欄はない） |
| デザイナー | だんじり協奏曲（ぞね、ひのき、たづくり、まや）。MINIMUM JOURNEY / PROJECT EDGEWORTH の登録と同じ英語表記にする |
| アーティスト | 空欄（入力しない） |
| 出版社 | だんじり協奏曲（MINIMUM JOURNEY / PROJECT EDGEWORTH の登録と同じ英語表記） |

## カテゴリーとメカニクス（BGG の選択肢から）

| 種類 | 候補 | 理由 |
| --- | --- | --- |
| Category | Psychology | 題材が精神世界（人格・想い出・精神分析・自我の確立） |
| Category | Card Game | 想い出カードを手札・自己構成済みエリアで扱うカード中心のゲーム |
| Category | Mythology | 部屋の床カードがギリシャ神話の神々の名（HERMES、ATHENA など）を冠する |
| Mechanism | Worker Placement | 人格（ミープル）が部屋に入り、その床アクションを行う |
| Mechanism | Track Movement | 精神トラックの階層を上げて勝利を目指す |
| Mechanism | Hand Management | 手札（上限 8 枚）を、移動・自己構成・精神分析の支払いに捨てるか、自己構成するかをやりくりする |
| Mechanism | Variable Player Powers | 初期自我カードがプレイヤーごとに異なる能力を与える。強化自我も固有 |
| Mechanism | Open Drafting | 場札 6 枚から想い出カードを獲得できる（床アクションなど） |
| Mechanism | Closed Drafting | 初期自我カードを秘密裏に 1 枚選んで次のプレイヤーへ回す |
| Mechanism | Race | 3 種の即時勝利条件のどれかを先に満たす |
| Mechanism | Tableau Building | 自己構成済みカードを並べて鍵と効果のエンジンを作る |

## 作者の回答（2026-10-20）

| No. | 質問 | 回答 |
| --- | --- | --- |
| 1 | 対象年齢 | 10+ |
| 2 | 発売年 | 2025（公式ページの「ゲームマーケット2026春で予約受付中」の記載とは別に、発売は 2025 年とする） |
| 3 | アーティスト欄に載せる名前 | 空欄にする |
| 4 | カテゴリーに Mythology を入れるか | 入れる |
| 5 | 3 つの勝利条件に英語名を付けるか | 付ける（用語表の英訳を説明文で使う） |
