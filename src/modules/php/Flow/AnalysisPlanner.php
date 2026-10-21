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
use Bga\Games\IRevolveThereforeIAm\Effects\Query\AnalysisCostQuery;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\KeyViewQuery;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\LightScheduleQuery;
use Bga\Games\IRevolveThereforeIAm\Material\ActionContext;
use Bga\Games\IRevolveThereforeIAm\Material\AnalysisCardDefinition;
use Bga\Games\IRevolveThereforeIAm\Material\AnalysisCards;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCards;

/**
 * 精神分析アクション（62）の検証（001 §10、008 §6.5.3）。状態を変えない。
 *
 * 入力（`actAnalyze` の `payment`）:
 * `{"removed": {"WISDOM": 1}, "keys": [{"src": "TABLEAU"|"HAND"|"LIGHT", "card": ID, "key": 鍵の番号, "for": "WISDOM"}...]}`。
 * - `removed`: コストの軽減（E02・想起効果の代替の割引）で取り除くコストの鍵。どの鍵を減らすかはプレイヤーが選ぶ（T8-3）。
 * - `keys`: 残りのコストの鍵 1 つずつの支払い。`for` はその鍵が払うコストの種類。`TABLEAU` は自己構成済みカード（縦向きのもの）を横向きにして、
 *   `HAND` は手札を捨てて、そのカードの `key` 番目の鍵（印刷順）を使う。複数の鍵を持つカードの鍵は同時に使える。
 *   `LIGHT` は心光の行使 1 回（X 回目は X 個。M059 は表）。万能移動鍵トークンは使えない（001 §10-2）。
 * 万能鍵は何の代わりにもなる。M094 は知恵鍵を万能鍵としても見られる（`KeyViewQuery`）。
 */
final class AnalysisPlanner
{
    private const MAX_USES = 30;

    public function __construct(
        private readonly GameView $view,
        private readonly PlayerValues $players,
        private readonly AnalysisCostQuery $cost,
        private readonly LightScheduleQuery $light,
        private readonly KeyViewQuery $keyView,
    ) {
    }

    /**
     * 進める先の精神カード（次の階層。2 枚あるときはどちらでもよい）。
     *
     * @return list<AnalysisCardDefinition>
     */
    public function destinations(int $player): array
    {
        return array_map(
            static fn (string $id): AnalysisCardDefinition => AnalysisCards::get($id),
            $this->view->nextSpiritIds($player),
        );
    }

    /**
     * コストから取り除ける鍵の個数（E02、想起効果の代替の割引）。
     */
    public function reduction(int $player, int $discount): int
    {
        return $this->cost->reduction($player, $discount);
    }

