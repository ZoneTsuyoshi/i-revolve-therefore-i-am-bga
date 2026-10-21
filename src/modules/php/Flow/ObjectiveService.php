<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\GlobalValues;
use Bga\Games\IRevolveThereforeIAm\Data\ObjectiveRepository;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Effects\Objective\ObjectiveEvaluator;
use Bga\Games\IRevolveThereforeIAm\Effects\Objective\ObjectiveWindow;

/**
 * 目標カードの獲得（フリーアクション `actClaimObjective`。006 §6、008 §6.7）。
 * 早い者勝ち（Q-12）で、獲得した目標は手放さない。獲得の直後に勝利判定（断片の結集）を呼ぶ。
 */
final class ObjectiveService
{
    /**
     * @param \Closure(int): string $nameOf プレイヤー ID → プレイヤー名
     * @param \Closure(): void $victoryCheck 勝利判定
     */
    public function __construct(
        private readonly ObjectiveBoard $board,
        private readonly ObjectiveRepository $objectives,
        private readonly GlobalValues $globals,
        private readonly \Closure $nameOf,
        private readonly \Closure $victoryCheck,
    ) {
    }

    /**
     * 目標を獲得する。
     *
     * @return list<Notice> N22。勝利判定が溜める N21 は呼び出し側が溜まった通知として送る
     * @throws EffectViolation
     */
    public function claim(int $player, int $objectiveId, ObjectiveWindow $window): array
    {
        if ($this->globals->turnPlayer() !== $player) {
            throw new EffectViolation(clienttranslate('Only the turn player can claim a goal card'));
        }
        $turn = $this->globals->turnContext();
        if (!$this->board->mayClaim($player)) {
            throw new EffectViolation(clienttranslate('You have already claimed a goal card this turn'));
        }
        $status = $this->board->status($player, $objectiveId, $window)
            ?? throw new EffectViolation(clienttranslate('That goal card is not available'));
        $cardId = ObjectiveEvaluator::cardId($objectiveId);
        if (!$status->conditionMet) {
            throw new EffectViolation(clienttranslate('You do not meet the condition of that goal card'));
        }

        $this->objectives->claim($objectiveId, $player, $this->globals->turnNo());
        $turn->objectiveClaimed = $objectiveId;
        $this->globals->setTurnContext($turn);
        $notices = [Notification::objectiveClaimed(
            $player,
            ($this->nameOf)($player),
            $objectiveId,
            Notification::objectiveName($cardId),
            $status->points,
        )];
        ($this->victoryCheck)();

        return $notices;
    }
}
