<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Game;

/**
 * 70: 効果解決スタックの処理。他の全ての状態は処理後にここへ戻るハブ（006 §7.2）。
 * ループの中身は Flow/EffectResolver: スタックの先頭の frame を種類ごとに解決し、選択が要れば 71、自己構成・精神分析の行動が
 * 要れば 61・62、手札上限の超過があれば 72 へ出る。スタックが空になったら戻り先（resume）へ進む。
 *
 * 戻り先は、006 §3.2 では状態 ID（int）を返すが、未宣言の ID へ遷移できるかが Studio で未検証（006 §14 V-1）なので、
 * V-1 の代替案（戻り先ごとの遷移名の宣言）を最初から採用している。
 */
class EffectResolve extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::EFFECT_RESOLVE,
            type: StateType::GAME,
            transitions: [
                Transition::CHOICE => StateId::EFFECT_CHOICE,
                Transition::COMPOSE => StateId::COMPOSE,
                Transition::ANALYSIS => StateId::ANALYSIS,
                Transition::HAND_LIMIT => StateId::HAND_LIMIT_DISCARD,
                ...Transition::resumeDeclarations(),
                Transition::GAME_END => StateId::GAME_END,
            ],
        );
    }

    public function onEnteringState(): string
    {
        // TODO(I-08): 山札切れ（DECK_OUT）の判定を、frame の終了時にここで行う。勝利が先（Q-05）。
        //  断片の結集の判定（WinChecker）は Game::irCheckVictory（I-08・I-10）が end_reason を書く。
        $result = $this->game->irTurn()->resolver->resolve();
        $this->game->irSend(...$result->notices);
        if ($result->transition !== Transition::GAME_END) {
            $this->game->gamestate->changeActivePlayer($result->activePlayer);
        }

        return $result->transition;
    }
}
