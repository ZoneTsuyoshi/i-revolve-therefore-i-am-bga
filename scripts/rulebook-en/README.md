# 英語版ルールブックの原稿とビルド

ルールブック（原本 `data/rulebook/rulebook_web.pdf`、全 8 ページ、165 × 227 mm）を英語にした PDF を作る仕組み。翻訳の方針と用語集は `docs/publishing/rulebook-en.md`。この文書は「1 ページ分の原稿を書いて確認する」ための手引きで、これだけ読めば 1 ページ書ける。

- **方式**: 原本 PDF の日本語の本文だけを取り除いた画像（下地）を作り、同じ位置に英語の HTML テキストを重ねてローカルの Chrome で PDF にする。背景・図・カード画像・番号札・ページ番号は原本のまま。英語は文字として選択・検索でき、フォントは埋め込まれる。
- **必要なもの**: 作者の手元の `data/`（Git 管理外。ワークツリーからは見えないので、ツールが main の作業ツリーの `data/` を自動で探す。別の場所なら環境変数 `IR_DATA_DIR`）、macOS の Google Chrome（別の場所なら `CHROME_PATH`）、Node、`python3`。`npm ci` 済みであること。
- **成果物**は main の作業ツリーの `build/rulebook-en/`（Git 管理外）。原本の図版を含むので**画像・PDF はコミットしない**。コミットするのは `pages/*.html` だけ。

## 1. 1 ページを書いて確認する手順

1. 担当ページを決める（`p1`〜`p8`）。下地ができていなければ作る（初回だけ。約 40 秒）:

   ```bash
   npm run prepare:rulebook-en
   ```

2. 原文を読む。抜いた日本語の一覧は `build/rulebook-en/spans/pN.txt`（位置つき、上から下・左から右）。原本の見た目は領域を拡大して見る（Read ツールは大きい画像を縮小するので、ページ全体のままだと小さい字を読み違える）:

   ```bash
   node scripts/rulebook-en/measure.mjs crop orig p4 40 150 200 80 --zoom 3 --grid 10
   ```

3. `scripts/rulebook-en/pages/pN.html` を書く（第 4 節）。座標は `spans/pN.txt` の位置がそのまま使える。
4. ビルドして原本と見比べる:

   ```bash
   node scripts/rulebook-en/build.mjs --only p4 --png          # partial/p4.pdf と preview/p4.png
   node scripts/rulebook-en/build.mjs --only p4 --png --debug  # 文字の箱（マゼンタ）と覆う四角（緑の破線）に枠
   node scripts/rulebook-en/measure.mjs compare p4 36 28 400 200 --zoom 2.2   # 原本と並べた画像
   ```

5. 確認（第 7 節）。はみ出し・縮小の報告が出たら言い回しを短くするか箱を広げる。

全ページを通しでビルド（`npm run build:rulebook-en`）すると `build/rulebook-en/I-Revolve-Therefore-I-Am-Rulebook-EN.pdf`（8 ページ）ができる。原稿の無いページは下地だけになる。はみ出しが 1 つでも残ると非 0 で終わる。

| オプション | 意味 |
| --- | --- |
| `--only p3,p5` | 指定ページだけを `build/rulebook-en/partial/pN.pdf` に出す。複数人が同時に回しても出力は衝突しない（一時ファイルとユーザーデータは分かれている） |
| `--png` | `build/rulebook-en/preview/pN.png` を出す。**下地・原本画像と同じ寸法（400 dpi）・同じ座標系**なので、原本と同じ座標で見比べられる |
| `--debug` | 文字の箱と覆う四角に細い枠を出す（`--png` と併用すると PNG だけ作り、単独だと `-debug` 付きの PDF を作る） |
| `--dpi 300` `--quality 88` | 下地の解像度と JPEG の品質（第 8 節） |

毎回、文字の箱ごとの指定サイズ・最終サイズ・はみ出しを標準出力と `build/rulebook-en/report-pN.json` に出す。

