<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Inerba\DbConfig\AbstractPageSettings;

class MailSettings extends AbstractPageSettings
{
    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    protected static ?string $title = 'Mail Settings';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-envelope';

    protected static ?int $navigationSort = 2;

    protected function settingName(): string
    {
        return 'mail';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Administration';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_mail_settings') ?? false;
    }

    /**
     * Provide default values.
     *
     * @return array<string, mixed>
     */
    public function getDefaultData(): array
    {
        return [
            'to_helpdesk' => '',
            'to_barat' => '',
            'cc_barat' => '',
            'to_timur' => '',
            'cc_timur' => '',
            'to_pusat' => '',
            'cc_pusat' => '',
            'to_cass_barat' => '',
            'cc_cass_barat' => '',
            'to_luar_kota' => '',
            'cc_luar_kota' => '',
            'to_admin_support' => '',
            'to_manager_support' => '',
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([                
                Section::make('HO Support')
                    ->description('Email tujuan untuk HO Support')
                    ->icon('heroicon-o-user-group')
                    ->visible(fn() => !auth()->user()?->hasRole('head_support'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('to_manager_support')
                            ->label('To Manager Support')
                            ->email()
                            ->placeholder('manager-support@mail.com'),
                        TextInput::make('to_helpdesk')
                            ->label('To Helpdesk')
                            ->email()
                            ->placeholder('helpdesk@mail.com'),
                        TextInput::make('to_admin_support')
                            ->label('To Admin Support')
                            ->email()
                            ->placeholder('admin-support@mail.com'),
                    ]),

                Section::make('Barat')
                    ->description('Email tujuan dan CC untuk Barat')
                    ->icon('heroicon-o-envelope')
                    ->visible(fn() => !auth()->user()?->hasRole('head_support') || auth()->user()?->team?->name === 'Barat')
                    ->columns(2)
                    ->schema([
                        TextInput::make('to_barat')
                            ->label('To Barat')
                            ->email()
                            ->placeholder('barat@mail.com'),

                        TagsInput::make('cc_barat')
                            ->label('CC Barat')
                            ->trim()
                            ->placeholder('cc-barat@mail.com'),
                    ]),

                Section::make('Timur')
                    ->description('Email tujuan dan CC untuk Timur')
                    ->icon('heroicon-o-envelope')
                    ->visible(fn() => !auth()->user()?->hasRole('head_support') || auth()->user()?->team?->name === 'Timur')
                    ->columns(2)
                    ->schema([
                        TextInput::make('to_timur')
                            ->label('To Timur')
                            ->email()
                            ->placeholder('timur@mail.com'),

                        TagsInput::make('cc_timur')
                            ->label('CC Timur')
                            ->trim()
                            ->placeholder('cc-timur@mail.com'),
                    ]),

                Section::make('Pusat')
                    ->description('Email tujuan dan CC untuk Pusat')
                    ->icon('heroicon-o-envelope')
                    ->visible(fn() => !auth()->user()?->hasRole('head_support') || auth()->user()?->team?->name === 'Pusat')
                    ->columns(2)
                    ->schema([
                        TextInput::make('to_pusat')
                            ->label('To Pusat')
                            ->email()
                            ->placeholder('pusat@mail.com'),

                        TagsInput::make('cc_pusat')
                            ->label('CC Pusat')
                            ->trim()
                            ->placeholder('cc-pusat@mail.com'),
                    ]),

                Section::make('CASS Barat')
                    ->description('Email tujuan dan CC untuk CASS Barat')
                    ->icon('heroicon-o-envelope')
                    ->visible(fn() => !auth()->user()?->hasRole('head_support') || auth()->user()?->team?->name === 'CASS Barat')
                    ->columns(2)
                    ->schema([
                        TextInput::make('to_cass_barat')
                            ->label('To CASS Barat')
                            ->email()
                            ->placeholder('cass-barat@mail.com'),

                        TagsInput::make('cc_cass_barat')
                            ->label('CC CASS Barat')
                            ->trim()
                            ->placeholder('cc-cass-barat@mail.com'),
                    ]),

                Section::make('Luar Kota')
                    ->description('Email tujuan dan CC untuk Luar Kota')
                    ->icon('heroicon-o-envelope')
                    ->visible(fn() => !auth()->user()?->hasRole('head_support') || auth()->user()?->team?->name === 'Luar Kota')
                    ->columns(2)
                    ->schema([
                        TextInput::make('to_luar_kota')
                            ->label('To Luar Kota')
                            ->email()
                            ->placeholder('luar-kota@mail.com'),

                        TagsInput::make('cc_luar_kota')
                            ->label('CC Luar Kota')
                            ->trim()
                            ->placeholder('cc-luar-kota@mail.com'),
                    ]),
            ])
            ->statePath('data');
    }
}
