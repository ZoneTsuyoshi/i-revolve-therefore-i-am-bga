<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\IRevolveThereforeIAm\Flow\FreeActions;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Game;
use PHPUnit\Framework\TestCase;

/**
 * 状態機械の宣言の検査（006 §3）: 21 状態、ID、種類、遷移、アクション、zombie、進行度。
 * フレームワーク無しで状態クラスを読み込み（tests/Support/Framework/ のスタブ）、コンストラクタの宣言だけを見る。
 */
final class StateMachineTest extends TestCase
{
    /** 006 §3.2 の状態（ID => [クラス名, 種類]）。 */
    private const EXPECTED = [
        20 => ['SetupEgoDraftNext', StateType::GAME],
        21 => ['SetupEgoPick', StateType::ACTIVE_PLAYER],
        22 => ['SetupEgoReveal', StateType::GAME],
        23 => ['SetupMeepleNext', StateType::GAME],
        24 => ['SetupMeeplePlace', StateType::ACTIVE_PLAYER],
        25 => ['SetupFinish', StateType::GAME],
        30 => ['TurnStart', StateType::GAME],
        31 => ['Move', StateType::ACTIVE_PLAYER],
        40 => ['RecallStart', StateType::GAME],
        50 => ['FloorActionStart', StateType::GAME],
        51 => ['FloorActionChoose', StateType::ACTIVE_PLAYER],
        60 => ['Construct', StateType::ACTIVE_PLAYER],
        61 => ['Compose', StateType::ACTIVE_PLAYER],
        62 => ['Analysis', StateType::ACTIVE_PLAYER],
        70 => ['EffectResolve', StateType::GAME],
        71 => ['EffectChoice', StateType::ACTIVE_PLAYER],
        72 => ['HandLimitDiscard', StateType::ACTIVE_PLAYER],
        80 => ['Cleanup', StateType::GAME],
        81 => ['TurnEnd', StateType::ACTIVE_PLAYER],
        82 => ['NextTurn', StateType::GAME],
        95 => ['GameEnd', StateType::GAME],
    ];

    /** フリーアクションを使う状態（006 §6.2）。 */
    private const FREE_ACTION_STATES = [31, 51, 60, 61, 62, 71, 72, 81];

    /** @var array<int, GameState> */
    private static array $states = [];

    public static function setUpBeforeClass(): void
    {
        $game = (new \ReflectionClass(Game::class))->newInstanceWithoutConstructor();
        foreach (glob(dirname(__DIR__, 3) . '/src/modules/php/States/*.php') ?: [] as $file) {
            $class = 'Bga\\Games\\IRevolveThereforeIAm\\States\\' . basename($file, '.php');
            $state = new $class($game);
            self::assertInstanceOf(GameState::class, $state);
            self::$states[$state->id] = $state;
        }
        ksort(self::$states);
    }

    public function testStateSetMatchesTheSpec(): void
    {
        self::assertCount(21, self::$states);
        self::assertSame(array_keys(self::EXPECTED), array_keys(self::$states));
        foreach (self::EXPECTED as $id => [$class, $type]) {
            self::assertSame($class, (new \ReflectionClass(self::$states[$id]))->getShortName(), "state $id");
            self::assertSame($type, self::$states[$id]->type, "state $id");
        }
        $gameCount = count(array_filter(self::$states, static fn(GameState $s): bool => $s->type === StateType::GAME));
        self::assertSame(11, $gameCount);
        self::assertSame(10, count(self::$states) - $gameCount);
    }

    public function testIdsAvoidReservedAndTemplateIds(): void
    {
        foreach (array_keys(self::$states) as $id) {
            self::assertNotContains($id, [1, 99, 10, 90, 98]);
            self::assertGreaterThanOrEqual(2, $id);
            self::assertLessThanOrEqual(98, $id);
        }
    }

    public function testStateIdConstantsMatchTheStates(): void
    {
        $constants = array_values((new \ReflectionClass(StateId::class))->getConstants());
        sort($constants);
        self::assertSame(array_keys(self::EXPECTED), $constants);
    }

    public function testEveryTransitionTargetsADeclaredState(): void
    {
        foreach (self::$states as $id => $state) {
            foreach ($state->transitions ?? [] as $name => $target) {
                self::assertArrayHasKey($target, self::$states, "state $id transition $name");
            }
        }
    }

    public function testEveryStateIsReachableFromTheFirstState(): void
    {
        $seen = [20 => true];
        $queue = [20];
        while ($queue !== []) {
            $id = array_shift($queue);
            foreach (self::$states[$id]->transitions ?? [] as $target) {
                if (!isset($seen[$target])) {
                    $seen[$target] = true;
                    $queue[] = $target;
                }
            }
        }
        self::assertSame(array_keys(self::EXPECTED), array_keys($seen) === [] ? [] : $this->sorted(array_keys($seen)));
    }

