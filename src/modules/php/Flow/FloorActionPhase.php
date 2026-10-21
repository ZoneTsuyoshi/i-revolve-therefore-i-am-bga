<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\EffectFrame;
use Bga\Games\IRevolveThereforeIAm\Data\EffectStack;
use Bga\Games\IRevolveThereforeIAm\Data\GlobalValues;
use Bga\Games\IRevolveThereforeIAm\Data\PlayerValues;
use Bga\Games\IRevolveThereforeIAm\Data\RoomRepository;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectEngine;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Material\ActionKind;
use Bga\Games\IRevolveThereforeIAm\Material\FloorCards;

/**
 * 50 `FloorActionStart` と 51 `FloorActionChoose`: 床アクションフェイズ（001 §8、006 §5.3）。
 *
 * ターンプレイヤーの現在の部屋（想起で移動していれば移動先。FAQ 行 8）の床アクションと、想起で記録された代替から選択肢を作る。
 * 選択肢の `kind`:
 * - `FLOOR`: 床アクション。「/」の分岐がある床（APHRODITE・HEPHAESTUS）は分岐ごとに `FLOOR_A`・`FLOOR_B`（分岐の順）。
 * - `ALT_ANALYSIS` / `ALT_COMPOSE`: 想起効果の代替（同じ種類は割引が最大の 1 つだけ。FAQ 行 12）。
 * 選択肢が 1 つなら 51 を経由せずに実行する。実行は frame を積むことで行い（床アクション全体と代替が手札上限の単位）、
 * 戻り先は 60。代替の自己構成・精神分析は床アクション扱いで、取り消すと 50 から選び直せる。
 */
final class FloorActionPhase
{
    public function __construct(
        private readonly RoomRepository $rooms,
        private readonly PlayerValues $players,
        private readonly GlobalValues $globals,
        private readonly EffectStack $stack,
        private readonly EffectEngine $engine,
        private readonly \Closure $nameOf,
    ) {
    }

    /**
     * 現在の部屋の床カードの番号（1〜12。`F01` なら 1）。
     */
    public function floorNumber(): int
    {
        return $this->rooms->floorAt($this->players->persona($this->globals->turnPlayer()));
    }

    /**
     * 選択肢の一覧。
     *
     * @return list<array{kind: string, branch?: int, discount?: int}>
     */
    public function options(): array
    {
        $floorId = sprintf('F%02d', $this->floorNumber());
        $branches = $this->engine->branchCount($floorId);
        $options = [];
        if ($branches > 0) {
            for ($branch = 0; $branch < $branches; $branch++) {
                $options[] = ['kind' => 'FLOOR_' . chr(ord('A') + $branch), 'branch' => $branch];
            }
        } else {
            $options[] = ['kind' => 'FLOOR'];
        }
        foreach ($this->globals->turnContext()->floorAlternatives as $alternative) {
            $kind = ActionKind::tryFrom((string) ($alternative['kind'] ?? ''));
            if ($kind !== null) {
                $options[] = ['kind' => 'ALT_' . $kind->value, 'discount' => (int) ($alternative['discount'] ?? 0)];
            }
        }

        return $options;
    }

    /**
     * 50: 選択肢が 1 つならそのまま実行する。
     *
     * @return array{choose: bool, notices: list<Notice>} `choose` が真なら 51 で選ぶ
     */
    public function start(): array
    {
        $this->globals->setResume(StateId::CONSTRUCT);
        $options = $this->options();
        if (count($options) > 1) {
            return ['choose' => true, 'notices' => []];
        }

        return ['choose' => false, 'notices' => $this->execute($options[0]['kind'])];
    }

    /**
     * 51: 選択肢を選んで実行する。空文字は床アクション自体（分岐は先頭。zombie）。
     *
     * @return list<Notice>
     * @throws EffectViolation
     */
    public function choose(string $kind): array
    {
        $options = $this->options();
        if ($kind === '') {
            $kind = $options[0]['kind'];
        }
        if (!in_array($kind, array_column($options, 'kind'), true)) {
            throw new EffectViolation("That floor action is not available: {$kind}");
        }
        $this->globals->setResume(StateId::CONSTRUCT);

        return $this->execute($kind);
    }

    /**
     * @return list<Notice>
     */
    private function execute(string $kind): array
    {
        $turn = $this->globals->turnPlayer();
        $floorId = sprintf('F%02d', $this->floorNumber());
        $floor = FloorCards::get($floorId);
        if (str_starts_with($kind, 'ALT_')) {
            $alternative = ActionKind::from(substr($kind, 4));
            $discount = 0;
            foreach ($this->options() as $option) {
                if ($option['kind'] === $kind) {
                    $discount = $option['discount'] ?? 0;
                }
            }
            $main = EffectFrame::action(
                $alternative === ActionKind::ANALYSIS ? EffectFrame::KIND_ANALYZE : EffectFrame::KIND_COMPOSE,
                $turn,
                'FLOOR',
                $discount,
                StateId::FLOOR_ACTION_START,
            );
            $name = Notification::alternativeName($kind);
        } else {
            $frame = $this->engine->start($floorId, $turn, $floorId);
            if (str_starts_with($kind, 'FLOOR_')) {
                $this->engine->selectBranch($frame, ord(substr($kind, 6)) - ord('A'));
            }
            $main = EffectFrame::specRun($floorId, $turn, $floorId, params: ['decisions' => $frame->decisions]);
            $main = new EffectFrame(
                $main->kind,
                $main->owner,
                source: $main->source,
                spec: $main->spec,
                step: $frame->step,
                params: $main->params,
            );
            $name = $floor->roomName;
        }
        $this->stack->pushFront([$main, EffectFrame::handLimitCheck($turn)]);

        return [Notification::floorActionChosen($turn, ($this->nameOf)($turn), $kind, $name)];
    }
}
