# メタ画像の生成と登録

BGA のゲームページ（ゲーム一覧・テーブル一覧・紹介ページ）に出る画像の手順書。要件と設計の根拠は [仕様書 009 §14](../specs/009-ui-assets.md) にある。画像は `src/` に置かず、BGA の Game Metadata Manager の Media タブから登録する（005 の自動同期の対象外）。

## 画像と登録欄の対応

| ファイル（`build/meta-images/`） | Media タブの欄 | 形式・寸法 | 必須の時期 |
| --- | --- | --- | --- |
| `box.png` | Box | PNG、280 × 280、透過、影なし | Public Alpha まで |
| `icon.png` | Icon | PNG、50 × 50、枠なし | Public Alpha まで |
| `title.png` | Title | PNG、2000 × 2000、透過、2 MB 以下 | Beta まで |
| `banner.jpg` | Banner | JPG、1386 × 400、2 MB 以下、文字なし | Beta まで |
| `display-1.jpg`、`display-2.jpg`、`display-3.jpg` | Display（3 枚） | JPG、1140 × 760 | Release まで |
| （なし） | Publisher | PNG、280 × 280、任意 | 任意。「だんじり協奏曲」のロゴを載せるなら作者が用意する |

## 登録の手順

1. 「作り直しの手順」で画像を生成する（出力先は `build/meta-images/`）。
2. Private Alpha の前は Studio の <https://studio.boardgamearena.com/controlpanelgames>、以後は本番の <https://boardgamearena.com/controlpanelgames> を開き、このゲームのプロジェクトを選ぶ。
3. Media タブの各欄に、上の表のファイルをアップロードして保存する。
4. ゲームページを開き、次を確かめる。
   - 箱が目立つか（バナーの左に重なる）。
   - タイトルがバナーの上で読めるか。
   - ゲーム一覧のアイコン（50 px）で「廻」の字が判読できるか。
   - 紹介ページのカルーセルに Display 3 枚が出るか。

確認用の見本は `build/meta-images/preview/` にある（登録しない）。`gamepage-mock.png`（バナーの左に箱、上にタイトルを重ねた見立て。実際の重なり方は BGA 側で決まる）、`title-on-light.png`（明るい背景の上のタイトル）、`icon-candidates.png`（Icon の候補を 50 px と 4 倍で並べたもの）、`contact-sheet.jpg`（全出力の一覧）。

## 作り直しの手順

```bash
npm ci
npm run build:meta-images
```

- `data/`（作者の原本）とローカルの Google Chrome が必要。Chrome は既定で `/Applications/Google Chrome.app/Contents/MacOS/Google Chrome`。別の場所なら `CHROME_PATH` で指定する。
- ワークツリーからは `data/` が見えないので、スクリプトは `git rev-parse --git-common-dir` からメインの作業ツリーの `data/` を求める。`IR_DATA_DIR` で上書きできる。
- 出力先は、メインの作業ツリーの `build/meta-images/`（`.gitignore` 済み。`IR_META_OUT` で上書きできる）。画像はコミットしない。
- フォント（OFL の Shippori Mincho B1 ExtraBold と EB Garamond Italic）は初回だけ Google Fonts のリポジトリから `build/fonts/` にダウンロードする。macOS のシステムフォントは使わない。
- 最後に寸法・形式・透過・容量を検査し、違反があれば非 0 で終わる。検査の関数は `scripts/meta-images/lib/checks.mjs`（単体テストは `tests/js/meta-images.test.mjs`）。

## 各画像の素材と構図

- Box: `data/box/box@4x.png` を高さ 280 に縮小し、280 × 280 の透明なキャンバスの中央に置く。
- Icon: 箱の表のタイトルの「廻」を正方形に切り出して 50 × 50 に縮小する。明朝体で描いた「廻」を箱の色の地に置く代替案も作るが、50 px では切り出し版のほうが太く読みやすいので、切り出し版を採った。
- Title: "I Revolve," / "Therefore I Am"（EB Garamond Italic）を title zone に、日本語『我廻る、故に我あり』（Shippori Mincho B1 ExtraBold。「廻」と 2 つ目の「我」を大きく）を subtitle zone の上寄りに置く。アイボリーの文字に濃いセピアの縁取りと影を付け、背景は透明。
- Banner: 暗いセピアの回廊の壁に、想い出カードの額縁（No.79・2・46・94・3・91 の順。右端は途中で切れる）を暖色の照明つきで掛ける。左の約 320 px は無地に近く、箱を重ねる余地にする。文字なし。
- Display 1: `data/thumbnails/ゲーム盤面.png` の左 1680 × 1120 を切り出して縮小する（右の 120 px を削り、左上の心光の山を欠けさせない）。
- Display 2: 想い出カードの表 5 枚（No.3・46・79・91・94）を扇状に、裏面（`Rev1B`・`Rev2H`）を 2 枚、暗い木目の卓に並べる。
- Display 3: 精神カード第一〜第五階層を下から上へ重ね、キューブを置き、初期自我（I1）・強化自我（E1）・目標（O1）・トークン 4 種・人格（ミープル）を添える。

BGA 版のスクリーンショットは Display に使わない（公式の方針）。
