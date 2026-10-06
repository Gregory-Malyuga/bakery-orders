<?php

namespace Domains\User\Domain\Enums;

enum UserStatus: string
{
    case Verify = 'verify';
    case Pending = 'pending';
    case Banned = 'banned';

    public function label(): string
    {
        return match ($this) {
            self::Verify => 'Верифицирован',
            self::Pending => 'Ожидает верификации',
            self::Banned => 'Заблокирован',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