## 2. 座標系

- 単位はすべて **PDF のポイント（pt）**。原点はページの左上、x は右、y は下に増える（PyMuPDF の座標と同じ）。ページは 467.739 × 643.487 pt。
- フォントサイズ・行送り・箱の位置と大きさも pt。原稿の数値には単位を付けない（`--x:40.4`）。
- 下地・原本画像・preview は 400 dpi なので、1 pt = 5.556 px。`measure.mjs` は pt で入出力するので、px への換算は要らない。

## 3. 測り方（`measure.mjs`）

画像は Read ツールで開く。結果は `build/rulebook-en/tmp/` に出る。

| 目的 | コマンド |
| --- | --- |
| 領域を拡大し、pt の目盛りを重ねて見る | `crop <orig\|bg\|preview> p4 x y w h --zoom 3 --grid 10`（`bg` は日本語を抜いた下地。どこまで抜けたかを見る） |
| 領域内の日本語の位置・フォント・サイズ・色 | `spans p4 x y w h`（`--all` で残した文字も） |
| 点・矩形の色（覆う四角の色決め） | `color bg p4 x y [w h]`（多い色の上位 3 つと平均） |
| 原本と preview を左右に並べる | `compare p4 [x y w h] [--zoom 2] [--grid 10]`（領域を省くとページ全体） |

**画像を目で見て決めた座標より、`spans` の数値を使う。** 原本の文字の bbox がそのまま箱の位置になる。

## 4. 原稿の書き方

`pages/pN.html` は HTML の断片（`<html>` などは書かない）。ページの左上が原点で、要素は `--x --y --w --h`（pt）で絶対配置する。`<script>` は書かない。**HTML コメントの外に日本語を残さない**（テストが検査する。メモはコメントに書いてよい）。見本は [pages/p4.html](pages/p4.html)。

### 文字の箱 `.t`

```html
<div class="t" style="--x:44.7;--y:176.3;--w:194;--h:38;--fs:7;--lh:1.45">English text with <span class="b8">emphasis</span>.</div>
<div class="t nw vm" style="--x:251.1;--y:58.9;--w:180;--h:11.3;--fs:7;--lh:1">1 行の帯の中の文字</div>
```

| 変数 | 意味（既定） |
| --- | --- |
| `--x` `--y` `--w` `--h` | 位置と大きさ（pt）。`--h` を超えるとはみ出し扱い（自動縮小の対象） |
| `--fs` | フォントサイズ（7）。pt |
| `--lh` | 行間（1.4）。単位なしの倍率 |
| `--ls` | 字間（0）。em の倍率 |
| `--fit-min` | 自動縮小の下限（0.85）。`nofit` クラスで自動縮小を止める |
| `--pg` | `<p>` どうしの間隔（0.3em） |
| `--c` | `class="ct"` を付けたときの文字色 |

| クラス | 意味 |
| --- | --- |
| `c` `r` `j` | 中央・右・両端揃え（既定は左） |
| `vm` `vb` | 縦に中央・下に寄せる（既定は上）。1 行の帯・札・セルの中で使う。1 行なら `--lh:1` にする |
| `nw` | 折り返さない（`.t` に付けると箱全体、文中の `<span class="nw">` ならその範囲） |
| `b5` `b6` `b7` `b8` | 太さ（既定は 500。強調 700、見出し 800。必要なら 600） |
| `cond` | 狭い札用の Barlow Condensed（500・700） |
| `title` | p1 の題字用の EB Garamond Italic（箱絵の英字に合わせる） |
| `up` `it` | 大文字・イタリック |
| `ink` `wh` `alert` `ct` | 文字色（暗い・白・「重要」の赤・`--c` の任意色）。`.t` にも文中の `<span>` にも付く |

