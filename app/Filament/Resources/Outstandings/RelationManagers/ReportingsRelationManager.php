<?php

namespace App\Filament\Resources\Outstandings\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReportingsRelationManager extends RelationManager
{
    protected static string $relationship = 'reportings';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('cause')
                    ->columnSpanFull(),
                Textarea::make('action')
                    ->columnSpanFull(),
                Textarea::make('solution')
                    ->columnSpanFull(),
                TextInput::make('work')
                    ->required()
                    ->default('visit'),
                DatePicker::make('date_visit')
                    ->required(),
                Select::make('user_id')
                    ->relationship('user', 'name'),
                Toggle::make('status'),
                DatePicker::make('revisit'),
                Textarea::make('note')
                    ->columnSpanFull(),
                Textarea::make('signature')
                    ->columnSpanFull(),
                DateTimePicker::make('start_work'),
                DateTimePicker::make('end_work'),
                DateTimePicker::make('send_mail_at'),
                DateTimePicker::make('user_created_at'),
                Textarea::make('email_to')
                    ->columnSpanFull(),
                Textarea::make('email_cc')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('cause')
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('work')
                    ->searchable(),
                TextColumn::make('date_visit')
                    ->date()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->searchable(),
                IconColumn::make('status')
                    ->boolean(),
                TextColumn::make('revisit')
                    ->date()
                    ->sortable(),
                TextColumn::make('start_work')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('end_work')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('send_mail_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user_created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // CreateAction::make(),
                // AssociateAction::make(),
            ])
            ->recordActions([
                // EditAction::make(),
                // DissociateAction::make(),
                // DeleteAction::make(),
            ])
            ->toolbarActions([
                // BulkActionGroup::make([
                //     DissociateBulkAction::make(),
                //     DeleteBulkAction::make(),
                // ]),
            ]);
    }
}
