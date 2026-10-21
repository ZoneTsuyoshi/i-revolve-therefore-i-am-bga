<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Value;

use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameView;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCards;
use Bga\Games\IRevolveThereforeIAm\Material\ValueExpr;

/**
 * 値式（008 §3.3）を数える。状態を変えない。解決の時点の状態で数える（M090・M097・M099 は自己構成済みに自身を含む。R8-4）。
 */
final class ValueEvaluator
{
    /**
     * @param int|ValueExpr $value 整数はそのまま返す
     */
    public static function evaluate(int|ValueExpr $value, GameView $view, int $player): int
    {
        if (is_int($value)) {
            return $value;
        }

        return match ($value->kind) {
            ValueExpr::COUNT_COMPOSED => count($view->tableauIds($player)),
            ValueExpr::COUNT_COMPOSED_KEYS => self::keys($view, $player, $value),
            ValueExpr::FLOOR_DIV => intdiv(self::evaluate($value->inner ?? throw new \LogicException('FLOOR_DIV needs an inner value'), $view, $player), $value->divisor),
            default => throw new \LogicException("Unknown value expression: {$value->kind}"),
        };
    }

    private static function keys(GameView $view, int $player, ValueExpr $value): int
    {
        $count = 0;
        foreach ($view->tableauIds($player) as $cardId) {
            foreach (MemoryCards::get($view->cardType($cardId))->keys as $printed) {
                if ($printed === $value->key) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
