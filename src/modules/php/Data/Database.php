<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Data;

/**
 * DB 操作の最小限の口（仕様書 007 §12.2）。BGA の静的メソッドを直接呼ばないことで、データ層を差し替え可能にする。
 * 本番は BgaDatabase、PHPUnit の結合テストは PDO 実装。
 */
interface Database
{
    public function execute(string $sql): void;

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(string $sql): array;

    public function value(string $sql): mixed;

    public function lastInsertId(): int;

    /**
     * SQL の文字列リテラルに埋め込むための値のエスケープ（前後の引用符は付けない）。
     */
    public function escape(string $value): string;
}