書体は同梱の OFL フォント（Barlow Semi Condensed、Barlow Condensed、EB Garamond）だけ。`build/fonts/` に初回のビルドで取得される。**同梱フォントに字形が無い記号は使わない**（`◆` `▪` `≒` `①` など。システムの別のフォントで代用されてしまう）。専用の部品がある: `<span class="dia"></span>`（◆）、`<span class="dot"></span>`（●）、`<div class="li bu">`（▪ の箇条書き）、`<span class="cn">1</span>`（丸数字）。`≧` や矢印は、原本の図の中にあればそのまま残るので書き直さない。

### 箇条書き・番号札・丸数字

`li` は文字の箱 `.t` の**中**に書く（`<div class="t" style="…"><div class="li bu">…</div></div>`。`.t` 自身に `li` を付けると、字下げのぶん位置がずれる）。

```html
<div class="li bu" style="--ind:1.1">Each room you enter requires paying the move cost.</div>   <!-- ▪ つき。折り返しが本文の頭にそろう -->
<div class="li" style="--ind:1.5"><span class="n">a)</span>Using constructed cards</div>      <!-- 見出し付き -->
<span class="nt" style="--c:#2a9d8f">1</span>    <!-- 番号札（色は --c。原本の画像で足りるなら画像を残す） -->
<span class="cn">1</span>                          <!-- 丸数字 -->
```

### 覆う四角 `.e`

**文字は下地から抜いてあるので、日本語を覆う四角は要らない。** 使うのは、文字でないものを消すとき（p1 の題字の絵、本文の途中に埋め込まれたアイコンなど）だけ。

```html
<div class="e" style="--x:96;--y:366;--w:50;--h:12;--c:#e1dcbd;--r:2"></div>
```

色は `--c`（既定は地の色 `#e1dcbd`）。**必ず `measure.mjs color bg pN …` で測る。** 色つきの箱・帯・カードの上に地の色の四角を置くと目立つ。四角は罫線・図・ほかの文字にかけない。

### 本文に埋め込まれたアイコン

本文の行の途中にアイコンが入っている所（鍵・心光・トークンなど）は、下地にアイコンが**残っている**。日本語を抜いた後も、アイコンは元の位置に浮いている。

1. 英語の文の順序・長さが変わるので、元のアイコンを `.e` で消し（色を測る）、英語の文の中に `<i class="ic …"></i>` で置き直す。
2. 位置が合うなら、アイコンを消さずに文字の箱を避けて置いてもよい。

### インラインのアイコン `<i class="ic NAME"></i>`

高さは文字の 1.15 倍（`style="--ich:1.5em"` で変更。大きくしても行の高さは変わらない）。幅は絵の縦横比で決まる（原本の画像の透明な余白は、書き出すときに落としている）。**鍵は縦長なので、文中では `--ich:1.5em` にする**（既定の大きさだと細くて見えにくい）。

| クラス | 中身 | 出どころ |
| --- | --- | --- |
| `key-wisdom` `key-will` `key-emotion` `key-wild` | 知恵鍵（本）・意志鍵（炎）・感情鍵（ハート）・万能鍵 | `data/icons/key_*` |
| `lock-wisdom` `lock-will` `lock-emotion` | 錠前 | `data/icons/lock_*` |
| `energy` | 自己構成エネルギー（COST） | `data/icons/cost` |
| `draw` `open` | 山札から引く・場札から選んで引く | `data/icons/draw` `opendraw` |
| `score` | 自己構成スコア | `data/icons/score` |
| `light` | 心光 | `data/tokens/tokenHR` |
| `omni` `omni-used` | 万能移動鍵トークン（表・使用済み） | `data/tokens/tokenM` `tokenMrev` |
| `tok-construct` `tok-psy` | 自己構成トークン・精神分析トークン | `data/tokens/tokenC` `tokenR` |

足りないアイコンは `assets.mjs` に足す（原本の画像から `{ src }`、または原本ページから切り出す `{ page: 'p4', x, y, w, h }`。座標は pt）。共有ファイルなので、足したいときは報告する。

## 5. 文字の大きさ

