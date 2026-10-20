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
 * 想起効果の識別子（002 §3.4）。処理は仕様書 008。
 */
enum RecallEffect: string
{
    /** 想い出の山札から 1 枚引き、手札に加える */
    case DRAW_1 = 'DRAW_1';

    /** 想い出の場札から 1 枚選び、手札に加える */
    case OPEN_DRAW_1 = 'OPEN_DRAW_1';

    /** 心光を 1 つ得る */
    case LIGHT_1 = 'LIGHT_1';

    /** （想起効果）このターンで次の自己構成アクションにおけるエネルギー -1 */
    case NEXT_COMPOSE_DISCOUNT_1 = 'NEXT_COMPOSE_DISCOUNT_1';

    /** 手札から想い出カードを好きな枚数捨て、その枚数だけ心光を得る */
    case DISCARD_FOR_LIGHT = 'DISCARD_FOR_LIGHT';

    /** 手札の想い出カードと想い出の場札を好きな枚数入れ替える */
    case SWAP_HAND_FIELD = 'SWAP_HAND_FIELD';

    /** 自己構成済みの想い出カード 1 枚と同じコストの手札の想い出カードを交換してもよい */
    case SWAP_SAME_COST = 'SWAP_SAME_COST';

    /** 床アクションの代わりに精神分析アクションを行ってもよい */
    case ALT_ANALYSIS = 'ALT_ANALYSIS';

    /** 床アクションの代わりに自己構成アクションを行ってもよい */
    case ALT_COMPOSE = 'ALT_COMPOSE';

    /** 床アクションの代わりに、コスト -1 で精神分析アクションを行ってもよい */
    case ALT_ANALYSIS_DISCOUNT_1 = 'ALT_ANALYSIS_DISCOUNT_1';

    /** 床アクションの代わりに、エネルギー -1 で自己構成アクションを行ってもよい */
    case ALT_COMPOSE_DISCOUNT_1 = 'ALT_COMPOSE_DISCOUNT_1';

    /** 次のレベル 1 の部屋に移動してもよい。そうした場合、場札を 1 枚獲得し、移動した部屋で床アクションを行う */
    case MOVE_NEXT_LV1 = 'MOVE_NEXT_LV1';

    /** 次のレベル 2 の部屋に移動してもよい。そうした場合、場札を 1 枚獲得し、移動した部屋で床アクションを行う */
    case MOVE_NEXT_LV2 = 'MOVE_NEXT_LV2';

    /** 想起効果のグループ（002 §3.5）。 */
    public function group(): RecallGroup
    {
        return match ($this) {
            self::DRAW_1 => RecallGroup::DRAW,
            self::OPEN_DRAW_1 => RecallGroup::GAIN,
            self::LIGHT_1 => RecallGroup::GAIN,
            self::NEXT_COMPOSE_DISCOUNT_1 => RecallGroup::GAIN,
            self::DISCARD_FOR_LIGHT => RecallGroup::EXCHANGE,
            self::SWAP_HAND_FIELD => RecallGroup::EXCHANGE,
            self::SWAP_SAME_COST => RecallGroup::EXCHANGE,
            self::ALT_ANALYSIS => RecallGroup::ALTERNATE,
            self::ALT_COMPOSE => RecallGroup::ALTERNATE,
            self::ALT_ANALYSIS_DISCOUNT_1 => RecallGroup::ALTERNATE_DISCOUNT,
            self::ALT_COMPOSE_DISCOUNT_1 => RecallGroup::ALTERNATE_DISCOUNT,
            self::MOVE_NEXT_LV1 => RecallGroup::ALTERNATE,
            self::MOVE_NEXT_LV2 => RecallGroup::ALTERNATE_DISCOUNT,
        };
    }
}
