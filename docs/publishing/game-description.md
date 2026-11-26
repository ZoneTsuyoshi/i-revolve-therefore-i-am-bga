# ゲーム紹介文（英語）と BGG 登録の手順

BoardGameGeek（BGG）のゲーム登録と、BGA の Game Metadata Manager の説明文に使う英語の紹介文。ルールの内容はルールブック（`data/rulebook/rulebook_web.pdf`）と公式ページ <https://danjiri-concerto.github.io/i-revolve-therefore-i-am/index.html> に基づく。

BGG に未登録であることを作者が確認したうえで、2026-10-20 に作者が申請し、2026-10-22 に承認された（<https://boardgamegeek.com/boardgame/481968/i-revolve-therefore-i-am>）。BGG_ID は `src/gameinfos.jsonc` に設定済み。BGA のライセンス申請は [bga-license.md](bga-license.md)。

| 項目 | 値 |
| --- | --- |
| BGG_ID | 481968（2026-10-22 承認） |

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

## BGA の説明文（Metadata タブの Description）

BGA の公式ドキュメント <https://doc.studio.boardgamearena.com/framework-documentation/game-metadata/metadata/> によると、Description はゲームの紹介ページに出る短い紹介文で、段落の配列として入力する。各段落は書式なしのプレーンテキスト（HTML・強調なし）で、全体の目安は 100〜150 語。BGG 用の長文（上の節）は長すぎるため、BGA 用に短くした版を用意した（2026-11-26 確認）。用語は下の「用語の英訳案」の表に合わせた。

管理画面では段落ごとに 1 行ずつ入力するため、段落ごとに別のコードブロックで載せる。

段落 1:

```text
You were once someone. Now you wander the Psycho Cloister, a labyrinth of the mind where many personalities circle in search of themselves. Only one ego can be established, and establishing yours means losing every other.
```

訳: かつて君は誰かだった。いま君は精神回廊（サイコ・クロイスター）をさまよう。そこは、数多の人格が自分自身を求めて廻る心の迷宮だ。確立できる自我はただ一つ。君の自我を確立することは、ほかのすべてを失うことを意味する。

段落 2:

```text
Each turn, guide your personality clockwise around a ring of twelve rooms, paying keys to enter each one. Flip the Memory card in the room where you stop to trigger its recall effect, then take it into your hand and use the room's floor action. Construct Memory cards to build an engine of keys and effects, and use Psychoanalysis to climb the Mind Track.
```

訳: 毎ターン、人格を 12 の部屋の環に沿って時計回りに進め、部屋に入るたびに鍵を支払う。止まった部屋の想い出カードを表にして想起効果を発動し、それを手札に加えて、その部屋の床アクションを行う。想い出カードを自己構成して鍵と効果のエンジンを作り、精神分析で精神トラックを登っていく。

段落 3:

```text
Three paths lead to instant victory: enter every room in a single turn, reach a Construction Score of 20, or climb to the fifth tier of the Mind Track. But if either Memory deck runs out, nobody wins.
```

訳: 即時勝利への道は 3 つ。1 ターンですべての部屋に入る、自己構成スコア 20 に達する、精神トラックの第五階層まで登る。ただし、どちらかの想い出の山札が尽きたら、誰も勝てない。

語数は 3 段落の合計で約 140 語（目安の 100〜150 語に収まる）。

### Metadata タブのその他の欄（参考）

| 欄 | 値 |
| --- | --- |
| Publication year | 2025（基本情報の表と同じ） |
| Minimum age | 10（基本情報の表と同じ） |
| Complexity | 0〜5 の尺度（0 = 極めて単純、5 = 極めて複雑）。作者が入力（2026-11-26 時点: 2） |
| Luck | 0〜5 の尺度（0 = 運の要素なし、5 = 完全に運）。作者が入力（同: 3） |
| Strategy | 0〜5 の尺度（0 = 戦略の余地なし、5 = 完全に戦略）。作者が入力（同: 4） |
| Diplomacy | 0〜5 の尺度（0 = 相互作用なし、5 = プレイヤー間の相互作用と話し合いが中心）。作者が入力（同: 3） |
| Zombie Mode level | **0** を推奨（下記） |

Zombie Mode level の理由。BGA の定義（<https://doc.studio.boardgamearena.com/framework-documentation/back-end/zombie-mode/>）では、0 = 何もせず次へ進む（ゲームの進行に必要な行動だけ取る）、1 = 合法手からランダムに選ぶ、2 = 目先の利益が最大の手を選ぶ、3 = 先を読む。複数のレベルが混在する場合は最も多いレベルを、同数なら低いほうを選ぶ。本実装の zombie は仕様書 006 §10（[006-state-machine.md](../specs/006-state-machine.md)）のとおり、ランダムを使わず決定的に、ほとんどの状態で「行わない・スキップ」を選ぶ（自己構成・精神分析は取り消し、目標は獲得しない）。移動や必須の選択も、進行に必要な最小限の行動に限られるため、レベル 0 に当たる。2026-11-26 時点の入力値 1 は 0 に直すことを推奨する。BGA は 1〜2 を目安としているため、審査でレベルの引き上げを求められた場合は、006 §10 を改める仕様 PR から対応する。

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
