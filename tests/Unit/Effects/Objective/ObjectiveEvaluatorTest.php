<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Effects\Objective;

use Bga\Games\IRevolveThereforeIAm\Effects\Objective\ObjectiveEvaluator;
use Bga\Games\IRevolveThereforeIAm\Effects\Objective\ObjectiveStatus;
use Bga\Games\IRevolveThereforeIAm\Effects\Objective\ObjectiveWindow;
use Bga\Games\IRevolveThereforeIAm\Material\AnalysisCards;
use Bga\Games\IRevolveThereforeIAm\Material\EffectCatalog;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;
use Bga\Games\IRevolveThereforeIAm\Material\Mechanism;
use Bga\Games\IRevolveThereforeIAm\Material\ObjectiveCards;
use Bga\Games\IRevolveThereforeIAm\Material\TokenKind;
use IRevolveTests\Support\ArrayCatalog;
use IRevolveTests\Support\ArrayGame;
use IRevolveTests\Support\CardTypes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 目標カード O001〜O007 の条件・X・得点表（008 §6.7、§7.7。UT-05 の目標側、UT-23、UT-67〜UT-70、UT-72）。
 */
final class ObjectiveEvaluatorTest extends TestCase
{
    private const P = 1;

    private ArrayGame $game;

    private ObjectiveEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->game = new ArrayGame();
        $this->evaluator = ObjectiveEvaluator::forView($this->game, ArrayCatalog::withTestSpecs());
    }

    private function eval(string $id, ?ObjectiveWindow $window = null): ObjectiveStatus
    {
        return $this->evaluator->evaluate($id, self::P, $window ?? ObjectiveWindow::none());
    }

    /** 同じ鍵の組を持つ、別々のカードを n 枚自己構成済みにする。 */
    private function compose(int $n, KeyType $key, int $level): void
    {
        $used = [];
        for ($i = 0; $i < $n; $i++) {
            $keys = $level === 1 ? [$key] : [$key, $key];
            $id = $this->cardOf($keys, $level, $used);
            $used[] = $id;
            $this->game->giveTableau(self::P, [$id]);
        }
    }

    /**
     * @param list<KeyType> $keys
     * @param list<string> $used
     */
    private function cardOf(array $keys, int $level, array $used): string
    {
        return CardTypes::id($keys, $level, $used);
    }

    /** UT-05（目標側）: 目標 7 が OBJECTIVE として定義済みで、得点表は 002 §10 の生成物と同じ。 */
    public function testObjectivesAreDefinedInTheCatalog(): void
    {
        self::assertCount(7, ObjectiveCards::all());
        foreach (ObjectiveCards::all() as $id => $def) {
            $specs = EffectCatalog::forCard($id);
            self::assertCount(1, $specs, $id);
            self::assertSame(Mechanism::OBJECTIVE, $specs[0]->mechanism);
            self::assertSame($id, $specs[0]->id);
            self::assertSame($def->scoreTable, $specs[0]->params['table']);
            self::assertSame([], $specs[0]->ops);
        }
    }

    /**
     * UT-67: 得点表の境界値（002 §10）。
     *
     * @return iterable<string, array{string, int, int}>
     */
    public static function pointTables(): iterable
    {
        foreach (['O001', 'O003', 'O005'] as $id) {
            foreach ([[0, 0], [1, 1], [2, 2], [3, 2], [4, 3], [5, 3], [6, 4], [9, 4]] as [$x, $points]) {
                yield "$id X=$x" => [$id, $x, $points];
            }
        }
        foreach (['O002', 'O006', 'O007'] as $id) {
            foreach ([[0, 0], [1, 1], [2, 2], [3, 3], [4, 4], [5, 4]] as [$x, $points]) {
                yield "$id X=$x" => [$id, $x, $points];
            }
        }
        foreach ([[0, 0], [1, 1], [2, 1], [3, 2], [4, 2], [5, 3], [6, 3], [7, 4], [8, 4]] as [$x, $points]) {
            yield "O004 X=$x" => ['O004', $x, $points];
        }
    }

    #[DataProvider('pointTables')]
    public function testPointTables(string $id, int $x, int $points): void
    {
        match ($id) {
            'O001' => $this->game->tokens[self::P][TokenKind::WILD_MOVE_KEY->value] = $x,
            'O002' => $this->game->giveTableau(self::P, CardTypes::level2($x)),
            'O003' => $this->compose($x, KeyType::WILL, 1),
            'O004' => $this->game->giveHand(self::P, array_fill(0, $x, 'M001')),
            'O005' => $this->game->lights[self::P] = $x,
            'O006' => $this->game->spirit[self::P] = $x === 0 ? null : $this->spiritOfTier(min($x, 5)),
            'O007' => $this->keySets($x),
        };
        if ($id === 'O003') {
            // 1 枚 1 本なので、x 枚で最多鍵 x 本
            self::assertSame($x, $this->eval($id)->x);
        }
        if ($id === 'O006') {
            self::assertSame(min($x, 5), $this->eval($id)->x);
        }

        $status = $this->eval($id);

        self::assertSame($points, $status->points, "$id X={$status->x}");
        if ($id !== 'O006') {
            self::assertSame($x, $status->x);
        }
    }

    private function spiritOfTier(int $tier): string
    {
        foreach (AnalysisCards::all() as $id => $card) {
            if ($card->floor === $tier) {
                return $id;
            }
        }
        throw new \LogicException('no spirit card');
    }

    /** セットの数 n: 知恵・意志・感情の鍵を n 本ずつ。 */
    private function keySets(int $n): void
    {
        foreach ([KeyType::WISDOM, KeyType::WILL, KeyType::EMOTION] as $key) {
            $this->compose($n, $key, 1);
        }
    }

    /** UT-67: O001。1 ターンに 6 部屋以上の入室（想起効果の移動も `roomsEnteredCount` に数えられる。FAQ 行 23）。 */
    public function testO001NeedsSixEntries(): void
    {
        $this->game->roomsEntered = 5;
        self::assertFalse($this->eval('O001')->conditionMet);
        $this->game->roomsEntered = 6;
        self::assertTrue($this->eval('O001')->conditionMet);
        // 想起効果の移動（入室に数える）も合計に入る
        $this->game->roomsEntered = 5;
        $this->game->movePersona(self::P, 3, true);
        self::assertTrue($this->eval('O001')->conditionMet);
    }

    /** UT-68: O001 の X は表向きと使用済みの万能移動鍵トークンの合計。 */
    public function testO001CountsUsedWildMoveKeys(): void
    {
        $this->game->tokens[self::P][TokenKind::WILD_MOVE_KEY->value] = 2;
        $this->game->wildUsed[self::P] = 2;

        $status = $this->eval('O001');

        self::assertSame(4, $status->x);
        self::assertSame(3, $status->points);
    }

    public function testO002Needs2Level2Cards(): void
    {
        $this->compose(1, KeyType::WISDOM, 2);
        $this->compose(3, KeyType::WILL, 1); // レベル 1 は数えない
        self::assertFalse($this->eval('O002')->conditionMet);
        self::assertSame(1, $this->eval('O002')->points);

        $this->compose(1, KeyType::EMOTION, 2);
        self::assertTrue($this->eval('O002')->conditionMet);
        self::assertSame(2, $this->eval('O002')->x);
    }

    /** O003: 最多鍵の本数が 4 以上。万能鍵は数えない（Q-68）。 */
    public function testO003MostKeysIgnoresWild(): void
    {
        $this->game->giveTableau(self::P, ['M094', 'M001', 'M002']); // 知恵 3、感情 1
        $this->compose(3, KeyType::WILD, 1); // 万能鍵 3 本は数えない
        $s = $this->eval('O003');
        self::assertSame(3, $s->x);
        self::assertFalse($s->conditionMet);

        $this->game->giveTableau(self::P, ['M004']);
        self::assertTrue($this->eval('O003')->conditionMet);
        self::assertSame(3, $this->eval('O003')->points);
    }

    /** UT-71: O004 は 81（ターン終了時の窓）でだけ条件を満たせる。 */
    public function testO004OnlyAtTurnEnd(): void
    {
        $this->game->giveHand(self::P, array_fill(0, 6, 'M001'));

        self::assertFalse($this->eval('O004')->conditionMet);
        self::assertFalse($this->eval('O004', ObjectiveWindow::move())->conditionMet);
        self::assertTrue($this->eval('O004', ObjectiveWindow::turnEnd())->conditionMet);
        self::assertSame(3, $this->eval('O004')->points, '得点は窓に依らない');

        $this->game->hand[self::P] = array_slice($this->game->hand[self::P], 0, 5);
        self::assertFalse($this->eval('O004', ObjectiveWindow::turnEnd())->conditionMet);
    }

    /** UT-68・UT-70: O005 は獲得数（減らない）で条件を、保有数（支払いで減る）で X を決める。 */
    public function testO005GainedVersusHeld(): void
    {
        $this->game->gainLight(self::P, 4, null);
        self::assertTrue($this->eval('O005')->conditionMet);
        self::assertSame(3, $this->eval('O005')->points);

        $this->game->lights[self::P] = 1; // 支払いで使った
        self::assertTrue($this->eval('O005')->conditionMet, '獲得数は減らない');
        self::assertSame(1, $this->eval('O005')->x);
        self::assertSame(1, $this->eval('O005')->points, 'X の数え直しで得点が下がる');

        $this->game->lightsGained = 3;
        self::assertFalse($this->eval('O005')->conditionMet);
    }

    /** UT-69: O006 は階層を上げた精神分析アクションが 2 回。X は精神階層。 */
    public function testO006CountsTierUpActions(): void
    {
        $this->game->analysisActions = 1;
        self::assertFalse($this->eval('O006')->conditionMet);
        $this->game->analysisActions = 2;
        self::assertTrue($this->eval('O006')->conditionMet);
        $this->game->spirit[self::P] = $this->spiritOfTier(3);
        self::assertSame(3, $this->eval('O006')->x);
        self::assertSame(3, $this->eval('O006')->points);
    }

    /** UT-72: O007 は揃っている間だけ。印刷された万能鍵があれば窓に依らず満たす。 */
    public function testO007WithPrintedWild(): void
    {
        $this->game->giveTableau(self::P, ['M001', 'M002', 'M003']);
        self::assertFalse($this->eval('O007')->conditionMet);

        [$wild] = $this->game->giveTableau(self::P, ['M040']);
        $status = $this->eval('O007');
        self::assertTrue($status->conditionMet);
        self::assertSame(1, $status->x, '万能鍵はセットの数に数えない（Q-68）');
        self::assertSame(1, $status->points);

        // 建築家（I03）などで万能鍵のカードを手放すと、条件は崩れる
        $this->game->tableau[self::P] = array_values(array_diff($this->game->tableau[self::P], [$wild]));
        self::assertFalse($this->eval('O007')->conditionMet);
    }

    /** UT-23: M096 は 31（MOVE）の中だけ、M094 は 62（ANALYSIS）の中だけ O007 の万能鍵になる（FAQ 行 29）。 */
    public function testO007UsesTheKeyViewOfTheWindow(): void
    {
        $this->game->giveTableau(self::P, ['M096', 'M001', 'M002']); // 意志 2、知恵 1、感情 1

        self::assertTrue($this->eval('O007', ObjectiveWindow::move())->conditionMet);
        self::assertFalse($this->eval('O007', ObjectiveWindow::none())->conditionMet);
        self::assertFalse($this->eval('O007', ObjectiveWindow::analysis())->conditionMet);
        self::assertFalse($this->eval('O007', ObjectiveWindow::turnEnd())->conditionMet);
        self::assertSame(1, $this->eval('O007', ObjectiveWindow::move())->x, '読み替えは X に含めない');

        $other = new ArrayGame();
        $other->giveTableau(self::P, ['M094', 'M003', 'M002']); // 知恵 2、意志 1、感情 1
        $evaluator = ObjectiveEvaluator::forView($other, ArrayCatalog::withTestSpecs());
        self::assertTrue($evaluator->evaluate('O007', self::P, ObjectiveWindow::analysis())->conditionMet);
        self::assertFalse($evaluator->evaluate('O007', self::P, ObjectiveWindow::move())->conditionMet);
        self::assertFalse($evaluator->evaluate('O007', self::P, ObjectiveWindow::none())->conditionMet);

        // 意志鍵が無ければ M094 の読み替えがあっても足りない
        $without = new ArrayGame();
        $without->giveTableau(self::P, ['M094', 'M002']);
        $evaluator = ObjectiveEvaluator::forView($without, ArrayCatalog::withTestSpecs());
        self::assertFalse($evaluator->evaluate('O007', self::P, ObjectiveWindow::analysis())->conditionMet);
    }

    public function testUnknownObjectiveIsRejected(): void
    {
        $this->expectException(\OutOfBoundsException::class);
        $this->eval('O099');
    }
}
