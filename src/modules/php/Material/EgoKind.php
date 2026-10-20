<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 自我カードの種類（002 §9、§12）。
 */
enum EgoKind: string
{
    /** 初期自我（I01〜I08） */
    case INITIAL = 'INITIAL';

    /** 強化自我（E01〜E05）。第三階層到達時に獲得する */
    case ENHANCEMENT = 'ENHANCEMENT';
}
