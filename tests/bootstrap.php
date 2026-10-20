<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

// BGA のフレームワークは Studio にしか無い。状態クラス・Game・Globals を読み込めるだけの最小のスタブ（tests/Support/Framework/）
foreach (glob(__DIR__ . '/Support/Framework/*.php') ?: [] as $stub) {
    require_once $stub;
}