原本の地の文は 7 pt（行送り約 12.2 pt）、小さい注釈は 6 pt、見出しは 7〜12 pt（極太）。欧文は同じ pt でも日本語より小さく見える（x ハイトが低い）ので、**英語は原本より一回り大きくして、見た目の大きさをそろえる**。行送りは英語に合わせて詰める。

| 役割（原本） | 英語の第一候補 | 収まらないとき |
| --- | --- | --- |
| 章の見出し（極太 12 pt。「3．遊び方」など） | 13 pt、`b8` | 12 pt |
| ◆ の見出し（極太 10 pt） | 10.5 pt、`b8` | 9.5 pt まで |
| 小見出し・札（極太 7〜8 pt。「a) 手札を捨てる」、赤い札） | 8 pt、`b7`（赤い札は `b8`） | 7 pt まで。狭い札は `cond` |
| 地の文（7 pt） | 8 pt、`--lh` 1.3〜1.4 | 7.5 pt → 7 pt |
| 暗い箱の白い文字（7 pt） | 7.5〜8 pt | 7 pt |
| 細かい注釈（6 pt。p6 の効果の種類、p8 の凡例・謝意） | 6.5〜7 pt | 6 pt |

- **同じページの同じ役割の文字は同じ大きさにそろえる。** 箱ごとにばらばらにしない。地の文が 8 pt で収まらない箱が 1 つでもあれば、まず箱を空いている場所まで広げ、次に言い回しを短くし、それでも駄目ならそのページの地の文をそろえて 7.5 pt（さらに 7 pt）に下げる。
- 地の文は 7 pt より、細かい注釈は 6 pt より小さくしない（図の中のごく細かい注釈だけ 5.5 pt まで）。
- 自動縮小（`fit.js`）は箱の高さ・幅を超えたら 0.25 pt ずつ下げる（下限は指定の 85%）。**自動縮小に頼らず、報告の「縮小」が 0 になるようにする。** 収まらない箱は「はみ出し」と出て、`--png` ではその箱が赤い太枠になる。
- 見出し・箱の中の 1 行は `--lh:1` と `vm` で帯の中心にそろえる。
- 番号札のある箱（p2・p3 の手順）は、原本と同じく 1 行目だけ番号札の右から始める（`style="text-indent:…pt"`）。

## 6. 原本から変えてはいけないところ

罫線・図・番号札・ページ番号・カード画像（カード名・効果文・`CONDITION`/`SCORE`・`COST`/`BONUS`・部屋名など）の中の文字は**原本のまま**（下地に残してある。下地から抜く規則は第 9 節）。ルールを足さない・削らない・直さない。読み取れない文、二通りに読める文は推測で訳さず報告する。

## 7. 確認のしかた（必ずやる）

`--png` の結果を原本と見比べる。全体を縮小して 1 回、さらに領域ごとに等倍以上で、次を確かめる。

- (a) 日本語の消し残し（下地は抜いてあるが、原本が文字でなく図に焼き込んだ日本語は `bg` に残る。`measure.mjs crop bg …` で確かめる）
- (b) 位置のずれ・帯や枠からのはみ出し・文字どうしの重なり
- (c) 色（強調の赤、枠の地の上の文字の読みやすさ）
- (d) 埋め込みアイコンとのずれ

PDF は PyMuPDF（`build/rulebook-en/venv/bin/python`）などで開いて、英語が文字として取り出せること・フォントが埋め込まれていることも確かめる。

## 8. 下地の画質と PDF の大きさ

下地は 400 dpi の画像を 300 dpi 相当に縮め、JPEG 品質 88（mozjpeg、クロマサブサンプリングなし）にする（`--dpi` `--quality`）。カードの中の小さな日本語（4.7 pt）が 700 dpi で拡大しても読め、通しの PDF が約 3.3 MB（10 MB 未満）になる。縮小・圧縮後の JPEG は `build/rulebook-en/bgjpg/` にキャッシュされる。

## 9. 下地の作り方と、抜く文字の規則（`prepare.py`）

