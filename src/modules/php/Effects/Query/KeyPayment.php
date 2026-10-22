<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Query;

use Bga\Games\IRevolveThereforeIAm\Material\KeyType;

/**
 * 移動の支払い 1 つ（006 §5.1 の `payments`）。鍵の種類を数える判定（CD-02）と、使った万能移動鍵トークンの数（CD-03）に使う。
 */
final readonly class KeyPayment
{
    public const TABLEAU = 'TABLEAU';
    public const HAND = 'HAND';
    public const LIGHT = 'LIGHT';
    public const TOKEN = 'TOKEN';

    /**
     * @param string $source `TABLEAU` / `HAND` / `LIGHT` / `TOKEN`
     * @param KeyType|null $printedKey カードに印刷された鍵の種類（`TABLEAU` / `HAND` のとき。使った鍵 1 本）
     */
    public function __construct(
        public string $source,
        public ?KeyType $printedKey = null,
    ) {
    }
}
