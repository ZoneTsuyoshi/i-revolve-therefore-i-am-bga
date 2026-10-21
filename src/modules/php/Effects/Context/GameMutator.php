<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Context;

use Bga\Games\IRevolveThereforeIAm\Material\ActionKind;
use Bga\Games\IRevolveThereforeIAm\Material\FloorBonus;
use Bga\Games\IRevolveThereforeIAm\Material\Origin;
use Bga\Games\IRevolveThereforeIAm\Material\RecallGroup;
use Bga\Games\IRevolveThereforeIAm\Material\TokenKind;

/**
 * 効果エンジンがゲーム状態を書くための口（008 §11.1。003 §4.2）。本番の実装は `Data/`（書き込みと通知）、
 * 単体テストはメモリ実装（`tests/Support/ArrayGame`）。規則の判断は `Effects/` が行い、ここは決まった結果を反映するだけ。
 */
interface GameMutator
{
    /**
     * レベル 1 の山札から手札に引く。足りなければ引けるだけ引く。
     *
     * @return int 実際に引いた枚数
     */
    public function drawFromDeck(int $player, int $count, ?Origin $origin): int;

    /** 山札切れのフラグを立てる（引ききれなかった瞬間。判定は frame の終了時。008 §6.8） */
    public function flagDeckExhausted(): void;

    /** 場札の 1 枚を手札に加える */
    public function takeFromField(int $player, int $cardId): void;

    /** 心光を得る（`lights_gained` にも加算する） */
    public function gainLight(int $player, int $count, ?Origin $origin): void;

    public function gainToken(int $player, TokenKind $token, int $count): void;

    /**
     * 到着した精神カード上の心光トークンを取る。
     *
     * @return bool 心光があって取れたか
     */
    public function takeSpiritLight(int $player): bool;

    /**
     * @param list<int> $cardIds 手札から捨札（カードのレベルの捨札）へ
     */
    public function discardFromHand(int $player, array $cardIds): void;

    /**
     * 手札と場札を同数入れ替える。
     *
     * @param list<int> $handIds
     * @param list<int> $fieldIds
     */
    public function swapHandAndField(int $player, array $handIds, array $fieldIds): void;

    /** 自己構成済みの 1 枚と手札の 1 枚を交換する（自己構成ではない扱い。OP-09） */
    public function swapComposedAndHand(int $player, int $tableauCardId, int $handCardId): void;

    /** このターンの自己構成エネルギーの軽減を n にする（POSEIDON。累積しない） */
    public function setTurnComposeDiscount(int $count): void;

    /** 「このターンで次の自己構成アクション」の軽減を n 足す */
    public function addNextComposeDiscount(int $count): void;

    /**
     * 床アクションの代替の記録を置き換える。
     *
     * @param list<array{kind: ActionKind, discount: int}> $alternatives
     */
    public function setFloorAlternatives(array $alternatives): void;

    /**
     * 部屋の想い出カードを手札に加える（部屋は空になる）。
     *
     * @return int|null 取ったカードの個体 ID。部屋が空なら null
     */
    public function takeRoomCard(int $player, int $position): ?int;

    public function markOnceUsed(int $player, string $specId): void;

    /** 勝利判定を行う（008 §6.8。全プレイヤーを評価する） */
    public function checkVictory(): void;

    /**
     * 想起効果による移動で人格を動かす（OP-15）。`$entry` なら入室に数える。12 部屋に入室した時点で刹那の追憶が成立する（Q-09）。
     * 入室に数えない移動（M072 のワープ）は後続のタスクで足す。
     */
    public function movePersona(int $player, int $to, bool $entry): void;

    /** 周遊ボーナスを与える（`origin = TOUR`）。想起効果の移動で通過・到着したレベル 2 の部屋の分（FAQ 行 8） */
    public function grantFloorBonus(int $player, FloorBonus $bonus): void;

    /**
     * 精神トラックの階層を 1 つ上げる（OP-12 とそのほか精神分析アクション）。`$via` は `ACTION` / `EFFECT`。
     * 到着した精神カードの心光の獲得とボーナスの frame を、解決スタックの先頭に積む（第五階層は心理の極致で勝利する）。
     *
     * @return bool 到着した精神カードに心光が置かれていたか（008 の `dest_had_light`）
     */
    public function advanceSpirit(int $player, string $spiritId, string $via): bool;

    /** 強化自我の山から 1 枚を獲得する（公開される） */
    public function obtainEnhancedEgo(int $player, string $egoId): void;

    /** 即座に勝利する（`$reason` は `APEX_OF_PSYCHE` など。OP-14） */
    public function win(int $player, string $reason): void;

    /**
     * 想い出カード `$cardId`（`M071` で自己構成したカードなど）の想起効果の発動を、解決スタックの先頭に積む（OP-25）。
     *
     * @param list<RecallGroup> $groups 発動してよい想起効果のグループ（空なら制限なし）
     */
    public function pushRecallActivation(int $player, string $cardId, int $times, array $groups): void;

    /**
     * 人格を好きな部屋へ移す（M072 のワープ。OP-16）。入室に数えず、周遊ボーナスも「止まった」扱いも無い（R8-12）。
     */
    public function warpPersona(int $player, int $to): void;

    /** 回廊上の 2 つの部屋の想い出カード（裏向き）を入れ替える（M052〜M054。OP-17）。誰も券面を見ない */
    public function swapRoomCards(int $player, int $posA, int $posB): void;

    /** 回廊上の 2 つの部屋の床カードだけを入れ替える（M055〜M057。OP-18）。想い出カード・床の下・人格は位置に残る */
    public function swapRoomFloors(int $player, int $posA, int $posB): void;

    /** 手札のカードと、人格のいない部屋の想い出カードを入れ替える（I05。OP-19）。手札から置くカードは裏向き */
    public function swapHandAndRoom(int $player, int $handCardId, int $position): void;

    /**
     * 精神分析のコストとして払った手札を、人格のいる部屋から反時計回りに 1 枚ずつ床カードの下に置く（I04。OP-20。現在の部屋を含む）。
     *
     * @param list<int> $cardIds
     */
    public function placeUnderFloor(int $player, array $cardIds): void;

    /** 止まった部屋の床カードの下の想い出カードを全て手札に加える（I04。OP-21。強制） */
    public function collectUnderFloor(int $player, int $position): void;

    /** ゲームが終わる状態か（勝利が確定した。以降の節は解決しない） */
    public function isGameOver(): bool;
}
