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
 * 鍵の種類（002 §3.2）。
 */
enum KeyType: string
{
    /** 知恵 */
    case WISDOM = 'WISDOM';

    /** 意志 */
    case WILL = 'WILL';

    /** 感情 */
    case EMOTION = 'EMOTION';

    /** 万能 */
    case WILD = 'WILD';
}
