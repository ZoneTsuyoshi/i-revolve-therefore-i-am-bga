# カードの英語テキスト 対照表（作者の確認用）

『我廻る、故に我あり』の BGA 版で、カード画像（日本語の印刷）に添える英語のテキスト（ツールチップ・拡大表示・想起中の案内パネル・Card captions の英語名）の対照表。
実装は `src/modules/js/ui/texts.js`（仕様書 009 §8）。日本語の列は仕様書 002 の原文（印刷どおり。アイコンは `[心光]` のような角括弧表記）、英語の列が起草した案。

## 確認のしかた

- 英語の列を直接直してください（直した表をそのままもらえれば、`texts.js` に反映します）。意味が原文とずれていないか、ゲーム内の用語として自然かを見てください。
- `{light}` のような波括弧は、画面ではアイコンに置き換わります（`{light}` 心光、`{draw}` 山札から引く、`{open}` 場札、`{energy}` エネルギー、`{wisdom}`・`{will}`・`{emotion}`・`{wild}` 鍵、`{wildkey}` 万能移動鍵トークン、`{spirit}` 精神カード、`{floor}` 床カード）。
- 想い出カードの効果文は、画像と英語を並べて確認してもらうのが確実です（画像は `data/cards/memory/`）。FAQ の裁定（008 §9）と食い違う言い回しが無いかも見てください。
- この表と `texts.js` は同じ内容にそろえてあります（`tests/js/card-texts.test.mjs` が、`texts.js` の英語がこの表に載っていることを検査します）。

## 用語の方針

[game-description.md](game-description.md) の英訳案に合わせています。

| 日本語 | 英語 |
| --- | --- |
| 想い出カード / 想い出の山札 | Memory card / Memory deck |
| 場札 | open cards（game-description に無いので 002 §14 の案） |
| 自己構成 / 自己構成済みカード | construct (Construction) / constructed card |
| 精神分析 | Psychoanalysis |
| 精神トラック / 精神カード / 階層 | Mind Track / Mind Track card / tier |
| 心光 | Psychic Light |
| 鍵 | key（Wisdom / Will / Emotion / Wild） |
| 万能移動鍵トークン | Omni-Move Key token |
| 人格 | personality |
| 想起効果 / 周遊ボーナス | recall effect / Tour bonus |
| 初期自我 / 強化自我 | Initial Ego / Enhanced Ego |

## 迷った点（作者に聞きたいこと）

1. **用語の統一**: 仕様書 009 §8.5 の案は compose / light / analysis action / persona でしたが、紹介文の英訳案（Construction / Psychic Light / Psychoanalysis / personality）に合わせて書き換えました。これでよいか。
2. **万能鍵と万能移動鍵**: 万能鍵は Wild key、万能移動鍵トークンは Omni-Move Key token（紹介文の案）にしたため、名前が似ず別物として読めます。一方、目標カード O001 の名前案（002）は Wild Move Key Goal です。Omni-Move Key Goal に揃えるか。
3. **目標カードの名前案の不揃い**: 002 の名前案は Level 2 Composed Goal、Light Goal、Analysis Floor Goal など、本文の用語（Construction、Psychic Light、Psychoanalysis、Mind Track）とずれています。本文の用語に揃えるか、002 の案のままか。
4. **場札**: open cards としました（Memory deck に対して、表向きに並ぶカード）。紹介文の案に無いので、別の呼び方があれば。
5. **M058（観測前夜）**: 「[心光]が配置されていない[精神]を精神分析したとき」を "When you Psychoanalyze a Mind Track card that has no Psychic Light on it" としました。「その精神カードに進んだとき」の意味で合っているか。
6. **M064・M065・M066**: 原文（`card.csv`）は「…入る場合、[移動コスト]-1」です（作者の裁定。Q-70）。英語は "the move cost of that room is reduced by 1" としました。
7. **M069（希望の篝火）**: 原文は「4歩以上」ですが、M067・M068 と同じ意味（Q-35）なので "4 or more rooms" にそろえました。
8. **M061（没頭する刻）**: 鍵の並びは印刷どおり Will, Wisdom, Emotion の順です。
9. **E04（気まぐれな）・E05（真面目な）**: 原文は個数を書いていませんが、"gain 1 ..." としました（008 の定義が 1）。
10. **I01（巨匠）**: 「N枚目になったとき」を "When you reach N constructed cards" としました（I03 で枚数が戻った後も、目盛りに達すれば反応する。008 X-16）。
11. **心光の行使（M059・M063・M070）**: "the 1st/2nd/3rd/4th/5th use of Psychic Light costs only 1/1/2/2/3 Psychic Light" としました。6 回目以降は FAQ 行 15 のとおり n 個で、文には書いていません（原文も書いていない）。
12. **想起効果の "(turn player only)"**: 床アクションを代替する 4 つの想起効果に、仕様書 009 の案どおり付けました（FAQ 行 7）。
13. **目標カードの得点表**: カードの下の表（X → 得点）は、素材の得点表から "1 → 1, 2–3 → 2, 4–5 → 3, 6+ → 4" の形で出します（この表には載せていません）。
14. **精神カードの名前**: 階層だけ（"Tier 3"）です。分岐 a・b・c は表記しません。

