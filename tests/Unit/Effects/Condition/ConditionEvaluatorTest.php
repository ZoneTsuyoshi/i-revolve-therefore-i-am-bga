<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Effects\Condition;

use Bga\Games\IRevolveThereforeIAm\Effects\Condition\ConditionContext;
use Bga\Games\IRevolveThereforeIAm\Effects\Condition\ConditionEvaluator;
use Bga\Games\IRevolveThereforeIAm\Material\Condition;
use Bga\Games\IRevolveThereforeIAm\Material\ConditionRef;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCards;
use Bga\Games\IRevolveThereforeIAm\Material\Origin;
use IRevolveTests\Support\ArrayGame;
use PHPUnit\Framework\TestCase;

/**
 * 条件 CD-01〜CD-13（008 §5.2）の評価。
 */
final class ConditionEvaluatorTest extends TestCase
{
    private ArrayGame $game;

    private ConditionEvaluator $conditions;

    protected function setUp(): void
    {
        $this->game = new ArrayGame();
        $this->conditions = new ConditionEvaluator($this->game);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $payload
     */
    private function check(Condition $condition, array $params = [], array $payload = [], ?string $card = null): bool
    {
        return $this->conditions->evaluate(
            ConditionRef::of($condition, $params),
            new ConditionContext(1, $card === null ? null : MemoryCards::get($card), $payload),
        );
    }

    public function testRoomsEntered(): void
    {
        $this->game->roomsEntered = 3;
        self::assertFalse($this->check(Condition::ROOMS_ENTERED_GE, ['n' => 4]));
        $this->game->roomsEntered = 4;
        self::assertTrue($this->check(Condition::ROOMS_ENTERED_GE, ['n' => 4]));
        $this->game->roomsEntered = 8; // 8 部屋でも真（回数の制限は limit が担う）
        self::assertTrue($this->check(Condition::ROOMS_ENTERED_GE, ['n' => 4]));
    }

    public function testKeyKindsPaidAndWildTokens(): void
    {
        $this->game->keyKindsPaid = [KeyType::WISDOM, KeyType::WILL];
        self::assertFalse($this->check(Condition::ALL_KEY_KINDS_PAID));
        $this->game->keyKindsPaid = [KeyType::WISDOM, KeyType::WILL, KeyType::EMOTION];
        self::assertTrue($this->check(Condition::ALL_KEY_KINDS_PAID));

        self::assertTrue($this->check(Condition::NO_WILD_TOKEN_USED));
        $this->game->wildTokensUsed = 1;
        self::assertFalse($this->check(Condition::NO_WILD_TOKEN_USED));
    }

    public function testOtherPersonaInRoom(): void
    {
        $this->game->persona = [1 => 4, 2 => 4];
        self::assertTrue($this->check(Condition::OTHER_PERSONA_IN_ROOM, [], ['room' => 4]));
        self::assertFalse($this->check(Condition::OTHER_PERSONA_IN_ROOM, [], ['room' => 5]));
        // 部屋を渡さなければ、想起フェイズ開始時の部屋
        self::assertFalse($this->check(Condition::OTHER_PERSONA_IN_ROOM));
        $this->game->recallRoom = 4;
        self::assertTrue($this->check(Condition::OTHER_PERSONA_IN_ROOM));
        $this->game->persona = [1 => 4, 2 => 7];
        self::assertFalse($this->check(Condition::OTHER_PERSONA_IN_ROOM));
    }

    public function testFirstLevelTwoEntry(): void
    {
        self::assertTrue($this->check(Condition::FIRST_LV2_ENTRY_THIS_TURN, [], ['entering_level' => 2]));
        self::assertFalse($this->check(Condition::FIRST_LV2_ENTRY_THIS_TURN, [], ['entering_level' => 1]));
        $this->game->lv2Entries = 1;
        self::assertFalse($this->check(Condition::FIRST_LV2_ENTRY_THIS_TURN, [], ['entering_level' => 2]));
    }

    public function testComposedCardConditions(): void
    {
        $emotion = ['type' => KeyType::EMOTION];
        self::assertTrue($this->check(Condition::COMPOSED_CARD_HAS_KEY, $emotion, [], 'M002'));
        self::assertFalse($this->check(Condition::COMPOSED_CARD_HAS_KEY, $emotion, [], 'M003'));
        self::assertTrue($this->check(Condition::COMPOSED_CARD_HAS_KEY, $emotion, [], 'M082')); // 知恵 + 感情
        self::assertFalse($this->check(Condition::COMPOSED_CARD_HAS_KEY, $emotion)); // カードが無い

        self::assertTrue($this->check(Condition::COMPOSED_CARD_LEVEL, ['level' => 2], [], 'M085'));
        self::assertFalse($this->check(Condition::COMPOSED_CARD_LEVEL, ['level' => 2], [], 'M001'));
    }

    public function testEventPayloadConditions(): void
    {
        $set = ['set' => [1, 3, 5, 7, 9]];
        self::assertTrue($this->check(Condition::COMPOSED_COUNT_IN, $set, ['composed_count' => 5]));
        self::assertFalse($this->check(Condition::COMPOSED_COUNT_IN, $set, ['composed_count' => 6]));

        self::assertTrue($this->check(Condition::TIER_UP_VIA, ['via' => 'ACTION'], ['via' => 'ACTION']));
        self::assertFalse($this->check(Condition::TIER_UP_VIA, ['via' => 'ACTION'], ['via' => 'EFFECT']));

        self::assertTrue($this->check(Condition::DEST_HAD_NO_LIGHT, [], ['dest_had_light' => false]));
        self::assertFalse($this->check(Condition::DEST_HAD_NO_LIGHT, [], ['dest_had_light' => true]));
        self::assertFalse($this->check(Condition::DEST_HAD_NO_LIGHT));

        $spirit = ['origin' => Origin::SPIRIT_CARD];
        self::assertTrue($this->check(Condition::LIGHT_ORIGIN, $spirit, ['origin' => 'SPIRIT_CARD']));
        self::assertTrue($this->check(Condition::LIGHT_ORIGIN, $spirit, ['origin' => Origin::SPIRIT_CARD]));
        self::assertFalse($this->check(Condition::LIGHT_ORIGIN, $spirit, ['origin' => 'EFFECT']));

        self::assertTrue($this->check(Condition::FLOOR_ELEMENT, ['type' => 'LIGHT'], ['element' => 'LIGHT']));
        self::assertFalse($this->check(Condition::FLOOR_ELEMENT, ['type' => 'LIGHT'], ['element' => 'DRAW']));
    }

    public function testFirstComposeActionOfTheTurn(): void
    {
        self::assertTrue($this->check(Condition::FIRST_COMPOSE_ACTION_THIS_TURN));
        $this->game->composeActions = 1;
        self::assertFalse($this->check(Condition::FIRST_COMPOSE_ACTION_THIS_TURN));
    }

    public function testAllIsAnAnd(): void
    {
        $this->game->roomsEntered = 4;
        $context = new ConditionContext(1);
        $four = ConditionRef::of(Condition::ROOMS_ENTERED_GE, ['n' => 4]);
        $first = ConditionRef::of(Condition::FIRST_COMPOSE_ACTION_THIS_TURN);

        self::assertTrue($this->conditions->all([], $context));
        self::assertTrue($this->conditions->all([$four, $first], $context));
        $this->game->composeActions = 1;
        self::assertFalse($this->conditions->all([$four, $first], $context));
    }
}
