<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\GamePhase;

/**
 * 通知の組み立て（仕様書 007 §10.2）。ログ文言（英語原文）はここに集める（翻訳の抽出のため）。
 * 今は N01〜N18、N24〜N26、N31 `gameEnded`、N33・N34 と、移動できなかったときの `moveBlocked` 。残り（N19〜N23、N27〜N30、N32、N35）は
 * 後続のタスクで足す。
 */
final class Notification
{
    private function __construct()
    {
    }

    /**
     * N01: 初期自我を 1 枚選んだ。公開部分は山の残り枚数だけ。選んだカードは本人の `_private` にだけ入れる。
     */
    public static function initialEgoPicked(int $playerId, string $playerName, int $packSize, string $egoId): Notice
    {
        return new Notice('initialEgoPicked', clienttranslate('${player_name} chooses an initial ego card'), [
            'player_id' => $playerId,
            'player_name' => $playerName,
            'pack_size' => $packSize,
            '_private' => [$playerId => ['ego_id' => $egoId]],
        ]);
    }

    /**
     * N02: 全員の初期自我を一斉に公開する。
     *
     * @param list<array{player_id: int, ego_id: string}> $egos
     */
    public static function initialEgosRevealed(array $egos): Notice
    {
        return new Notice('initialEgosRevealed', clienttranslate('All players reveal their initial ego cards'), [
            'egos' => $egos,
        ]);
    }

    /**
     * N03: 人格を四隅の部屋に置いた。
     */
    public static function personaPlaced(int $playerId, string $playerName, int $pos, string $roomName): Notice
    {
        return new Notice('personaPlaced', clienttranslate('${player_name} places their persona in ${room_name}'), [
            'player_id' => $playerId,
            'player_name' => $playerName,
            'pos' => $pos,
            'room_name' => $roomName,
            'i18n' => ['room_name'],
        ]);
    }

    /**
     * N04: ターンの開始。ログには出さない。
     */
    public static function turnStarted(int $playerId, string $playerName, int $turnNo): Notice
    {
        return new Notice('turnStarted', '', [
            'player_id' => $playerId,
            'player_name' => $playerName,
            'turn_no' => $turnNo,
        ]);
    }

    /**
     * N06: 部屋に入室した。支払った資源の変化は N15〜N17 が先に続く（006 §5.1 の流れ）。
     *
     * @param array{tapped: list<int>, discarded: list<array{id: int, type: int}>, light: int, wild_tokens: int, granted_keys: int} $payment
     *        `light` は払った心光の個数、`wild_tokens` は裏返した万能移動鍵トークンの数、`granted_keys` は MD-06 の万能鍵の数
     */
    public static function roomEntered(
        int $playerId,
        string $playerName,
        int $fromPos,
        int $pos,
        string $roomName,
        int $step,
        array $payment,
        int $enteredCount,
    ): Notice {
        return new Notice('roomEntered', clienttranslate('${player_name} enters ${room_name}'), [
            'player_id' => $playerId,
            'player_name' => $playerName,
            'from_pos' => $fromPos,
            'pos' => $pos,
            'room_name' => $roomName,
            'step' => $step,
            'payment' => $payment,
            'entered_count' => $enteredCount,
            'i18n' => ['room_name'],
        ]);
    }

    /**
     * N07: レベル 2 の部屋をスキップした。周遊ボーナスは N16 が続く。
     */
    public static function roomSkipped(int $playerId, string $playerName, int $pos, string $roomName): Notice
    {
        return new Notice('roomSkipped', clienttranslate('${player_name} skips ${room_name}'), [
            'player_id' => $playerId,
            'player_name' => $playerName,
            'pos' => $pos,
            'room_name' => $roomName,
            'i18n' => ['room_name'],
        ]);
    }

    /**
     * 払える移動が 1 つも無く、移動せずに想起フェイズへ進む（Q-38）。007 の通知一覧に無い通知（ログだけ）。
     */
    public static function moveBlocked(int $playerId, string $playerName, int $pos, string $roomName): Notice
    {
        return new Notice('moveBlocked', clienttranslate('${player_name} cannot pay to move and stays in ${room_name}'), [
            'player_id' => $playerId,
            'player_name' => $playerName,
            'pos' => $pos,
            'room_name' => $roomName,
            'i18n' => ['room_name'],
        ]);
    }

