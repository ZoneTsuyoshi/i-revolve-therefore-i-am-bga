<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Material;

use Bga\Games\IRevolveThereforeIAm\Material\ChoiceKind;
use Bga\Games\IRevolveThereforeIAm\Material\Op;
use Bga\Games\IRevolveThereforeIAm\Material\ResourceTouch;
use PHPUnit\Framework\TestCase;

/**
 * 触る資源の記法と衝突規則（008 §6.3.2）、操作の資源の表（§3.1）、`choice_kind` のスキップ（§6.4）。
 * UT-73（衝突行列）のうち、操作の資源だけで確かめられる部分。カード効果どうしの行列は想い出カードの定義が入ってから足す。
 */
final class ResourceTouchTest extends TestCase
{
    public function testParse(): void
    {
        $touch = ResourceTouch::parse('NDw');
        self::assertSame('ND', $touch->symbol->value);
        self::assertSame('w', $touch->access->value);
        $list = ResourceTouch::parseList('D+  H+');
        self::assertSame(['D+', 'H+'], array_map(static fn (ResourceTouch $t): string => $t->toString(), $list));
        self::assertSame([], ResourceTouch::parseList(''));
    }

    public function testConflictRules(): void
    {
        $p = static fn (string $a, string $b): bool => ResourceTouch::parse($a)->conflictsWith(ResourceTouch::parse($b));

        self::assertFalse($p('H+', 'H+'), '加算どうしは交換可能');
        self::assertFalse($p('Hr', 'Hr'), '読み取りどうしは衝突しない');
        self::assertTrue($p('Hw', 'H+'));
        self::assertTrue($p('H+', 'Hw'));
        self::assertTrue($p('Hr', 'H+'));
        self::assertTrue($p('Hw', 'Hr'));
        self::assertTrue($p('Hw', 'Hw'));
        self::assertFalse($p('Hw', 'D+'), '別の資源は衝突しない');
    }

    /** 008 §6.3.3・§6.3.4 の例のうち、操作の資源だけで決まるもの。 */
    public function testExamplesFromTheSpec(): void
    {
        $touches = static fn (Op ...$ops): array => array_merge(...array_map(static fn (Op $o): array => $o->touches(), $ops));

        $e03 = $touches(Op::DRAW_DECK);
        $e04 = $touches(Op::GAIN_LIGHT);
        $e05 = $touches(Op::GAIN_TOKEN);
        $i05 = $touches(Op::SWAP_HAND_ROOM);
        $m052 = $touches(Op::SWAP_ROOM_MEMORY);
        $m055 = $touches(Op::SWAP_ROOM_FLOOR);

        // ターン開始時に E03・E04・E05: 衝突なし（固定順）
        self::assertFalse(ResourceTouch::anyConflict($e03, $e04));
        self::assertFalse(ResourceTouch::anyConflict($e03, $e05));
        self::assertFalse(ResourceTouch::anyConflict($e04, $e05));
        // E03 と I05: 衝突（H）
        self::assertTrue(ResourceTouch::anyConflict($e03, $i05));
        // M052 と M055: 衝突なし。M052 と I05: 衝突（RM）
        self::assertFalse(ResourceTouch::anyConflict($m052, $m055));
        self::assertTrue(ResourceTouch::anyConflict($m052, $i05));
        // M073〜M075 と I01（どれも D+ H+）は互いに交換可能
        self::assertFalse(ResourceTouch::anyConflict($e03, $e03));
    }

    public function testEveryOpDeclaresItsTouchesOrNoneOnPurpose(): void
    {
        $empty = array_filter(Op::cases(), static fn (Op $op): bool => $op->touches() === []);
        // 資源を触らないのは、勝利させる OP-14 と、発動する想起効果で決まる OP-25
        self::assertSame([Op::WIN, Op::RECALL_ACTIVATE], array_values($empty));
    }

    public function testChoiceKindSkipTable(): void
    {
        $notSkippable = array_filter(ChoiceKind::cases(), static fn (ChoiceKind $k): bool => !$k->skippable());
        self::assertEqualsCanonicalizing(
            [
                ChoiceKind::ORDER_EFFECTS,
                ChoiceKind::PICK_FIELD_CARD,
                ChoiceKind::PICK_DISCARD,
                ChoiceKind::PICK_ENHANCED_EGO,
                ChoiceKind::PICK_SPIRIT_BRANCH,
            ],
            array_values($notSkippable),
        );
    }

    public function testVictoryCheckingOps(): void
    {
        $checked = array_filter(Op::cases(), static fn (Op $op): bool => $op->checksVictory());
        self::assertContains(Op::DRAW_DECK, $checked);
        self::assertContains(Op::GAIN_TOKEN, $checked);
        self::assertContains(Op::SWAP_COMPOSED_HAND, $checked);
        self::assertNotContains(Op::ADD_NEXT_COMPOSE_DISCOUNT, $checked);
        self::assertNotContains(Op::RECORD_FLOOR_ALTERNATIVE, $checked);
        self::assertNotContains(Op::ACQUIRE_ROOM_CARD, $checked);
    }
}
