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
use Bga\Games\IRevolveThereforeIAm\Data\GamePhase;
use Bga\Games\IRevolveThereforeIAm\Effects\Objective\ObjectiveWindow;
use Bga\Games\IRevolveThereforeIAm\Flow\FreeActions;
use Bga\Games\IRevolveThereforeIAm\Flow\MoveViolation;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Game;

/**
 * 31: 移動フェイズ。経路全体と支払いをまとめて 1 回の actMove で確定する（006 §5.1）。
 * 検証と適用は Flow/MovePhase（MovePlanner と MoveExecutor）が行い、ここは通知の送信と遷移だけ。
 */
class Move extends GameState
{
    use FreeActions;

    protected function irObjectiveWindow(): ObjectiveWindow
    {
        return ObjectiveWindow::move();
    }

    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::MOVE,
            type: StateType::ACTIVE_PLAYER,
            description: clienttranslate('${actplayer} must move'),
            descriptionMyTurn: clienttranslate('${you} must move'),
            transitions: [
                Transition::MOVED => StateId::EFFECT_RESOLVE,
                Transition::GAME_END => StateId::GAME_END,
            ],
        );
    }

    public function onEnteringState(): void
    {
        $this->game->irEnterPhase(GamePhase::Move);
    }

    /**
     * クライアントが経路を計画するための情報（007 §8.3、006 §5.1）。公開部分は部屋ごとの錠前（`rooms`: pos / level / lock / locks /
     * bonus）、資源（`resources`: 心光・表向きの万能移動鍵トークン・心光の逓増 `lightCosts`・MD-06 の錠前の軽減数 `lockReduction`。このターン最初のレベル 2 の入室の錠前の数を減らす。`rooms[].locks` は軽減前）、
     * 自己構成済みカード（`tableau`）、鍵の読み替え（`keyServes`: 持つ鍵 → 払える錠前の種類）、`canMove`（払える移動があるか）。
     * 手札は `_private[ターンプレイヤー].hand` にだけ入れる。
     *
     * @return array<string, mixed>
     */
    public function getArgs(): array
    {
        $turnPlayer = $this->game->irGlobals()->turnPlayer();
        $data = $this->game->irMovePhase()->planningData($turnPlayer);

        return [
            'turnPlayer' => $turnPlayer,
            ...$data['public'],
            '_private' => [$turnPlayer => ['hand' => $data['hand']]],
        ];
    }

    /**
     * 経路と支払いを確定する。
     *
     * 入力: {"rooms": [入室する部屋の位置...], "payments": [[{"src", "card", "key"}...]...]}。
     * `src` は TABLEAU / HAND / LIGHT / TOKEN。レベル 2 の部屋の支払いの数は `lockReduction` の分だけ減る。`rooms` が空なのは、払える移動が 1 つも無いときだけ（Q-38）。
     *
     * @param array<string, mixed> $plan
     */
    #[PossibleAction]
    public function actMove(#[JsonParam(alphanum: false)] array $plan, int $activePlayerId): string
    {
        return $this->irSubmit($plan, $activePlayerId, false);
    }

    /**
     * 万能移動鍵トークン → 自己構成済みカードの鍵 → 心光の順で、次の部屋まで 1 部屋だけ払えるなら移動する。
     * 払えなければ移動しない（手札は捨てない。006 §10）。
     */
    public function zombie(int $playerId): string
    {
        return $this->irSubmit($this->game->irMovePhase()->zombiePlan($playerId), $playerId, true);
    }

    /**
     * @param array<string, mixed> $plan
     */
    private function irSubmit(array $plan, int $playerId, bool $zombie): string
    {
        try {
            $result = $this->game->irMovePhase()->submit($playerId, $this->game->getPlayerNameById($playerId), $plan, $zombie);
        } catch (MoveViolation $e) {
            throw new UserException($this->irMessage($e->reason));
        }
        $this->game->irSend(...$result->notices);
        if ($result->ended) {
            return Transition::GAME_END;
        }

        // 移動終了時のトリガー（EV-02〜EV-04）と手札上限の確認（積み済み）を 70 で解決してから、想起フェイズへ
        $this->game->irEmitEvents($result->events);
        $this->game->irGlobals()->setResume(StateId::RECALL_START);

        return Transition::MOVED;
    }

    private function irMessage(string $reason): string
    {
        return match ($reason) {
            MoveViolation::INVALID_ROUTE => clienttranslate('The route must follow the corridor clockwise'),
            MoveViolation::MUST_MOVE => clienttranslate('You must move at least one room'),
            MoveViolation::LOCK_COUNT => clienttranslate('Each room must be paid for with exactly as many keys as it has locks'),
            MoveViolation::KEY_MISMATCH => clienttranslate('That key does not fit the lock'),
            MoveViolation::BAD_CARD => clienttranslate('You cannot use that card for payment'),
            MoveViolation::SPLIT_CARD => clienttranslate('A card with several keys can only pay for consecutive rooms'),
            MoveViolation::NOT_ENOUGH_RESOURCES => clienttranslate('You do not have enough inner light or wild move keys'),
            default => clienttranslate('Invalid move'),
        };
    }
}
