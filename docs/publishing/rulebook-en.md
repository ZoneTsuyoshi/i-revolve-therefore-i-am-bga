# 英語版ルールブック（PDF）

印刷版のルールブック（`data/rulebook/rulebook_web.pdf`、全 8 ページ）を英語にした PDF の作り方と、翻訳の決まり。BGA や BoardGameGeek で遊ぶ海外のプレイヤーに渡すためのもの。公式サイトの英語ページは、いまは日本語のルールブックだけを案内している。

- 成果物: `build/rulebook-en/I-Revolve-Therefore-I-Am-Rulebook-EN.pdf`（メインの作業ツリーの `build/`。Git 管理外。原本の図版を含むためコミットしない）
- 原稿とビルド: [`scripts/rulebook-en/`](../../scripts/rulebook-en/README.md)、`npm run prepare:rulebook-en`（初回だけ）→ `npm run build:rulebook-en`
- ビルドには作者の手元の `data/`、Google Chrome、Python 3（PyMuPDF を仮想環境に入れる）が必要

## 1. 方式

原本の PDF から**日本語の地の文・見出し・図の注釈だけを取り除いたページ**を画像にして下地にし、同じ位置に英語の文字（PDF 上は文字として選択・検索できる）を重ねる。図・枠・番号札・ページ番号・カードやコマの画像は原本のまま。ページ構成と寸法（165 × 227 mm、8 ページ）も原本と同じ。

カードやコマの画像の中の日本語（カード名・効果文）は製品の姿なので、そのまま残す（英語版の画像はない。rule-questions Q-16）。

英語の書体は、原本の丸ゴシックより字幅の狭い Barlow Semi Condensed（狭い札は Barlow Condensed）、題字は箱絵の英字に合わせた EB Garamond Italic（いずれも OFL）。欧文は同じポイント数でも日本語より小さく見えるので、原本の地の文 7 pt に対して英語は 7.5〜8 pt で組む（役割ごとの大きさは `scripts/rulebook-en/README.md` の 5 節）。

姉妹プロジェクト PROJECT EDGEWORTH の英語版ルールブックと同じ考え方で、違いは下地の作り方（あちらは原本画像の文字を背景色で覆った。こちらは原本の PDF の文字が本物のテキストなので、文字だけを抜ける）。

## 2. 翻訳の方針

1. **印刷版に忠実に訳す。** ルールを足さない、削らない、直さない。FAQ の裁定や BGA 版だけの扱い（スタートプレイヤーをランダムに決める、など）は入れない。
2. **確定している誤記は直す。** 作者が誤記と認めた箇所、明らかな転記ミスは訂正後の内容で書き、4 節に記録する。
3. **用語は BGA 版の英語に合わせる。** 3 節の用語集と、[card-texts-review.md](card-texts-review.md) のカード名・効果文、[game-description.md](game-description.md) の英訳案に従う。
4. **読み取れない・二通りに読める文は推測で埋めない。** 仕様書 [001](../specs/001-game-rules.md) は日本語の意味を確かめるためだけに使い、そこにしかない内容は足さない。迷った箇所は 6 節に載せる。
5. **文体。** アメリカ英語。短く平易な文。プレイヤーへの指示は命令形か "you"。数は数字で書く。原本の「2/3 人の場合…52/26 枚」のようなスラッシュの並びは同じ形で残す。
6. **表記。**
   - フェイズ名は BGA と同じ `Move phase` / `Recall phase` / `Floor Action phase` / `Construction phase` / `Cleanup phase`（phase は小文字。見出しの中だけ各語の頭を大文字にする）。
   - 見出しの「◆移動フェイズ）ミープルを移動する」の形は `◆ Move Phase: Move Your Meeple` にする（閉じ括弧をコロンに）。
   - 部屋名（`HERMES` など）は印刷どおり大文字で、引用符を付けない。カード名は “Vow of Love” のように引用符で囲む。カード番号は添えない（原本も添えていない）。
   - レベルは文中では小文字の `level 1` / `level 2`（BGA のカード文と同じ）。札や見出しの頭では `Level 1`。
   - 階層は `tier 1`〜`tier 5`。
   - `Memory card`・`Goal card`・`Ego card`・`Mind Track card` は 1 語目だけ大文字。`floor card`・`open cards`・`meeple`・`cube`・`token` は小文字。
   - 例に出るプレイヤー名は Maria（マリア）/ Bob（ボブ）/ Toshiki（トシキ）。**she / he を使わない**（名前を繰り返すか they / their）。
   - 「発動する」は trigger（常時・保有時の効果は in effect）。activate は効果の種類の「起動」（Activated）だけに使う。
   - 引用符は曲がった引用符（“ ”）。句読点は閉じ引用符の内側（アメリカ英語の流儀）。
   - 原本が太字・赤字にしている語は、英語でも同じ語を太字・赤字にする。原本が文中に置いているアイコンは、英語でも同じ位置づけで置く。

