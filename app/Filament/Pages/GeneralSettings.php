<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Inerba\DbConfig\AbstractPageSettings;

class GeneralSettings extends AbstractPageSettings
{
    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    protected static ?string $title = 'General Settings';

    protected static ?int $navigationSort = 1;

    protected function settingName(): string
    {
        return 'general';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Administration';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_general_settings') ?? false;
    }

    /**
     * Provide default values.
     *
     * @return array<string, mixed>
     */
    public function getDefaultData(): array
    {
        return [
            'brand_name' => '',
            'brand_logo' => '',
            'site_favicon' => '',
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Brand')
                    ->description('Konfigurasi brand aplikasi')
                    ->icon('heroicon-o-building-office')
                    ->columns(2)
                    ->schema([
                        TextInput::make('brand_name')
                            ->label('Brand Name')
                            ->placeholder('Masukkan nama brand')
                            ->columnSpanFull(),

                        FileUpload::make('brand_logo')
                            ->label('Brand Logo')
                            ->image()
                            ->directory('settings')
                            ->maxSize(2048),

                        FileUpload::make('site_favicon')
                            ->label('Favicon')
                            ->image()
                            ->directory('settings')
                            ->maxSize(1024),
                    ]),
            ])
            ->statePath('data');
    }
}
