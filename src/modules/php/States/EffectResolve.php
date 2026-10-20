<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\IRevolveThereforeIAm\Data\EffectFrame;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Game;

/**
 * 70: 効果解決スタックの処理。他の全ての状態は処理後にここへ戻るハブ（006 §7.2）。
 * 骨格ではスタックを積む処理が無いので、空のスタックを確認して戻り先（resume）へ進むだけ。
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
        $globals = $this->game->irGlobals();

        // TODO(I-08): 勝利判定（WinChecker）と山札切れ判定。成立したら end_reason を決めて GAME_END を返す
        if ($globals->endReason() !== null) {
            return Transition::GAME_END;
        }

        // TODO(I-05/I-07/I-09): 006 §7.2 のループ。スタックの先頭の frame を種類ごとに解決する
        //  （自動で済む効果はループ内で解決、選択が要る所は changeActivePlayer して CHOICE、COMPOSE / ANALYSIS / HAND_LIMIT）。
        //  1 回の入場で処理する frame は 500 件まで
        $stack = $this->game->irEffectStack();
        while (($head = $stack->peek()) !== null) {
            if ($head->kind !== EffectFrame::KIND_HAND_LIMIT_CHECK || $head->id === null) {
                throw new \LogicException("effect frame is not supported yet: {$head->kind}");
            }
            // 手札上限の確認（006 §8）。超過した人がいれば 72 へ。捨て終えて戻ってきたら、もう一度確認する
            $over = $this->game->irHandLimit()->firstOver($globals->turnPlayer());
            if ($over !== null) {
                $this->game->gamestate->changeActivePlayer($over);

                return Transition::HAND_LIMIT;
            }
            $stack->complete($head->id);
        }

        $this->game->gamestate->changeActivePlayer($globals->turnPlayer());

        return Transition::resumeTransition($globals->resume());
    }
}