## 3. 用語集

| 日本語 | 英語 | 備考 |
| --- | --- | --- |
| 我廻る、故に我あり | I Revolve, Therefore I Am | |
| 精神回廊《サイコ・クロイスター》 | the Psycho Cloister | |
| 人格 | personality | 物語の文だけに出る。コマは meeple |
| ミープル / キューブ | meeple / cube | |
| 自我（物語の文） | ego | 自我カードの Ego と同じ語。「自己」は self |
| 回廊 / 部屋 | cloister / room | |
| 床カード / 床 / 床アクション | floor card / floor card / floor action | |
| 周遊ボーナス | Tour Bonus | |
| 想い出カード | Memory card | |
| 想い出の山札 / レベル 2 想い出の山札 | Memory deck / level 2 Memory deck | 単に Memory deck と言えばレベル 1（p.II 手順 2） |
| 想い出の捨札 | Memory discard pile | |
| 想い出の場札 / 場札 | open cards | BGA 版と同じ |
| 手札 / 手札上限 | hand / hand limit | |
| 表向き / 裏向き | face up / face down | |
| 横向き（使用済み）/ 縦向き | sideways (used) / upright | |
| 自己構成（アクション名）/ 自己構成する | Construction / construct | |
| 自己構成アクション / 自己構成フェイズ | Construction action / Construction phase | |
| 自己構成エネルギー / エネルギー | Construction energy / energy | |
| 自己構成済みカード / 自己構成済みエリア・置き場 | constructed card / constructed area | |
| 自己構成スコア | Construction Score | |
| 自己構成トークン / 精神分析トークン | Construction token / Psychoanalysis token | |
| 精神分析 / 精神分析アクション | Psychoanalysis / Psychoanalysis action | |
| 精神分析コスト / 精神分析ボーナス | Psychoanalysis cost / Psychoanalysis bonus | |
| 精神トラック / 精神カード | Mind Track / Mind Track card | |
| 階層 / 第 N 階層 | tier / tier N | |
| 鍵 | key | |
| 知恵鍵 / 意志鍵 / 感情鍵 / 万能鍵 | Wisdom key / Will key / Emotion key / Wild key | |
| 移動コスト | move cost | |
| 心光 / 心光トークン | Psychic Light / Psychic Light token | |
| 心光の行使 | Use Psychic Light | 支払い方法の名前。文中では “Use Psychic Light” と引用符で囲む |
| 万能移動鍵（トークン） | Omni-Move Key (token) | |
| 自我カード / 初期自我カード / 強化自我カード | Ego card / Initial Ego card / Enhanced Ego card | |
| 目標カード | Goal card | 獲得する = claim |
| 想起フェイズ / 想起効果 | Recall phase / recall effect | |
| 移動フェイズ / 床アクションフェイズ / クリーンアップフェイズ | Move phase / Floor Action phase / Cleanup phase | |
| フリーアクション | free action | |
| 入室する / スキップする / 通過する | enter / skip / pass through | |
| スタートプレイヤー / ターンプレイヤー | start player / turn player | |
| 手番・ターン | turn | |
| ターン開始時 / 起動 / 常時 / 即時 / 保有時 | Turn start / Activated / Constant / Instant / Held | 効果の種類 |
| 刹那の追憶 / 断片の結集 / 心理の極致 | Fleeting Recollection / Gathered Fragments / Pinnacle of the Mind | 作者の回答（game-description、2026-10-20） |
| 勝利条件 | victory condition | |
| 例 | Example | |
| 重要 / 注意 | Important / Note | |
| 回復 / 補充（クリーンアップ） | refresh / refill | |

