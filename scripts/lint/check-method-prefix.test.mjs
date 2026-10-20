// check-method-prefix.mjs の自己テスト（仕様書 004 §2.8）。実行: node --test scripts/lint/
// PHP の断片は文字列として持つ（tests/ の PHP 構文検査や src/ 衛生の検査に触れないため）。
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { checkSources } from './check-method-prefix.mjs';

const NS = 'namespace Bga\\Games\\IRevolveThereforeIAm;\n';
const check = (...codes) => checkSources(codes.map((code, i) => ({ path: `f${i}.php`, code: `<?php\n${code}` })));
const fails = (r, re) => r.errors.some((e) => re.test(e));

test('Game extends Table の sendNotifications は失敗（姉妹の事故）', () => {
  const r = check(`${NS}class Game extends \\Bga\\GameFramework\\Table { public function sendNotifications() {} }`);
  assert.equal(r.errors.length, 1);
  assert.match(r.errors[0], /f0\.php:\d+: Game::sendNotifications は ir で始まっていません/);
});

test('irSendNotifications は成功。irregular・irq は失敗', () => {
  const ok = check(`${NS}class Game extends \\Bga\\GameFramework\\Table { function irSendNotifications() {} }`);
  assert.deepEqual(ok.errors, []);
  const ng = check(`${NS}class Game extends \\Bga\\GameFramework\\Table { function irregular() {} function irq() {} }`);
  assert.equal(ng.errors.length, 2);
});

test('状態クラスの private function record は失敗、irRecord は成功', () => {
  const head = `${NS}use Bga\\GameFramework\\States\\GameState;\nclass S extends GameState {`;
  assert.equal(check(`${head} private function record() {} }`).errors.length, 1);
  assert.deepEqual(check(`${head} private function irRecord() {} }`).errors, []);
});

test('onEnteringState・getArgs・zombie・__construct は成功', () => {
  const r = check(`${NS}use Bga\\GameFramework\\States\\GameState;
class S extends GameState {
  public function __construct() {}
  public function onEnteringState() {}
  public function getArgs() {}
  public function zombie() {}
}`);
  assert.deepEqual(r.errors, []);
  assert.equal(r.methods, 4);
  assert.equal(r.listUses, 4);
});

test('#[PossibleAction] 付きの actPass は成功、属性なしは失敗', () => {
  const head = `${NS}use Bga\\GameFramework\\States\\GameState;\nuse Bga\\GameFramework\\Actions\\Types\\PossibleAction;\nclass S extends GameState {`;
  assert.deepEqual(check(`${head}\n #[PossibleAction]\n public function actPass() {} }`).errors, []);
  assert.deepEqual(check(`${head} #[PossibleAction] public function actPass(int $x) {} }`).errors, []);
  assert.equal(check(`${head} public function actPass() {} }`).errors.length, 1);
  // 属性は直後のメソッドだけに効く
  assert.equal(check(`${head} #[PossibleAction] public function actA() {} public function actB() {} }`).errors.length, 1);
});

test('debug_ は Game では成功、状態クラスでは失敗', () => {
  assert.deepEqual(check(`${NS}class Game extends \\Bga\\GameFramework\\Table { function debug_goToState() {} }`).errors, []);
  const r = check(`${NS}use Bga\\GameFramework\\States\\GameState;\nclass S extends GameState { function debug_x() {} }`);
  assert.equal(r.errors.length, 1);
});

test('Game の setupNewGame などは成功、状態クラスでは失敗', () => {
  const g = `${NS}class Game extends \\Bga\\GameFramework\\Table {
    function setupNewGame() {} function getAllDatas() {} function getGameProgression() {} function upgradeTableDb() {} }`;
  assert.deepEqual(check(g).errors, []);
  assert.equal(check(`${NS}use Bga\\GameFramework\\States\\GameState;\nclass S extends GameState { function setupNewGame() {} }`).errors.length, 1);
});