## 想い出カードの効果文（42 種）

| ID | 日本語（原文） | 英語（案） |
| --- | --- | --- |
| M052 選択と未来 | ターン開始時、回廊上の2箇所の同レベルの想い出カードを入れ替えてもよい | At the start of your turn, you may swap 2 Memory cards of the same level on the cloister |
| M053 主観と客観 | ターン開始時、回廊上の2箇所の同レベルの想い出カードを入れ替えてもよい | At the start of your turn, you may swap 2 Memory cards of the same level on the cloister |
| M054 自己像 | ターン開始時、回廊上の2箇所の同レベルの想い出カードを入れ替えてもよい | At the start of your turn, you may swap 2 Memory cards of the same level on the cloister |
| M055 記憶の目録 | ターン開始時、回廊上の2箇所のレベル1の[床]を入れ替えてもよい | At the start of your turn, you may swap 2 level 1 floor cards {floor} on the cloister |
| M056 正義の境界線 | ターン開始時、回廊上の2箇所のレベル1の[床]を入れ替えてもよい | At the start of your turn, you may swap 2 level 1 floor cards {floor} on the cloister |
| M057 封印された選択肢 | ターン開始時、回廊上の2箇所のレベル1の[床]を入れ替えてもよい | At the start of your turn, you may swap 2 level 1 floor cards {floor} on the cloister |
| M058 観測前夜 | [心光]が配置されていない[精神]を精神分析したとき、[心光]+1 | When you Psychoanalyze a Mind Track card {spirit} that has no Psychic Light {light} on it, gain 1 Psychic Light {light} |
| M059 矛盾した公理 | 精神分析時、1/2/3/4/5回目の心光の行使では[心光]を1/1/2/2/3個支払えばよい | When you Psychoanalyze, the 1st/2nd/3rd/4th/5th use of Psychic Light {light} costs only 1/1/2/2/3 {light} |
| M060 あの日の残像 | レベル2の想い出カードを自己構成するとき、[エネルギー]-1 | When you construct a level 2 Memory card, reduce its energy {energy} by 1 |
| M061 没頭する刻 | 移動フェイズで移動コストとして3種類全ての鍵([意志鍵]、[知恵鍵]、[感情鍵])を使用していれば、移動フェイズの終了時に[山札]+1 | If you paid move costs with all 3 types of key (Will {will}, Wisdom {wisdom}, Emotion {emotion}) in the Move phase, draw 1 Memory card {draw} at the end of the Move phase |
| M062 自己矛盾 | 他の人格がいる部屋で移動フェイズを終えたとき、想起効果を2回発動できる | When you end the Move phase in a room with another personality, you can activate the recall effect twice |
| M063 閉じたアルバム | 移動フェイズで、1/2/3/4/5回目の心光の行使は[心光]を1/1/2/2/3個支払えばよい | In the Move phase, the 1st/2nd/3rd/4th/5th use of Psychic Light {light} costs only 1/1/2/2/3 {light} |
| M064 瞬間の保存 | 移動フェイズで、このターンに初めてレベル2の部屋に入る場合、[移動コスト]-1 | In the Move phase, the first time you enter a level 2 room this turn, the move cost of that room is reduced by 1 |
| M065 思惟の渦中 | 移動フェイズで、このターンに初めてレベル2の部屋に入る場合、[移動コスト]-1 | In the Move phase, the first time you enter a level 2 room this turn, the move cost of that room is reduced by 1 |
| M066 偉大なる指導者 | 移動フェイズで、このターンに初めてレベル2の部屋に入る場合、[移動コスト]-1 | In the Move phase, the first time you enter a level 2 room this turn, the move cost of that room is reduced by 1 |
| M067 偉人のひらめき | 1ターンに4部屋以上移動したら[心光]+1 | When you have moved 4 or more rooms in a turn, gain 1 Psychic Light {light} |
| M068 夏至の午後 | 1ターンに4部屋以上移動していれば、そのターン内で最初の自己構成時の[エネルギー]-1 | If you have moved 4 or more rooms in a turn, reduce energy {energy} by 1 the first time you construct that turn |
| M069 希望の篝火 | 1ターンに4歩以上移動したら[場札]+1 | When you have moved 4 or more rooms in a turn, take 1 open card {open} |
| M070 終端の構造 | 自己構成時、1/2/3/4/5回目の心光の行使は[心光]を1/1/2/2/3支払えばよい | When you construct, the 1st/2nd/3rd/4th/5th use of Psychic Light {light} costs only 1/1/2/2/3 {light} |
| M071 子守唄 | 自己構成時、自己構成したカードの想起効果を使用してもよい | When you construct, you may use the recall effect of the card you constructed |
| M072 鏡の向こう | ターン開始時、好きな部屋にワープして入室する。ワープではその部屋に「入室済み」とはみなされない | At the start of your turn, warp into any room. A room you warp into is not counted as entered |
| M073 断絶した思考の輪 | [知恵鍵]を持つ想い出カードを自己構成するたび、[山札]+1 | Each time you construct a Memory card with a Wisdom key {wisdom}, draw 1 Memory card {draw} |
| M074 心の修復 | [感情鍵]を持つ想い出カードを自己構成するたび、[山札]+1 | Each time you construct a Memory card with an Emotion key {emotion}, draw 1 Memory card {draw} |
| M075 友情の証 | [意志鍵]を持つ想い出カードを自己構成するたび、[山札]+1 | Each time you construct a Memory card with a Will key {will}, draw 1 Memory card {draw} |
| M076 昇華 | [知恵鍵]を持つ想い出カードを自己構成するたび、[心光]+1 | Each time you construct a Memory card with a Wisdom key {wisdom}, gain 1 Psychic Light {light} |
| M077 眠りの瞬間 | [感情鍵]を持つ想い出カードを自己構成するとき、[エネルギー]-1 | When you construct a Memory card with an Emotion key {emotion}, reduce energy {energy} by 1 |
| M078 夕暮れの約束 | [意志鍵]を持つ想い出カードを自己構成するたび、[場札]+1 | Each time you construct a Memory card with a Will key {will}, take 1 open card {open} |
| M079 過去への扉 | 精神分析階層を上げたとき、[心光]+1 | When you move up a Mind Track tier, gain 1 Psychic Light {light} |
| M080 赦しの言葉 | 精神分析階層を上げたとき、そのターン内の次の自己構成における[エネルギー]-1 | When you move up a Mind Track tier, reduce energy {energy} by 1 on your next construction this turn |
| M081 無名の憧れ | 精神分析階層を上げたとき、[場札]+1 | When you move up a Mind Track tier, take 1 open card {open} |
| M085 照らすもの | [床]の効果で[心光]獲得時、追加で[心光]+1 | When you gain Psychic Light {light} from a floor card's {floor} effect, gain 1 additional Psychic Light {light} |
| M086 旋律のしぶき | [床]の効果で想い出の山札からカードを獲得するとき、追加で[山札]+1 | When you draw Memory cards from the deck by a floor card's {floor} effect, draw 1 additional Memory card {draw} |
| M087 勇気の残り香 | [床]の効果で想い出の場札からカードを獲得するとき、追加で[場札]+1 | When you take open cards by a floor card's {floor} effect, take 1 additional open card {open} |
| M088 知識の方舟 | 精神分析階層を1つ上げる。ただし、この効果で第五階層に上げることはできない | Move up 1 Mind Track tier. This effect cannot take you to tier 5 |
| M089 好きぴ | 自己構成エネルギーを支払わずにレベル1想い出カードを1枚自己構成してもよい | You may construct 1 level 1 Memory card without paying energy {energy} |
| M090 絆の糸 | 自己構成済みカード3枚につき[万能移動鍵]+1 | For every 3 constructed cards, gain 1 Omni-Move Key token {wildkey} |
| M094 知識の迷宮 | 精神分析時、[知恵鍵]は全て[万能鍵]として扱う | When you Psychoanalyze, treat all Wisdom keys {wisdom} as Wild keys {wild} |
| M095 歓喜の残滓 | 自己構成時、[感情鍵]を持つこのターン未使用の自己構成済みカードを好きな枚数横向きにする。X枚横向きにしたら[エネルギー]-X | When you construct, turn sideways any number of your constructed cards that have an Emotion key {emotion} and have not been used this turn. If you turn X cards, reduce energy {energy} by X |
| M096 存在の多面性 | 移動フェイズ中、[意志鍵]は全て[万能鍵]として扱う | During the Move phase, treat all Will keys {will} as Wild keys {wild} |
| M097 文字の恩恵 | 自己構成済みの[知恵鍵]1つにつき、[心光]+1 | For each Wisdom key {wisdom} on your constructed cards, gain 1 Psychic Light {light} |
| M098 母の手の温もり | 自己構成済みの[感情鍵]1つにつき、このカードの[エネルギー]-1 | For each Emotion key {emotion} on your constructed cards, reduce this card's energy {energy} by 1 |
| M099 繋いできた願い | 自己構成済みの[意志鍵]1つにつき、[場札]+1 | For each Will key {will} on your constructed cards, take 1 open card {open} |

