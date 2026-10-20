<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\KeyPayment;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\LightScheduleQuery;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\MovePaymentQuery;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\RouteStep;
use Bga\Games\IRevolveThereforeIAm\Material\ActionContext;
use Bga\Games\IRevolveThereforeIAm\Material\FloorBonus;

/**
 * 移動フェイズの経路と支払いの検証（仕様書 006 §5.1、008 §6.2）。フレームワークも DB も呼ばない。
 * 経路全体を一度に受け取り、状態を変えずに `MoveOutcome`（適用すべき結果）を返す。規則に反すれば `MoveViolation`。
 *
 * 入力（006 §5.1）:
 * `{"rooms": [位置...], "payments": [[{"src", "card", "key"}...]...]}`。`rooms` は入室する部屋だけ（スキップした部屋は書かない）。
 * `payments[i]` は `rooms[i]` の錠前 1 個ごとの支払い（レベル 1 は 1 つ、レベル 2 は 2 つ）。`rooms` が空なら移動しない
 * （払える移動が 1 つも無いときだけ許す。Q-38）。
 *
 * 検証の手順:
 * 1. 形（V-1）。2. 経路: 各入室は現在位置の次の部屋か、次がレベル 2 のときのその次（スキップ。V-2）。
 * 3. 錠前の数と鍵の種類（MD-05 込み）、カード・鍵の所有と二重使用の禁止、MD-06 の万能鍵。
 * 4. 同じカードを使う入室は、入室した部屋の列で連続している（スキップを挟んでも連続。Q-07）。
 * 5. 心光と万能移動鍵トークン: 経路の順に 1 部屋ずつ、周遊ボーナスで得た分を後の支払いに使えるとして数える（T8-5）。
 */
final class MovePlanner
{
    /** 経路の長さの上限（006 §5.1。1 周を超える移動の規定がないため） */
    public const MAX_ROOMS = 24;

    /** 支払いの `src`（006 §5.1） */
    private const SOURCES = [KeyPayment::TABLEAU, KeyPayment::HAND, KeyPayment::LIGHT, KeyPayment::TOKEN, KeyPayment::EFFECT];

