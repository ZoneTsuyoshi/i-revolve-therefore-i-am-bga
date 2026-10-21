<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Trigger;

use Bga\Games\IRevolveThereforeIAm\Effects\Catalog\SpecSource;
use Bga\Games\IRevolveThereforeIAm\Effects\Condition\ConditionContext;
use Bga\Games\IRevolveThereforeIAm\Effects\Condition\ConditionEvaluator;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameEvent;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameView;
use Bga\Games\IRevolveThereforeIAm\Material\EffectKind;
use Bga\Games\IRevolveThereforeIAm\Material\EffectLimit;
use Bga\Games\IRevolveThereforeIAm\Material\Event;
use Bga\Games\IRevolveThereforeIAm\Material\Mechanism;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCards;

/**
 * イベントに反応する `TRIGGER` の収集（008 §4.2）。ターンプレイヤーの自己構成済みカードと自我カードだけが対象（手札の効果は働かない）。
 *
 * 1. イベントを起こしたのがターンプレイヤーでなければ何も誘発しない（§4.1。想起の発動者の操作に反応する効果は無い）。
 * 2. `hook` にそのイベントを持つ効果で、`ONCE_PER_TURN` が未使用、`conditions` を満たすものが候補。
 * 3. EV-05 では、自己構成したカード自身の即時効果（`INSTANT`）だけが自身に反応し、その起動効果は反応しない（R8-3、Q-52）。
 *    即時効果は、自己構成したカード自身にだけ反応する。
 */
final class TriggerCollector
{
    public function __construct(
        private readonly GameView $view,
        private readonly SpecSource $specs,
        private readonly ConditionEvaluator $conditions,
    ) {
    }

    /**
     * @return list<TriggeredEffect> 自己構成済みカード → 自我カードの順、カード内は定義順
     */
    public function collect(GameEvent $event): array
    {
        $owner = $this->view->turnPlayer();
        if ($event->playerId !== $owner) {
            return [];
        }
        $payload = $event->payload;
        $composedType = is_string($payload['card_type'] ?? null) ? $payload['card_type'] : null;
        $card = $composedType !== null ? MemoryCards::get($composedType) : null;

        $sources = [];
        foreach ($this->view->tableauIds($owner) as $cardId) {
            $sources[] = $this->view->cardType($cardId);
        }
        foreach ($this->view->egoIds($owner) as $egoId) {
            $sources[] = $egoId;
        }

        $out = [];
        foreach ($sources as $source) {
            foreach ($this->specs->forCard($source) as $spec) {
                if ($spec->mechanism !== Mechanism::TRIGGER || !in_array($event->event, $spec->events, true)) {
                    continue;
                }
                if ($event->event === Event::COMPOSED) {
                    $isOwnCard = $source === $composedType;
                    if ($spec->kind === EffectKind::INSTANT ? !$isOwnCard : $isOwnCard) {
                        continue;
                    }
                }
                if ($spec->limit === EffectLimit::ONCE_PER_TURN && $this->view->isOnceUsed($owner, $spec->id)) {
                    continue;
                }
                if (!$this->conditions->all($spec->conditions, new ConditionContext($owner, $card, $payload))) {
                    continue;
                }
                $out[] = new TriggeredEffect($spec, $source, $payload);
            }
        }

        return $out;
    }
}
