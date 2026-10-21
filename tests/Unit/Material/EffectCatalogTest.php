<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Material;

use Bga\Games\IRevolveThereforeIAm\Material\AnalysisCards;
use Bga\Games\IRevolveThereforeIAm\Material\ChoiceKind;
use Bga\Games\IRevolveThereforeIAm\Material\ComposeContext;
use Bga\Games\IRevolveThereforeIAm\Material\Condition;
use Bga\Games\IRevolveThereforeIAm\Material\Control;
use Bga\Games\IRevolveThereforeIAm\Material\EffectCatalog;
use Bga\Games\IRevolveThereforeIAm\Material\EffectKind;
use Bga\Games\IRevolveThereforeIAm\Material\EffectNode;
use Bga\Games\IRevolveThereforeIAm\Material\EgoCards;
use Bga\Games\IRevolveThereforeIAm\Material\OwnerScope;
use Bga\Games\IRevolveThereforeIAm\Material\EffectSpec;
use Bga\Games\IRevolveThereforeIAm\Material\Event;
use Bga\Games\IRevolveThereforeIAm\Material\FloorBonus;
use Bga\Games\IRevolveThereforeIAm\Material\FloorCards;
use Bga\Games\IRevolveThereforeIAm\Material\Mechanism;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCards;
use Bga\Games\IRevolveThereforeIAm\Material\Modifier;
use Bga\Games\IRevolveThereforeIAm\Material\Op;
use Bga\Games\IRevolveThereforeIAm\Material\Origin;
use Bga\Games\IRevolveThereforeIAm\Material\RecallEffect;
use Bga\Games\IRevolveThereforeIAm\Material\TokenKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 仕様書 008 §12.1 の整合テスト（UT-01〜UT-10）。想い出カード 42・自我 13 の定義は I-09。目標 7 は I-10 なので、
 * 目標に依存する UT-05 の側と texts.js の UT-09 は別（JS 側）。
 */
final class EffectCatalogTest extends TestCase
{
    public function testEnumSizesMatchSpec(): void
    {
        // 008 §1.1: 原子操作 26・制御構造 4・イベント 8・修飾 11・条件 13、choice_kind 14
        self::assertCount(26, Op::cases());
        self::assertCount(4, Control::cases());
        self::assertCount(8, Event::cases());
        self::assertCount(11, Modifier::cases());
        self::assertCount(13, Condition::cases());
        self::assertCount(14, ChoiceKind::cases());
        self::assertCount(7, Origin::cases());
    }

    /** UT-01（NONE の 57 種の側）: 効果の種類が NONE のカードは、カタログに効果を持たない。 */
    public function testNoneCardsHaveNoEffects(): void
    {
        $none = array_filter(MemoryCards::all(), static fn ($c): bool => $c->effectKind === EffectKind::NONE);
        self::assertCount(57, $none);
        foreach ($none as $id => $card) {
            self::assertSame([], EffectCatalog::forCard($id), "{$id} must have no effect");
        }
    }

    /** UT-01（効果ありの 42 種の側）: 効果の種類が NONE でないカードは、同じ種類（kind）の効果を持つ。 */
    public function testEffectCardsHaveEffects(): void
    {
        $withEffect = array_filter(MemoryCards::all(), static fn ($c): bool => $c->effectKind !== EffectKind::NONE);
        self::assertCount(42, $withEffect);
        foreach ($withEffect as $id => $card) {
            $specs = EffectCatalog::forCard($id);
            self::assertCount(1, $specs, $id);
            self::assertSame("{$id}.1", $specs[0]->id);
            self::assertSame($card->effectKind, $specs[0]->kind, $id);
            self::assertSame($id, $specs[0]->source);
            self::assertSame(OwnerScope::OWNER, $specs[0]->ownerScope);
        }
    }

    /** UT-02: 99 種の想起効果の識別子が RC-01〜RC-13 に対応し、13 種全てに EffectSpec がある。 */
    public function testRecallEffectsAreCovered(): void
    {
        $ids = [];
        foreach (RecallEffect::cases() as $recall) {
            $ids[] = EffectCatalog::recallId($recall);
            self::assertSame(EffectCatalog::recallId($recall), EffectCatalog::recall($recall)->id);
        }
        self::assertSame(array_map(static fn (int $n): string => sprintf('RC-%02d', $n), range(1, 13)), $ids);
        foreach (MemoryCards::all() as $id => $card) {
            self::assertNotNull(EffectCatalog::find(EffectCatalog::recallId($card->recall)), "{$id} recall");
        }
        foreach (EffectCatalog::all() as $spec) {
            if (str_starts_with($spec->id, 'RC-')) {
                self::assertSame('', $spec->source);
                self::assertSame(Mechanism::ACTION, $spec->mechanism);
            }
        }
    }

