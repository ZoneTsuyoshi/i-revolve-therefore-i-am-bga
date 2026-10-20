<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Query;

use Bga\Games\IRevolveThereforeIAm\Effects\Catalog\SpecSource;
use Bga\Games\IRevolveThereforeIAm\Effects\Condition\ConditionContext;
use Bga\Games\IRevolveThereforeIAm\Effects\Condition\ConditionEvaluator;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameView;
use Bga\Games\IRevolveThereforeIAm\Material\EffectSpec;
use Bga\Games\IRevolveThereforeIAm\Material\Mechanism;
use Bga\Games\IRevolveThereforeIAm\Material\Modifier;

/**
 * 修飾（`MODIFIER`）の問い合わせに答えるために、持ち主の「働いている効果」を集める（008 §5.1）。
 * 評価の時点で毎回計算し、結果を保存しない。
 *
 * 対象は持ち主の自己構成済みカードと自我カード。手札のカードの効果は働かない（例外の MD-02 は `heldModifiers()`）。
 */
final class ActiveSpecs
{
    public function __construct(
        private readonly GameView $view,
        private readonly SpecSource $specs,
        private readonly ConditionEvaluator $conditions,
    ) {
    }

    public function view(): GameView
    {
        return $this->view;
    }

    public function conditions(): ConditionEvaluator
    {
        return $this->conditions;
    }

    /**
     * 持ち主の自己構成済みカードと自我カードの修飾のうち、`$modifier` に答え、条件を満たすもの。
     * 使用済みの `ONCE_PER_TURN` の効果は除く。
     *
     * @return list<EffectSpec>
     */
    public function modifiers(int $player, Modifier $modifier, ConditionContext $context): array
    {
        $sourceIds = [];
        foreach ($this->view->tableauIds($player) as $cardId) {
            $sourceIds[] = $this->view->cardType($cardId);
        }
        foreach ($this->view->egoIds($player) as $egoId) {
            $sourceIds[] = $egoId;
        }

        $active = [];
        foreach ($sourceIds as $sourceId) {
            foreach ($this->specs->forCard($sourceId) as $spec) {
                if ($this->answers($spec, $modifier, $context)) {
                    $active[] = $spec;
                }
            }
        }

        return $active;
    }

    /**
     * 手札のカード 1 枚が持つ修飾（保有時の効果。M098）。
     *
     * @return list<EffectSpec>
     */
    public function heldModifiers(int $player, int $cardId, Modifier $modifier, ConditionContext $context): array
    {
        $active = [];
        foreach ($this->specs->forCard($this->view->cardType($cardId)) as $spec) {
            if ($this->answers($spec, $modifier, $context)) {
                $active[] = $spec;
            }
        }

        return $active;
    }

    private function answers(EffectSpec $spec, Modifier $modifier, ConditionContext $context): bool
    {
        if ($spec->mechanism !== Mechanism::MODIFIER || $spec->modifier !== $modifier) {
            return false;
        }
        if ($this->view->isOnceUsed($context->player, $spec->id)) {
            return false;
        }

        return $this->conditions->all($spec->conditions, $context);
    }
}
