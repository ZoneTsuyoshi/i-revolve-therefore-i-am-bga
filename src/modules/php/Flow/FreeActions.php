<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\GameFramework\Actions\Types\IntArrayParam;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;

/**
 * フリーアクション（目標カードの獲得、想い出カードを捨てて心光を得る。仕様書 006 §6）。
 * アクティブプレイヤーがターンプレイヤーである状態（31・51・60・61・62・71・72・81）だけが使う。
 * BGA は States/ の全クラスを状態として読むので、トレイトは States/ に置かない。
 * 使う側は $game（Game）を持つこと。
 */
trait FreeActions
{
    /**
     * 目標カードを獲得する（1 ターンに 1 枚。条件はサーバーが評価。001 §11-2）。
     */
    #[PossibleAction]
    public function actClaimObjective(int $objectiveId, int $activePlayerId): void
    {
        $this->irRequireTurnPlayer($activePlayerId);
        // TODO(I-10): ObjectiveEvaluator で条件を評価して獲得し、勝利判定（断片の結集）と手札上限の確認を行う
        throw new UserException(clienttranslate('Claiming goal cards is not available yet'));
    }

    /**
     * 想い出カードを捨てて心光を得る（レベル 1 は 1 枚分、レベル 2 は 2 枚分。2 枚分につき心光 1。回数無制限）。
     *
     * @param list<int> $cardIds
     */
    #[PossibleAction]
    public function actDiscardForLight(#[IntArrayParam] array $cardIds, int $activePlayerId): void
    {
        $this->irRequireTurnPlayer($activePlayerId);
        // TODO(I-07): 手札のレベルから W を数え、心光 floor(W / 2) を獲得して捨札へ。手札上限の確認を行う
        throw new UserException(clienttranslate('Discarding cards for inner light is not available yet'));
    }

    /**
     * フリーアクションを呼べるのはターンプレイヤーだけ（仕様書 006 §6.2）。
     */
    private function irRequireTurnPlayer(int $playerId): void
    {
        if ($this->game->irGlobals()->turnPlayer() !== $playerId) {
            throw new UserException(clienttranslate('Only the turn player can do this'));
        }
    }
}
