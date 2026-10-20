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
 * 効果の種類（002 §3.3）。`NONE` は効果文が無いカード。
 */
enum EffectKind: string
{
    /** ターン開始時 */
    case TURN_START = 'TURN_START';

    /** 起動 */
    case ACTIVATED = 'ACTIVATED';

    /** 常時 */
    case CONSTANT = 'CONSTANT';

    /** 即時 */
    case INSTANT = 'INSTANT';

    /** 保有時 */
    case HELD = 'HELD';

    /** （なし） */
    case NONE = 'NONE';
}
