<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\PlayerValues;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameView;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\ComposeEnergyQuery;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\LightScheduleQuery;
use Bga\Games\IRevolveThereforeIAm\Material\ActionContext;
use Bga\Games\IRevolveThereforeIAm\Material\ComposeContext;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCards;

/**
 * 自己構成アクション（61）の検証（001 §9、008 §6.5.1・§6.5.2）。状態を変えない。
 *
 * 入力（`actCompose` の `payment`）: `{"discard": [手札のカード ID...], "light": 心光の行使の回数, "tap": [自己構成済みのカード ID...]}`。
 * - `discard`: 手札を捨てて、そのカードのレベル分のエネルギーを払う（レベル 2 は 2 つ分）。
 * - `light`: 心光でエネルギー何個分を払うか。X 回目の行使は X 個（M070 は表）。アクションごとに数え直す。
 * - `tap`: M095 で横向きにする、感情鍵を持つ自己構成済みカード。1 枚につきエネルギー -1。
 * 払う合計が必要なエネルギー以上なら成立する（過払い可。余りは失われる。Q-22）。エネルギーは 0 未満にならない（FAQ 行 18）。
 * 同名のカードは自己構成できない（001 §9-1）。
 */
final class ComposePlanner
{
    /** 心光の行使の回数の上限（探索の打ち切り） */
    private const MAX_USES = 30;

    public function __construct(
        private readonly GameView $view,
        private readonly PlayerValues $players,
        private readonly ComposeEnergyQuery $energy,
        private readonly LightScheduleQuery $light,
    ) {
    }

    /**
     * 自己構成できる手札のカード（同名が自己構成済みでなく、文脈のレベル制限に合うもの）。
     *
     * @return list<int>
     */
    public function candidates(int $player, ComposeContext $context): array
    {
        $composed = array_map(fn (int $id): string => $this->view->cardType($id), $this->view->tableauIds($player));
        $limit = $context->levelLimit();
        $out = [];
        foreach ($this->view->handIds($player) as $cardId) {
            $type = $this->view->cardType($cardId);
            if (in_array($type, $composed, true)) {
                continue;
            }
            if ($limit !== null && MemoryCards::get($type)->level !== $limit) {
                continue;
            }
            $out[] = $cardId;
        }

        return $out;
    }

    /**
     * 自己構成アクションを行えるか: 候補があり、エネルギーを払う文脈では、手札・心光・横向きで払える候補がある。
     */
    public function canCompose(int $player, ComposeContext $context, int $discount): bool
    {
        $candidates = $this->candidates($player, $context);
        if ($candidates === [] || !$context->paysEnergy()) {
            return $candidates !== [];
        }
        $uses = $this->maxLightUses($player);
        foreach ($candidates as $cardId) {
            $need = $this->energy->need($player, $cardId, $context, $discount);
            $capacity = $uses + $this->tapCapacity($player, $cardId, $context, $discount);
            foreach ($this->view->handIds($player) as $otherId) {
                if ($otherId !== $cardId) {
                    $capacity += MemoryCards::get($this->view->cardType($otherId))->level;
                }
            }
            if ($need <= $capacity) {
                return true;
            }
        }

        return false;
    }

    /**
     * 心光でエネルギーを何個分まで払えるか（持っている心光と、逓増・軽減から）。
     */
    public function maxLightUses(int $player): int
    {
        $lights = $this->players->light($player);
        $uses = 0;
        while ($uses < self::MAX_USES && $this->light->totalLights($player, ActionContext::COMPOSE, $uses + 1) <= $lights) {
            $uses++;
        }

        return $uses;
    }

    /**
     * 支払いを検証する。
     *
     * @param array<string, mixed> $payment
     * @throws EffectViolation
     */
    public function plan(int $player, ComposeContext $context, int $discount, int $cardId, array $payment): ComposePlan
    {
        if (!in_array($cardId, $this->candidates($player, $context), true)) {
            throw new EffectViolation(clienttranslate('That card cannot be composed now (not in hand, same name already composed, or wrong level)'));
        }
        $extra = $this->ids($payment['extra'] ?? [], 'extra');
        $discard = $this->ids($payment['discard'] ?? [], 'discard');
        $taps = $this->ids($payment['tap'] ?? [], 'tap');
        $uses = $payment['light'] ?? 0;
        if (!is_int($uses) || $uses < 0) {
            throw new EffectViolation(clienttranslate('The inner light uses must be a count'));
        }
        if (count($extra) > $this->energy->extraPayMax($player)) {
            throw new EffectViolation(clienttranslate('You cannot pay that many extra cards'));
        }
        if (!$context->paysEnergy() && ($discard !== [] || $taps !== [] || $uses > 0)) {
            throw new EffectViolation(clienttranslate('No energy is needed here, so nothing can be paid'));
        }
        $hand = $this->view->handIds($player);
        $paid = $uses;
        foreach ($extra as $id) {
            if ($id === $cardId || in_array($id, $discard, true) || !in_array($id, $hand, true)) {
                throw new EffectViolation(clienttranslate('A selected card cannot be paid as an extra card'));
            }
        }
        foreach ($discard as $id) {
            if ($id === $cardId || !in_array($id, $hand, true)) {
                throw new EffectViolation(clienttranslate('A selected card cannot be discarded to pay'));
            }
            $paid += MemoryCards::get($this->view->cardType($id))->level;
        }
        $this->energy->assertTaps($player, $cardId, $context, $taps, $discount);
        $need = $this->energy->need($player, $cardId, $context, $discount, count($taps));
        if ($paid < $need) {
            throw new EffectViolation(clienttranslate('Not enough energy was paid'));
        }
        $lights = $this->light->totalLights($player, ActionContext::COMPOSE, $uses);
        if ($lights > $this->players->light($player)) {
            throw new EffectViolation(clienttranslate('Not enough inner light'));
        }

        return new ComposePlan($cardId, $discard, $uses, $lights, $taps, $need, $extra);
    }

    /**
     * M095 で横向きにできる枚数（そのカードの必要エネルギーを超えない範囲の上限として、候補の数を返す）。
     */
    private function tapCapacity(int $player, int $cardId, ComposeContext $context, int $discount): int
    {
        $count = 0;
        foreach ($this->view->tableauIds($player) as $tableauId) {
            try {
                $this->energy->assertTaps($player, $cardId, $context, [$tableauId], $discount);
                $count++;
            } catch (EffectViolation) {
                // 横向きにできないカード
            }
        }

        return $count;
    }

    /**
     * @return list<int>
     * @throws EffectViolation
     */
    private function ids(mixed $value, string $key): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new EffectViolation(clienttranslate('The selection must be a list of card ids'));
        }
        $ids = [];
        foreach ($value as $item) {
            if (!is_int($item)) {
                throw new EffectViolation(clienttranslate('The selection must contain ids only'));
            }
            $ids[] = $item;
        }
        if (count(array_unique($ids)) !== count($ids)) {
            throw new EffectViolation(clienttranslate('The selection must not contain duplicates'));
        }

        return $ids;
    }
}
