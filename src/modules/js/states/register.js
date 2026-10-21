/**
 * 操作 UI の状態クラスを登録する（U-10。仮のボタンの置き換え）。移動の計画（31）は MoveState.js。
 * 状態と操作: 21 初期自我のドラフト・24 人格の配置（SetupStates.js）、60 自己構成フェイズ・51 床アクションの選択・72 手札上限（TurnStates.js）、
 * 61 自己構成（ComposeState.js）、62 精神分析（AnalysisState.js）、71 効果の選択（ChoiceState.js）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { AnalysisState } from './AnalysisState.js';
import { ChoiceState } from './ChoiceState.js';
import { ComposeState } from './ComposeState.js';
import { SetupEgoPickState, SetupMeeplePlaceState } from './SetupStates.js';
import { ConstructState, FloorChooseState, HandLimitState } from './TurnStates.js';

/**
 * @param {Bga} bga
 * @param {() => import('./ActionState.js').ActionDeps} getDeps setup のあとで作られる盤面への参照
 */
export function registerActionStates(bga, getDeps) {
  bga.states.register('SetupEgoPick', new SetupEgoPickState(bga, getDeps));
  bga.states.register('SetupMeeplePlace', new SetupMeeplePlaceState(bga, getDeps));
  bga.states.register('Construct', new ConstructState(bga, getDeps));
  bga.states.register('FloorActionChoose', new FloorChooseState(bga, getDeps));
  bga.states.register('Compose', new ComposeState(bga, getDeps));
  bga.states.register('Analysis', new AnalysisState(bga, getDeps));
  bga.states.register('EffectChoice', new ChoiceState(bga, getDeps));
  bga.states.register('HandLimitDiscard', new HandLimitState(bga, getDeps));
}
