<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Context;

use Bga\Games\IRevolveThereforeIAm\Material\ActionKind;
use Bga\Games\IRevolveThereforeIAm\Material\Origin;
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
}
