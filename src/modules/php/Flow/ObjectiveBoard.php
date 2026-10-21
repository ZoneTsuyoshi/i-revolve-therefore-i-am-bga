<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\GlobalValues;
use Bga\Games\IRevolveThereforeIAm\Data\ObjectiveRepository;
use Bga\Games\IRevolveThereforeIAm\Effects\Objective\ObjectiveEvaluator;
use Bga\Games\IRevolveThereforeIAm\Effects\Objective\ObjectiveStatus;
use Bga\Games\IRevolveThereforeIAm\Effects\Objective\ObjectiveWindow;

/**
 * 場に残っている目標の現在の状況（読み取りだけ。N23 と `myObjectiveStatus`、81 に入る判定。008 §6.7）。
 * 獲得できるのは、ターンプレイヤーが、このターンにまだ獲得しておらず、場に残っている目標の条件を窓の中で満たしているとき。
 */
final class ObjectiveBoard
{
    /** 81 の対象になる目標（O004）の個体 ID。他の目標は 60 までに獲得できる（006 §6.3）。 */
    public const HAND_OBJECTIVE_ID = 4;

    public function __construct(
        private readonly ObjectiveRepository $objectives,
        private readonly ObjectiveEvaluator $evaluator,
        private readonly GlobalValues $globals,
    ) {
    }

    /**
     * 場に残っている目標 1 枚の、本人から見た評価。場に無ければ null。
     */
    public function status(int $player, int $objectiveId, ObjectiveWindow $window): ?ObjectiveStatus
    {
        foreach ($this->objectives->all() as $o) {
            if ($o['id'] === $objectiveId && $o['state'] === ObjectiveRepository::OPEN) {
                return $this->evaluator->evaluate(ObjectiveEvaluator::cardId($objectiveId), $player, $window);
            }
        }

        return null;
    }

    /** ターンプレイヤーで、このターンにまだ目標を獲得していない。 */
    public function mayClaim(int $player): bool
    {
        return $this->globals->turnPlayer() === $player && $this->globals->turnContext()->objectiveClaimed === null;
    }

    /**
     * 場に残っている（未獲得の）目標の、本人から見た現在の X・得点・獲得できるか（N23、`myObjectiveStatus`）。
     *
     * @return list<array{obj_id: int, x: int, points: int, claimable: bool}>
     */
    public function statuses(int $player, ObjectiveWindow $window): array
    {
        $out = [];
        foreach ($this->objectives->all() as $o) {
            if ($o['state'] !== ObjectiveRepository::OPEN) {
                continue;
            }
            $status = $this->evaluator->evaluate(ObjectiveEvaluator::cardId($o['id']), $player, $window);
            $out[] = [
                'obj_id' => $o['id'],
                'x' => $status->x,
                'points' => $status->points,
                'claimable' => $status->conditionMet && $this->mayClaim($player),
            ];
        }

        return $out;
    }

    /**
     * 81（ターン終了時の窓）に入るか。O004 が場に残り、ターンプレイヤーがこのターンにまだ獲得しておらず、手札が 6 枚以上のとき（006 §6.3）。
     */
    public function turnEndClaimable(int $player): bool
    {
        if (!$this->mayClaim($player)) {
            return false;
        }

        return $this->status($player, self::HAND_OBJECTIVE_ID, ObjectiveWindow::turnEnd())->conditionMet ?? false;
    }
}