    /**
     * カードの参照（007 §10.1）。種類まで公開してよいカードにだけ使う。
     *
     * @return array{id: int, type: int}
     */
    public static function cardRef(int $cardId, int $type): array
    {
        return ['id' => $cardId, 'type' => $type];
    }

    /**
     * N08: 想起効果による人格の移動（Q-06）。
     */
    public static function personaMoved(
        int $playerId,
        string $playerName,
        int $fromPos,
        int $pos,
        string $roomName,
        string $reason,
        bool $countsAsEntry,
    ): Notice {
        return new Notice('personaMoved', clienttranslate('${player_name} moves to ${room_name}'), [
            'player_id' => $playerId,
            'player_name' => $playerName,
            'from_pos' => $fromPos,
            'pos' => $pos,
            'room_name' => $roomName,
            'reason' => $reason,
            'counts_as_entry' => $countsAsEntry,
            'i18n' => ['room_name'],
        ]);
    }

    /**
     * N09: 想起フェイズの開始。ターンプレイヤーの部屋の想い出カードを表向きにする。
     *
     * @param array{id: int, type: int} $card
     * @param list<int> $activators 発動者（ターンプレイヤーから時計回り）
     * @param array<int, int> $counts 発動者ごとの発動回数
     */
    public static function recallStarted(
        int $playerId,
        string $playerName,
        int $pos,
        string $roomName,
        array $card,
        string $cardName,
        array $activators,
        array $counts,
    ): Notice {
        return new Notice('recallStarted', clienttranslate('${player_name} recalls ${card_name} in ${room_name}'), [
            'player_id' => $playerId,
            'player_name' => $playerName,
            'pos' => $pos,
            'room_name' => $roomName,
            'card' => $card,
            'card_name' => $cardName,
            'activators' => $activators,
            'counts' => $counts,
            'i18n' => ['room_name', 'card_name'],
        ]);
    }

    /**
     * N10: 床アクション（または想起効果による代替）を選んだ。`$kind` は `FLOOR` / `FLOOR_A` / `FLOOR_B` / `ALT_ANALYSIS` / `ALT_COMPOSE`。
     */
    public static function floorActionChosen(int $playerId, string $playerName, string $kind, string $actionName): Notice
    {
        return new Notice('floorActionChosen', clienttranslate('${player_name} performs ${action_name}'), [
            'player_id' => $playerId,
            'player_name' => $playerName,
            'kind' => $kind,
            'action_name' => $actionName,
            'i18n' => ['action_name'],
        ]);
    }

    /**
     * 床アクションの代替の名前（N10 の `action_name`。翻訳対象の英語原文）。
     */
    public static function alternativeName(string $kind): string
    {
        return $kind === 'ALT_ANALYSIS' ? clienttranslate('an analysis instead of the floor action') : clienttranslate('a composition instead of the floor action');
    }

    /**
     * N11: 想起効果・起動効果の発動（共通の部品）。想起効果では `$sourceName` に表向きにしたカードの名前、M062 の 2 回目は `$nth = 2`。
     */
    public static function effectActivated(
        int $playerId,
        string $playerName,
        string $source,
        string $sourceName,
        ?string $specId,
        ?int $nth = null,
        ?int $times = null,
    ): Notice {
        $args = [
            'player_id' => $playerId,
            'player_name' => $playerName,
            'source' => $source,
            'source_name' => $sourceName,
            'i18n' => ['source_name'],
        ];
        if ($specId !== null) {
            $args['spec_id'] = $specId;
        }
        if ($nth !== null && $times !== null && $times > 1) {
            $args['nth'] = $nth;
            $args['times'] = $times;
        }

        return new Notice('effectActivated', clienttranslate('${player_name} activates ${source_name}'), $args);
    }