## 初期自我・強化自我（13 種）

| ID | 日本語（原文） | 英語（案） |
| --- | --- | --- |
| I01 巨匠 | 自己構成済みカードが1/3/5/7/9枚目になったとき、想い出の山札を1枚引いて手札に加える | When you reach 1/3/5/7/9 constructed cards, draw 1 Memory card from the deck {draw} and add it to your hand |
| I02 領事 | 精神分析トラックで心光を獲得したとき、追加で心光を1つ獲得する | When you gain Psychic Light {light} on the Mind Track, gain 1 additional Psychic Light {light} |
| I03 建築家 | 自己構成アクション時、自己構成をする代わりに以下を行っても良い：自己構成済みカードを1枚捨札にし、捨てたカードの自己構成エネルギーより2まで大きい想い出カードを、エネルギーを支払わずに自己構成する | During a Construction action, instead of constructing, you may do the following: discard 1 constructed card, then construct a Memory card whose energy {energy} is up to 2 higher than the discarded card's energy, without paying energy |
| I04 論理学者 | 精神分析コストとして想い出カードを支払った場合、現在自分がいる部屋から反時計回りに床カードの下に1枚ずつコストとして支払った想い出カードを置く。床カードの下に想い出カードが置かれている部屋に自分が止まった場合、その想い出カードを自分の手札に加える | When you pay Memory cards as a Psychoanalysis cost, place each paid Memory card under the floor card {floor} of a room, one per room, starting from the room you are in and going counterclockwise. When you stop in a room that has Memory cards under its floor card {floor}, add those Memory cards to your hand |
| I05 起業家 | ターン開始時、どの人格もいない回廊上の想い出カードと手札の同レベルの想い出カードを1枚入れ替えてもよい | At the start of your turn, you may swap 1 Memory card in your hand with a Memory card of the same level on the cloister in a room where no personality is present |
| I06 擁護者 | 移動フェイズで万能移動鍵トークンを1つも使わなかった場合、移動フェイズ終了時に想い出の山札からカードを1枚引いて手札に加える | If you did not use any Omni-Move Key token {wildkey} in the Move phase, draw 1 Memory card from the deck {draw} at the end of the Move phase |
| I07 冒険家 | 各ターンに初めて他の人格がいる部屋を通過したとき、想い出の山札から1枚引いて手札に加える | The first time each turn you pass through a room with another personality, draw 1 Memory card from the deck {draw} |
| I08 主人公 | 自己構成をするとき、想い出カードを2枚まで追加で支払ってもよい。そうした場合、追加で支払った想い出カードを自己構成したカードの下に裏向きで置く。それらの想い出カード1枚につき自己構成スコア+1 | When you construct, you may pay up to 2 additional Memory cards. If you do, place the additionally paid Memory cards face down under the constructed card. Each of those Memory cards gives +1 Construction Score |
| E01 頑張り屋な | 自己構成時、自己構成エネルギーを1軽減する。 | When you construct, reduce energy {energy} by 1 |
| E02 素直な | 精神分析を行うとき、精神分析コストを1軽減する。 | When you Psychoanalyze, reduce the Psychoanalysis cost by 1 |
| E03 照れ屋な | ターン開始時、想い出の山札から1枚引いて手札に加える。 | At the start of your turn, draw 1 Memory card from the deck {draw} |
| E04 気まぐれな | ターン開始時、心光を獲得する。 | At the start of your turn, gain 1 Psychic Light {light} |
| E05 真面目な | ターン開始時、万能移動鍵トークンを獲得する。 | At the start of your turn, gain 1 Omni-Move Key token {wildkey} |

