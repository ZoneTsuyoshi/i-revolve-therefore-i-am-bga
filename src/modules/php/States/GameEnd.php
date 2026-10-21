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
use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Data\PublicView;
use Bga\Games\IRevolveThereforeIAm\Flow\Notification;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Game;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;

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

        $results = $this->game->irPublicView()->results($seats);
        $stats = $this->game->irStats();
        $stats->turnFinished($globals->turnContext()->roomsEnteredCount);
        $stats->finalize($reason, $results, $this->irKeyCounts($seats));

        $this->game->irSend(Notification::gameEnded(
            $reason,
            $winnerId,
            $winnerId === null ? null : $this->game->getPlayerNameById($winnerId),
            $results,
        ));

        return GameResult::individualRanking();
    }

    /**
     * 自己構成済みカードの鍵の本数（プレイヤー ID → 鍵の種類ごと。統計 key_*）。
     *
     * @param list<int> $seats
     * @return array<int, array<string, int>>
     */
    private function irKeyCounts(array $seats): array
    {
        $counts = [];
        foreach ($seats as $playerId) {
            $held = [KeyType::WISDOM->value => 0, KeyType::WILL->value => 0, KeyType::EMOTION->value => 0, KeyType::WILD->value => 0];
            foreach ($this->game->irMemoryCards()->inLoc(MemoryLocation::Tableau, $playerId) as $card) {
                foreach (PublicView::definition($card->type)->keys as $key) {
                    $held[$key->value]++;
                }
            }
            $counts[$playerId] = $held;
        }

        return $counts;
    }
}
