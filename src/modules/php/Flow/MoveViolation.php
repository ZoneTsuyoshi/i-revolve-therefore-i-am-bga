<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

/**
 * 移動の入力が規則に反するときの例外（仕様書 006 §5.1 の V-*）。`reason` は状態クラスが翻訳済みの文言に対応づける。
 */
final class MoveViolation extends \DomainException
{
    /** 入力の形が不正（`rooms` と `payments` の長さ、`src` の値など） */
    public const BAD_INPUT = 'BAD_INPUT';

    /** 経路が時計回りの次の部屋（次がレベル 2 ならその次）になっていない */
    public const INVALID_ROUTE = 'INVALID_ROUTE';

    /** 最低 1 部屋は移動する（払える移動があるのに動かない入力） */
    public const MUST_MOVE = 'MUST_MOVE';

    /** 錠前の数と支払いの数が合わない */
    public const LOCK_COUNT = 'LOCK_COUNT';

    /** 鍵が錠前の種類に合わない */
    public const KEY_MISMATCH = 'KEY_MISMATCH';

    /** 手札・自己構成済みカードの指定が不正（持っていない、横向き、鍵の番号が無い、同じ鍵を 2 度使う） */
    public const BAD_CARD = 'BAD_CARD';

    /** 複数の鍵を持つカードを、連続しない入室に分けて使っている */
    public const SPLIT_CARD = 'SPLIT_CARD';

    /** 心光または万能移動鍵トークンが足りない */
    public const NOT_ENOUGH_RESOURCES = 'NOT_ENOUGH_RESOURCES';

    public function __construct(public readonly string $reason, string $detail = '')
    {
        parent::__construct($detail === '' ? $reason : "$reason: $detail");
    }
}
