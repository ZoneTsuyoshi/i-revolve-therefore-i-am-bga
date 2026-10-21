<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Effects\Trigger;

use Bga\Games\IRevolveThereforeIAm\Effects\Condition\ConditionEvaluator;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameEvent;
use Bga\Games\IRevolveThereforeIAm\Effects\Trigger\TriggerCollector;
use Bga\Games\IRevolveThereforeIAm\Material\Condition;
use Bga\Games\IRevolveThereforeIAm\Material\ConditionRef;
use Bga\Games\IRevolveThereforeIAm\Material\EffectKind;
use Bga\Games\IRevolveThereforeIAm\Material\EffectLimit;
use Bga\Games\IRevolveThereforeIAm\Material\EffectNode;
use Bga\Games\IRevolveThereforeIAm\Material\EffectSpec;
use Bga\Games\IRevolveThereforeIAm\Material\Event;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;
use Bga\Games\IRevolveThereforeIAm\Material\Mechanism;
use Bga\Games\IRevolveThereforeIAm\Material\Op;
use Bga\Games\IRevolveThereforeIAm\Material\OwnerScope;
use IRevolveTests\Support\ArrayCatalog;
use IRevolveTests\Support\ArrayGame;
use PHPUnit\Framework\TestCase;

/**
 * イベントに反応する TRIGGER の収集（008 §4.2、UT-35・UT-42・UT-43 の収集側）。
 */
final class TriggerCollectorTest extends TestCase
{
    private ArrayGame $game;

    protected function setUp(): void
    {
        $this->game = new ArrayGame();
    }

    /**
     * @param list<ConditionRef> $conditions
     */
    private static function trigger(
        string $source,
        Event $event,
        EffectKind $kind = EffectKind::ACTIVATED,
        array $conditions = [],
        EffectLimit $limit = EffectLimit::PER_EVENT,
    ): EffectSpec {
        return new EffectSpec(
            id: $source . '.1',
            source: $source,
            mechanism: Mechanism::TRIGGER,
            kind: $kind,
            ownerScope: OwnerScope::OWNER,
            ops: [EffectNode::op(Op::DRAW_DECK, ['n' => 1])],
            events: [$event],
            conditions: $conditions,
            limit: $limit,
        );
    }

    /**
     * @param list<EffectSpec> $specs
     * @return list<string> 誘発した効果 ID
     */
    private function collect(array $specs, GameEvent $event): array
    {
        $collector = new TriggerCollector($this->game, new ArrayCatalog($specs), new ConditionEvaluator($this->game));

        return array_map(static fn ($e): string => $e->spec->id, $collector->collect($event));
    }

    public function testOnlyTheTurnPlayersTableauAndEgosReactToTheirOwnEvents(): void
    {
        $this->game->giveTableau(1, ['M073']);
        $this->game->giveTableau(2, ['M074']);
        $this->game->giveEgos(1, ['I01']);
        $specs = [
            self::trigger('M073', Event::LIGHT_GAINED),
            self::trigger('M074', Event::LIGHT_GAINED),
            self::trigger('I01', Event::LIGHT_GAINED),
            self::trigger('M075', Event::LIGHT_GAINED), // 手札のカードの効果は働かない
        ];
        $this->game->giveHand(1, ['M075']);

        self::assertSame(['M073.1', 'I01.1'], $this->collect($specs, new GameEvent(Event::LIGHT_GAINED, 1, ['count' => 1])));
        // ターンプレイヤー以外のプレイヤーの操作が起こしたイベントには、誰の効果も反応しない（008 §4.1）
        self::assertSame([], $this->collect($specs, new GameEvent(Event::LIGHT_GAINED, 2, ['count' => 1])));
        // 別のイベントには反応しない
        self::assertSame([], $this->collect($specs, new GameEvent(Event::MOVE_ENDED, 1)));
    }

    /** UT-35: 自己構成したカード自身の起動効果は、自身の自己構成に反応しない（R8-3）。他のカードは反応する。 */
    public function testActivatedEffectsDoNotReactToTheirOwnComposition(): void
    {
        $this->game->giveTableau(1, ['M073', 'M074']);
        $specs = [self::trigger('M073', Event::COMPOSED), self::trigger('M074', Event::COMPOSED)];

        $result = $this->collect($specs, new GameEvent(Event::COMPOSED, 1, ['card_type' => 'M073', 'composed_count' => 2]));

        self::assertSame(['M074.1'], $result);
    }

    /** 即時効果は、自己構成したカード自身にだけ反応する。 */
    public function testInstantEffectsReactOnlyToTheirOwnComposition(): void
    {
        $this->game->giveTableau(1, ['M089', 'M090']);
        $specs = [
            self::trigger('M089', Event::COMPOSED, EffectKind::INSTANT),
            self::trigger('M090', Event::COMPOSED, EffectKind::INSTANT),
        ];

        self::assertSame(['M090.1'], $this->collect($specs, new GameEvent(Event::COMPOSED, 1, ['card_type' => 'M090'])));
    }

    /** UT-34: 条件を満たすものだけ。知恵鍵のカードの自己構成には知恵鍵に反応する効果が誘発する。 */
    public function testConditionsAreEvaluatedWithTheEventPayload(): void
    {
        $this->game->giveTableau(1, ['M073', 'M074']);
        $specs = [
            self::trigger('M073', Event::COMPOSED, conditions: [ConditionRef::of(Condition::COMPOSED_CARD_HAS_KEY, ['type' => KeyType::WISDOM])]),
            self::trigger('M074', Event::COMPOSED, conditions: [ConditionRef::of(Condition::COMPOSED_CARD_HAS_KEY, ['type' => KeyType::EMOTION])]),
        ];

        self::assertSame(['M073.1'], $this->collect($specs, new GameEvent(Event::COMPOSED, 1, ['card_type' => 'M001'])));
        self::assertSame(['M074.1'], $this->collect($specs, new GameEvent(Event::COMPOSED, 1, ['card_type' => 'M002'])));
        self::assertSame(['M073.1', 'M074.1'], $this->collect($specs, new GameEvent(Event::COMPOSED, 1, ['card_type' => 'M082'])));
    }

    /** 1 ターンに 1 回の効果は、使用済みなら誘発しない。 */
    public function testOncePerTurnEffectsAreSkippedOnceUsed(): void
    {
        $this->game->giveTableau(1, ['M069']);
        $specs = [self::trigger('M069', Event::MOVE_ENDED, limit: EffectLimit::ONCE_PER_TURN)];
        $event = new GameEvent(Event::MOVE_ENDED, 1);

        self::assertSame(['M069.1'], $this->collect($specs, $event));
        $this->game->markOnceUsed(1, 'M069.1');
        self::assertSame([], $this->collect($specs, $event));
    }
}
