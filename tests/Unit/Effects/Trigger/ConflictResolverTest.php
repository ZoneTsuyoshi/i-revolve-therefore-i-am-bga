<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Effects\Trigger;

use Bga\Games\IRevolveThereforeIAm\Effects\Trigger\ConflictResolver;
use Bga\Games\IRevolveThereforeIAm\Effects\Trigger\TriggeredEffect;
use Bga\Games\IRevolveThereforeIAm\Material\EffectKind;
use Bga\Games\IRevolveThereforeIAm\Material\EffectNode;
use Bga\Games\IRevolveThereforeIAm\Material\EffectSpec;
use Bga\Games\IRevolveThereforeIAm\Material\Event;
use Bga\Games\IRevolveThereforeIAm\Material\Mechanism;
use Bga\Games\IRevolveThereforeIAm\Material\Op;
use Bga\Games\IRevolveThereforeIAm\Material\OwnerScope;
use Bga\Games\IRevolveThereforeIAm\Material\ResourceTouch;
use PHPUnit\Framework\TestCase;

/**
 * 同時に誘発した効果の順序（008 §6.3、UT-73・UT-74）。触る資源が衝突する対があれば `ORDER_EFFECTS`、無ければ固定順。
 */
final class ConflictResolverTest extends TestCase
{
    private ConflictResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ConflictResolver();
    }

    /**
     * 触る資源（008 §6.3.3 の表）だけを宣言した、順序の検査用の効果。
     *
     * @param array<string, scalar|null> $payload
     */
    private static function effect(string $id, string $touches, ?Op $op = null, array $payload = []): TriggeredEffect
    {
        $spec = new EffectSpec(
            id: $id . '.1',
            source: $id,
            mechanism: Mechanism::TRIGGER,
            kind: EffectKind::ACTIVATED,
            ownerScope: OwnerScope::OWNER,
            ops: [EffectNode::op($op ?? Op::GAIN_LIGHT, ['n' => 1, 'tag' => $id])],
            events: [Event::COMPOSED],
            touchesOverride: ResourceTouch::parseList($touches),
        );

        return new TriggeredEffect($spec, $id, $payload);
    }

    /** 衝突の無い組は順序を聞かない（E03・E04・E05 / M052 と M055 / M079 と R1）。 */
    public function testNonConflictingEffectsKeepAFixedOrder(): void
    {
        self::assertFalse($this->resolver->needsOrder([
            self::effect('E03', 'D+ H+'),
            self::effect('E04', 'L+'),
            self::effect('E05', 'T+'),
        ]));
        self::assertFalse($this->resolver->needsOrder([self::effect('M052', 'RMw'), self::effect('M055', 'RFw')]));
        self::assertFalse($this->resolver->needsOrder([self::effect('M079', 'L+'), self::effect('R1A', 'D+ H+')]));
        self::assertFalse($this->resolver->needsOrder([self::effect('M073', 'D+ H+'), self::effect('M074', 'D+ H+')]));
        self::assertFalse($this->resolver->needsOrder([self::effect('M081', 'O+ H+'), self::effect('R1A', 'D+ H+')]), 'H+ どうしは交換可能');
        self::assertFalse($this->resolver->needsOrder([self::effect('M076', 'L+')]));
        self::assertFalse($this->resolver->needsOrder([]));
    }

    /** 衝突する組（引いたカードを入れ替えに使える E03 と I05、場札を取ってから自己構成する M081 と R2）。 */
    public function testConflictingEffectsNeedAnOrder(): void
    {
        self::assertTrue($this->resolver->needsOrder([self::effect('E03', 'D+ H+'), self::effect('I05', 'Hw RMw Pr')]));
        self::assertTrue($this->resolver->needsOrder([self::effect('M081', 'O+ H+'), self::effect('R2', 'Hr Bw')]));
        self::assertTrue($this->resolver->needsOrder([self::effect('M055', 'RFw'), self::effect('M072', 'Pw RMr RFr')]));
        self::assertTrue($this->resolver->needsOrder([self::effect('E04', 'L+'), self::effect('E03', 'D+ H+'), self::effect('I05', 'Hw RMw Pr')]));
    }

    /** 一方が `r` だけで、他方が `+` の組も衝突する。`r` どうしは衝突しない。 */
    public function testReadAndWriteConflictRules(): void
    {
        self::assertTrue($this->resolver->needsOrder([self::effect('A', 'Hr'), self::effect('B', 'H+')]));
        self::assertFalse($this->resolver->needsOrder([self::effect('A', 'Hr'), self::effect('B', 'Hr')]));
        self::assertTrue($this->resolver->needsOrder([self::effect('A', 'Lw'), self::effect('B', 'L+')]));
    }

    /** 同一の定義（操作列と条件が同じ）の効果は交換可能なので、衝突とみなさない（M052 / M053 / M054）。 */
    public function testIdenticalDefinitionsAreInterchangeable(): void
    {
        $a = self::effect('M052', 'RMw');
        $spec = new EffectSpec(
            id: 'M053.1',
            source: 'M053',
            mechanism: Mechanism::TRIGGER,
            kind: EffectKind::ACTIVATED,
            ownerScope: OwnerScope::OWNER,
            ops: $a->spec->ops,
            events: [Event::COMPOSED],
            touchesOverride: ResourceTouch::parseList('RMw'),
        );

        self::assertFalse($this->resolver->needsOrder([$a, new TriggeredEffect($spec, 'M053')]));
    }

    /** UT-74: 固定順は、自己構成したカード自身の効果が先、その後は効果 ID の昇順。 */
    public function testFixedOrderPutsTheComposedCardsOwnEffectFirstThenById(): void
    {
        $effects = [
            self::effect('M076', 'L+', null, ['card_type' => 'M088']),
            self::effect('M073', 'D+ H+', null, ['card_type' => 'M088']),
            self::effect('M088', 'L+', null, ['card_type' => 'M088']),
            self::effect('I01', 'D+ H+', null, ['card_type' => 'M088']),
        ];

        $ordered = array_map(static fn (TriggeredEffect $e): string => $e->spec->id, $this->resolver->fixedOrder($effects));

        self::assertSame(['M088.1', 'I01.1', 'M073.1', 'M076.1'], $ordered);
    }
}
