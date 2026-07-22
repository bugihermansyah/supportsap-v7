<?php

namespace App\Filament\Resources\ReportingEmails;

use App\Filament\Resources\ReportingEmails\Pages\ManageReportingEmails;
use App\Models\ReportingEmail;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReportingEmailResource extends Resource
{
    protected static ?string $model = ReportingEmail::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static string|\UnitEnum|null $navigationGroup = 'Support';
    
    protected static ?string $navigationLabel = 'Outbox Mail';
    
    protected static ?string $modelLabel = 'Outbox Mail';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('reporting_id')
                    ->relationship('reporting', 'id')
                    ->required(),
                TextInput::make('email_to')
                    ->email(),
                TextInput::make('email_cc')
                    ->email(),
                TextInput::make('cause'),
                Textarea::make('action')
                    ->columnSpanFull(),
                Textarea::make('note')
                    ->columnSpanFull(),
                DateTimePicker::make('start_work'),
                DateTimePicker::make('end_work'),
                DateTimePicker::make('send_mail_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('send_mail_at')
                    ->label('Dikirim Pada')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('reporting.outstanding.location.name')
                    ->label('Lokasi')
                    ->searchable(),
                TextColumn::make('reporting.outstanding.title')
                    ->label('Masalah')
                    ->searchable(),
                TextColumn::make('email_to')
                    ->label('Email To')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state),
                TextColumn::make('cause')
                    ->label('Sebab')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReportingEmails::route('/'),
        ];
    }
}