## 目標カード（7 種）

条件（CONDITION）と X の定義。得点表は印刷の表（002 §10）と同じ数字を素材から出します。

| ID | 日本語（原文） | 英語（案） |
| --- | --- | --- |
| O001 万能移動鍵の目標 条件 | 1 ターンに 6 部屋以上入室 | Enter 6 or more rooms in a single turn |
| O001 X | 万能移動鍵トークンの数 | Number of Omni-Move Key tokens {wildkey} |
| O002 Lv2自己構成の目標 条件 | レベル 2 の想い出カードを 2 枚以上自己構成 | Have constructed 2 or more level 2 Memory cards |
| O002 X | 自己構成済みのレベル 2 想い出カードの枚数 | Number of level 2 Memory cards you have constructed |
| O003 同一エンジンの目標 条件 | 自己構成済みカードの最多鍵の本数が 4 本以上（`MAX(WILL, WISDOM, EMOTION)` ≧ 4） | The most common key type among your constructed cards appears 4 or more times (the highest of Will {will}, Wisdom {wisdom}, and Emotion {emotion} is 4 or more) |
| O003 X | 自己構成済みカードの最多鍵の本数 | Number of keys of the most common type among your constructed cards |
| O004 手札の目標 条件 | ターン終了時の手札が 6 枚以上 | Have 6 or more cards in your hand at the end of your turn |
| O004 X | 手札枚数 | Number of cards in your hand |
| O005 心光の目標 条件 | 1 ターンに心光を 4 つ以上獲得 | Gain 4 or more Psychic Light {light} in a single turn |
| O005 X | 心光保有数 | Number of Psychic Light {light} you hold |
| O006 精神分析階層の目標 条件 | 1 ターンに精神分析を 2 回以上実行 | Perform Psychoanalysis 2 or more times in a single turn |
| O006 X | 精神分析階層 | Your Mind Track tier |
| O007 多種エンジンの目標 条件 | 自己構成済みカードに知恵鍵・意志鍵・感情鍵・万能鍵が全て存在 | Your constructed cards include all of Wisdom {wisdom}, Will {will}, Emotion {emotion}, and Wild {wild} keys |
| O007 X | 知恵鍵・意志鍵・感情鍵のセットの数 | Number of sets of Wisdom {wisdom}, Will {will}, and Emotion {emotion} keys |

