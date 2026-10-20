<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

// 自動生成。仕様書 002 の表から scripts/build-card-data.mjs が生成した。手で編集しない。
// 変更は仕様書 002（または card.csv）を直して、npm run build:cards で再生成する。

/**
 * 想起効果のグループ（002 §3.5）。card.csv の `otype` 列に対応する。
 */
enum RecallGroup: string
{
    /** 山札から引く（otype `c`） */
    case DRAW = 'DRAW';

    /** 獲得（心光・次の自己構成の割引・場札）（otype `s`） */
    case GAIN = 'GAIN';

    /** 交換・変換（otype `w`） */
    case EXCHANGE = 'EXCHANGE';

    /** 床アクションの代替・移動（otype `a`） */
    case ALTERNATE = 'ALTERNATE';

    /** 床アクションの代替（-1）（otype `a2`） */
    case ALTERNATE_DISCOUNT = 'ALTERNATE_DISCOUNT';

    /** card.csv の `otype` 列の値。 */
    public function otype(): string
    {
        return match ($this) {
            self::DRAW => 'c',
            self::GAIN => 's',
            self::EXCHANGE => 'w',
            self::ALTERNATE => 'a',
            self::ALTERNATE_DISCOUNT => 'a2',
        };
    }
}
