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
use Bga\Games\IRevolveThereforeIAm\Material\FloorBonus;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;
use Bga\Games\IRevolveThereforeIAm\Material\Modifier;

/**
 * 移動の支払いに関わる効果の問い合わせ（008 §6.2。MD-04（MOVE）・MD-05（MOVE）・MD-06）。
 * 経路全体の検証（連続した部屋、錠前の割り当て）は移動フェイズ（`Flow/`）の担当で、ここは効果が決める部分だけを答える。
 */
final class MovePaymentQuery
{
    /** レベル 2 の部屋の錠前の数。MD-06 の軽減の上限（008 §6.2 の 3） */
    public const LEVEL2_LOCKS = 2;

    public function __construct(
        private readonly ActiveSpecs $active,
        private readonly KeyViewQuery $keyView,
        private readonly LightScheduleQuery $lightSchedule,
    ) {
    }

    /**
     * 鍵 `$held` で、錠前 `$lock` を払えるか（M096 の読み替え込み）。
     */
    public function canPayLock(int $player, KeyType $lock, KeyType $held): bool
    {
        return $this->keyView->canServeAs($player, $held, $lock, ActionContext::MOVE);
    }

    /**
     * MD-06（M064〜M066）による錠前の軽減数。このターン最初のレベル 2 の部屋への入室のときだけ、持っているカードの枚数
     * （重ねて働く）。錠前の数（レベル 2 は 2 個）を超えては減らない。トークンも万能鍵も得ない（Q-70。FAQ 行 22 の「移動コスト -1」）。
     */
    public function lockReduction(int $player, int $enteringLevel): int
    {
        if ($enteringLevel !== 2) {
            return 0;
        }
        $context = new ConditionContext($player, null, ['entering_level' => $enteringLevel]);
        $total = 0;
        foreach ($this->active->modifiers($player, Modifier::MOVE_LOCK_REDUCTION, $context) as $spec) {
            $reduction = $spec->params['reduction'] ?? 1;
            $total += is_int($reduction) ? $reduction : 1;
        }

        return min($total, self::LEVEL2_LOCKS);
    }

    /**
     * 支払いで使った鍵の種類（CD-02 / M061）。手札・自己構成済みカードの印刷された非万能鍵だけを数える。
     * 心光・万能移動鍵トークンは数えない（FAQ 行 19・20）。
     *
     * @param list<KeyPayment> $payments
     * @return list<KeyType>
     */
    public function keyKindsPaid(array $payments): array
    {
        $kinds = [];
        foreach ($payments as $payment) {
            $isCard = $payment->source === KeyPayment::TABLEAU || $payment->source === KeyPayment::HAND;
            $key = $payment->printedKey;
            if ($isCard && $key !== null && $key !== KeyType::WILD && !in_array($key, $kinds, true)) {
                $kinds[] = $key;
            }
        }

        return $kinds;
    }

    /**
     * 使った万能移動鍵トークンの数（CD-03 / I06）。
     *
     * @param list<KeyPayment> $payments
     */
    public function wildTokensUsed(array $payments): int
    {
        return count(array_filter($payments, static fn (KeyPayment $p): bool => $p->source === KeyPayment::TOKEN));
    }

    /**
     * 経路を 1 部屋ずつ順にシミュレートして、心光と万能移動鍵トークンが足りるか検証する（T8-5）。
     * 周遊ボーナスで得た心光とトークンは、その後の部屋の支払いに使える。心光の行使は経路全体で数え（MD-04 込み）、
     * スキップしたレベル 2 の部屋のボーナスは次の入室の支払いより先に得る。
     *
     * @param list<RouteStep> $steps
     * @throws EffectViolation
     */
    public function assertResources(int $player, int $lights, int $tokens, array $steps): void
    {
        $uses = 0;
        foreach ($steps as $index => $step) {
            [$lights, $tokens] = $this->applyBonus($step->skipBonus, $lights, $tokens);
            for ($i = 0; $i < $step->lightUses; $i++) {
                $uses++;
                $lights -= $this->lightSchedule->lightsForUse($player, ActionContext::MOVE, $uses);
            }
            $tokens -= $step->tokenUses;
            if ($lights < 0 || $tokens < 0) {
                throw new EffectViolation(clienttranslate('Not enough inner light or wild move keys on this route'));
            }
            [$lights, $tokens] = $this->applyBonus($step->entryBonus, $lights, $tokens);
        }
    }

    /**
     * @return array{int, int} 心光、万能移動鍵トークン
     */
    private function applyBonus(FloorBonus $bonus, int $lights, int $tokens): array
    {
        return match ($bonus) {
            FloorBonus::LIGHT => [$lights + 1, $tokens],
            FloorBonus::WILD_MOVE_KEY => [$lights, $tokens + 1],
            default => [$lights, $tokens],
        };
    }
}