## 床カードの床アクション（12 室）

部屋名（DEMETER など）は印刷が英語なのでそのままです。

| ID | 日本語（原文） | 英語（案） |
| --- | --- | --- |
| F01 DEMETER | `[山札]`+3（想い出の山札から 3 枚引く） | Draw 3 Memory cards {draw} |
| F02 HERMES | 精神分析アクション | Take a Psychoanalysis action |
| F03 ATHENA | `[山札]`+3 のあと、手札を 1 枚捨てる | Draw 3 Memory cards {draw}, then discard 1 card from your hand |
| F04 POSEIDON | 自己構成アクション。このターン中、自己構成時のエネルギーは常に -1 | Take a Construction action. This turn, energy {energy} is always reduced by 1 when you construct |
| F05 ARES | `[場札]`+1、`[心光]`+1 | Take 1 open card {open} and gain 1 Psychic Light {light} |
| F06 DIONYSUS | 精神分析アクション | Take a Psychoanalysis action |
| F07 HESTIA | `[場札]`+1、`[山札]`+1、`[心光]`+1 | Take 1 open card {open}, draw 1 Memory card {draw}, and gain 1 Psychic Light {light} |
| F08 APOLLO | `[山札]`+3 のあと、手札を 1 枚捨てる | Draw 3 Memory cards {draw}, then discard 1 card from your hand |
| F09 APHRODITE | `[場札]`+2 / `[心光]`+2（`/` は左右いずれか） | Take 2 open cards {open} or gain 2 Psychic Light {light} (choose one) |
| F10 HADES | 精神分析アクション、`[心光]`+1 | Take a Psychoanalysis action and gain 1 Psychic Light {light} |
| F11 ARTEMIS | `[場札]`+1、`[心光]`+1 | Take 1 open card {open} and gain 1 Psychic Light {light} |
| F12 HEPHAESTUS | 自己構成アクション（エネルギー -1 で）/ `[山札]`+2（`/` は左右いずれか） | Take a Construction action with energy {energy} -1, or draw 2 Memory cards {draw} (choose one) |

