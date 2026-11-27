<?php

declare(strict_types=1);

/**
 * プレビュー用の固定データ（scripts/preview/fixtures/*.json）を、本物のサーバーのコードから生成する。
 * 実行: npm run preview:fixtures（Docker の ir-test-php で PHP 8.4 を動かす。中身は bash scripts/preview/dump-fixtures.sh）
 * 場面は SQLite 上の本物のセットアップをリポジトリ経由で書き換えて作り、getAllDatas と各状態の getArgs と同じ関数を呼んで出力する。
 */

use Bga\Games\IRevolveThereforeIAm\Data\EgoRepository;
use Bga\Games\IRevolveThereforeIAm\Data\GamePhase;
use Bga\Games\IRevolveThereforeIAm\Data\PublicView;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Setup\InitialEgoDraft;
use Bga\Games\IRevolveThereforeIAm\Setup\PersonaPlacement;
use IRevolveTests\Support\PreviewRig;

require dirname(__DIR__, 2) . '/tests/bootstrap.php';

$outDir = $argv[1] ?? __DIR__ . '/fixtures';
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

/**
 * @param array<string, mixed> $data
 */
function writeJson(string $dir, string $name, array $data): void
{
    file_put_contents(
        "$dir/$name.json",
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
    );
}

/**
 * @param array<string, mixed> $args
 * @param list<array<string, mixed>> $steps
 * @return array<string, mixed>
 */
function scene(PreviewRig $rig, int $me, string $state, array $args, bool $active = true, ?int $actplayer = null, array $steps = []): array
{
    $gamedatas = $rig->gamedatas($me);
    unset($gamedatas['catalog']); // 全場面で同じなので catalog.json に分ける（preview.js が足す）
    foreach ($gamedatas['playerData'] as $id => $row) {
        $gamedatas['players'][$id]['score'] = $row['score'];
    }
    $preview = ['me' => $me, 'state' => ['name' => $state, 'active' => $active, 'args' => $args]];
    if ($actplayer !== null) {
        $preview['state']['actplayer'] = $actplayer;
    }
    if ($steps !== []) {
        $preview['steps'] = $steps;
    }

    return ['preview' => $preview, 'gamedatas' => $gamedatas];
}

/**
 * 序盤から中盤の共通の準備: 初期自我・人格・トークンを整える。
 *
 * @param array<int, int> $personas プレイヤー ID => 位置
 */
function seat(PreviewRig $rig, array $personas, bool $withEgos = true): void
{
    $egos = [11 => 'I07', 22 => 'I02', 33 => 'I05', 44 => 'I03'];
    foreach ($personas as $id => $pos) {
        $rig->players->setPersona($id, $pos);
        if ($withEgos) {
            $rig->egos->move($egos[$id], EgoRepository::OWNED, $id);
        }
    }
}

// カタログ（全場面で共通。007 §8.2）
writeJson($outDir, 'catalog', PublicView::catalog());

// 1. 2p-setup: 初期自我のドラフト（SetupEgoPick）
$rig = new PreviewRig(2, 3);
$draft = new InitialEgoDraft($rig->egos, $rig->globals, static fn(int $min, int $max): int => $min);
$picker = $draft->nextPicker($rig->seats) ?? 11;
writeJson($outDir, '2p-setup', scene($rig, $picker, 'SetupEgoPick', [
    'pickerId' => $picker,
    'packSize' => count($draft->candidates()),
    '_private' => [$picker => ['candidates' => $draft->candidates()]],
], true));

// 1b. 2p-setup-place: 人格の配置（SetupMeeplePlace）
$rig = new PreviewRig(2, 3);
$rig->egos->move('I07', EgoRepository::OWNED, 11);
$rig->egos->move('I02', EgoRepository::OWNED, 22);
$rig->globals->setPhase(GamePhase::SetupPersona);
$rig->globals->setSetupStep(2);
$placement = new PersonaPlacement($rig->players, $rig->rooms, $rig->globals);
$placer = $placement->nextPlacer($rig->seats) ?? 22;
writeJson($outDir, '2p-setup-place', scene($rig, $placer, 'SetupMeeplePlace', [
    'freeCorners' => $placement->freeCorners($rig->seats),
], true));

