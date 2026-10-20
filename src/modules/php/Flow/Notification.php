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
 * 今は N01〜N07、N15〜N17、N31 `gameEnded` と、移動できなかったときの `moveBlocked` だけ。残りは後続のタスクで足す。
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