    /** UT-03: 床 12 室に操作列があり、周遊ボーナスを持つのはレベル 2 の 4 室だけ（002 のボーナスと一致）。 */
    public function testFloorsAndTourBonuses(): void
    {
        self::assertCount(12, FloorCards::all());
        $expectedToken = [
            FloorBonus::ANALYSIS_TOKEN->value => TokenKind::ANALYSIS,
            FloorBonus::COMPOSE_TOKEN->value => TokenKind::COMPOSE,
            FloorBonus::WILD_MOVE_KEY->value => TokenKind::WILD_MOVE_KEY,
        ];
        $withBonus = [];
        foreach (FloorCards::all() as $id => $floor) {
            $action = EffectCatalog::floorAction($id);
            self::assertNotSame([], $action->ops, "{$id} has an action");
            self::assertSame($id, $action->source);

            $tour = EffectCatalog::tourBonus($id);
            if ($floor->bonus === FloorBonus::NONE) {
                self::assertNull($tour, "{$id} has no tour bonus");
                self::assertSame(1, $floor->level);
                continue;
            }
            $withBonus[] = $id;
            self::assertNotNull($tour);
            self::assertSame(2, $floor->level);
            $node = $tour->ops[0];
            if ($floor->bonus === FloorBonus::LIGHT) {
                self::assertSame(Op::GAIN_LIGHT, $node->atomicOp());
            } else {
                self::assertSame(Op::GAIN_TOKEN, $node->atomicOp());
                self::assertSame($expectedToken[$floor->bonus->value], $node->params['token']);
            }
        }
        self::assertSame(['F01', 'F04', 'F07', 'F10'], $withBonus);
    }

    /** UT-04: 精神カード 9 枚にボーナスがあり、WIN を持つのは R5 だけ。 */
    public function testSpiritBonuses(): void
    {
        self::assertCount(9, AnalysisCards::all());
        $winners = [];
        foreach (AnalysisCards::all() as $id => $card) {
            $spec = EffectCatalog::spiritBonus($id);
            self::assertNotSame([], $spec->ops);
            if (in_array(Op::WIN, $spec->allOps(), true)) {
                $winners[] = $id;
            }
        }
        self::assertSame(['R5'], $winners);
        self::assertSame(
            [Op::DRAW_DECK],
            EffectCatalog::spiritBonus('R1A')->allOps(),
        );
        self::assertSame([Op::GAIN_ENHANCED_EGO], EffectCatalog::spiritBonus('R3B')->allOps());
        self::assertSame(ComposeContext::BONUS_LV1, $this->firstParam('R2', 'ctx'));
        self::assertSame(ComposeContext::BONUS_ANY, $this->firstParam('R4', 'ctx'));
    }

    /** UT-05: 初期自我 8・強化自我 5 の効果が定義済み。効果の種類は強化自我が card.csv と一致。 */
    public function testEgoCardsAreDefined(): void
    {
        self::assertCount(13, EgoCards::all());
        foreach (EgoCards::all() as $id => $ego) {
            $specs = EffectCatalog::forCard($id);
            self::assertNotSame([], $specs, $id);
            foreach ($specs as $spec) {
                self::assertSame($id, $spec->source);
                self::assertStringStartsWith($id . '.', $spec->id);
                if ($ego->effectKind !== null) {
                    self::assertSame($ego->effectKind, $spec->kind, $spec->id);
                }
            }
        }
        // I04 だけ 2 つの効果（MD-10 の修飾と、止まったときの取得）
        self::assertSame(['I04.1', 'I04.2'], array_map(static fn (EffectSpec $s): string => $s->id, EffectCatalog::forCard('I04')));
        self::assertCount(14, array_filter(EffectCatalog::all(), static fn (EffectSpec $s): bool => preg_match('/^[IE]\d\d\./', $s->id) === 1));
    }

