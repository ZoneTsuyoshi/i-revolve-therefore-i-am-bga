# 我廻る、故に我あり for Board Game Arena

自作ボードゲーム **『我廻る、故に我あり』（I Revolve, Therefore I Am）** を [Board Game Arena](https://boardgamearena.com/)（BGA）に実装するためのリポジトリです。

> 現在のステータス: **立ち上げ段階**（仕様書の作成から着手します。実装コードはまだありません）

## ゲーム概要

| 項目 | 内容 |
| --- | --- |
| プレイ人数 | 2〜4 人 |
| プレイ時間 | 30〜60 分 |
| ジャンル | エンジンビルド × 周遊型ワーカープレイスメント |
| 制作・著作 | だんじり協奏曲（ぞね、ひのき、たづくり、まや） |
| 公式サイト | <https://danjiri-concerto.github.io/i-revolve-therefore-i-am/index.html> |

舞台は精神回廊《サイコ・クロイスター》。各プレイヤーは迷宮を廻る人格となり、自我を確立してこの輪廻から抜け出すことを目指します。

- 12 の部屋が並ぶ回廊を、自分のミープル（人格）で時計回りに移動する。入室には部屋の想い出カードの裏面に描かれた移動コスト（鍵）を支払う
- 手番は **移動 → 想起 → 床アクション → 自己構成 → クリーンアップ** の 5 フェイズ
- 想い出カードを **自己構成** して鍵・効果・スコアを持つエンジンを組み、**精神分析** で精神トラックを進める
- 勝利条件は 3 種類で、いずれかを満たしたプレイヤーが即座に勝利する
  - **刹那の追憶**: 1 ターンで全ての部屋に入室する
  - **断片の結集**: 自己構成スコア（想い出カード＋目標カード）の合計が 20 以上になる
  - **心理の極致**: 精神トラックの第五階層に到達する
- 想い出の山札が切れた場合は全員の敗北

正式なルールはルールブック（ローカルの `data/rulebook/rulebook_web.pdf`）と公式サイトの [FAQ](https://danjiri-concerto.github.io/i-revolve-therefore-i-am/faq.html) を参照してください。FAQ とカードデータの原本はサイト側のリポジトリ（`danjiri-concerto.github.io` の `i-revolve-therefore-i-am/data/`）にあり、新しい裁定はそちらに追加します。

## リポジトリ構成

```text
.
├── README.md          # このファイル
├── CLAUDE.md          # Claude Code 向けの開発ルール
├── data/              # 原本アセット（編集しない。Git 管理外でローカルにのみ置く）
│   ├── box/           #   箱絵
│   ├── cards/         #   カード画像（想い出・床・精神・初期自我・強化自我・目標）
│   ├── icons/         #   鍵・錠前などのアイコン
│   ├── piecies/       #   ミープル・キューブ
│   ├── tokens/        #   トークン
│   └── rulebook/      #   ルールブック PDF
├── docs/              # 開発用ドキュメント
│   ├── specs/         #   仕様書（実装前に必ず作成）
│   ├── development/   #   開発ルール（Git 運用・タスクの進め方・CI/CD・作者への質問・FAQ の起票）
│   ├── publishing/    #   公開用の文章（BGG の紹介文など）
│   └── tasks.md       #   タスク一覧
├── src/               # BGA Studio のプロジェクトに同期するゲーム本体（今後作成）
└── .worktrees/        # タスクごとの作業用ワークツリー（Git 管理外）
```

## 開発の進め方

姉妹プロジェクト [minimum-journey-bga](https://github.com/ZoneTsuyoshi/minimum-journey-bga)（ローカルでは `../minimum-journey-bga`）と同じ運用です。

1. タスクごとに `.worktrees/` 配下へワークツリーを作成する
2. まず `docs/specs/` に仕様書を作成し、PR でレビューする
3. 仕様書のマージ後、別 PR で実装する
4. PR マージ後、ワークツリーとリモートブランチを削除する

詳細は以下を参照してください。

- [docs/README.md](docs/README.md) — ドキュメントの索引
- [docs/development/git-workflow.md](docs/development/git-workflow.md) — Git 運用ルール
- [docs/development/task-workflow.md](docs/development/task-workflow.md) — タスクの進め方（サブエージェントへの委託）
- [docs/development/cicd.md](docs/development/cicd.md) — CI/CD と BGA Studio への同期
- [docs/tasks.md](docs/tasks.md) — タスク一覧

## クレジット

- ゲームデザイン・アートワーク: だんじり協奏曲
- お問い合わせ: <https://x.com/danjiriconcerto>

カード画像・ルールブックを含むゲームコンテンツの著作権は、だんじり協奏曲に帰属します。
