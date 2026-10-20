<?php

declare(strict_types=1);

namespace Bga\GameFramework;

/**
 * BGA フレームワークの最小のスタブ（PHPUnit 専用。本番の src/ には入れない）。状態クラスを読み込んで宣言を検査するためのもの。
 */
enum StateType: string
{
    case ACTIVE_PLAYER = 'activeplayer';
    case MULTIPLE_ACTIVE_PLAYER = 'multipleactiveplayer';
    case PRIVATE = 'private';
    case GAME = 'game';
}
