<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Query;

use Bga\Games\IRevolveThereforeIAm\Effects\Condition\ConditionContext;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Material\ComposeContext;
use Bga\Games\IRevolveThereforeIAm\Material\EffectSpec;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCardDefinition;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCards;
use Bga\Games\IRevolveThereforeIAm\Material\Modifier;

/**
 * 自己構成のエネルギーの計算（008 §6.5.1）。MD-01（エネルギーの増減）、MD-02（手札の値）、MD-07（横向き）、MD-09（建築家）。
 *
 * ```text
 * cardEnergy = max(0, card.energy + Σ MD-02)
 * discount   = ctx.discount + turn_compose_discount + next_compose_discount(自己構成アクションの文脈のみ)
 *              + Σ MD-01 + tap_count
 * need       = max(0, cardEnergy - discount)           # 0 への切り上げは最後に 1 回だけ（FAQ 行 18）
 * ```
 */
final class ComposeEnergyQuery
{
    public function __construct(private readonly ActiveSpecs $active)
    {
    }

    /**
     * 手札のカードの現在のエネルギー（MD-02 込み。0 未満にならない）。I03・OP-09 の比較にも使う。
     *
     * @param int|null $excludeTableauCardId この自己構成済みカードが場を離れた後の値を求めるとき（I03 は捨てる処理が先）
     */
    public function heldEnergy(int $player, int $handCardId, ?int $excludeTableauCardId = null): int
    {
        $view = $this->active->view();
        $card = MemoryCards::get($view->cardType($handCardId));
        $context = new ConditionContext($player, $card);

        $delta = 0;
        foreach ($this->active->heldModifiers($player, $handCardId, Modifier::HELD_ENERGY_DELTA, $context) as $spec) {
            $key = $spec->params['key'] ?? KeyType::EMOTION;
            if ($key instanceof KeyType) {
                $delta -= $this->intParam($spec, 'per', 1) * $this->composedKeyCount($player, $key, $excludeTableauCardId);
            }
        }

        return max(0, $card->energy + $delta);
    }