    /**
     * N12: 山札から手札へ。公開部分は枚数だけで、引いたカードは本人の `_private` にだけ入れる。
     *
     * @param list<array{id: int, type: int}> $cards
     * @param array<int, array{count: int, top: ?string}> $decks
     */
    public static function cardsDrawn(
        int $playerId,
        string $playerName,
        array $cards,
        string $reason,
        array $decks,
        int $handCount,
    ): Notice {
        return new Notice('cardsDrawn', clienttranslate('${player_name} draws ${count} memory card(s)'), [
            'player_id' => $playerId,
            'player_name' => $playerName,
            'count' => count($cards),
            'level' => 1,
            'reason' => $reason,
            'decks' => $decks,
            'hand_count' => $handCount,
            '_private' => [$playerId => ['cards' => $cards]],
        ]);
    }

    /**
     * N13: 場札から手札へ（表向きのカードなので種類を公開する）。
     *
     * @param list<array{id: int, type: int}> $cards
     * @param list<int> $fromSlots 場札の枠
     */
    public static function cardsGained(
        int $playerId,
        string $playerName,
        array $cards,
        array $fromSlots,
        int $handCount,
        string $reason,
    ): Notice {
        return new Notice('cardsGained', clienttranslate('${player_name} takes ${count} memory card(s) from the open cards'), [
            'player_id' => $playerId,
            'player_name' => $playerName,
            'cards' => $cards,
            'count' => count($cards),
            'source' => 'OPEN',
            'from_slots' => $fromSlots,
            'hand_count' => $handCount,
            'reason' => $reason,
        ]);
    }

    /**
     * N14: 想起の最後の獲得（ターンプレイヤー。Q-04）。
     *
     * @param array{id: int, type: int} $card
     */
    public static function roomCardAcquired(
        int $playerId,
        string $playerName,
        int $pos,
        array $card,
        string $cardName,
        int $handCount,
    ): Notice {
        return new Notice('roomCardAcquired', clienttranslate('${player_name} acquires ${card_name}'), [
            'player_id' => $playerId,
            'player_name' => $playerName,
            'pos' => $pos,
            'card' => $card,
            'card_name' => $cardName,
            'hand_count' => $handCount,
            'i18n' => ['card_name'],
        ]);
    }

    /**
     * N18: 手札から自己構成済みエリアへ。
     *
     * @param array{id: int, type: int} $card
     * @param array{discarded: list<array{id: int, type: int}>, light: int, energy: int, tapped: list<int>} $payment
     */
    public static function cardComposed(
        int $playerId,
        string $playerName,
        array $card,
        string $cardName,
        string $context,
        array $payment,
        int $tableauCount,
        int $handCount,
    ): Notice {
        return new Notice('cardComposed', clienttranslate('${player_name} composes ${card_name}'), [
            'player_id' => $playerId,
            'player_name' => $playerName,
            'card' => $card,
            'card_name' => $cardName,
            'context' => $context,
            'payment' => $payment,
            'tableau_count' => $tableauCount,
            'hand_count' => $handCount,
            'i18n' => ['card_name'],
        ]);
    }

    /**
     * N24: 精神トラックの階層を上げた。心光の獲得は N16 が続く。
     *
     * @param array{tapped: list<int>, discarded: list<array{id: int, type: int}>, light: int}|null $payment 精神分析アクションの支払い（効果による上昇は null）
     */
    public static function spiritAdvanced(
        int $playerId,
        string $playerName,
        ?string $from,
        string $to,
        int $tier,
        string $via,
        bool $lightTaken,
        ?array $payment,
        int $analysisCount,
    ): Notice {
        return new Notice('spiritAdvanced', clienttranslate('${player_name} advances to tier ${tier}'), [
            'player_id' => $playerId,
            'player_name' => $playerName,
            'from_spirit' => $from,
            'to_spirit' => $to,
            'tier' => $tier,
            'via' => $via,
            'light_taken' => $lightTaken,
            'payment' => $payment,
            'analysis_count' => $analysisCount,
        ]);
    }

    /**
     * N25: 強化自我の獲得（公開。Q-08）。
     */
    public static function enhancedEgoObtained(int $playerId, string $playerName, string $egoId, string $egoName, int $deckCount): Notice
    {
        return new Notice('enhancedEgoObtained', clienttranslate('${player_name} obtains the enhanced ego ${ego_name}'), [
            'player_id' => $playerId,
            'player_name' => $playerName,
            'ego_id' => $egoId,
            'ego_name' => $egoName,
            'deck_count' => $deckCount,
            'i18n' => ['ego_name'],
        ]);
    }