// 2. 4p-early: 序盤の移動の開始（手札 5 枚、経路なし）
$rig = new PreviewRig(4, 5);
seat($rig, [11 => 1, 22 => 4, 33 => 7, 44 => 10]);
$rig->startTurn(11, 1, GamePhase::Move);
$rig->handSize(11, 5);
$moveArgs = static fn(PreviewRig $r, int $p): array => (static function (array $d) use ($p): array {
    return ['turnPlayer' => $p, ...$d['public'], '_private' => [$p => ['hand' => $d['hand']]]];
})($r->move->planningData($p));
writeJson($outDir, '4p-early', scene($rig, 11, 'Move', $moveArgs($rig, 11)));

// 3. 4p-move-plan: 中盤の移動の計画（経路 3 つ、支払いを選択中）
$rig = new PreviewRig(4, 6);
seat($rig, [11 => 1, 22 => 4, 33 => 7, 44 => 10]);
$rig->startTurn(11, 6, GamePhase::Move);
$rig->handSize(11, 6);
$rig->compose(11, 3, 1, [0]);
$rig->compose(22, 2, 0);
$rig->compose(33, 3, 1);
$rig->compose(44, 2, 0);
$rig->spirit(11, 1);
$rig->players->setToken(11, 'player_light', 3);
$rig->players->setToken(11, 'player_wild_up', 1);
writeJson($outDir, '4p-move-plan', scene($rig, 11, 'Move', $moveArgs($rig, 11), true, null, [
    ['click' => '.ir-room[data-pos="2"]'],
    ['click' => '.ir-room[data-pos="3"]'],
    ['click' => '.ir-room[data-pos="4"]'],
]));

// 4. 4p-recall: 想起中（OPEN_DRAW_1 の場札の選択 = EffectChoice）
$rig = new PreviewRig(4, 7);
seat($rig, [11 => 2, 22 => 2, 33 => 7, 44 => 10]);
$rig->startTurn(11, 4, GamePhase::Recall);
$rig->handSize(11, 5);
$rig->compose(11, 2, 0);
$rig->roomCard(2, 15);
$rig->kit->recall->start();
$result = $rig->kit->resolver->resolve();
if ($result->transition !== Transition::CHOICE) {
    throw new LogicException('4p-recall: 選択に入りませんでした: ' . $result->transition);
}
$choice = $rig->kit->choices->args();
writeJson($outDir, '4p-recall', scene($rig, $result->activePlayer, 'EffectChoice', [
    'turnPlayer' => 11,
    ...$choice['public'],
    '_private' => [$result->activePlayer => $choice['private']],
]));

// 5. 4p-construct: 自己構成フェイズ（トークンあり）
$rig = new PreviewRig(4, 8);
seat($rig, [11 => 5, 22 => 4, 33 => 8, 44 => 10]);
$rig->startTurn(11, 7, GamePhase::Construct);
$rig->handSize(11, 6);
$rig->compose(11, 4, 1, [0, 1]);
$rig->compose(22, 3, 0);
$rig->compose(33, 4, 1);
$rig->compose(44, 3, 1);
$rig->players->setToken(11, 'player_light', 2);
$rig->players->setToken(11, 'player_compose_tok', 1);
$rig->players->setToken(11, 'player_analysis_tok', 1);
writeJson($outDir, '4p-construct', scene($rig, 11, 'Construct', $rig->kit->construct->args(11)));

// 6. 4p-compose-pay: 自己構成の支払い中
$rig2 = new PreviewRig(4, 8);
seat($rig2, [11 => 5, 22 => 4, 33 => 8, 44 => 10]);
$rig2->startTurn(11, 7, GamePhase::Construct);
$rig2->handSize(11, 6);
$rig2->compose(11, 4, 1, [0, 1]);
$rig2->compose(22, 3, 0);
$rig2->compose(33, 4, 1);
$rig2->compose(44, 3, 1);
$rig2->players->setToken(11, 'player_light', 3);
$rig2->kit->construct->startPhaseCompose(11);
$compose = $rig2->kit->compose->args(11);
writeJson($outDir, '4p-compose-pay', scene($rig2, 11, 'Compose', [
    'turnPlayer' => 11,
    ...$compose['public'],
    '_private' => [11 => $compose['private']],
], true, null, [
    // 自己構成するカードを選び（ir-subject になる）、捨てる手札を 1 枚選ぶ（ir-picked。残りの選べる手札は ir-can）
    ['click' => '.ir-hand .ir-card.ir-can'],
    ['click' => '.ir-hand .ir-card.ir-can'],
]));

