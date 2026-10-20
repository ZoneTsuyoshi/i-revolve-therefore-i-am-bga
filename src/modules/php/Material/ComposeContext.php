<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 自己構成アクション（OP-10）の文脈（008 §6.5.2）。
 */
enum ComposeContext: string
{
    case PHASE = 'PHASE';
    case TOKEN = 'TOKEN';
    case FLOOR = 'FLOOR';
    case BONUS_LV1 = 'BONUS_LV1';
    case BONUS_ANY = 'BONUS_ANY';
    case EFFECT_FREE_LV1 = 'EFFECT_FREE_LV1';

    /** エネルギーを払う文脈か（`PHASE` / `TOKEN` / `FLOOR`）。それ以外はエネルギー 0。 */
    public function paysEnergy(): bool
    {
        return match ($this) {
            self::PHASE, self::TOKEN, self::FLOOR => true,
            default => false,
        };
    }

    /**
     * `next_compose_discount` を消費し、`compose_actions_done` に数える文脈か（008 §6.5.2。【確定 R8-6・R8-7】）。
     * 自己構成アクションの文脈（エネルギーを払う文脈）と同じ。
     */
    public function consumesNextDiscount(): bool
    {
        return $this->paysEnergy();
    }

    /** レベルの制限（無ければ null）。 */
    public function levelLimit(): ?int
    {
        return match ($this) {
            self::BONUS_LV1, self::EFFECT_FREE_LV1 => 1,
            default => null,
        };
    }
}