    /**
     * 骨格の経路（Express Start で手番が回り続ける）: 20 → 22 → 23 → 25 → 30 → 31 → 70 → 40 → 70 → 50 → 70 → 60 → 70 → 80 → 82 → 30。
     */
    public function testSkeletonLoopFollowsDeclaredTransitions(): void
    {
        $path = [
            [20, Transition::REVEAL, 22],
            [22, Transition::DONE, 23],
            [23, Transition::DONE, 25],
            [25, Transition::START, 30],
            [30, Transition::MOVE, 31],
            [31, Transition::MOVED, 70],
            [70, Transition::RESUME_RECALL, 40],
            [40, Transition::RESOLVE, 70],
            [70, Transition::RESUME_FLOOR, 50],
            [50, Transition::EXECUTE, 70],
            [70, Transition::RESUME_CONSTRUCT, 60],
            [60, Transition::END, 70],
            [70, Transition::RESUME_CLEANUP, 80],
            [80, Transition::SKIP, 82],
            [82, Transition::NEXT, 30],
        ];
        foreach ($path as [$from, $name, $to]) {
            self::assertSame($to, self::$states[$from]->transitions[$name] ?? null, "$from --$name--> $to");
        }
    }

    public function testEffectResolveDeclaresEveryResumeTarget(): void
    {
        $transitions = self::$states[70]->transitions ?? [];
        foreach (Transition::resumeDeclarations() as $name => $target) {
            self::assertSame($target, $transitions[$name] ?? null, $name);
        }
        self::assertSame(95, $transitions[Transition::GAME_END] ?? null);
    }

    public function testGameEndIsReachableFromTheStatesThatCanEndTheGame(): void
    {
        foreach ([31, 60, 61, 62, 70, 71, 72, 80, 81] as $id) {
            self::assertSame(95, self::$states[$id]->transitions[Transition::GAME_END] ?? null, "state $id");
        }
    }

    public function testActivePlayerStatesHaveDescriptionsAndZombie(): void
    {
        foreach (self::$states as $id => $state) {
            $reflection = new \ReflectionClass($state);
            if ($state->type === StateType::ACTIVE_PLAYER) {
                self::assertNotSame('', $state->descriptionMyTurn, "state $id");
                self::assertNotSame('', $state->description, "state $id");
                self::assertTrue($reflection->hasMethod('zombie'), "state $id needs zombie");
                $zombie = $reflection->getMethod('zombie');
                self::assertSame(['playerId'], array_map(static fn(\ReflectionParameter $p): string => $p->getName(), $zombie->getParameters()));
            } else {
                self::assertFalse($reflection->hasMethod('zombie'), "GAME state $id must not have zombie");
                self::assertFalse($reflection->hasMethod('getArgs'), "GAME state $id must not have getArgs");
            }
        }
    }

    public function testPossibleActionsFollowNamingAndSignatureRules(): void
    {
        foreach (self::$states as $id => $state) {
            foreach ((new \ReflectionClass($state))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $isAction = $method->getAttributes(PossibleAction::class) !== [];
                if (str_starts_with($method->getName(), 'act')) {
                    self::assertTrue($isAction, "state $id {$method->getName()} needs #[PossibleAction]");
                }
                if ($isAction) {
                    self::assertTrue(str_starts_with($method->getName(), 'act'), "state $id {$method->getName()}");
                    $names = array_map(static fn(\ReflectionParameter $p): string => $p->getName(), $method->getParameters());
                    self::assertContains('activePlayerId', $names, "state $id {$method->getName()}");
                    // act は遷移名か void を返す。戻り値の型は宣言する
                    self::assertNotNull($method->getReturnType(), "state $id {$method->getName()}");
                }
            }
        }
    }

    public function testFreeActionsAreOnlyInTheStatesOfSection62(): void
    {
        $withTrait = [];
        foreach (self::$states as $id => $state) {
            if (in_array(FreeActions::class, class_uses($state) ?: [], true)) {
                $withTrait[] = $id;
            }
        }
        self::assertSame(self::FREE_ACTION_STATES, $withTrait);
    }

    public function testExactlyOneStateUpdatesProgress(): void
    {
        $ids = array_keys(array_filter(self::$states, static fn(GameState $s): bool => $s->updateGameProgression));
        self::assertSame([82], array_values($ids));
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private function sorted(array $ids): array
    {
        sort($ids);

        return $ids;
    }
}