test('use ... as Base と extends Base は対象と解釈される', () => {
  const r = check(`${NS}use Bga\\GameFramework\\States\\GameState as Base;\nclass S extends Base { function record() {} }`);
  assert.equal(r.classes, 1);
  assert.equal(r.errors.length, 1);
});

test('グループ use も解釈される', () => {
  const r = check(`${NS}use Bga\\GameFramework\\{Table as T, Other};\nclass G extends T { function record() {} }`);
  assert.equal(r.errors.length, 1);
});

test('基底クラスを挟んだ継承も対象（別ファイル）', () => {
  const base = `${NS}use Bga\\GameFramework\\States\\GameState;\nabstract class BaseState extends GameState { function irOk() {} }`;
  const child = `${NS}class Child extends BaseState { function record() {} }`;
  const r = check(base, child);
  assert.equal(r.classes, 2);
  assert.equal(r.errors.length, 1);
  assert.match(r.errors[0], /Child::record/);
});

test('フレームワークを継承しないクラス・インターフェース・enum は対象外', () => {
  const r = check(`namespace Bga\\Games\\IRevolveThereforeIAm\\Flow;
class Calc { function calc() {} }
interface I { function foo(); }
enum E: string { case A = 'a'; function label() {} }`);
  assert.deepEqual(r.errors, []);
  assert.equal(r.classes, 0);
});

test('トレイトのメソッドは対象クラスが use するときに検査される', () => {
  const tr = `${NS}trait T { function helper() {} function irOk() {} }`;
  const game = `${NS}class Game extends \\Bga\\GameFramework\\Table { use T; }`;
  const r = check(tr, game);
  assert.equal(r.errors.length, 1);
  assert.match(r.errors[0], /T::helper/);
  // 誰も use しないトレイトは対象外
  assert.deepEqual(check(tr).errors, []);
});

test('メソッド内のクロージャ、コメント・文字列・ヒアドキュメント中の function は無視される', () => {
  const r = check(`${NS}class Game extends \\Bga\\GameFramework\\Table {
  // function inComment() {}
  /* function inBlock() {} */
  # function inHash() {}
  public function irRun() {
    $f = function ($x) { return $x; };
    $g = function foo() {};
    $s = 'function inString() {}';
    $t = "function inDouble() {}";
    $h = <<<EOT
    function inHeredoc() {}
    EOT;
    return Game::class;
  }
}`);
  assert.deepEqual(r.errors, []);
  assert.equal(r.methods, 1);
});

test('// ir-lint: allow <理由> の直後のメソッドは成功、理由なしは失敗', () => {
  const head = `${NS}class Game extends \\Bga\\GameFramework\\Table {`;
  const ok = check(`${head}\n  // ir-lint: allow 公式ドキュメントで上書きが必須\n  public function onSomething() {} }`);
  assert.deepEqual(ok.errors, []);
  assert.equal(ok.commentUses, 1);
  const ng = check(`${head}\n  // ir-lint: allow\n  public function onSomething() {} }`);
  assert.equal(ng.errors.length, 1);
  assert.match(ng.errors[0], /理由が必要/);
  // 許可コメントは次のメソッドだけに効く
  const two = check(`${head}\n  // ir-lint: allow 理由\n  function a() {}\n  function b() {} }`);
  assert.equal(two.errors.length, 1);
  assert.match(two.errors[0], /Game::b/);
});

test('new class extends Table は失敗（未対応の構文）', () => {
  const r = check(`${NS}function f() { return new class extends \\Bga\\GameFramework\\Table { }; }`);
  assert.equal(r.errors.length, 1);
  assert.match(r.errors[0], /名前付きクラスに書き直して/);
});

test('複数の namespace は失敗', () => {
  const r = check('namespace A;\nclass X {}\nnamespace B;\nclass Y {}');
  assert.ok(fails(r, /namespace が複数/));
});

test('対象が無ければ成功', () => {
  assert.deepEqual(checkSources([]).errors, []);
});
