<?php

declare(strict_types=1);

namespace IRevolveTests\Support;

use Bga\Games\IRevolveThereforeIAm\Effects\Catalog\SpecSource;
use Bga\Games\IRevolveThereforeIAm\Effects\Condition\ConditionEvaluator;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\ActiveSpecs;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\AnalysisCostQuery;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\ComposeEnergyQuery;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\KeyViewQuery;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\LightScheduleQuery;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\MovePaymentQuery;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\RecallTimesQuery;

/**
 * 修飾の問い合わせ（`Effects/Query/`）をまとめて組み立てる、テスト用の道具。
 */
final class QueryKit
{
    public readonly ArrayGame $game;

    public readonly ConditionEvaluator $conditions;

    public readonly ActiveSpecs $active;

    public readonly ComposeEnergyQuery $compose;

    public readonly LightScheduleQuery $light;

    public readonly AnalysisCostQuery $analysis;

    public readonly KeyViewQuery $keyView;

    public readonly MovePaymentQuery $move;

    public readonly RecallTimesQuery $recall;

    public function __construct(?ArrayGame $game = null, ?SpecSource $specs = null)
    {
        $this->game = $game ?? new ArrayGame();
        $this->conditions = new ConditionEvaluator($this->game);
        $this->active = new ActiveSpecs($this->game, $specs ?? ArrayCatalog::withTestSpecs(), $this->conditions);
        $this->compose = new ComposeEnergyQuery($this->active);
        $this->light = new LightScheduleQuery($this->active);
        $this->analysis = new AnalysisCostQuery($this->active);
        $this->keyView = new KeyViewQuery($this->active);
        $this->move = new MovePaymentQuery($this->active, $this->keyView, $this->light);
        $this->recall = new RecallTimesQuery($this->active);
    }
}