    /**
     * 精神分析アクションを行えるか（どの進む先でも、軽減のしかたを選べるとして払える先が 1 つでもあるか）。
     * 精神分析トークンの空撃ちを防ぐ（Q-28）。
     */
    public function canAnalyze(int $player, int $discount): bool
    {
        $supplies = $this->supplies($player);
        $uses = $this->maxLightUses($player);
        $reduction = $this->reduction($player, $discount);
        foreach ($this->destinations($player) as $card) {
            foreach ($this->removals($card->cost, min($reduction, array_sum($card->cost))) as $remaining) {
                $slots = [];
                foreach ($remaining as $key => $count) {
                    for ($i = 0; $i < $count; $i++) {
                        $slots[] = KeyType::from($key);
                    }
                }
                if (count($slots) - $this->maxMatching($player, $supplies, $slots) <= $uses) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 心光でコストの鍵を何個分まで払えるか。
     */
    public function maxLightUses(int $player): int
    {
        $lights = $this->players->light($player);
        $uses = 0;
        while ($uses < self::MAX_USES && $this->light->totalLights($player, ActionContext::ANALYSIS, $uses + 1) <= $lights) {
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
    public function plan(int $player, int $discount, string $destination, array $payment): AnalysisPlan
    {
        if (!in_array($destination, $this->view->nextSpiritIds($player), true)) {
            throw new EffectViolation('You can only advance to a spirit card on the next tier');
        }
        $card = AnalysisCards::get($destination);
        $removed = [];
        foreach ((array) ($payment['removed'] ?? []) as $key => $count) {
            if (!is_int($count)) {
                throw new EffectViolation("'removed' must map key types to counts");
            }
            $removed[(string) $key] = $count;
        }
        $remaining = $this->cost->remaining($card, $this->reduction($player, $discount), $removed);

        $keys = $payment['keys'] ?? [];
        if (!is_array($keys) || !array_is_list($keys)) {
            throw new EffectViolation("'keys' must be a list");
        }
        $hand = $this->view->handIds($player);
        $tableau = $this->view->tableauIds($player);
        $paid = [];
        $usedSlots = [];
        $tapped = [];
        $discarded = [];
        $uses = 0;
        foreach ($keys as $item) {
            $source = is_array($item) ? ($item['src'] ?? null) : null;
            $for = is_array($item) ? KeyType::tryFrom((string) ($item['for'] ?? '')) : null;
            if (!is_string($source) || $for === null || $for === KeyType::WILD) {
                throw new EffectViolation('Each payment needs a source (src) and the cost key it pays (for)');
            }
            $paid[$for->value] = ($paid[$for->value] ?? 0) + 1;
            if ($source === 'LIGHT') {
                $uses++;
                continue;
            }
            if ($source !== 'TABLEAU' && $source !== 'HAND') {
                throw new EffectViolation("Cannot pay with {$source}");
            }
            $cardId = $item['card'] ?? null;
            $index = $item['key'] ?? null;
            if (!is_int($cardId) || !is_int($index)) {
                throw new EffectViolation('A card payment needs the card id and the key number');
            }
            $isTableau = $source === 'TABLEAU';
            if (!in_array($cardId, $isTableau ? $tableau : $hand, true) || ($isTableau && $this->view->isTapped($cardId))) {
                throw new EffectViolation("Card {$cardId} cannot be used to pay");
            }
            $keyList = MemoryCards::get($this->view->cardType($cardId))->keys;
            if (!isset($keyList[$index])) {
                throw new EffectViolation("Card {$cardId} has no key number {$index}");
            }
            $slot = "{$source}:{$cardId}:{$index}";
            if (isset($usedSlots[$slot])) {
                throw new EffectViolation("A key of card {$cardId} is used twice");
            }
            $usedSlots[$slot] = true;
            if (!$this->keyView->canServeAs($player, $keyList[$index], $for, ActionContext::ANALYSIS)) {
                throw new EffectViolation("A {$keyList[$index]->value} key cannot pay a {$for->value} cost");
            }
            if ($isTableau) {
                $tapped[$cardId] = $cardId;
            } else {
                $discarded[$cardId] = $cardId;
            }
        }
        ksort($paid);
        $expected = $remaining;
        ksort($expected);
        if ($paid !== $expected) {
            throw new EffectViolation('The payment must cover the remaining cost exactly');
        }
        $lights = $this->light->totalLights($player, ActionContext::ANALYSIS, $uses);
        if ($lights > $this->players->light($player)) {
            throw new EffectViolation('Not enough inner light');
        }

        return new AnalysisPlan($destination, array_values($tapped), array_values($discarded), $uses, $lights);
    }

    /**
     * 払える鍵の一覧（縦向きの自己構成済みカードの鍵と、手札の鍵）。
     *
     * @return list<KeyType>
     */
    private function supplies(int $player): array
    {
        $supplies = [];
        foreach ($this->view->tableauIds($player) as $cardId) {
            if (!$this->view->isTapped($cardId)) {
                array_push($supplies, ...MemoryCards::get($this->view->cardType($cardId))->keys);
            }
        }
        foreach ($this->view->handIds($player) as $cardId) {
            array_push($supplies, ...MemoryCards::get($this->view->cardType($cardId))->keys);
        }

        return $supplies;
    }

    /**
     * コストの鍵を `$count` 個取り除くすべての方法（取り除いた後の残り）。
     *
     * @param array<string, int> $cost
     * @return list<array<string, int>>
     */
    private function removals(array $cost, int $count): array
    {
        $results = [];
        $keys = array_keys($cost);
        $recurse = static function (int $i, int $left, array $current) use (&$recurse, $keys, $cost, &$results): void {
            if ($i === count($keys)) {
                if ($left === 0) {
                    $results[] = $current;
                }

                return;
            }
            for ($take = 0; $take <= min($left, $cost[$keys[$i]]); $take++) {
                $next = $current;
                if ($cost[$keys[$i]] - $take > 0) {
                    $next[$keys[$i]] = $cost[$keys[$i]] - $take;
                }
                $recurse($i + 1, $left - $take, $next);
            }
        };
        $recurse(0, $count, []);

        return $results;
    }

    /**
     * 鍵（供給）とコストの鍵（スロット）の最大マッチング数。
     *
     * @param list<KeyType> $supplies
     * @param list<KeyType> $slots
     */
    private function maxMatching(int $player, array $supplies, array $slots): int
    {
        $owner = [];
        $matched = 0;
        foreach (array_keys($slots) as $slot) {
            $visited = [];
            if ($this->augment($player, $supplies, $slots, $slot, $owner, $visited)) {
                $matched++;
            }
        }

        return $matched;
    }

    /**
     * @param list<KeyType> $supplies
     * @param list<KeyType> $slots
     * @param array<int, int> $owner 供給の番号 → 割り当て済みのスロット
     * @param array<int, bool> $visited 今回の探索で調べた供給
     */
    private function augment(int $player, array $supplies, array $slots, int $slot, array &$owner, array &$visited): bool
    {
        foreach ($supplies as $i => $held) {
            if (isset($visited[$i]) || !$this->keyView->canServeAs($player, $held, $slots[$slot], ActionContext::ANALYSIS)) {
                continue;
            }
            $visited[$i] = true;
            if (!isset($owner[$i]) || $this->augment($player, $supplies, $slots, $owner[$i], $owner, $visited)) {
                $owner[$i] = $slot;

                return true;
            }
        }

        return false;
    }
}
