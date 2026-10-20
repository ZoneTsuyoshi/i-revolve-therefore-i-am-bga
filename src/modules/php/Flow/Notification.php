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
 * 今は N01〜N05 と N31 `gameEnded` だけ。残りは後続のタスクで足す。
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