    /** UT-06: 全 EffectSpec が参照する OP / MD / CD / EV が既知の値（enum で型が守るので、木の中も確かめる）。 */
    public function testEverySpecReferencesKnownIds(): void
    {
        $opIds = array_map(static fn (Op $o): string => $o->value, Op::cases());
        foreach (EffectCatalog::all() as $spec) {
            foreach ($spec->steps() as $step) {
                $kind = $step->node->kind;
                if ($kind instanceof Op) {
                    self::assertContains($kind->value, $opIds, $spec->id);
                } else {
                    self::assertContains($kind, Control::cases(), $spec->id);
                }
            }
            foreach ($spec->events as $event) {
                self::assertInstanceOf(Event::class, $event);
            }
            foreach ($spec->conditions as $ref) {
                self::assertInstanceOf(Condition::class, $ref->condition);
            }
        }
        foreach (Op::cases() as $op) {
            self::assertMatchesRegularExpression('/^OP-\d{2}$/', $op->value);
        }
    }

    /** UT-07: TRIGGER の hook は EV のみ、MODIFIER の hook は MD のみ。touches の宣言が操作の和集合と一致。 */
    public function testHooksAndTouches(): void
    {
        foreach (EffectCatalog::all() as $spec) {
            match ($spec->mechanism) {
                Mechanism::TRIGGER => $this->assertTrue($spec->events !== [] && $spec->modifier === null, $spec->id),
                Mechanism::MODIFIER => $this->assertTrue($spec->events === [] && $spec->modifier !== null, $spec->id),
                default => $this->assertTrue($spec->events === [] && $spec->modifier === null, $spec->id),
            };
            $declared = array_map(static fn ($t): string => $t->toString(), $spec->touches());
            $union = array_map(static fn ($t): string => $t->toString(), EffectSpec::unionOfOps($spec->ops));
            self::assertSame($union, $declared, $spec->id);
        }
    }

    /** UT-07 の補足: 資源の記法の読み書きと、001 の例（F05 は O+ H+ L+）。 */
    public function testTouchesOfKnownSpecs(): void
    {
        $touches = static fn (string $id): array => array_map(
            static fn ($t): string => $t->toString(),
            EffectCatalog::get($id)->touches(),
        );
        self::assertSame(['D+', 'H+'], $touches('RC-01'));
        self::assertSame(['O+', 'H+', 'L+'], $touches('F05'));
        self::assertSame(['TDw', 'Hw', 'Bw', 'NDw'], $touches('F04'));
        self::assertSame(['T+'], $touches('F10.TOUR'));
    }

    /** UT-08: 効果 ID が一意（all() が重複で例外を投げる）。 */
    public function testEffectIdsAreUnique(): void
    {
        $ids = array_keys(EffectCatalog::all());
        self::assertSame($ids, array_values(array_unique($ids)));
        self::assertCount(13 + 12 + 4 + 9 + 42 + 14 + 7, $ids);
    }

    /** UT-08 の後半: M052〜M054・M055〜M057・M064〜M066 の定義（条件と操作と修飾）が同一。 */
    public function testInterchangeableCardsShareDefinitions(): void
    {
        foreach ([['M052', 'M053', 'M054'], ['M055', 'M056', 'M057'], ['M064', 'M065', 'M066']] as $group) {
            $first = EffectCatalog::get($group[0] . '.1');
            foreach (array_slice($group, 1) as $id) {
                $spec = EffectCatalog::get($id . '.1');
                self::assertEquals($first->ops, $spec->ops, $id);
                self::assertEquals($first->conditions, $spec->conditions, $id);
                self::assertSame($first->modifier, $spec->modifier, $id);
                self::assertEquals($first->params, $spec->params, $id);
                self::assertSame($first->events, $spec->events, $id);
                self::assertSame($first->limit, $spec->limit, $id);
            }
        }
        self::assertNotEquals(EffectCatalog::get('M052.1')->ops, EffectCatalog::get('M055.1')->ops);
    }

    /** UT-09: texts.js のキーとの一致。後続で有効化。 */
    public function testTextsKeysMatch(): void
    {
        self::markTestSkipped('UT-09: texts.js（009 §8）は後続のタスクで作る。JS の組み込みテストランナー側で有効化する');
    }

