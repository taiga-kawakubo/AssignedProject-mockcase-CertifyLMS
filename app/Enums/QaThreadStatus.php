<?php

declare(strict_types=1);

namespace App\Enums;

/**
 *  質問掲示板の状態を表す Enum。
 *
 * - 未解決、解決済みの２つの種類を表す。
 * - 投稿者が質問掲示板に投稿、未解決に戻すボタンを押すとOpen(未解決)となり、
 *   解決済みとした場合、Resolved（解決済み）となる。
 */
enum QaThreadStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => '未解決',
            self::Resolved => '解決済み',
        };
    }
}
