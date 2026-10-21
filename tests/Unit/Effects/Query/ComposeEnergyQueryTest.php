<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Effects\Query;

use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Material\ComposeContext;
use IRevolveTests\Support\QueryKit;
use PHPUnit\Framework\TestCase;

/**
 * 仕様書 008 §12.2 の UT-11〜UT-16、UT-27（文脈の規則の部分）。
 * カード効果の定義は本番の `EffectCatalog`（008 §7.2）。
 */
final class ComposeEnergyQueryTest extends TestCase
{
    /** UT-11: M060 があり、R4 のボーナスで M091（レベル 2、エネルギー 5）を自己構成するときのエネルギーは 0。負にならない。 */
    public function testBonusComposeIsFreeAndNeverNegative(): void
    {
        $kit = new QueryKit();
        $kit->game->giveTableau(1, ['M060']);
        [$m091] = $kit->game->giveHand(1, ['M091']);

        self::assertSame(0, $kit->compose->need(1, $m091, ComposeContext::BONUS_ANY));
        self::assertSame(0, $kit->compose->need(1, $m091, ComposeContext::BONUS_LV1));
        self::assertSame(0, $kit->compose->need(1, $m091, ComposeContext::EFFECT_FREE_LV1));
        // 通常の自己構成では M060 のぶんだけ減る（レベル 2 のカード）
        self::assertSame(4, $kit->compose->need(1, $m091, ComposeContext::PHASE));
        // 軽減がエネルギーを超えても 0 で止まり、リソースは増えない（FAQ 行 18）
        $kit->game->turnDiscount = 9;
        self::assertSame(0, $kit->compose->need(1, $m091, ComposeContext::PHASE));
    }

    public function testM060OnlyAppliesToLevelTwoCards(): void
    {
        $kit = new QueryKit();
        $kit->game->giveTableau(1, ['M060']);
        [$level1, $level2] = $kit->game->giveHand(1, ['M001', 'M085']); // M001: Lv1 エネルギー 2, M085: Lv2 エネルギー 5

        self::assertSame(2, $kit->compose->need(1, $level1, ComposeContext::PHASE));
        self::assertSame(4, $kit->compose->need(1, $level2, ComposeContext::PHASE)); // エネルギー 5 - 1
    }

    /** UT-12: POSEIDON の軽減、E01、M077、次の自己構成の軽減が加算され、最後に 1 回だけ 0 へ切り上げる。 */
    public function testDiscountsAddUpAndClampOnce(): void
    {
        $kit = new QueryKit();
        $kit->game->giveTableau(1, ['M060', 'M077']);
        $kit->game->giveEgos(1, ['E01']);
        [$m095] = $kit->game->giveHand(1, ['M095']); // Lv2、エネルギー 6、感情鍵 2 本

        $kit->game->turnDiscount = 1; // POSEIDON
        // E01 -1、M077 -1（感情鍵）、M060 -1（Lv2）、POSEIDON -1 = 4 → 6 - 4 = 2
        self::assertSame(2, $kit->compose->need(1, $m095, ComposeContext::FLOOR));

        $kit->game->nextDiscount = 2;
        self::assertSame(0, $kit->compose->need(1, $m095, ComposeContext::FLOOR));

        $kit->game->nextDiscount = 5; // 超過は切り捨て（0 未満にならない）
        self::assertSame(0, $kit->compose->need(1, $m095, ComposeContext::FLOOR));
        // 床アクションの文脈の割引（HEPHAESTUS の -1）も加算される
        $kit->game->nextDiscount = 0;
        self::assertSame(1, $kit->compose->need(1, $m095, ComposeContext::FLOOR, 1));
    }

    public function testM077NeedsAnEmotionKeyOnTheComposedCard(): void
    {
        $kit = new QueryKit();
        $kit->game->giveTableau(1, ['M077']);
        [$emotion, $will] = $kit->game->giveHand(1, ['M002', 'M003']); // M002: 感情鍵, M003: 意志鍵、どちらもエネルギー 2

        self::assertSame(1, $kit->compose->need(1, $emotion, ComposeContext::PHASE));
        self::assertSame(2, $kit->compose->need(1, $will, ComposeContext::PHASE));
    }

