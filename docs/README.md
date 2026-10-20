# 開発用ドキュメント

『我廻る、故に我あり』の BGA 実装に関する開発用ドキュメントの索引です。

## 構成

| パス | 内容 |
| --- | --- |
| [tasks.md](tasks.md) | タスク一覧と進捗 |
| [specs/](specs/README.md) | 仕様書。実装の前に必ずここへ作成する |
| [development/git-workflow.md](development/git-workflow.md) | Git 運用ルール（ブランチ・ワークツリー・コミット・PR・後始末） |
| [development/task-workflow.md](development/task-workflow.md) | タスクの進め方とサブエージェントへの委託方法 |
| [development/cicd.md](development/cicd.md) | CI（lint）と BGA Studio への自動同期の方針 |

## 一次情報

- ルールブック: `data/rulebook/rulebook_web.pdf`
- カード画像: `data/cards/`
- 公式サイト: <https://danjiri-concerto.github.io/i-revolve-therefore-i-am/index.html>（FAQ: <https://danjiri-concerto.github.io/i-revolve-therefore-i-am/faq.html>）
- BGA Studio 公式ドキュメント: <https://en.doc.boardgamearena.com/Studio>

`data/` は Git 管理外で、メインの作業ツリーにだけあります。ワークツリーからの参照方法は [../CLAUDE.md](../CLAUDE.md) の「画像ファイル名」を参照してください。

## 参考

- 姉妹プロジェクト [minimum-journey-bga](https://github.com/ZoneTsuyoshi/minimum-journey-bga)（同じ運用で先行している BGA 実装。仕様書の書き方、CI/CD、BGA フレームワークの使い方の参考にする）

## ドキュメントを書くときのルール

- 日本語で書く。
- 仕様書とルールブックが食い違う場合はルールブックが正。ルールブックに記載のない点は「未確定事項」として明記し、作者に確認する。
- BGA フレームワークに関する記述には、確認した公式ドキュメントの URL を添える。