    /**
     * 自己構成済みカードに印刷された `$key` の本数（同種 2 本のカードは 2。万能鍵は数えない。KEY_VIEW は適用しない）。
     */
    public function composedKeyCount(int $player, KeyType $key, ?int $excludeCardId = null): int
    {
        $view = $this->active->view();
        $count = 0;
        foreach ($view->tableauIds($player) as $cardId) {
            if ($cardId === $excludeCardId) {
                continue;
            }
            foreach (MemoryCards::get($view->cardType($cardId))->keys as $printed) {
                if ($printed === $key) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * 軽減の合計（`ctx.discount`、POSEIDON、次の自己構成の軽減、MD-01、横向きにした枚数）。
     * エネルギーを払わない文脈（ボーナス・M089）では 0。
     */
    public function discount(
        int $player,
        MemoryCardDefinition $card,
        ComposeContext $context,
        int $contextDiscount = 0,
        int $tapCount = 0,
    ): int {
        if (!$context->paysEnergy()) {
            return 0;
        }
        $view = $this->active->view();
        $discount = $contextDiscount + $view->turnComposeDiscount() + $tapCount;
        if ($context->consumesNextDiscount()) {
            $discount += $view->nextComposeDiscount();
        }
        $conditionContext = new ConditionContext($player, $card);
        foreach ($this->active->modifiers($player, Modifier::COMPOSE_ENERGY_DELTA, $conditionContext) as $spec) {
            $discount -= $this->intParam($spec, 'delta', 0);
        }

        return $discount;
    }

    /**
     * 払う必要のあるエネルギー。エネルギーを払わない文脈（ボーナス・M089）では 0。
     */
    public function need(
        int $player,
        int $handCardId,
        ComposeContext $context,
        int $contextDiscount = 0,
        int $tapCount = 0,
    ): int {
        if (!$context->paysEnergy()) {
            return 0;
        }
        $card = MemoryCards::get($this->active->view()->cardType($handCardId));
        $discount = $this->discount($player, $card, $context, $contextDiscount, $tapCount);

        return max(0, $this->heldEnergy($player, $handCardId) - $discount);
    }

    /**
     * MD-07（M095）で横向きにするカードの検証。違反は {@see EffectViolation}（008 T8-10、FAQ 行 30・31）。
     *
     * - M095 を持たなければ横向きにできない。エネルギーを払う文脈だけ。
     * - 自己構成済みで、このターン未使用（縦向き）、感情鍵を印刷されたカード（万能鍵のみは不可）、重複なし。
     * - 横向きにする枚数は、横向き無しで必要なエネルギーを超えてはならない。
     *
     * @param list<int> $tapIds
     * @throws EffectViolation
     */
    public function assertTaps(
        int $player,
        int $handCardId,
        ComposeContext $context,
        array $tapIds,
        int $contextDiscount = 0,
    ): void {
        if ($tapIds === []) {
            return;
        }
        $spec = $this->tapSpec($player);
        if ($spec === null || !$context->paysEnergy()) {
            throw new EffectViolation(clienttranslate('Cards cannot be tapped for energy here'));
        }
        $key = $spec->params['key'] ?? KeyType::EMOTION;
        $view = $this->active->view();
        $tableau = $view->tableauIds($player);
        if (count(array_unique($tapIds)) !== count($tapIds)) {
            throw new EffectViolation(clienttranslate('Duplicate card to tap'));
        }
        foreach ($tapIds as $cardId) {
            if (!in_array($cardId, $tableau, true) || $view->isTapped($cardId)) {
                throw new EffectViolation(clienttranslate('A selected card cannot be tapped'));
            }
            if (!in_array($key, MemoryCards::get($view->cardType($cardId))->keys, true)) {
                throw new EffectViolation(clienttranslate('A selected card does not have the required key'));
            }
        }
        if (count($tapIds) > $this->need($player, $handCardId, $context, $contextDiscount, 0)) {
            throw new EffectViolation(clienttranslate('Too many cards tapped for the required energy'));
        }
    }

    /**
     * M095 で横向きにできる自己構成済みカード（縦向きで、横向きにする鍵を印刷されたもの）。エネルギーを払う文脈だけ。
     *
     * @return list<int>
     */
    public function tapCandidates(int $player, ComposeContext $context): array
    {
        $spec = $this->tapSpec($player);
        if ($spec === null || !$context->paysEnergy()) {
            return [];
        }
        $key = $spec->params['key'] ?? KeyType::EMOTION;
        $view = $this->active->view();
        $out = [];
        foreach ($view->tableauIds($player) as $cardId) {
            if (!$view->isTapped($cardId) && in_array($key, MemoryCards::get($view->cardType($cardId))->keys, true)) {
                $out[] = $cardId;
            }
        }

        return $out;
    }

    /**
     * MD-09（I03 建築家）の代替が成り立つか: 自己構成済みの 1 枚を捨て、その印刷されたエネルギーより `plus`（2）まで大きい
     * エネルギーの手札のカードを、エネルギーなしで自己構成する。比較する手札の値は MD-02 込みで、捨てた後の状態で求める
     * （FAQ 行 32、X-15）。エネルギーを払う文脈だけ。
     */
    public function architectSubstituteAllowed(int $player, int $tableauCardId, int $handCardId, ComposeContext $context): bool
    {
        if (!$context->paysEnergy()) {
            return false;
        }
        $view = $this->active->view();
        if (!in_array($tableauCardId, $view->tableauIds($player), true) || !in_array($handCardId, $view->handIds($player), true)) {
            return false;
        }
        $spec = $this->architectSpec($player);
        if ($spec === null) {
            return false;
        }
        $discarded = MemoryCards::get($view->cardType($tableauCardId));
        $limit = $discarded->energy + $this->intParam($spec, 'plus', 2);

        return $this->heldEnergy($player, $handCardId, $tableauCardId) <= $limit;
    }

    /**
     * MD-08（I08）で、自己構成するときに追加で払ってよい手札の枚数（持っていなければ 0）。自己構成の文脈を問わない（FAQ 行 38）。
     */
    public function extraPayMax(int $player): int
    {
        $specs = $this->active->modifiers($player, Modifier::EXTRA_PAY_SCORE, new ConditionContext($player));
        $max = $specs[0]->params['max'] ?? 0;

        return isset($specs[0]) && is_int($max) ? $max : 0;
    }

    private function tapSpec(int $player): ?EffectSpec
    {
        $specs = $this->active->modifiers($player, Modifier::TAP_FOR_ENERGY, new ConditionContext($player));

        return $specs[0] ?? null;
    }

    private function architectSpec(int $player): ?EffectSpec
    {
        $specs = $this->active->modifiers($player, Modifier::ARCHITECT_SUBSTITUTE, new ConditionContext($player));

        return $specs[0] ?? null;
    }

    private function intParam(EffectSpec $spec, string $key, int $default): int
    {
        $value = $spec->params[$key] ?? $default;

        return is_int($value) ? $value : $default;
    }
}
