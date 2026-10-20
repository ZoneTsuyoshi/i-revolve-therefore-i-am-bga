<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\States;

use Bga\GameFramework\GameResult\GameResult;
use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\IRevolveThereforeIAm\Data\GamePhase;
use Bga\Games\IRevolveThereforeIAm\Flow\Notification;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Game;

/**
 * 95: 終局。勝者または全員敗北に応じてスコアを確定し、GameResult を返す（006 §9）。
 * 99 ではなく GameResult::individualRanking を返してゲームを終える（BGA の推奨）。
 * ゲーム中の player_score は 0 のままで、ここで勝者 1・他 0、全員敗北なら全員 0 を書く（001 §15、007 §7.1）。
 */
class GameEnd extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::GAME_END,
            type: StateType::GAME,
        );
    }

    public function onEnteringState(): GameResult
    {
        $globals = $this->game->irGlobals();
        $globals->setPhase(GamePhase::End);
        $this->game->irEffectStack()->clear();

        $reason = $globals->endReason();
        $winnerId = $globals->winnerId();
        $seats = $this->game->irSeatOrder();

        foreach ($seats as $playerId) {
            $this->bga->playerScore->set($playerId, $playerId === $winnerId ? 1 : 0, null);
        }

        $this->game->irSend(Notification::gameEnded(
            $reason,
            $winnerId,
            $winnerId === null ? null : $this->game->getPlayerNameById($winnerId),
            $this->game->irPublicView()->results($seats),
        ));

        // TODO(I-11): 統計 end_reason・final_score などの確定
        return GameResult::individualRanking();
    }
}
