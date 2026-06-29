<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
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
            // KPI Parameters
            'kpi_base_score' => 100,
            'kpi_late_penalty_h1' => 10,
            'kpi_late_penalty_h2' => 20,
            'kpi_late_penalty_h3' => 50,
            'kpi_no_photo_penalty' => 15,
            'kpi_no_form_penalty' => 30,
            'kpi_sameday_bonus' => 15,
            'kpi_bonus_very_hard' => 15,
            'kpi_bonus_hard' => 10,
            'kpi_grade_a_plus_min' => 100,
            'kpi_grade_a_min' => 85,
            'kpi_grade_b_min' => 70,
            'kpi_grade_c_min' => 50,
        ];
    }



    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Settings')
                    ->tabs([
                        Tab::make('Brand')
                            ->icon('heroicon-o-building-office')
                            ->schema([
                                Section::make('Brand')
                                    ->description('Konfigurasi brand aplikasi')
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
                            ]),
                        Tab::make('KPI Parameters')
                            ->icon('heroicon-o-chart-bar')
                            ->schema([
                                Section::make('Nilai Dasar & Batas Grade')
                                    ->columns(4)
                                    ->schema([
                                        TextInput::make('kpi_base_score')
                                            ->label('Skor Dasar')
                                            ->numeric()
                                            ->minValue(1)
                                            ->required(),

                                        TextInput::make('kpi_grade_a_min')
                                            ->label('Min. Grade A')
                                            ->numeric()
                                            ->required(),

                                        TextInput::make('kpi_grade_b_min')
                                            ->label('Min. Grade B')
                                            ->numeric()
                                            ->required(),

                                        TextInput::make('kpi_grade_c_min')
                                            ->label('Min. Grade C')
                                            ->numeric()
                                            ->required(),
                                    ]),

                                Section::make('Bonus Level & Inisiatif')
                                    ->columns(3)
                                    ->schema([
                                        TextInput::make('kpi_bonus_hard')
                                            ->label('Level Hard')
                                            ->prefix('+')
                                            ->numeric()
                                            ->required(),

                                        TextInput::make('kpi_bonus_very_hard')
                                            ->label('Level Very Hard')
                                            ->prefix('+')
                                            ->numeric()
                                            ->required(),

                                        TextInput::make('kpi_sameday_bonus')
                                            ->label('Sameday Progress')
                                            ->prefix('+')
                                            ->numeric()
                                            ->required(),
                                    ]),

                                Section::make('Penalti Report')
                                    ->columns(5)
                                    ->schema([
                                        TextInput::make('kpi_late_penalty_h1')
                                            ->label('Report H+1')
                                            ->prefix('-')
                                            ->numeric()
                                            ->required(),

                                        TextInput::make('kpi_late_penalty_h2')
                                            ->label('Report H+2')
                                            ->prefix('-')
                                            ->numeric()
                                            ->required(),

                                        TextInput::make('kpi_late_penalty_h3')
                                            ->label('Report H+3 ke atas')
                                            ->prefix('-')
                                            ->numeric()
                                            ->required(),

                                        TextInput::make('kpi_no_photo_penalty')
                                            ->label('Tidak Ada Foto')
                                            ->prefix('-')
                                            ->numeric()
                                            ->required(),

                                        TextInput::make('kpi_no_form_penalty')
                                            ->label('Tidak Ada Form Support')
                                            ->prefix('-')
                                            ->numeric()
                                            ->required(),
                                    ]),
                            ]),

                    ])
                    ->persistTabInQueryString(),
            ])
            ->statePath('data');
    }
}
