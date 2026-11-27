// インラインのアイコンの出どころ。ビルドのたびに build/rulebook-en/assets/ へ小さく書き出し、icons.css を作る。
// 使い方: <i class="ic key-wisdom"></i>（クラス名は下のキー）。
// 出どころは 2 種類。
//   { src: 'icons/key_book@4x.png' }          data/ の原本（data/ からの相対パス）
//   { page: 'p4', x, y, w, h, keyOut: false }   原本のページ画像（build/rulebook-en/orig/）から切り出す。座標は pt（左上原点）
// 足りないアイコンは、ここに足す（原稿の担当者は触らない前提。必要なら報告する）。

export const ICON_HEIGHT = 160; // 書き出しの高さ（px）。インライン表示には十分

export const ICONS = {
  // 鍵（移動コスト・精神分析コストの支払いに使う）
  'key-wisdom': { src: 'icons/key_book@4x.png' },   // 知恵鍵（本）
  'key-will': { src: 'icons/key_fire@4x.png' },     // 意志鍵（炎）
  'key-emotion': { src: 'icons/key_heart@4x.png' }, // 感情鍵（ハート）
  'key-wild': { src: 'icons/key_general@4x.png' },  // 万能鍵
  // 錠前（想い出カードの裏の移動コスト）
  'lock-wisdom': { src: 'icons/lock_book@4x.png' },
  'lock-will': { src: 'icons/lock_fire@4x.png' },
  'lock-emotion': { src: 'icons/lock_heart@4x.png' },
  // そのほかのアイコン
  energy: { src: 'icons/cost@4x.png' },       // 自己構成エネルギー（COST）
  draw: { src: 'icons/draw@4x.png' },         // 山札から引く
  open: { src: 'icons/opendraw@4x.png' },     // 場札から選んで引く
  score: { src: 'icons/score@4x.png' },       // 自己構成スコア
  // トークン
  light: { src: 'tokens/tokenHR.png' },          // 心光
  omni: { src: 'tokens/tokenM.png' },            // 万能移動鍵トークン
  'omni-used': { src: 'tokens/tokenMrev.png' },  // 万能移動鍵トークン（使用済み）
  'tok-construct': { src: 'tokens/tokenC.png' }, // 自己構成トークン
  'tok-psy': { src: 'tokens/tokenR.png' },       // 精神分析トークン
};
