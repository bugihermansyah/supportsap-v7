<?php

namespace App\Filament\Pages;

use Jeffgreco13\FilamentBreezy\Pages\MyProfilePage as BaseProfilePage;

class Settings extends BaseProfilePage
{
    protected static bool $shouldRegisterNavigation = false;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected string $view = 'filament.pages.settings';
}