`npm run prepare:rulebook-en` が、PyMuPDF の墨消しで原本 PDF から文字だけを取り除いた画像を作る（背景・図・カード画像は無傷）。初回は `build/rulebook-en/venv` を作って依存を入れる。

- 抜く規則: フォントが `HiraMaruProN-W4` / `HiraKakuStd-W8` / `HiraKakuStdN-W8` で、サイズが 5.5 pt 以上で、数字だけでない文字列。カードの中の文字（どれも 5 pt 未満か別のフォント）、番号札の数字、ページ番号、`≧20` などの図の記号は残る。
- 例外は `overrides.json`（ページ・文字列・位置で `keep` / `remove`）。判定を変えたら `prepare` を回し直す。
- 墨消しは空白以外の文字の範囲だけ（行頭の字下げが番号札に重なっていても巻き込まない）。
- **自動検査**: 原本と下地を画像にして差分を取り、抜いた文字の範囲（2 pt の余白つき）の外に差分が出ていたら場所を報告して非 0 で終わる。残した文字の範囲は 2 画素の塊でも許さない。箱の枠線の描画の揺らぎ（1〜3 画素幅の線）だけは許す。
- 出力: `bg/pN.png`（下地）、`orig/pN.png`（原本そのまま）、`spans/pN.json`（全文字列の位置・フォント・サイズ・色・`removed`）、`spans/pN.txt`（抜いた文字の一覧）。

## 10. よくある失敗

| 症状 | 原因と対処 |
| --- | --- |
| 文字が下地の日本語と二重に見える | 抜かれていない日本語（図に焼き込み、または規則から外れた文字）。`measure.mjs spans` で確かめ、`overrides.json` か `.e` で対処 |
| 記号が別のフォントで出る（`◆` `≒` など） | 同梱フォントに字形が無い。専用の部品（第 4 節）を使うか、言い換える |
| 1 行なのにはみ出しと報告される | `--lh`（既定 1.4）× `--fs` が `--h` より大きい。`--lh:1` にするか `--h` を広げる |
| 文字の箱が縮小される | 箱が狭い。広げるか言い回しを短くする。ほかの箱とサイズがばらばらにならないようにする |
| 通しのビルドで「ページ数が違う」 | ページの外へ要素が溢れて 2 ページ目ができた。座標が 467.739 × 643.487 pt の内側か確かめる |
| 覆う四角が目立つ | 色が違う。`measure.mjs color bg …` で測る |

## 11. ファイル構成

```text
scripts/rulebook-en/
  README.md         この文書
  prepare.sh        下地づくりの入口（venv を作って prepare.py を実行）
  prepare.py        原本 PDF から日本語の本文を抜いた下地・span の一覧・差分検査
  overrides.json    抜く・残すの上書き表
  requirements.txt  prepare.py の依存（pymupdf を版固定）
  build.mjs         PDF のビルド（Node、sharp、Chrome）
  measure.mjs       測定補助
  rulebook.css      共通スタイル
  fit.js            ブラウザ内の自動縮小・はみ出し検出
  assets.mjs        インラインアイコンの出どころ
  lib/              ビルドの部品（パス・フォント取得、引数、原稿の検査、PDF 検査）
  pages/pN.html     1 ページにつき 1 ファイルの原稿（無ければ下地だけ）
tests/js/rulebook-en.test.mjs   原稿の検査と純粋な関数のテスト（CI で回る）
```

フォントは SIL Open Font License 1.1。出どころは `https://github.com/google/fonts/tree/main/ofl/`（`barlowsemicondensed`、`barlowcondensed`、`ebgaramond`）。

## 12. 作り直しの手順

原本が差し替わったら `npm run prepare:rulebook-en` を回し直す（`spans/*.txt` と原稿の座標が変わっていないか確かめる）。出力を最初から作り直すなら `build/rulebook-en/` を消してよい（`venv`・`bgjpg`・`assets` も再生成される。`build/fonts/` は消さなくてよい）。