    /** UT-10: 想起効果・床・精神カードの origin と unit の付与が 7.3〜7.5 の表と一致。 */
    public function testOriginsAndUnits(): void
    {
        // 7.3: 想起効果の操作は全て origin = RECALL。発動者の 1 回の発動が手札上限の単位
        foreach (RecallEffect::cases() as $recall) {
            $spec = EffectCatalog::recall($recall);
            self::assertTrue($spec->unit, $spec->id);
            foreach ($this->nodes($spec) as $node) {
                if ($node->kind instanceof Op) {
                    self::assertSame(Origin::RECALL, $node->originParam(), $spec->id);
                }
            }
        }
        // 7.4: 床アクションの OP-01 / OP-02 / OP-03 は origin = FLOOR。床アクション全体が単位
        foreach (FloorCards::all() as $id => $floor) {
            $spec = EffectCatalog::floorAction($id);
            self::assertTrue($spec->unit, $id);
            foreach ($this->nodes($spec) as $node) {
                $op = $node->atomicOp();
                if ($op === Op::DRAW_DECK || $op === Op::GAIN_OPEN || $op === Op::GAIN_LIGHT) {
                    self::assertSame(Origin::FLOOR, $node->originParam(), $id);
                }
            }
            // 周遊ボーナスは origin = TOUR で、床の単位には含めない
            $tour = EffectCatalog::tourBonus($id);
            if ($tour !== null) {
                self::assertFalse($tour->unit);
                self::assertSame(Origin::TOUR, $tour->ops[0]->originParam());
            }
        }
        // 7.5: 精神カードの R1 は origin = BONUS。ボーナスは精神分析アクションの単位に含まれる
        foreach (['R1A', 'R1B', 'R1C'] as $id) {
            self::assertSame(Origin::BONUS, EffectCatalog::spiritBonus($id)->ops[0]->originParam());
        }
        foreach (array_keys(AnalysisCards::all()) as $id) {
            self::assertFalse(EffectCatalog::spiritBonus($id)->unit, $id);
        }
    }

    /** 008 §7.3・§7.4 の個別の定義（表の転記が正しいことの固定）。 */
    #[DataProvider('recallOps')]
    public function testRecallDefinitions(string $id, Op $op): void
    {
        self::assertSame($op, EffectCatalog::get($id)->allOps()[0]);
    }

    /**
     * @return array<string, array{string, Op}>
     */
    public static function recallOps(): array
    {
        return [
            'RC-01' => ['RC-01', Op::DRAW_DECK],
            'RC-02' => ['RC-02', Op::GAIN_OPEN],
            'RC-03' => ['RC-03', Op::GAIN_LIGHT],
            'RC-04' => ['RC-04', Op::ADD_NEXT_COMPOSE_DISCOUNT],
            'RC-05' => ['RC-05', Op::DISCARD_FOR_LIGHT],
            'RC-06' => ['RC-06', Op::SWAP_HAND_OPEN],
            'RC-07' => ['RC-07', Op::SWAP_COMPOSED_HAND],
            'RC-08' => ['RC-08', Op::RECORD_FLOOR_ALTERNATIVE],
            'RC-12' => ['RC-12', Op::MOVE_NEXT_ROOM],
            'RC-13' => ['RC-13', Op::MOVE_NEXT_ROOM],
        ];
    }

    public function testFlattenedStepsOfFloorTwelve(): void
    {
        // F12 = CHOOSE_ONE( OPTIONAL(COMPOSE), DRAW_DECK ) → 先行順: 0 CHOOSE_ONE, 1 OPTIONAL, 2 COMPOSE, 3 DRAW_DECK
        $steps = EffectCatalog::get('F12')->steps();
        self::assertCount(4, $steps);
        self::assertSame(Control::CHOOSE_ONE, $steps[0]->node->kind);
        self::assertSame(4, $steps[0]->end);
        self::assertSame(Control::OPTIONAL, $steps[1]->node->kind);
        self::assertSame(3, $steps[1]->end);
        self::assertSame(Op::COMPOSE, $steps[2]->node->kind);
        self::assertSame(Op::DRAW_DECK, $steps[3]->node->kind);
        self::assertSame(1, $steps[2]->node->intParam('discount'));
    }

    public function testUnknownEffectIdThrows(): void
    {
        $this->expectException(\OutOfBoundsException::class);
        EffectCatalog::get('M999.1');
    }

    private function firstParam(string $id, string $key): mixed
    {
        foreach ($this->nodes(EffectCatalog::get($id)) as $node) {
            if (isset($node->params[$key])) {
                return $node->params[$key];
            }
        }

        return null;
    }

    /**
     * @return list<EffectNode>
     */
    private function nodes(EffectSpec $spec): array
    {
        return array_map(static fn ($step): EffectNode => $step->node, $spec->steps());
    }
}