    /** M068（UT-31 の計算の部分）: 4 部屋以上で、最初の自己構成アクションにだけ効く。 */
    public function testM068OnlyForTheFirstComposeActionAfterFourRooms(): void
    {
        $kit = new QueryKit();
        $kit->game->giveTableau(1, ['M068']);
        [$card] = $kit->game->giveHand(1, ['M001']);

        self::assertSame(2, $kit->compose->need(1, $card, ComposeContext::PHASE));
        $kit->game->roomsEntered = 4;
        self::assertSame(1, $kit->compose->need(1, $card, ComposeContext::PHASE));
        $kit->game->composeActions = 1;
        self::assertSame(2, $kit->compose->need(1, $card, ComposeContext::PHASE));
    }

    /** UT-13: M098（エネルギー 7）の手札での値は、自己構成済みの感情鍵 3 本で 4、7 本以上で 0。 */
    public function testHeldEnergyOfM098(): void
    {
        $kit = new QueryKit();
        [$m098] = $kit->game->giveHand(1, ['M098']);

        self::assertSame(7, $kit->compose->heldEnergy(1, $m098));
        $kit->game->giveTableau(1, ['M002', 'M002', 'M002']); // 感情鍵 3 本
        self::assertSame(4, $kit->compose->heldEnergy(1, $m098));
        $kit->game->giveTableau(1, ['M095', 'M095']); // 感情鍵 2 本のカード 2 枚 → 7 本
        self::assertSame(0, $kit->compose->heldEnergy(1, $m098));
        $kit->game->giveTableau(1, ['M002']); // 8 本でも 0 未満にならない
        self::assertSame(0, $kit->compose->heldEnergy(1, $m098));
        // 自己構成済みになった後は働かない（手札のカードの修飾は手札にあるときだけ）
        self::assertSame(0, $kit->compose->need(1, $m098, ComposeContext::PHASE));
    }

    /** UT-14: I03 の代替。感情鍵を持たないカードを捨てると M098 を自己構成でき、感情鍵のカードを捨てると 5 になりできない。 */
    public function testArchitectSubstituteComparesAfterDiscarding(): void
    {
        $kit = new QueryKit();
        $kit->game->giveEgos(1, ['I03']);
        [$noEmotion] = $kit->game->giveTableau(1, ['M001']); // 知恵鍵、エネルギー 2
        [$emotion] = $kit->game->giveTableau(1, ['M002', 'M002', 'M002']); // 感情鍵 3 本
        [$m098] = $kit->game->giveHand(1, ['M098']);

        // M001 を捨てる: M098 は 7 - 3 = 4 ≦ 2 + 2
        self::assertTrue($kit->compose->architectSubstituteAllowed(1, $noEmotion, $m098, ComposeContext::PHASE));
        // M002 を捨てる: 感情鍵が 2 本になり M098 は 5 > 2 + 2
        self::assertFalse($kit->compose->architectSubstituteAllowed(1, $emotion, $m098, ComposeContext::PHASE));
        // エネルギーを払わない文脈では使えない
        self::assertFalse($kit->compose->architectSubstituteAllowed(1, $noEmotion, $m098, ComposeContext::BONUS_ANY));
    }

    public function testArchitectSubstituteNeedsTheEgo(): void
    {
        $kit = new QueryKit();
        [$tableau] = $kit->game->giveTableau(1, ['M001']);
        [$hand] = $kit->game->giveHand(1, ['M003']);

        self::assertFalse($kit->compose->architectSubstituteAllowed(1, $tableau, $hand, ComposeContext::PHASE));
        $kit->game->giveEgos(1, ['I03']);
        self::assertTrue($kit->compose->architectSubstituteAllowed(1, $tableau, $hand, ComposeContext::PHASE));
        // 2 を超えて大きいエネルギーは不可（M085 はレベル 2）
        [$big] = $kit->game->giveHand(1, ['M091']); // エネルギー 5 > 2 + 2
        self::assertFalse($kit->compose->architectSubstituteAllowed(1, $tableau, $big, ComposeContext::PHASE));
    }

