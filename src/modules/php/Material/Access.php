<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 資源への触り方（008 §6.3.2）。
 */
enum Access: string
{
    /** 加算のみの書き込み。同じ記号の `+` どうしは交換可能 */
    case ADD = '+';

    /** 読み取り（選択がその資源の中身に依存する） */
    case READ = 'r';

    /** 上書き・非加算の書き込み */
    case WRITE = 'w';
}
