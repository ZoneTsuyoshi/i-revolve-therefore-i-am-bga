<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\EffectStack;
use Bga\Games\IRevolveThereforeIAm\Data\EgoRepository;
use Bga\Games\IRevolveThereforeIAm\Data\GlobalValues;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryCardRepository;
use Bga\Games\IRevolveThereforeIAm\Data\PlayerValues;
use Bga\Games\IRevolveThereforeIAm\Data\RoomRepository;
use Bga\Games\IRevolveThereforeIAm\Data\SpiritRepository;
use Bga\Games\IRevolveThereforeIAm\Data\StoredGameMutator;
use Bga\Games\IRevolveThereforeIAm\Effects\Catalog\SpecSource;
use Bga\Games\IRevolveThereforeIAm\Effects\Condition\ConditionEvaluator;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameView;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectEngine;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpRegistry;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\ActiveSpecs;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\AnalysisCostQuery;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\ComposeEnergyQuery;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\KeyViewQuery;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\LightScheduleQuery;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\RecallTimesQuery;
use Bga\Games\IRevolveThereforeIAm\Effects\Trigger\ConflictResolver;
use Bga\Games\IRevolveThereforeIAm\Effects\Trigger\TriggerCollector;

/**
 * 想起・床アクション・自己構成・精神分析・効果解決の部品の組み立て（70・71・40・50・51・60・61・62 とフリーアクションが共有する）。
 * 状態クラスは `Game::irTurn()` からこれを受け取り、各部品を呼ぶだけにする。1 回のリクエストの中では同じ `StoredGameMutator` を
 * 共有する（通知が溜まる順序を保つため）。単体テストも同じ組み立てを使い、SQLite 上の DB で状態機械の中身を通して動かす。
 */
final class TurnKit
{
    public readonly StoredGameMutator $mutator;

    public readonly EffectEngine $engine;

    public readonly TriggerPlanner $triggers;

    public readonly HandLimit $handLimit;

    public readonly EffectResolver $resolver;

    public readonly ChoiceFlow $choices;

    public readonly RecallPhase $recall;

    public readonly FloorActionPhase $floor;

    public readonly ConstructPhase $construct;

    public readonly ComposeAction $compose;

    public readonly AnalysisAction $analysis;

    public readonly FreeActionService $free;

    public readonly ComposePlanner $composePlanner;

    public readonly AnalysisPlanner $analysisPlanner;

    /**
     * @param \Closure(int): string $nameOf プレイヤー ID → プレイヤー名
     * @param \Closure(): void $victoryCheck 断片の結集などの勝利判定（I-08・I-10）。成立したら end_reason を書く
     */
    public function __construct(
        MemoryCardRepository $memory,
        RoomRepository $rooms,
        SpiritRepository $spirits,
        EgoRepository $egos,
        PlayerValues $players,
        GlobalValues $globals,
        EffectStack $stack,
        GameView $view,
        SpecSource $specs,
        \Closure $nameOf,
        \Closure $victoryCheck,
    ) {
        $conditions = new ConditionEvaluator($view);
        $active = new ActiveSpecs($view, $specs, $conditions);
        $energy = new ComposeEnergyQuery($active);
        $light = new LightScheduleQuery($active);
        $this->composePlanner = new ComposePlanner($view, $players, $energy, $light);
        $this->analysisPlanner = new AnalysisPlanner($view, $players, new AnalysisCostQuery($active), $light, new KeyViewQuery($active));

        $this->triggers = new TriggerPlanner(new TriggerCollector($view, $specs, $conditions), new ConflictResolver(), $view, $stack);
        $arrival = new SpiritArrival($spirits, $players, $globals, $stack, $specs, $this->triggers, $nameOf);
        $this->mutator = new StoredGameMutator($memory, $rooms, $spirits, $egos, $players, $globals, $stack, $arrival, $nameOf, $victoryCheck);
        $this->engine = new EffectEngine(
            $view,
            $this->mutator,
            $specs,
            new OpRegistry(),
            new ActionAvailabilityService($this->composePlanner, $this->analysisPlanner),
        );
        $runner = new FrameRunner($stack, $this->triggers);
        $this->handLimit = new HandLimit($memory, $players, $stack);
        $this->resolver = new EffectResolver($stack, $runner, $this->engine, $this->handLimit, $globals, $specs, $conditions, $this->mutator, $nameOf);
        $this->choices = new ChoiceFlow($stack, $runner, $this->engine, $view, $this->mutator, $specs, $this->triggers);
        $this->recall = new RecallPhase($memory, $players, $rooms, $globals, $stack, new RecallTimesQuery($active), $specs, $nameOf);
        $this->floor = new FloorActionPhase($rooms, $players, $globals, $stack, $this->engine, $nameOf);
        $this->construct = new ConstructPhase($players, $globals, $stack, $this->composePlanner, $this->analysisPlanner);
        $frames = new ActionFrames($stack, $runner, $this->engine, $globals);
        $this->compose = new ComposeAction(
            $this->composePlanner,
            $frames,
            $memory,
            $players,
            $globals,
            $this->mutator,
            $this->triggers,
            $view,
            $energy,
            $light,
            $nameOf,
        );
        $this->analysis = new AnalysisAction(
            $this->analysisPlanner,
            $frames,
            $memory,
            $players,
            $this->mutator,
            $arrival,
            $view,
            $light,
            $nameOf,
        );
        $this->free = new FreeActionService($memory, $players, $globals, $nameOf, $victoryCheck);
    }
}
