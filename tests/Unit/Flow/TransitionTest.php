<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Flow;

use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TransitionTest extends TestCase
{
    public function testResumeMapsToDeclaredTransition(): void
    {
        $declared = Transition::resumeDeclarations();
        foreach ([StateId::MOVE, StateId::RECALL_START, StateId::FLOOR_ACTION_START, StateId::CONSTRUCT, StateId::CLEANUP] as $resume) {
            $name = Transition::resumeTransition($resume);
            self::assertArrayHasKey($name, $declared);
            self::assertSame($resume, $declared[$name]);
        }
        self::assertCount(5, $declared);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function invalidResumes(): array
    {
        return [
            'unset' => [0],
            'reserved 1' => [1],
            'effect resolve itself' => [StateId::EFFECT_RESOLVE],
            'game end' => [StateId::GAME_END],
            'template state' => [10],
        ];
    }

    #[DataProvider('invalidResumes')]
    public function testInvalidResumeThrows(int $resume): void
    {
        $this->expectException(\LogicException::class);
        Transition::resumeTransition($resume);
    }
}