例に出るカード名（[card-texts-review.md](card-texts-review.md) の英語名）: 愛の誓い = Vow of Love、昇華 = Sublimation、知識の迷宮 = Labyrinth of Knowledge、あの日の残像 = Afterimage of That Day、叡智の末路 = Fate of Wisdom、夏至の午後 = Summer Solstice Afternoon、気まぐれな = Capricious。

## 4. 原本から変えるところ

| 箇所 | 原本 | 英語版 | 理由 |
| --- | --- | --- | --- |
| p.I 題字 | 「我廻る、故に我あり」（絵） | I Revolve, Therefore I Am（文字。箱絵の英字と同じイタリックのセリフ体） | 英語版の題。日本語の題字は覆う（6 節 No. 5） |
| p.I 物語の文 | — | 公式サイトの英語ページの文をもとに、自我 = ego、全精神 = the whole mind に直した | サイトは 自我 を self、全精神 を the whole world としていたが、ゲームの用語（Ego カード）と原文に合わせた（6 節 No. 4）。サイトの英語ページも同じ語にそろえた（2026-11-27） |
| p.I | — | "Component images show the Japanese edition." の注記を足した | カードの画像は日本語版のまま載せているため |
| p.V 床アクションの例 | 「アルテミス」の床アクション | HESTIA | 作者が誤記と確認済み（rule-questions Q-31、001 D-1）。図も内容も HESTIA |
| p.VII 精神分析コストの c) | この**自己構成**アクションで心光を**エネルギー**として…**エネルギー** 1 つ分…2/3 **エネルギー**には心光を 3/6 個 | this **Psychoanalysis** action … as **a cost** … 1 **key** of any type … 2/3 **keys** cost 3/6 Psychic Light | 自己構成（p.VI の b)）の文面の転記。作者が確認済み（rule-questions Q-73、001 D-2。6 節 No. 1） |
| p.VIII その他 | いつでも想い出カード 2 枚捨てれば、心光を獲得可能 | … to gain **1** Psychic Light | 英語は数が要る。1 つは作者の回答（rule-questions Q-03）のとおり |
| p.VIII 謝意・クレジット | 日本語の名前 | ローマ字（下の表） | 姉妹プロジェクトの英語版ルールブックでの作者の決定（2026-11-26）に合わせた。読みは作者が確認済み（6 節 No. 3） |
| 全体 | 例の人物 | she / he を使わず名前を繰り返す | 性別を決めつけない |

p.VIII の名前の表記（ヘボン式、長音記号なし。名前の順は原本のまま）:

| 小見出し | 原本 | 英語版 |
| --- | --- | --- |
| Special Thanks (Playtesting) | にわにわ、かれん、あっさい、まるも、いでらん、シロクマ、クロクマ、小河タイラ | Niwaniwa, Karen, Assai, Marumo, Ideran, Shirokuma, Kurokuma, Ogawa Taira |
| Credits (Production) | だんじり協奏曲（ぞね、ひのき、たづくり、まや） | Danjiri Concerto (Zone, Hinoki, Tazukuri, Maya) |

箱に収めるために、意味を変えずに短くした箇所:

| 箇所 | 用語集・直訳 | 英語版 |
| --- | --- | --- |
| p.II 山札・捨札の札 | Memory discard pile | Memory discard（札の幅が狭いため。本文では discard pile） |
| p.III 強化自我の吹き出し | Each player who reaches tier 3 … | Each player reaching tier 3 secretly looks at the fronts and takes 1 card. |
| p.VI 効果の種類「ターン開始時」 | Triggers at the start of your turn. | Triggers at turn start. |
| p.VI 効果の種類「即時」 | Triggers once, when you construct this card. | Triggers once, when constructed. |
| p.VIII その他 | You cannot construct 2 Memory cards with the same name | Cannot construct 2 Memory cards with the same name（原本も「自己構成不可」の体言止め） |

カードの図柄の一部として印刷されている日本語は、ルールブックの図の中でもそのまま残した（p.III の強化自我カードの裏面の「強化自我」、p.VIII の凡例のアイコンの中の「ターン中」「想い出」「床」、勝利条件の図の「第五階層」のタイル、各ページのカード画像）。実物のカードと見比べられるようにするため。

## 5. 作り方と確認

