<?php

declare(strict_types=1);

namespace IRevolveTests\Support;

use Bga\Games\IRevolveThereforeIAm\Data\ObjectiveRepository;
use Bga\Games\IRevolveThereforeIAm\Effects\Objective\ObjectiveEvaluator;
use Bga\Games\IRevolveThereforeIAm\Flow\CleanupPhase;
use Bga\Games\IRevolveThereforeIAm\Flow\GameScoreCalculator;
use Bga\Games\IRevolveThereforeIAm\Flow\ObjectiveBoard;
use Bga\Games\IRevolveThereforeIAm\Flow\ScoreReporter;
use Bga\Games\IRevolveThereforeIAm\Flow\TurnKit;
use Bga\Games\IRevolveThereforeIAm\Flow\WinChecker;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCards;

/**
 * 終局のテスト用: `MoveFixture` のデータベースに、本番と同じ勝利判定（`WinChecker` + `ScoreReporter`。`Game::irCheckVictory` と同じ組み立て）を
 * つないだ `TurnKit` とクリーンアップを組み立てる。
 */
final class EndgameRig
{
    public readonly TurnKit $kit;

    public readonly WinChecker $win;

    public readonly ScoreReporter $reporter;

    public readonly CleanupPhase $cleanup;

    public readonly ObjectiveBoard $board;

    public function __construct(public readonly MoveFixture $fx)
    {
        $objectives = new ObjectiveRepository($fx->db);
        $evaluator = ObjectiveEvaluator::forView($fx->view, ArrayCatalog::withTestSpecs());
        $this->board = new ObjectiveBoard($objectives, $evaluator, $fx->globals);
        $calculator = new GameScoreCalculator($fx->memory, $objectives, $evaluator);
        $this->win = new WinChecker($calculator, $fx->players, $fx->spirits, $fx->globals, $fx->stack);
        $this->reporter = new ScoreReporter($calculator, static fn (): array => MoveFixture::SEATS);
        $this->reporter->prime();
        $this->kit = new TurnKit(
            $fx->memory,
            $fx->rooms,
            $fx->spirits,
            $fx->egos,
            $fx->players,
            $fx->globals,
            $fx->stack,
            $fx->view,
            ArrayCatalog::withTestSpecs(),
            static fn (int $id): string => "P{$id}",
            function (): void {
                $notice = $this->reporter->report();
                if ($notice !== null) {
                    $this->kit->mutator->add([$notice]);
                }
                $this->win->check();
            },
        );
        $this->cleanup = new CleanupPhase(
            $fx->memory,
            $fx->rooms,
            $fx->players,
            $fx->globals,
            $this->kit->mutator,
            static fn (int $id): string => "P{$id}",
            $this->board,
        );
    }

    /**
     * 印刷スコアの高い順に、合計が `$target` 以上になるまでの想い出カードの種類（M 番号）を返す。
     *
     * @param list<int> $exclude 使わない種類（別のプレイヤーに渡す分）
     * @return list<int>
     */
    public static function typesForScore(int $target, array $exclude = []): array
    {
        $byScore = [];
        foreach (MemoryCards::all() as $id => $def) {
            $byScore[(int) substr($id, 1)] = $def->score;
        }
        arsort($byScore);
        $types = [];
        $sum = 0;
        foreach ($byScore as $type => $score) {
            if (in_array($type, $exclude, true)) {
                continue;
            }
            if ($sum >= $target) {
                break;
            }
            $types[] = $type;
            $sum += $score;
        }

        return $types;
    }
}
