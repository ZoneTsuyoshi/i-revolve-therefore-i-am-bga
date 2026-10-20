# FAQ・カードデータ（サイト側）の起票・改修

『我廻る、故に我あり』の FAQ とカードデータの原本は、サイト側リポジトリ（`../danjiri-concerto.github.io`）にある。作者の方針により、**新しい裁定やカードデータの訂正は、このリポジトリの docs ではなくサイト側の CSV を直接編集して追加・改修し**、BGA 実装はそれに追従させる。

| ファイル | 内容 | 公開ページ |
| --- | --- | --- |
| `../danjiri-concerto.github.io/i-revolve-therefore-i-am/data/faq.csv` | FAQ（裁定） | <https://danjiri-concerto.github.io/i-revolve-therefore-i-am/faq.html> |
| `../danjiri-concerto.github.io/i-revolve-therefore-i-am/data/card.csv` | 全カードのデータ（想い出・初期自我・強化自我・目標） | FAQ ページのカード名表示などに使われる |

公開ページ（`faq.html`）は 2 つの CSV をブラウザで直接読み込んで表示する。変換スクリプトはなく、**CSV を `gh-pages` に push した時点で公開される**。

## `faq.csv` の列

| 列 | 内容 |
| --- | --- |
| `category` | 分類。複数はカンマ区切り（例: `"一般, 目標"`）。既存の分類から選ぶ: 一般 / 初期自我 / 想い出カード効果 / 想起 / 床アクション / 移動 / 精神分析 / 自己構成 / 目標 |
| `card_ids` | 主な対象カードの番号（`card.csv` の `card_id`。例: `62` / `58,I2`）。ルール全般の質問は空欄。ページにカード画像が出る |
| `recall_card_ids` | 想起効果が対象になるカードの番号。既存の行に合わせて書く（2026-10-20 時点で `faq.html` の表示には使われていない） |
| `add_display_ids` | 追加で画像を表示するカードの番号 |
| `question` | 質問文 |
| `answer` | 回答文（「はい、〜」「いいえ、〜」から始める） |

`status` 列（下書き）はない。**作者の回答で確定した裁定だけを書く。** 下書きは [rule-questions.md](rule-questions.md) に置き、サイト側には書かない。

## `card.csv` の列

`card_id, lv2_id, card_name, id_name, n_card, original_id, level, engine, e1, e2, cost, r, otype, score, condition, effect`。`n_card` の合計が 144（想い出カードの枚数）。列の意味と英語識別子は仕様書 002 で確定させる。印刷物（`data/cards/` の画像・ルールブック）と食い違う場合は印刷物を正とし、作者に確認したうえで `card.csv` を直す。

## 手順

1. 作者への質問（[rule-questions.md](rule-questions.md)）の回答のうち、プレイヤーにも有用な裁定を選ぶ。カードデータの誤りは、印刷物との差分として選ぶ。
2. サイト側の作業ツリーの状態を確認する。サイトは `gh-pages` ブランチで直接運用している。

   ```bash
   cd ../danjiri-concerto.github.io
   git switch gh-pages && git pull --ff-only origin gh-pages
   git status --short   # 作者の作業中のファイルがあっても触らない
   ```

3. Python の `csv` モジュールで行を追加・修正する。**ファイルの形式（CRLF 改行、末尾に改行なし、必要なときだけ引用符）を保つ**こと。この書き方なら、変更した行以外に差分が出ない。

   ```bash
   cd ../danjiri-concerto.github.io
   python3 - <<'EOF'
   import csv, io
   path = "i-revolve-therefore-i-am/data/faq.csv"
   raw = open(path, encoding="utf-8", newline="").read()
   rows = list(csv.reader(io.StringIO(raw)))
   rows.append(["想い出カード効果", "62", "", "", "質問文", "回答文"])
   out = io.StringIO()
   csv.writer(out, lineterminator="\r\n").writerows(rows)
   open(path, "w", encoding="utf-8", newline="").write(out.getvalue()[:-2])  # 末尾の改行を付けない
   EOF
   git diff   # 追加・修正した行だけが変わっていることを確認
   ```

4. 追加・修正した行を作者に報告する。
5. FAQ を追加・修正するたびに、すぐ公開してよい（作者の方針。このリポジトリの Git ルールの対象外）。

   ```bash
   cd ../danjiri-concerto.github.io
   git add i-revolve-therefore-i-am/data/faq.csv   # card.csv を直したときはそれも
   git commit -m "Update I Revolve, Therefore I Am FAQ: <要約>"
   git push origin gh-pages
   ```

   作者の作業中のファイル（未追跡の画像など）はステージしない。

6. このリポジトリ側に反映する。
   - [rule-questions.md](rule-questions.md) の「FAQ」列に起票の有無とサイト側のコミットを書く。
   - 関連する仕様書の「未確定事項」に回答を反映し、本文で FAQ を根拠として引用する（引用は質問文の冒頭で特定する）。
   - `card.csv` を直したときは、仕様書 002 と `src/` のカード定義を追従させる（取り込み方法は仕様書 002 で決める）。

## 同期の確認

サイト側の CSV はこのリポジトリの外で更新されることがある（作者の直接編集など）。仕様書の作成・改訂の前、および実装タスクの着手前に、次で変更がないか確認する。

```bash
git -C ../danjiri-concerto.github.io log --oneline -5 -- i-revolve-therefore-i-am/data/
```

前回確認したコミットより新しい変更があれば、差分を読み、仕様書・実装への影響を確認する。

## 注意

- FAQ の文体は既存の行に合わせる（です・ます調、カード名は「」で囲む）。
- サイト側リポジトリには独自の `CLAUDE.md` がある。FAQ・カードデータ以外（HTML・CSS・画像）は編集しない。