## 精神カードの精神分析ボーナス（9 枚）

| ID | 日本語（原文） | 英語（案） |
| --- | --- | --- |
| R1A | `[山札]`+1 | Draw 1 Memory card {draw} |
| R1B | `[山札]`+1 | Draw 1 Memory card {draw} |
| R1C | `[山札]`+1 | Draw 1 Memory card {draw} |
| R2 | `[エネルギー]`を支払わずにレベル 1 の想い出カードを 1 枚自己構成してもよい | You may construct 1 level 1 Memory card without paying energy {energy} |
| R3A | 強化自我（強化自我カードの表を秘密裏に確認して 1 枚獲得する。p.VII の例） | Enhanced Ego: secretly look at the Enhanced Ego cards and take 1 |
| R3B | 強化自我 | Enhanced Ego: secretly look at the Enhanced Ego cards and take 1 |
| R3C | 強化自我 | Enhanced Ego: secretly look at the Enhanced Ego cards and take 1 |
| R4 | `[エネルギー]`を支払わずに想い出カードを 1 枚自己構成してもよい | You may construct 1 Memory card without paying energy {energy} |
| R5 | 【心理の極致】即座にゲームに勝利する | Pinnacle of the Mind: you win the game immediately |

## 想起効果（13 種）

日本語は 002 §3.4 の意味の欄（p.VIII の注釈）。画面では "Recall:" に続けて出します。

| ID | 日本語（原文） | 英語（案） |
| --- | --- | --- |
| DRAW_1 | 想い出の山札から 1 枚引き、手札に加える | Draw 1 Memory card from the deck {draw} |
| OPEN_DRAW_1 | 想い出の場札から 1 枚選び、手札に加える | Take 1 open card into your hand {open} |
| LIGHT_1 | 心光を 1 つ得る | Gain 1 Psychic Light {light} |
| NEXT_COMPOSE_DISCOUNT_1 | （想起効果）このターンで次の自己構成アクションにおけるエネルギー -1 | Your next Construction action this turn costs 1 less energy {energy} |
| DISCARD_FOR_LIGHT | 手札から想い出カードを好きな枚数捨て、その枚数だけ心光を得る | Discard any number of Memory cards from your hand. Gain that much Psychic Light {light} |
| SWAP_HAND_FIELD | 手札の想い出カードと想い出の場札を好きな枚数入れ替える | Swap any number of Memory cards in your hand with open cards |
| SWAP_SAME_COST | 自己構成済みの想い出カード 1 枚と同じコストの手札の想い出カードを交換してもよい | You may swap a constructed card with a Memory card of the same energy cost {energy} in your hand |
| ALT_ANALYSIS | 床アクションの代わりに精神分析アクションを行ってもよい | Instead of your floor action {floor}, you may take a Psychoanalysis action (turn player only) |
| ALT_COMPOSE | 床アクションの代わりに自己構成アクションを行ってもよい | Instead of your floor action {floor}, you may take a Construction action (turn player only) |
| ALT_ANALYSIS_DISCOUNT_1 | 床アクションの代わりに、コスト -1 で精神分析アクションを行ってもよい | Instead of your floor action {floor}, you may take a Psychoanalysis action with cost -1 (turn player only) |
| ALT_COMPOSE_DISCOUNT_1 | 床アクションの代わりに、エネルギー -1 で自己構成アクションを行ってもよい | Instead of your floor action {floor}, you may take a Construction action with energy {energy} -1 (turn player only) |
| MOVE_NEXT_LV1 | 次のレベル 1 の部屋に移動してもよい。そうした場合、場札を 1 枚獲得し、移動した部屋で床アクションを行う | You may move to the next level 1 room. If you do, take 1 open card {open} and perform that room's floor action |
| MOVE_NEXT_LV2 | 次のレベル 2 の部屋に移動してもよい。そうした場合、場札を 1 枚獲得し、移動した部屋で床アクションを行う | You may move to the next level 2 room. If you do, take 1 open card {open} and perform that room's floor action |

