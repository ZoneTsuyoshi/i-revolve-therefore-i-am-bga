<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\States;

use Bga\GameFramework\Actions\Types\JsonParam;
use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Effects\Objective\ObjectiveWindow;
use Bga\Games\IRevolveThereforeIAm\Flow\FreeActions;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Game;

/**
 * 62: 精神分析アクションの実行（006 §5.4）。検証と確定は Flow/AnalysisAction（支払いの検証は Flow/AnalysisPlanner）。
 */
class Analysis extends GameState
{
    use FreeActions;

    protected function irObjectiveWindow(): ObjectiveWindow
    {
        return ObjectiveWindow::analysis();
    }

    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::ANALYSIS,
            type: StateType::ACTIVE_PLAYER,
            description: clienttranslate('${actplayer} must perform an analysis'),
            descriptionMyTurn: clienttranslate('${you} must perform an analysis'),
            transitions: [
                Transition::DONE => StateId::EFFECT_RESOLVE,
                Transition::GAME_END => StateId::GAME_END,
            ],
        );
    }

    /**
     * 公開部分は文脈（`context`: FLOOR / TOKEN）、割引、進む先ごとのコスト（`destinations`）、軽減できる鍵の個数（`reduction`）、
     * 心光の逓増、自己構成済みカードの鍵（`tableau`）、払えるか（`canAnalyze`）。手札の鍵は `_private[本人]`（`hand`）。
     *
     * @return array<string, mixed>
     */
    public function getArgs(int $activePlayerId): array
    {
        $args = $this->game->irTurn()->analysis->args($activePlayerId);

        return [
            'turnPlayer' => $this->game->irGlobals()->turnPlayer(),
            ...$args['public'],
            '_private' => [$activePlayerId => $args['private']],
        ];
    }

    /**
     * 精神分析を行う。`payment`: `{"removed": {"WISDOM": 1}, "keys": [{"src": "TABLEAU"|"HAND"|"LIGHT", "card": ID, "key": 鍵の番号,
     * "for": "WISDOM"}...]}`。万能移動鍵トークンは使えない。第五階層なら心理の極致で勝利する。
     *
     * @param array<string, mixed> $payment 支払い（TABLEAU / HAND / LIGHT）
     */
    #[PossibleAction]
    public function actAnalyze(string $destination, #[JsonParam(alphanum: false)] array $payment, int $activePlayerId): string
    {
        try {
            $notices = $this->game->irTurn()->analysis->analyze($activePlayerId, $destination, $payment);
        } catch (EffectViolation $e) {
            throw new UserException($e->getMessage());
        }
        $this->game->irSend(...$notices);

        return $this->game->irGlobals()->endReason() !== null ? Transition::GAME_END : Transition::DONE;
    }

    /**
     * 何も消費せずに戻る。床アクションの代替なら 50 に戻って別の選択肢を選べる。トークンから入ったときはトークンを消費しない。
     */
    #[PossibleAction]
    public function actCancel(int $activePlayerId): string
    {
        try {
            $notices = $this->game->irTurn()->analysis->cancel($activePlayerId);
        } catch (EffectViolation $e) {
            throw new UserException($e->getMessage());
        }
        $this->game->irSend(...$notices);

        return Transition::DONE;
    }

    /**
     * 行わない（006 §10）。
     */
    public function zombie(int $playerId): string
    {
        return $this->actCancel($playerId);
    }
}