// 7. 4p-analysis: 精神分析（第一階層の 2 択）
$rig = new PreviewRig(4, 9);
seat($rig, [11 => 5, 22 => 4, 33 => 8, 44 => 10]);
$rig->startTurn(11, 5, GamePhase::Construct);
$rig->handSize(11, 6);
$rig->compose(11, 5, 1, [0]);
$rig->compose(22, 2, 0);
$rig->compose(33, 2, 0);
$rig->compose(44, 2, 0);
$rig->players->setToken(11, 'player_light', 2);
$rig->players->setToken(11, 'player_analysis_tok', 1);
$rig->kit->construct->useAnalysisToken(11);
$analysis = $rig->kit->analysis->args(11);
writeJson($outDir, '4p-analysis', scene($rig, 11, 'Analysis', [
    'turnPlayer' => 11,
    ...$analysis['public'],
    '_private' => [11 => $analysis['private']],
]));

// 8. 4p-late: 終盤（手札 8 枚、自己構成済み 11 枚、強化自我、目標を獲得済み）
$late = static function (int $hand): PreviewRig {
    $rig = new PreviewRig(4, 10);
    seat($rig, [11 => 4, 22 => 7, 33 => 10, 44 => 1]);
    $rig->startTurn(11, 15, GamePhase::Construct);
    $rig->compose(11, 8, 3, [0, 2, 3, 6]);
    $rig->compose(22, 6, 2, [1]);
    $rig->compose(33, 4, 1);
    $rig->compose(44, 7, 3, [0, 1, 2]);
    $rig->handSize(11, $hand);
    $rig->handSize(22, 6);
    $rig->handSize(33, 7);
    $rig->handSize(44, 4);
    $rig->spirit(11, 3);
    $rig->spirit(22, 2, 1);
    $rig->spirit(33, 1);
    $rig->spirit(44, 4, 1);
    foreach ([11, 44] as $player) {
        $rig->egos->obtainEnhancement($rig->egos->inLoc(EgoRepository::ENH_DECK)[0]['id'], $player);
    }
    $rig->players->setToken(11, 'player_light', 4);
    $rig->players->setToken(11, 'player_wild_up', 1);
    $rig->players->setToken(11, 'player_wild_used', 1);
    $rig->players->setToken(11, 'player_compose_tok', 1);
    $rig->players->setToken(11, 'player_analysis_tok', 1);
    $rig->objectives->claim(7, 11, 12);
    $rig->objectives->claim(3, 22, 13);
    $rig->discard(1, 14);
    $rig->discard(2, 3);

    return $rig;
};
$rig = $late(8);
writeJson($outDir, '4p-late', scene($rig, 11, 'Construct', $rig->kit->construct->args(11)));

// 8b. 4p-late-hand9: 手札が上限（8 枚）を超えた状態（HandLimitDiscard）
$rig = $late(9);
$rig->globals->setPhase(GamePhase::Cleanup);
writeJson($outDir, '4p-late-hand9', scene($rig, 11, 'HandLimitDiscard', [
    'turnPlayer' => 11,
    'targetPlayerId' => 11,
    'limit' => 8,
    'excess' => 1,
]));

// 9. 3p-mid: 3 人の中盤。自分は手番ではない（他の人の自己構成フェイズを待つ）
$rig = new PreviewRig(3, 11);
seat($rig, [11 => 4, 22 => 8, 33 => 10]);
$rig->startTurn(22, 9, GamePhase::Construct);
$rig->compose(11, 4, 1, [0]);
$rig->compose(22, 5, 1, [1, 2]);
$rig->compose(33, 4, 0);
$rig->handSize(11, 6);
$rig->handSize(22, 5);
$rig->handSize(33, 7);
$rig->spirit(11, 1);
$rig->spirit(22, 2, 1);
$rig->players->setToken(22, 'player_compose_tok', 1);
writeJson($outDir, '3p-mid', scene($rig, 11, 'Construct', $rig->kit->construct->args(22), false, 22));

// 10. 4p-end: ゲーム終了（断片の結集）
$rig = $late(5);
$rig->globals->setPhase(GamePhase::End);
$rig->globals->setEndReason('FRAGMENTS');
$rig->globals->setWinnerId(11);
writeJson($outDir, '4p-end', scene($rig, 11, 'GameEnd', []));

echo "fixtures written to $outDir\n";