## 効果の種類（5 種）

| ID | 日本語（原文） | 英語（案） |
| --- | --- | --- |
| TURN_START | ターン開始時 | Turn start |
| ACTIVATED | 起動 | Activated |
| CONSTANT | 常時 | Constant |
| INSTANT | 即時 | Instant |
| HELD | 保有時 | Held |

## カード名（想い出カード 99 種）

英語名は仕様書 002 §6 の英語名案です。

| ID | 日本語（印刷） | 英語名（案） |
| --- | --- | --- |
| M001 | 捨てられたイデア | Abandoned Idea |
| M002 | 既視感 | Deja Vu |
| M003 | 荒廃 | Desolation |
| M004 | 解かれぬ構造 | Unsolved Structure |
| M005 | 肖像 | Portrait |
| M006 | 自由の代償 | Price of Freedom |
| M007 | 色褪せた写真 | Faded Photograph |
| M008 | 予知夢 | Prophetic Dream |
| M009 | 届かなかった想い | Unreached Feelings |
| M010 | 閉ざされた論理 | Closed Logic |
| M011 | 涙の輝き | Glitter of Tears |
| M012 | 虚ろな情報 | Hollow Information |
| M013 | 問い | The Question |
| M014 | 叡智の末路 | Fate of Wisdom |
| M015 | 意識の縁 | Edge of Consciousness |
| M016 | 想い出の忘却 | Forgetting Memories |
| M017 | 重圧 | Pressure |
| M018 | 強大な障壁 | Mighty Barrier |
| M019 | 理解の限界 | Limit of Understanding |
| M020 | 幼き日の恐怖 | Childhood Fear |
| M021 | 自己の数式 | Formula of the Self |
| M022 | 理性の自壊 | Collapse of Reason |
| M023 | 憂鬱 | Melancholy |
| M024 | 正義の重み | Weight of Justice |
| M025 | 連続性の崩壊 | Broken Continuity |
| M026 | 忘れられた約束 | Forgotten Promise |
| M027 | 別れの背中 | Back of Parting |
| M028 | 歪曲する記録 | Distorted Record |
| M029 | 古代の美学 | Ancient Aesthetics |
| M030 | 矛盾を抱いた決断 | Contradictory Decision |
| M031 | 曖昧な記憶 | Vague Memory |
| M032 | 宙吊りの罪悪感 | Suspended Guilt |
| M033 | 零れ落ちる星屑 | Spilling Stardust |
| M034 | 思考の鍵 | Key of Thought |
| M035 | 巣立ち | Leaving the Nest |
| M036 | 反復する日常 | Repeating Routine |
| M037 | こだまする問い | Echoing Question |
| M038 | 虚無の囁き | Whisper of the Void |
| M039 | 錯覚の牢獄 | Prison of Illusion |
| M040 | 日記 | Diary |
| M041 | 裏切りの果て | End of Betrayal |
| M042 | 意見の錯綜 | Tangled Opinions |
| M043 | アーカイブ | Archive |
| M044 | 嫉妬の茨 | Thorns of Jealousy |
| M045 | 革新の予感 | Premonition of Innovation |
| M046 | 迷宮の起点 | Origin of the Labyrinth |
| M047 | 消えた日常 | Vanished Routine |
| M048 | 存在の彼岸 | Beyond Existence |
| M049 | エイドスの残響 | Echo of Eidos |
| M050 | 涙の味 | Taste of Tears |
| M051 | 螺旋の指南 | Spiral Guidance |
| M052 | 選択と未来 | Choice and Future |
| M053 | 主観と客観 | Subjective and Objective |
| M054 | 自己像 | Self-Image |
| M055 | 記憶の目録 | Catalog of Memories |
| M056 | 正義の境界線 | Boundary of Justice |
| M057 | 封印された選択肢 | Sealed Choice |
| M058 | 観測前夜 | Eve of Observation |
| M059 | 矛盾した公理 | Contradictory Axiom |
| M060 | あの日の残像 | Afterimage of That Day |
| M061 | 没頭する刻 | Moment of Immersion |
| M062 | 自己矛盾 | Self-Contradiction |
| M063 | 閉じたアルバム | Closed Album |
| M064 | 瞬間の保存 | Preserved Moment |
| M065 | 思惟の渦中 | Amid Contemplation |
| M066 | 偉大なる指導者 | Great Leader |
| M067 | 偉人のひらめき | Great Mind's Inspiration |
| M068 | 夏至の午後 | Summer Solstice Afternoon |
| M069 | 希望の篝火 | Bonfire of Hope |
| M070 | 終端の構造 | Structure of the Terminus |
| M071 | 子守唄 | Lullaby |
| M072 | 鏡の向こう | Beyond the Mirror |
| M073 | 断絶した思考の輪 | Severed Ring of Thought |
| M074 | 心の修復 | Mending the Heart |
| M075 | 友情の証 | Proof of Friendship |
| M076 | 昇華 | Sublimation |
| M077 | 眠りの瞬間 | Moment of Sleep |
| M078 | 夕暮れの約束 | Twilight Promise |
| M079 | 過去への扉 | Door to the Past |
| M080 | 赦しの言葉 | Words of Forgiveness |
| M081 | 無名の憧れ | Nameless Longing |
| M082 | 有限と無限の間 | Between Finite and Infinite |
| M083 | 愛の誓い | Vow of Love |
| M084 | 走り続けた脚 | Legs That Kept Running |
| M085 | 照らすもの | That Which Illuminates |
| M086 | 旋律のしぶき | Spray of Melody |
| M087 | 勇気の残り香 | Lingering Scent of Courage |
| M088 | 知識の方舟 | Ark of Knowledge |
| M089 | 好きぴ | Crush |
| M090 | 絆の糸 | Thread of Bonds |
| M091 | 無限回廊の地図 | Map of the Infinite Cloister |
| M092 | 勝利の瞬間 | Moment of Victory |
| M093 | 彼方への越境 | Crossing Beyond |
| M094 | 知識の迷宮 | Labyrinth of Knowledge |
| M095 | 歓喜の残滓 | Remnants of Joy |
| M096 | 存在の多面性 | Many Facets of Existence |
| M097 | 文字の恩恵 | Blessing of Letters |
| M098 | 母の手の温もり | Warmth of Mother's Hand |
| M099 | 繋いできた願い | Wishes Passed Down |

