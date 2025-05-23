<?php

/*
 * Copyright (c) 2024.
 *
 * Filename: UserEnum.php
 * Project Name: ninshiki-backend
 * Project Repository: https://github.com/ninshiki-project/Ninshiki-backend
 *  License: MIT
 *  GitHub: https://github.com/MarJose123
 *  Written By: Marjose123
 */

namespace App\Enum;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum UserEnum: string implements HasColor, HasIcon, HasLabel
{
    case Invited = 'invited';
    case Active = 'active';
    case Deactivate = 'deactivated';

    public function getColor(): array
    {
        return match ($this) {
            self::Invited => Color::Orange,
            self::Active => Color::Green,
            self::Deactivate => Color::Gray,
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Invited => 'heroicon-o-envelope',
            self::Active => 'heroicon-o-check-circle',
            self::Deactivate => 'heroicon-o-arrow-trending-down',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Invited => 'Invited',
            self::Active => 'Active',
            self::Deactivate => 'Deactivate',
        };
    }
}
