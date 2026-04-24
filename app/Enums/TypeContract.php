<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TypeContract: string implements HasLabel
{
    case Sewa = 'sewa';
    case Putus = 'putus';
    case Kredit = 'kredit';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sewa => 'Sewa',
            self::Putus => 'Putus',
            self::Kredit => 'Kredit',
        };
    }
}