1. 初回だけ `npm run prepare:rulebook-en` で、原本から下地（日本語の本文を抜いたページの画像）と、原文の位置の一覧を作る。
2. 原稿（`scripts/rulebook-en/pages/pN.html`）を直す。書き方は [`scripts/rulebook-en/README.md`](../../scripts/rulebook-en/README.md)。
3. `node scripts/rulebook-en/build.mjs --only pN --png` で、はみ出し 0・縮小 0 を確かめ、`build/rulebook-en/preview/` の画像を原本と見比べる。
4. `npm run build:rulebook-en` で全ページの PDF を作る。

PDF は原本の図版（カード・コマ・ルールブックの絵）を含むので、このリポジトリにはコミットしない。公開するときは作者が公式サイトなどに置く。

## 6. 作者に確認したい点

作者が全件に回答した（2026-11-27）。訳文と PDF の変更はない。

| No. | 箇所 | 確認したいこと | 案 | 状態 |
| --- | --- | --- | --- | --- |
| 1 | p.VII 精神分析コストの c) | 原本は自己構成の文面（「この自己構成アクションで心光をエネルギーとして…」）。転記ミスか | 転記ミスとして、英語版は精神分析の文面（鍵 1 つ分）にした。日本語版の訂正は作者の判断 | **回答（2026-11-27）: 案のとおり**（転記ミス。rule-questions Q-73） |
| 2 | p.III 手順 12 | 「自我を早く確立した人がスタートプレイヤー」の訳 | "The player who established their ego earliest is the start player" | **回答（2026-11-27）: 案のとおり** |
| 3 | p.VIII 謝意・クレジット | 名前のローマ字。小河タイラ = Ogawa Taira（Kogawa かもしれない）、いでらん = Ideran、まるも = Marumo、にわにわ = Niwaniwa。ぞね = Zone | 4 節の表のとおり | **回答（2026-11-27）: 案のとおり**（小河タイラ = Ogawa Taira） |
| 4 | p.I 物語の文 | 自我 = ego（公式サイトの英語ページは self）、全精神 = the whole mind（サイトは the whole world） | ego / the whole mind。サイト側をそろえるかは作者の判断 | **回答（2026-11-27）: 案のとおり**。サイトの英語ページも ego / the whole mind にそろえた（作者の指示。サイト側のコミット `cb1a737f`） |
| 5 | p.I 題字 | 日本語の題字の絵を残して英題を添えるか、英題に置き換えるか | 英題に置き換えた | **回答（2026-11-27）: 英題に置き換えてよい** |
| 6 | 例のプレイヤー名 | マリア / ボブ / トシキの綴り | Maria / Bob / Toshiki | **回答（2026-11-27）: 案のとおり** |
| 7 | 用語 | 3 節の訳語。BGA 版の英語（[card-texts-review.md](card-texts-review.md) で確認待ちの用語）と同じ。変える場合は BGA 版の文言もそろえる | 3 節のとおり | **回答（2026-11-27）: 案のとおり**（BGA 版の用語もこのまま。[card-texts-review.md](card-texts-review.md) の「迷った点」1・4 も確定） |
| 8 | 公開のしかた | PDF をどこに置くか（公式サイトの英語ページのリンク、BGG、BGA のゲームページなど） | 作者が決める。エージェントは PDF を作るところまで | **回答（2026-11-27）: リンクを作ってから、作者が公式サイトに足す**。作者が英語ページにリンクを足した（2026-11-27。サイト側のコミット `fae5ab5d`） |

## 7. 記録

| 日付 | 内容 |
| --- | --- |
| 2026-11-27 | 方針・用語集を作成。ビルドの仕組み（`scripts/rulebook-en/`）と全 8 ページの初版を作成（8 ページ、約 3.4 MB、文字のはみ出し・自動縮小 0。作者の確認待ち） |
| 2026-11-27 | 作者の確認を反映。8 件とも案のとおりで確定（訳文・PDF の変更なし）。題字は英題への置き換えで確定。公開は、リンクを作ってから作者が公式サイトに足す |
| 2026-11-27 | 作者が公式サイトの英語ページに英語版ルールブックのリンクを足した（サイト側のコミット `fae5ab5d`）。作者の指示で、サイトの英語ページ（ゲームページの物語の文と決め文句、トップページの決め文句）を ego / the whole mind にそろえた（同 `cb1a737f`） |
