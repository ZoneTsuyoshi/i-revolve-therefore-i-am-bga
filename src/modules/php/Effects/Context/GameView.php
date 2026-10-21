<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Context;

use Bga\Games\IRevolveThereforeIAm\Material\ActionKind;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;

/**
 * 効果エンジンがゲーム状態を読むための口（008 §1.1、§11.1。003 §4.2）。`Effects/` はこれ越しにしか状態に触れない。
 * 本番の実装は `Data/` の読み取り（007）、単体テストはメモリ実装（`tests/Support/ArrayGame`）。
 *
 * カードは個体 ID（`int`）で扱い、種類（`M001` など）は `cardType()` で引く（007 §4）。
 */
interface GameView
{
    public function turnPlayer(): int;

    /**
     * @return list<int> 席順のプレイヤー ID
     */
    public function playerIds(): array;

    /**
     * @return list<int> 手札のカードの個体 ID
     */
    public function handIds(int $player): array;

    /**
     * @return list<int> 場札のカードの個体 ID（枠の順）
     */
    public function fieldIds(): array;

    /** 想い出の山札の残り枚数（レベル 1 / 2） */
    public function deckSize(int $level): int;

    /** カードの種類（002 の ID。`M001`〜`M099`） */
    public function cardType(int $cardId): string;

    /**
     * @return list<int> 自己構成済みのカード（自己構成した順）
     */
    public function tableauIds(int $player): array;

    /** 横向き（使用済み）か */
    public function isTapped(int $cardId): bool;

    /**
     * @return list<string> 持っている自我カードの ID（`I01`・`E01` など。公開されたもの）
     */
    public function egoIds(int $player): array;

    /** 部屋（位置 1〜12）の想い出カードの個体 ID。空なら null */
    public function roomCardId(int $position): ?int;

    public function personaPosition(int $player): int;

    /**
     * @return list<int> その位置にいる人格のプレイヤー ID
     */
    public function playersAt(int $position): array;

    /**
     * @return list<int> 位置の床カードの下に置かれた想い出カード（I04。置いた順。位置に付く）
     */
    public function floorUnderIds(int $position): array;

    /** 想起フェイズ開始時の部屋の位置（`TurnContext.recall.room_pos`）。想起フェイズ以外では null */
    public function recallRoomPosition(): ?int;

    /** このターンの入室の回数（移動と想起効果の移動の合計。ワープ・スキップは含まない） */
    public function roomsEnteredCount(): int;

    /** このターンのレベル 2 の部屋への入室回数 */
    public function lv2EntriesCount(): int;

    /** このターンに完了した自己構成アクション（`PHASE` / `TOKEN` / `FLOOR`）の回数 */
    public function composeActionsDone(): int;

    /**
     * @return list<KeyType> 移動フェイズの支払いで使った鍵の種類（印刷された非万能鍵）
     */
    public function moveKeyKindsPaid(): array;

    public function moveWildTokensUsed(): int;

    /** `ONCE_PER_TURN` の効果を、このターンに使用済みか */
    public function isOnceUsed(int $player, string $specId): bool;

    public function nextComposeDiscount(): int;

    public function turnComposeDiscount(): int;

    /**
     * @return list<array{kind: ActionKind, discount: int}> 記録された床アクションの代替（種類ごとに 1 つ）
     */
    public function floorAlternatives(): array;

    /** 位置（1〜12）の床カードの ID（`F01`〜`F12`。M055〜M057 で入れ替わる） */
    public function floorIdAt(int $position): string;

    /** 精神トラック上の現在の精神カード ID（階層 0 は null） */
    public function spiritId(int $player): ?string;

    /**
     * @return list<string> 次の階層の精神カード ID（現在地から 1 つ上。第五階層なら空）
     */
    public function nextSpiritIds(int $player): array;

    /**
     * @return list<string> 強化自我の山に残るカードの ID（昇順。本人にだけ見せる候補）
     */
    public function enhancedEgoIds(): array;
}
