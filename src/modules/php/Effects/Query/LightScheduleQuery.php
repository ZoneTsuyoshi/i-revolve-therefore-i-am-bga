<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Query;

use Bga\Games\IRevolveThereforeIAm\Effects\Condition\ConditionContext;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Material\ActionContext;
use Bga\Games\IRevolveThereforeIAm\Material\Modifier;

/**
 * 心光の行使の逓増（001 §6、008 §5.1 の MD-04）。同じアクション・フェイズで X 回目に払うときは X 個で 1 つ分になり、
 * M059（精神分析）・M063（移動）・M070（自己構成）は表 [1, 1, 2, 2, 3] に差し替える。表の外（6 回目以降）は X 個（FAQ 行 15）。
 * 回数はアクションごとに 0 から数え直す（FAQ 行 16）。保存しない。
 */
final class LightScheduleQuery
{
    public function __construct(private readonly ActiveSpecs $active)
    {
    }

    /**
     * n 回目（1 から）の心光の行使に払う心光の個数。
     */
    public function lightsForUse(int $player, ActionContext $context, int $n): int
    {
        foreach ($this->active->modifiers($player, Modifier::LIGHT_SCHEDULE, new ConditionContext($player)) as $spec) {
            if (($spec->params['context'] ?? null) !== $context) {
                continue;
            }
            $table = $spec->params['table'] ?? [];
            if (is_array($table) && isset($table[$n - 1]) && is_int($table[$n - 1])) {
                return $table[$n - 1];
            }
        }

        return $n;
    }

    /**
     * 心光の行使を `$uses` 回行うときの心光の総数（1 回目から `$uses` 回目までの和）。
     */
    public function totalLights(int $player, ActionContext $context, int $uses): int
    {
        $total = 0;
        for ($n = 1; $n <= $uses; $n++) {
            $total += $this->lightsForUse($player, $context, $n);
        }

        return $total;
    }

    /**
     * 払った心光の総数が、行使の回数から決まる個数と一致するか検証する。軽減が働くのに多く払う入力も拒否する（FAQ 行 17）。
     *
     * @throws EffectViolation
     */
    public function assertPayment(int $player, ActionContext $context, int $uses, int $lightsPaid): void
    {
        $expected = $this->totalLights($player, $context, $uses);
        if ($lightsPaid !== $expected) {
            throw new EffectViolation(clienttranslate('The inner light payment does not match the number of uses'));
        }
    }
}
