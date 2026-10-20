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
 * 想い出カードの裏面（錠前。002 §5.3）。値は画像名。
 */
enum CardBack: string
{
    /** レベル 1、本の錠前 × 1 */
    case Rev1B = 'Rev1B';

    /** レベル 1、炎の錠前 × 1 */
    case Rev1F = 'Rev1F';

    /** レベル 1、ハートの錠前 × 1 */
    case Rev1H = 'Rev1H';

    /** レベル 2、本の錠前 × 2 */
    case Rev2B = 'Rev2B';

    /** レベル 2、炎の錠前 × 2 */
    case Rev2F = 'Rev2F';

    /** レベル 2、ハートの錠前 × 2 */
    case Rev2H = 'Rev2H';

    /** 想い出カードのレベル。 */
    public function level(): int
    {
        return match ($this) {
            self::Rev1B => 1,
            self::Rev1F => 1,
            self::Rev1H => 1,
            self::Rev2B => 2,
            self::Rev2F => 2,
            self::Rev2H => 2,
        };
    }

    /** 錠前（移動コスト）の種類。レベル 2 は同じ種類の錠前が 2 つ。 */
    public function lock(): KeyType
    {
        return match ($this) {
            self::Rev1B => KeyType::WISDOM,
            self::Rev1F => KeyType::WILL,
            self::Rev1H => KeyType::EMOTION,
            self::Rev2B => KeyType::WISDOM,
            self::Rev2F => KeyType::WILL,
            self::Rev2H => KeyType::EMOTION,
        };
    }
}
