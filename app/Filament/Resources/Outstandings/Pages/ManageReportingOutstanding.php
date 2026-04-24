<?php

namespace App\Filament\Resources\Outstandings\Pages;

use App\Filament\Resources\Outstandings\OutstandingResource;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ManageReportingOutstanding extends ManageRelatedRecords
{
    protected static string $resource = OutstandingResource::class;

    protected static string $relationship = 'reportings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('cause')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('cause'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('cause')
            ->columns([
                TextColumn::make('date_visit')
                    ->label('Date Action')
                    ->date('d F Y')
                    ->searchable(),
                TextColumn::make('users.name')
                    ->label('User')
                    ->searchable(),
                TextColumn::make('start_work')
                    ->label('Start')
                    ->dateTime('H:i:s'),
                TextColumn::make('end_work')
                    ->label('End')
                    ->dateTime('H:i:s'),
                TextColumn::make('work_duration')
                    ->label('Duration')
                    ->getStateUsing(function ($record) {
                        if ($record->start_work && $record->end_work) {
                            $start = Carbon::parse($record->start_work);
                            $end = Carbon::parse($record->end_work);
                            
                            return $start->diffForHumans($end, [
                                'parts' => 2, // Menampilkan 2 unit waktu, misalnya: "2 hours 30 minutes"
                                'syntax' => Carbon::DIFF_ABSOLUTE, // Menghilangkan kata seperti "ago"
                            ]);
                        }
                        return '-'; // Jika salah satu kolom tidak ada nilainya
                    }),
                TextColumn::make('work')
                    ->label('Type')
                    ->formatStateUsing(fn ($state) => ucwords($state)),
                TextColumn::make('cause')
                    ->label('Reason')
                    ->html(),
                TextColumn::make('action')
                    ->label('Action')
                    ->html(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth(Width::ScreenExtraLarge),
                ViewAction::make()
                    ->modalWidth(Width::ScreenExtraLarge),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