    /**
     * N26: 手札と場札、自己構成済みと手札の交換（想起の `SWAP_HAND_FIELD`・`SWAP_SAME_COST`）。
     *
     * @param list<array{out: array{id: int, type: int}, in: array{id: int, type: int}, slot?: int}> $pairs
     */
    public static function cardsExchanged(int $playerId, string $playerName, string $kind, array $pairs, int $handCount): Notice
    {
        return new Notice('cardsExchanged', clienttranslate('${player_name} exchanges ${count} memory card(s)'), [
            'player_id' => $playerId,
            'player_name' => $playerName,
            'kind' => $kind,
            'pairs' => $pairs,
            'count' => count($pairs),
            'hand_count' => $handCount,
        ]);
    }

    /**
     * N33: 想起効果による床アクションの代替の記録（ターンプレイヤーのみ）。同じ種類は割引が最大の 1 件にまとまる。
     *
     * @param list<array{kind: string, discount: int}> $alternatives 記録後の全件
     */
    public static function floorAlternativeRecorded(int $playerId, string $playerName, array $alternatives): Notice
    {
        return new Notice('floorAlternativeRecorded', clienttranslate('${player_name} may perform another action instead of a floor action'), [
            'player_id' => $playerId,
            'player_name' => $playerName,
            'alternatives' => $alternatives,
        ]);
    }

    /**
     * N34: 自己構成エネルギーの軽減の現在の合計（POSEIDON と、次の自己構成の軽減）。
     */
    public static function composeDiscountChanged(int $playerId, string $playerName, int $turnDiscount, int $nextDiscount): Notice
    {
        return new Notice('composeDiscountChanged', clienttranslate('${player_name} reduces composition energy'), [
            'player_id' => $playerId,
            'player_name' => $playerName,
            'turn_compose_discount' => $turnDiscount,
            'next_compose_discount' => $nextDiscount,
        ]);
    }

    /**
     * N15: 手札から捨札へ。ID と種類を公開する（捨札は表向き。Q-23）。`COST` はログに出さない（N06 のログで足りる）。
     *
     * @param list<array{id: int, type: int}> $cards
     * @param array{1: int, 2: int} $pileCounts
     */
    public static function cardsDiscarded(
        int $playerId,
        string $playerName,
        array $cards,
        string $reason,
        array $pileCounts,
        int $handCount,
    ): Notice {
        $message = match ($reason) {
            'LIGHT' => clienttranslate('${player_name} discards ${count} memory card(s) for inner light'),
            'HAND_LIMIT' => clienttranslate('${player_name} discards ${count} memory card(s) (hand limit)'),
            'EFFECT', 'FLOOR' => clienttranslate('${player_name} discards ${count} memory card(s)'),
            default => '',
        };

        return new Notice('cardsDiscarded', $message, [
            'player_id' => $playerId,
            'player_name' => $playerName,
            'cards' => $cards,
            'count' => count($cards),
            'reason' => $reason,
            'pile_counts' => ['1' => $pileCounts[1], '2' => $pileCounts[2]],
            'hand_count' => $handCount,
        ]);
    }

    /**
     * N16: トークンの増減。`$delta` と `$totals` のキーは `light` / `wild_up` / `wild_used` / `compose` / `analysis`。
     * `$tokenName` が null ならログに出さない（`PAYMENT` など）。
     *
     * @param array<string, int> $delta 0 でないものだけ
     * @param array<string, int> $totals 変更後の同じキーの値
     */
    public static function tokensChanged(
        int $playerId,
        string $playerName,
        array $delta,
        array $totals,
        string $reason,
        ?string $origin = null,
        ?string $tokenName = null,
        int $amount = 0,
        bool $gain = true,
    ): Notice {
        $message = '';
        if ($tokenName !== null) {
            $message = $gain
                ? clienttranslate('${player_name} gains ${amount} ${token_name}')
                : clienttranslate('${player_name} spends ${amount} ${token_name}');
        }
        $args = [
            'player_id' => $playerId,
            'player_name' => $playerName,
            'delta' => $delta,
            'totals' => $totals,
            'reason' => $reason,
        ];
        if ($origin !== null) {
            $args['origin'] = $origin;
        }
        if ($tokenName !== null) {
            $args['token_name'] = $tokenName;
            $args['amount'] = $amount;
            $args['i18n'] = ['token_name'];
        }

        return new Notice('tokensChanged', $message, $args);
    }

