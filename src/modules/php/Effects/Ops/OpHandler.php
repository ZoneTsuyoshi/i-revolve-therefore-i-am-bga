<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Ops;

use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameEvent;
use Bga\Games\IRevolveThereforeIAm\Effects\Engine\ChoiceRequest;
use Bga\Games\IRevolveThereforeIAm\Material\ChoiceKind;

/**
 * 原子操作 1 つのハンドラ（008 §3.1）。選択が要るかの判断（`request`）と、実行（`execute`）に分ける。
 * 状態クラスは選択を `effect_frame` に保存し、答えが来たら `execute` に渡す（006 §7.4）。
 */
interface OpHandler
{
    /**
     * この操作が選択を要する場合の選択の種類。選択の無い操作は null。
     * `OPTIONAL` がこの種類を `via` に持つとき、操作自身が「行わない」選択を聞くので、確認を重ねて聞かない。
     */
    public function choiceKind(): ?ChoiceKind;

    /**
     * 選択が要るならその内容、自動で解決できる（候補が無い、全部を取る、など）なら null。
     */
    public function request(OpContext $context): ?ChoiceRequest;

    /**
     * 実行する。`request` が null のときは `OpAnswer::auto()`。選択があるときは検証して反映する。
     *
     * @return list<GameEvent> 起きたイベント
     * @throws \Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation 答えが規則に反するとき
     */
    public function execute(OpContext $context, OpAnswer $answer): array;
}
