# CI/CD の方針

> ステータス: lint（T-004）は**第 1 段階を導入**（markdownlint、lychee、gitleaks、`check-repo`、`Lint gate`。第 2 段階以降は `src/` ができてから）。設計は [../specs/004-ci-lint.md](../specs/004-ci-lint.md) を参照。同期（T-005）は**段階 3 まで導入**（`main` へのマージで `src/**` などに変更があれば自動で `sync`。手動の `workflow_dispatch` でも `test` / `dry-run` / `sync` を選べる。`sync` は lint 成功後に転送し、`remote-delete.txt` を実行する）。設計は [../specs/005-bga-sync.md](../specs/005-bga-sync.md) を参照。
>
> 姉妹プロジェクト [minimum-journey-bga](https://github.com/ZoneTsuyoshi/minimum-journey-bga) で同じ構成の lint と同期が稼働しています（同プロジェクトの仕様書 004 / 005、`.github/workflows/lint.yml`・`bga-sync.yml`）。仕様書では、それを土台にこのリポジトリ向けの差分（プロジェクト名、パス、アセットの扱いなど）を決めます。

## 全体像

```text
PR 作成・更新 ──▶ CI: lint ──▶（通過 + レビュー）──▶ squash merge
                                                              │
main への push ──▶ CD: src/ を BGA Studio へ SFTP 同期 ◀──────┘
```

## CI: lint チェック

GitHub Actions のワークフロー `Lint`（`.github/workflows/lint.yml`）で PR ごと、および `main` への push 時に実行します。必須チェックは集約ジョブ `Lint gate` の 1 つです。`main` の保護は設定できない（非公開の Free プラン）ので、マージ前に `gh pr checks <PR 番号>` で `Lint gate` の成功を確認し、失敗・実行中の PR はマージしません。

第 1 段階で動いているジョブ: `Markdown`（markdownlint と lychee の内部リンク）、`Secrets`（gitleaks）、`Repository rules`（`npm run lint:repo` と `npm run test:lint`）。ローカルでは PR 作成前に `npm ci && npm run lint`（lychee は任意で `npm run lint:links`）を実行します。

想定しているチェック項目（採用するツールと設定は仕様書で決定）:

| 対象 | チェック内容の候補 |
| --- | --- |
| PHP（ゲームロジック） | 構文チェック（`php -l`）、コーディング規約、静的解析 |
| JavaScript / TypeScript（クライアント） | ESLint |
| CSS | Stylelint |
| Markdown（docs） | markdownlint、リンク切れチェック |
| JSON（ゲームオプション・統計など） | 構文チェック |
| 全体 | 認証情報の混入チェック |

- PHP のバージョンは BGA 本番環境に合わせる（仕様書作成時に公式ドキュメントで確認）。
- ローカルでも同じコマンドで実行できるようにする。

## CD: BGA Studio への自動同期

BGA Studio はゲームのソースを SFTP で受け取ります。`main` へのマージをトリガーに、`src/` 配下を BGA Studio のプロジェクトフォルダへ自動転送します。

- 同期対象は **`src/` のみ**。`docs/`、CI 設定などは転送しない（`data/` はそもそも Git 管理外）。
- 認証情報（秘密鍵・ユーザー名）は GitHub Secrets、ホスト・ポート・パス・known_hosts は Variables で管理し、リポジトリには置かない。
- 手動実行（`workflow_dispatch`）もできるようにする。

仕様書で確定させる事項:

- BGA Studio の SFTP 接続情報（ホスト・ポート・認証方式）と GitHub Actions からの接続可否
- Secrets / Variables の名前と登録手順（姉妹プロジェクトと SSH 鍵を共用するか）
- 転送方式（差分転送 / ミラーリング、BGA 側にしかないファイルの扱い）
- 同期の単位（`main` のみか、動作確認用に PR ブランチからも手動同期できるようにするか）
- 同期後に BGA Studio 側で必要な操作（DB スキーマや状態定義を変更した場合の再読み込みなど）
- 失敗時の通知とリトライ

## ユーザー側で必要な準備

CD の導入前に、リポジトリオーナー側で以下が必要です。

1. ~~BGA Studio の開発者アカウントを取得する~~（済。姉妹プロジェクトと共通）
2. ~~BGA Studio でこのゲームのプロジェクトを作成する~~（済、2026-10-20。プロジェクト名: `irevolvethereforeiam`）
3. SSH 鍵を BGA Studio に登録し、Secrets / Variables を GitHub に登録する