## カード名（自我 13 種・目標 7 種・精神カードの階層）

目標カードの日本語は card.csv の名前です（印刷にはカード名が無い）。精神カードは階層だけを出します（R1B・R1C は R1A、R3B・R3C は R3A と同じ）。

| ID | 日本語 | 英語名（案） |
| --- | --- | --- |
| I01 | 巨匠 | Maestro |
| I02 | 領事 | Consul |
| I03 | 建築家 | Architect |
| I04 | 論理学者 | Logician |
| I05 | 起業家 | Entrepreneur |
| I06 | 擁護者 | Advocate |
| I07 | 冒険家 | Adventurer |
| I08 | 主人公 | Protagonist |
| E01 | 頑張り屋な | Diligent |
| E02 | 素直な | Candid |
| E03 | 照れ屋な | Shy |
| E04 | 気まぐれな | Capricious |
| E05 | 真面目な | Earnest |
| O001 | 万能移動鍵の目標 | Wild Move Key Goal |
| O002 | Lv2自己構成の目標 | Level 2 Composed Goal |
| O003 | 同一エンジンの目標 | Single Engine Goal |
| O004 | 手札の目標 | Hand Goal |
| O005 | 心光の目標 | Light Goal |
| O006 | 精神分析階層の目標 | Analysis Floor Goal |
| O007 | 多種エンジンの目標 | Multi Engine Goal |
| R1A | 第一階層 | Tier 1 |
| R2 | 第二階層 | Tier 2 |
| R3A | 第三階層 | Tier 3 |
| R4 | 第四階層 | Tier 4 |
| R5 | 第五階層 | Tier 5 |
