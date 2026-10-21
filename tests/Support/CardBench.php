<?php

declare(strict_types=1);

namespace IRevolveTests\Support;

use Bga\Games\IRevolveThereforeIAm\Data\TurnContext;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameEvent;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Material\Event;

/**
 * カード効果の結合テストの道具: `MoveFixture`（SQLite 上の 3 人戦。ターンプレイヤーは 11）に、イベントを起こし、
 * 解決スタック（70・71）を台本の答えで最後まで進める。
 */
final class CardBench
{
    public const P = 11;

    public function __construct(public readonly MoveFixture $fx = new MoveFixture())
    {
        // 70 の戻り先（ターン開始時の効果の後は 31）
        $this->fx->globals->setResume(\Bga\Games\IRevolveThereforeIAm\Flow\StateId::MOVE);
    }

    /** 自我カードを持たせる。 */
    public function ego(string ...$ids): void
    {
        foreach ($ids as $id) {
            $this->fx->egos->move($id, 'OWNED', self::P);
        }
    }

    /**
     * このターンの手番内カウンタを書き換える。
     *
     * @param \Closure(TurnContext): void $edit
     */
    public function turn(\Closure $edit): void
    {
        $context = $this->fx->globals->turnContext();
        $edit($context);
        $this->fx->globals->setTurnContext($context);
    }

    /**
     * イベントを起こして、誘発した効果を解決スタックに積む。
     *
     * @param array<string, scalar|null> $payload
     * @return bool 積んだか
     */
    public function emit(Event $event, array $payload = []): bool
    {
        return $this->fx->kit->triggers->emit([new GameEvent($event, self::P, $payload)]);
    }

    /**
     * 解決スタックを、選択以外では自動で進める。選択には台本の答えを順に返す。
     * 答え: 配列 = その内容で答える、`'use'` / `'no'` = 確認に答える、`'skip'` = スキップ、`'auto'` = 既定の答え。
     * 台本が尽きたらスキップ（できなければ既定）。
     *
     * @param list<array<string, mixed>|string> $script
     * @return string 止まった遷移（`Transition::*`）
     */
    public function drive(array $script = []): string
    {
        $this->choices = [];
        for ($i = 0; $i < 60; $i++) {
            $result = $this->fx->kit->resolver->resolve();
            array_push($this->notices, ...$result->notices);
            if ($result->transition !== Transition::CHOICE) {
                return $result->transition;
            }
            $args = $this->fx->kit->choices->args();
            $this->choices[] = (string) ($args['public']['choiceKind'] ?? 'BRANCH');
            $answer = array_shift($script) ?? 'skip';
            $notices = match (true) {
                $answer === 'use' => $this->fx->kit->choices->answer(self::P, ['use' => true]),
                $answer === 'no' => $this->fx->kit->choices->answer(self::P, ['use' => false]),
                $answer === 'auto' => $this->fx->kit->choices->auto(self::P),
                $answer === 'skip' => $this->skipOrAuto(),
                default => $this->fx->kit->choices->answer(self::P, $answer),
            };
            array_push($this->notices, ...$notices);
        }
        throw new \LogicException('the effects did not settle');
    }

    /** @var list<string> 直近の `drive()` で聞かれた選択の種類 */
    public array $choices = [];

    /** @var list<\Bga\Games\IRevolveThereforeIAm\Flow\Notice> 解決の途中で出た通知（送る順） */
    public array $notices = [];

    /**
     * @return list<string> 出た通知の種類
     */
    public function noticeTypes(): array
    {
        return array_map(static fn ($n): string => $n->type, $this->notices);
    }

    /**
     * @return list<\Bga\Games\IRevolveThereforeIAm\Flow\Notice>
     */
    private function skipOrAuto(): array
    {
        try {
            return $this->fx->kit->choices->skip(self::P);
        } catch (EffectViolation) {
            return $this->fx->kit->choices->auto(self::P);
        }
    }

    public function hand(): int
    {
        return $this->fx->memory->count(\Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation::Hand, self::P);
    }

    public function light(): int
    {
        return $this->fx->players->light(self::P);
    }
}