    /** UT-15: M095 で、感情鍵のカード 2 枚を横向きにするとエネルギー -2。万能鍵のカードは選べない。M098 を横向きにしても -1。 */
    public function testTapForEnergy(): void
    {
        $kit = new QueryKit();
        [, $a, $b, $wild, $m098] = $kit->game->giveTableau(1, ['M095', 'M002', 'M005', 'M040', 'M098']);
        [$target] = $kit->game->giveHand(1, ['M091']); // エネルギー 5

        $kit->compose->assertTaps(1, $target, ComposeContext::PHASE, [$a, $b]);
        self::assertSame(3, $kit->compose->need(1, $target, ComposeContext::PHASE, 0, 2));

        $kit->compose->assertTaps(1, $target, ComposeContext::PHASE, [$m098]);
        self::assertSame(4, $kit->compose->need(1, $target, ComposeContext::PHASE, 0, 1));

        $this->expectException(EffectViolation::class);
        $kit->compose->assertTaps(1, $target, ComposeContext::PHASE, [$wild]);
    }

    public function testTapRejectsTappedDuplicatesAndOtherPlayersCards(): void
    {
        $kit = new QueryKit();
        [, $a, $b] = $kit->game->giveTableau(1, ['M095', 'M002', 'M005']);
        [$other] = $kit->game->giveTableau(2, ['M002']);
        [$target] = $kit->game->giveHand(1, ['M091']);
        $kit->game->tapped[$b] = true;

        foreach ([[$b], [$a, $a], [$other]] as $ids) {
            try {
                $kit->compose->assertTaps(1, $target, ComposeContext::PHASE, $ids);
                self::fail('Expected a violation');
            } catch (EffectViolation) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTapNeedsM095AndAPayingContext(): void
    {
        $kit = new QueryKit();
        [$a] = $kit->game->giveTableau(1, ['M002']);
        [$target] = $kit->game->giveHand(1, ['M091']);
        try {
            $kit->compose->assertTaps(1, $target, ComposeContext::PHASE, [$a]);
            self::fail('M095 is missing');
        } catch (EffectViolation) {
            self::addToAssertionCount(1);
        }

        $kit->game->giveTableau(1, ['M095']);
        try {
            $kit->compose->assertTaps(1, $target, ComposeContext::BONUS_ANY, [$a]);
            self::fail('Bonus composes pay no energy');
        } catch (EffectViolation) {
            self::addToAssertionCount(1);
        }
        $kit->compose->assertTaps(1, $target, ComposeContext::PHASE, []);
    }

    /** UT-16: 必要エネルギーを超える枚数を横向きにする入力は拒否される。 */
    public function testTooManyTapsAreRejected(): void
    {
        $kit = new QueryKit();
        [, $a, $b, $c] = $kit->game->giveTableau(1, ['M095', 'M002', 'M005', 'M002']);
        [$target] = $kit->game->giveHand(1, ['M001']); // エネルギー 2

        $kit->compose->assertTaps(1, $target, ComposeContext::PHASE, [$a, $b]); // ちょうど
        $this->expectException(EffectViolation::class);
        $kit->compose->assertTaps(1, $target, ComposeContext::PHASE, [$a, $b, $c]);
    }

    /** UT-27（文脈の規則の部分）: 次の自己構成の軽減を消費し、完了に数えるのは自己構成アクションの文脈だけ。 */
    public function testNextComposeDiscountContexts(): void
    {
        foreach ([ComposeContext::PHASE, ComposeContext::TOKEN, ComposeContext::FLOOR] as $context) {
            self::assertTrue($context->consumesNextDiscount(), $context->value);
            self::assertTrue($context->paysEnergy());
            self::assertNull($context->levelLimit());
        }
        foreach ([ComposeContext::BONUS_LV1, ComposeContext::BONUS_ANY, ComposeContext::EFFECT_FREE_LV1] as $context) {
            self::assertFalse($context->consumesNextDiscount(), $context->value);
            self::assertFalse($context->paysEnergy());
        }
        self::assertSame(1, ComposeContext::BONUS_LV1->levelLimit());
        self::assertSame(1, ComposeContext::EFFECT_FREE_LV1->levelLimit());
        self::assertNull(ComposeContext::BONUS_ANY->levelLimit());

        $kit = new QueryKit();
        [$card] = $kit->game->giveHand(1, ['M001']);
        $kit->game->nextDiscount = 1;
        self::assertSame(1, $kit->compose->need(1, $card, ComposeContext::TOKEN));
    }
}
