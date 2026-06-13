<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum BorrowRequestType: string implements HasColor, HasLabel, HasIcon
{
    case Borrow = 'borrow';
    case Replacement = 'replacement';
    case Backup = 'backup';
    case PullRequest = 'pull_request';

    public function getLabel(): string
    {
        return match ($this) {
            self::Borrow => 'Borrow',
            self::Replacement => 'Replacement',
            self::Backup => 'Backup',
            self::PullRequest => 'Pull Request',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Borrow => 'info',
            self::Replacement => 'warning',
            self::Backup => 'success',
            self::PullRequest => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Borrow => 'heroicon-m-arrow-right-on-rectangle',
            self::Replacement => 'heroicon-m-arrow-path-rounded-square',
            self::Backup => 'heroicon-m-shield-check',
            self::PullRequest => 'heroicon-m-arrow-uturn-left',
        };
    }
}