    /**
     * トークンの名前（N16 の `token_name`。翻訳対象の英語原文）。
     *
     * @param string $key `light` / `wild_up` / `compose` / `analysis`
     */
    public static function tokenName(string $key): string
    {
        return match ($key) {
            'light' => clienttranslate('inner light'),
            'wild_up' => clienttranslate('wild move key'),
            'compose' => clienttranslate('compose token'),
            'analysis' => clienttranslate('analysis token'),
            default => throw new \InvalidArgumentException("unknown token key: $key"),
        };
    }

    /**
     * N17: 自己構成済みカードを横向きにする / 戻す（支払い）。ログには出さない。
     *
     * @param list<int> $cardIds
     */
    public static function cardsTapped(int $playerId, string $playerName, array $cardIds, bool $tapped): Notice
    {
        return new Notice('cardsTapped', '', [
            'player_id' => $playerId,
            'player_name' => $playerName,
            'card_ids' => $cardIds,
            'tapped' => $tapped,
        ]);
    }

    /**
     * ログに区切りを出すフェイズ（手番の 5 フェイズ。仕様書 001 §5）。
     */
    public static function isLoggedPhase(GamePhase $phase): bool
    {
        return in_array($phase, [
            GamePhase::Move,
            GamePhase::Recall,
            GamePhase::Floor,
            GamePhase::Construct,
            GamePhase::Cleanup,
        ], true);
    }

    /**
     * N05: フェイズの開始（ログの区切り）。
     */
    public static function phaseStarted(GamePhase $phase): Notice
    {
        return new Notice('phaseStarted', clienttranslate('${phase_name} phase'), [
            'phase' => $phase->value,
            'phase_name' => self::phaseName($phase),
            'i18n' => ['phase_name'],
        ]);
    }

    /**
     * N31: 終局。勝者または全員敗北の明示と得点の内訳。
     *
     * @param list<array<string, int>> $results プレイヤーごとの内訳（player_id、memory_score、objective_score、attached_score、total、tier）
     */
    public static function gameEnded(?string $reason, ?int $winnerId, ?string $winnerName, array $results): Notice
    {
        $args = [
            'reason' => $reason,
            'winner_id' => $winnerId,
            'results' => $results,
        ];
        if ($winnerId === null || $winnerName === null) {
            return new Notice(
                'gameEnded',
                $reason === 'DECK_OUT'
                    ? clienttranslate('The memory deck ran out. Everyone loses')
                    : clienttranslate('The game ends. Everyone loses'),
                $args,
            );
        }
        $args['player_id'] = $winnerId;
        $args['player_name'] = $winnerName;

        return new Notice('gameEnded', match ($reason) {
            'FRAGMENTS' => clienttranslate('${player_name} wins by gathering fragments'),
            'INSTANT_MEMORY' => clienttranslate('${player_name} wins by a fleeting recollection'),
            'APEX_OF_PSYCHE' => clienttranslate('${player_name} wins by reaching the apex of the psyche'),
            default => clienttranslate('${player_name} wins'),
        }, $args);
    }

    /**
     * フェイズ名（翻訳対象の英語原文）。
     */
    private static function phaseName(GamePhase $phase): string
    {
        return match ($phase) {
            GamePhase::Move => clienttranslate('Move'),
            GamePhase::Recall => clienttranslate('Recall'),
            GamePhase::Floor => clienttranslate('Floor action'),
            GamePhase::Construct => clienttranslate('Compose'),
            GamePhase::Cleanup => clienttranslate('Cleanup'),
            GamePhase::TurnStart => clienttranslate('Turn start'),
            GamePhase::TurnEnd => clienttranslate('Turn end'),
            GamePhase::SetupEgo => clienttranslate('Initial ego draft'),
            GamePhase::SetupPersona => clienttranslate('Persona placement'),
            GamePhase::End => clienttranslate('Game end'),
        };
    }
}
