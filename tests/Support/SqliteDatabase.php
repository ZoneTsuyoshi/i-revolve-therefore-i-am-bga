<?php

declare(strict_types=1);

namespace IRevolveTests\Support;

use Bga\Games\IRevolveThereforeIAm\Data\Database;

/**
 * `Database` の SQLite 実装（PHPUnit 専用）。スキーマは src/dbmodel.sql を SQLite 向けに読み替えて作る。
 * データ層の SQL は単純な SELECT / INSERT / UPDATE なので、MySQL 固有の機能（IF() など）を使う操作はここでは試さない。
 */
final class SqliteDatabase implements Database
{
    private function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * 空のメモリ DB に dbmodel.sql のテーブルと、BGA が作る player テーブルを作る。
     *
     * @param list<int> $playerIds 席順（player_no = 1 から）
     */
    public static function create(array $playerIds = []): self
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $db = new self($pdo);
        $db->execute(
            'CREATE TABLE `player` (`player_id` INTEGER PRIMARY KEY, `player_no` INTEGER NOT NULL DEFAULT 0,'
            . " `player_name` TEXT NOT NULL DEFAULT '', `player_color` TEXT NOT NULL DEFAULT '', `player_score` INTEGER NOT NULL DEFAULT 0)",
        );
        foreach (self::statements() as $statement) {
            $db->execute($statement);
        }
        foreach ($playerIds as $i => $id) {
            $db->execute("INSERT INTO `player` (`player_id`, `player_no`, `player_name`) VALUES ($id, " . ($i + 1) . ", 'P$id')");
        }

        return $db;
    }

    public function execute(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    public function rows(string $sql): array
    {
        $stmt = $this->pdo->query($sql);

        return $stmt === false ? [] : array_values($stmt->fetchAll());
    }

    public function value(string $sql): mixed
    {
        $stmt = $this->pdo->query($sql);
        if ($stmt === false) {
            return null;
        }
        $row = $stmt->fetch(\PDO::FETCH_NUM);

        return $row === false ? null : $row[0];
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function escape(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    /**
     * dbmodel.sql（MySQL）を SQLite の文に読み替える。
     *
     * @return list<string>
     */
    private static function statements(): array
    {
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/src/dbmodel.sql');
        $sql = (string) preg_replace('/^--.*$/m', '', $sql);
        $out = [];
        foreach (explode(';', $sql) as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            if (str_starts_with($statement, 'CREATE TABLE')) {
                $statement = (string) preg_replace('/\)\s*ENGINE=.*$/s', ')', $statement);
                $statement = (string) preg_replace('/^\s*KEY\s+`[^`]+`\s*\([^)]*\),?\s*$/m', '', $statement);
                $statement = (string) preg_replace('/UNIQUE KEY\s+`[^`]+`/', 'UNIQUE', $statement);
                $statement = (string) preg_replace(
                    '/`frame_id` INT UNSIGNED NOT NULL AUTO_INCREMENT/',
                    '`frame_id` INTEGER PRIMARY KEY AUTOINCREMENT',
                    $statement,
                );
                $statement = (string) preg_replace('/^\s*PRIMARY KEY \(`frame_id`\),?\s*$/m', '', $statement);
                // 最後の列の後ろに残ったカンマを消す
                $statement = (string) preg_replace('/,\s*\)\s*$/', ')', $statement);
            }
            $out[] = $statement;
        }

        return $out;
    }
}