    public function __construct(
        private readonly MovePaymentQuery $payments,
        private readonly LightScheduleQuery $light,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @param bool $allowStay true なら、払える移動があっても移動しない入力を許す（zombie が手札を捨てずに止まるとき）
     * @throws MoveViolation
     */
    public function plan(MoveBoard $board, array $input, bool $allowStay = false): MoveOutcome
    {
        [$rooms, $payments] = $this->parse($input);
        if ($rooms === []) {
            if (!$allowStay && $this->canMove($board)) {
                throw new MoveViolation(MoveViolation::MUST_MOVE);
            }

            return new MoveOutcome([]);
        }

        $steps = [];
        $routeSteps = [];
        $allPayments = [];
        $usedSlots = [];
        $cardEntries = [];
        $effectUsed = [];
        $lightUses = 0;
        $lv2Seen = 0;
        $pos = $board->position;

        foreach ($rooms as $index => $target) {
            $next = MoveBoard::after($pos);
            $skipped = null;
            if ($target !== $next) {
                if ($board->room($next)->level !== 2 || $target !== MoveBoard::after($next)) {
                    throw new MoveViolation(MoveViolation::INVALID_ROUTE, "room $target after $pos");
                }
                $skipped = $next;
            }
            $room = $board->room($target);
            $lock = $room->lock ?? throw new MoveViolation(MoveViolation::INVALID_ROUTE, "room $target has no memory card");
            if (count($payments[$index]) !== $room->level) {
                throw new MoveViolation(MoveViolation::LOCK_COUNT, "room $target needs {$room->level}");
            }
            $grants = ($room->level === 2 && $board->priorLv2Entries === 0 && $lv2Seen === 0)
                ? $this->payments->usableGrantedWildKeys($board->player, 2)
                : 0;

            $tapped = [];
            $discarded = [];
            $effectCards = [];
            $paid = [];
            $stepLights = 0;
            $stepTokens = 0;
            foreach ($payments[$index] as $item) {
                switch ($item['src']) {
                    case KeyPayment::TABLEAU:
                    case KeyPayment::HAND:
                        $isTableau = $item['src'] === KeyPayment::TABLEAU;
                        $card = ($isTableau ? $board->tableau : $board->hand)[$item['card']] ?? null;
                        $key = $item['key'];
                        if ($card === null || $card->tapped || $key === null || !isset($card->keys[$key])) {
                            throw new MoveViolation(MoveViolation::BAD_CARD, "{$item['src']} {$item['card']}");
                        }
                        $slot = "{$item['src']}:{$item['card']}:$key";
                        if (isset($usedSlots[$slot])) {
                            throw new MoveViolation(MoveViolation::BAD_CARD, "key used twice: $slot");
                        }
                        $usedSlots[$slot] = true;
                        if (!$this->payments->canPayLock($board->player, $lock, $card->keys[$key])) {
                            throw new MoveViolation(MoveViolation::KEY_MISMATCH, "{$card->keys[$key]->value} for {$lock->value}");
                        }
                        if (!isset($cardEntries[$card->id])) {
                            if ($isTableau) {
                                $tapped[] = $card->id;
                            } else {
                                $discarded[] = $card->id;
                            }
                        }
                        $cardEntries[$card->id][$index] = true;
                        $paid[] = new KeyPayment($item['src'], $card->keys[$key]);
                        break;

                    case KeyPayment::LIGHT:
                        $stepLights++;
                        $paid[] = new KeyPayment(KeyPayment::LIGHT);
                        break;

                    case KeyPayment::TOKEN:
                        $stepTokens++;
                        $paid[] = new KeyPayment(KeyPayment::TOKEN);
                        break;

                    case KeyPayment::EFFECT:
                        $card = $board->tableau[$item['card']] ?? null;
                        if ($card === null || isset($effectUsed[$card->id])) {
                            throw new MoveViolation(MoveViolation::BAD_GRANT, "card {$item['card']}");
                        }
                        $effectUsed[$card->id] = true;
                        $effectCards[] = $card->id;
                        $paid[] = new KeyPayment(KeyPayment::EFFECT);
                        break;
                }
            }
            if (count($effectCards) > $grants) {
                throw new MoveViolation(MoveViolation::BAD_GRANT, 'too many granted keys for room ' . $target);
            }

            $lightsPaid = 0;
            for ($i = 0; $i < $stepLights; $i++) {
                $lightUses++;
                $lightsPaid += $this->light->lightsForUse($board->player, ActionContext::MOVE, $lightUses);
            }
            $skipBonus = $skipped === null ? FloorBonus::NONE : $board->room($skipped)->bonus;
            $routeSteps[] = new RouteStep($stepLights, $stepTokens, $skipBonus, $room->bonus);
            $steps[] = new MoveStep(
                $pos,
                $target,
                $room->level,
                $skipped,
                $skipBonus,
                $room->bonus,
                $tapped,
                $discarded,
                $effectCards,
                $stepLights,
                $lightsPaid,
                $stepTokens,
                $paid,
            );
            array_push($allPayments, ...$paid);
            if ($room->level === 2) {
                $lv2Seen++;
            }
            $pos = $target;
        }

        foreach ($cardEntries as $cardId => $entries) {
            $indices = array_keys($entries);
            if (max($indices) - min($indices) + 1 !== count($indices)) {
                throw new MoveViolation(MoveViolation::SPLIT_CARD, "card $cardId");
            }
        }
        try {
            $this->payments->assertResources($board->player, $board->lights, $board->wildUp, $routeSteps);
        } catch (EffectViolation $e) {
            throw new MoveViolation(MoveViolation::NOT_ENOUGH_RESOURCES, $e->getMessage());
        }

        return new MoveOutcome(
            $steps,
            $this->payments->keyKindsPaid($allPayments),
            $this->payments->wildTokensUsed($allPayments),
            $lightUses,
        );
    }

    /**
     * 1 部屋でも移動できるか（Q-38）。次の部屋に入るか、次がレベル 2 ならスキップしてその次に入る。
     * 1 つの部屋の支払いの可否は、使える鍵の数（錠前に合う鍵、万能移動鍵トークン、MD-06 の万能鍵）と、
     * 足りない分を心光の行使で払えるかで決まる（スキップの周遊ボーナスは先に得る）。
     */
    public function canMove(MoveBoard $board): bool
    {
        foreach ($this->candidates($board) as [$target, $skipBonus]) {
            $room = $board->room($target);
            if ($room->lock === null) {
                continue;
            }
            $units = $board->wildUp + ($skipBonus === FloorBonus::WILD_MOVE_KEY ? 1 : 0);
            if ($room->level === 2 && $board->priorLv2Entries === 0) {
                $units += $this->payments->usableGrantedWildKeys($board->player, 2);
            }
            foreach ([$board->tableau, $board->hand] as $cards) {
                foreach ($cards as $card) {
                    if ($card->tapped) {
                        continue;
                    }
                    foreach ($card->keys as $key) {
                        if ($this->payments->canPayLock($board->player, $room->lock, $key)) {
                            $units++;
                        }
                    }
                }
            }
            $lights = $board->lights + ($skipBonus === FloorBonus::LIGHT ? 1 : 0);
            $shortfall = max(0, $room->level - $units);
            if ($lights >= $this->light->totalLights($board->player, ActionContext::MOVE, $shortfall)) {
                return true;
            }
        }

        return false;
    }

    /**
     * zombie の移動（006 §10）。次の部屋（レベル 2 でもスキップしない）まで 1 部屋だけ、万能鍵（カード効果）→ 万能移動鍵トークン →
     * 自己構成済みカードの鍵 → 心光の順で払えるなら移動する。手札は捨てない。払えなければ、次がレベル 2 のときだけスキップして
     * その次の部屋を試す。それも払えなければ移動しない（空の入力）。
     *
     * @return array<string, mixed> `plan` の入力
     */
    public function autoPlan(MoveBoard $board): array
    {
        $next = MoveBoard::after($board->position);
        foreach ([$next, $board->room($next)->level === 2 ? MoveBoard::after($next) : null] as $target) {
            if ($target === null) {
                continue;
            }
            $room = $board->room($target);
            if ($room->lock === null) {
                continue;
            }
            $items = $this->autoPayments($board, $room);
            if ($items === null) {
                continue;
            }
            $input = ['rooms' => [$target], 'payments' => [$items]];
            try {
                $this->plan($board, $input);
            } catch (MoveViolation) {
                continue;
            }

            return $input;
        }

        return ['rooms' => [], 'payments' => []];
    }

    /**
     * @return list<array{src: string, card?: int, key?: int}>|null 払えなければ null
     */
    private function autoPayments(MoveBoard $board, MoveRoom $room): ?array
    {
        $lock = $room->lock;
        if ($lock === null) {
            return null;
        }
        $skipBonus = $room->pos === MoveBoard::after($board->position)
            ? FloorBonus::NONE
            : $board->room(MoveBoard::after($board->position))->bonus;
        $items = [];
        $effect = ($room->level === 2 && $board->priorLv2Entries === 0)
            ? $this->payments->usableGrantedWildKeys($board->player, 2)
            : 0;
        $effectCards = $effect > 0 ? $this->effectCardIds($board) : [];
        $tokens = $board->wildUp + ($skipBonus === FloorBonus::WILD_MOVE_KEY ? 1 : 0);
        $slots = [];
        foreach ($board->tableau as $card) {
            if ($card->tapped) {
                continue;
            }
            foreach ($card->keys as $key => $kind) {
                if ($this->payments->canPayLock($board->player, $lock, $kind)) {
                    $slots[] = ['src' => KeyPayment::TABLEAU, 'card' => $card->id, 'key' => $key];
                }
            }
        }
        for ($i = 0; $i < $room->level; $i++) {
            if ($effect > 0 && $effectCards !== []) {
                $items[] = ['src' => KeyPayment::EFFECT, 'card' => array_shift($effectCards)];
                $effect--;
            } elseif ($tokens > 0) {
                $items[] = ['src' => KeyPayment::TOKEN];
                $tokens--;
            } elseif ($slots !== []) {
                $items[] = array_shift($slots);
            } else {
                $items[] = ['src' => KeyPayment::LIGHT];
            }
        }

        return $items;
    }

    /**
     * 万能鍵（MD-06）を与えるカードの候補。効果の定義は I-09 で入るので、いまは自己構成済みカードを昇順に並べるだけ。
     * 検証側は個数の上限（`usableGrantedWildKeys`）だけを見るので、与えるカードかどうかはここでは決めない。
     *
     * @return list<int>
     */
    private function effectCardIds(MoveBoard $board): array
    {
        $ids = array_keys($board->tableau);
        sort($ids);

        return $ids;
    }

    /**
     * 移動先の候補 [位置, 先に得るスキップの周遊ボーナス]。
     *
     * @return list<array{int, FloorBonus}>
     */
    private function candidates(MoveBoard $board): array
    {
        $next = MoveBoard::after($board->position);
        $out = [[$next, FloorBonus::NONE]];
        if ($board->room($next)->level === 2) {
            $out[] = [MoveBoard::after($next), $board->room($next)->bonus];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{list<int>, list<list<array{src: string, card: int|null, key: int|null}>>}
     * @throws MoveViolation
     */
    private function parse(array $input): array
    {
        $rooms = $input['rooms'] ?? [];
        $payments = $input['payments'] ?? [];
        if (!is_array($rooms) || !is_array($payments) || !array_is_list($rooms) || !array_is_list($payments)) {
            throw new MoveViolation(MoveViolation::BAD_INPUT, 'rooms and payments must be lists');
        }
        if (count($rooms) !== count($payments) || count($rooms) > self::MAX_ROOMS) {
            throw new MoveViolation(MoveViolation::BAD_INPUT, 'rooms and payments must have the same length (at most 24)');
        }
        $outRooms = [];
        $outPayments = [];
        foreach ($rooms as $i => $room) {
            $list = $payments[$i];
            if (!is_int($room) || !is_array($list) || !array_is_list($list)) {
                throw new MoveViolation(MoveViolation::BAD_INPUT, "room #$i");
            }
            $items = [];
            foreach ($list as $item) {
                $src = is_array($item) ? ($item['src'] ?? null) : null;
                if (!is_string($src) || !in_array($src, self::SOURCES, true)) {
                    throw new MoveViolation(MoveViolation::BAD_INPUT, "payment in room #$i");
                }
                $card = $item['card'] ?? null;
                $key = $item['key'] ?? null;
                if (($card !== null && !is_int($card)) || ($key !== null && !is_int($key))) {
                    throw new MoveViolation(MoveViolation::BAD_INPUT, "payment in room #$i");
                }
                $needsCard = $src === KeyPayment::TABLEAU || $src === KeyPayment::HAND || $src === KeyPayment::EFFECT;
                if ($needsCard && $card === null) {
                    throw new MoveViolation(MoveViolation::BAD_INPUT, "payment in room #$i needs a card");
                }
                $items[] = ['src' => $src, 'card' => $card, 'key' => $key];
            }
            $outRooms[] = $room;
            $outPayments[] = $items;
        }

        return [$outRooms, $outPayments];
    }
}
