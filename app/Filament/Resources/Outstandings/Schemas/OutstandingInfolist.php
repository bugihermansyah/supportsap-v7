<?php

namespace App\Filament\Resources\Outstandings\Schemas;

use App\Models\Outstanding;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OutstandingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID'),
                TextEntry::make('number')
                    ->placeholder('-'),
                TextEntry::make('location_id'),
                TextEntry::make('product_id')
                    ->placeholder('-'),
                TextEntry::make('team_id')
                    ->placeholder('-'),
                TextEntry::make('title')
                    ->placeholder('-'),
                TextEntry::make('reporter')
                    ->placeholder('-'),
                TextEntry::make('reporter_name')
                    ->placeholder('-'),
                TextEntry::make('date_in')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('date_visit')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('date_finish')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('date_temporary')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('lpm')
                    ->state(fn (Outstanding $record): string => $record->lpm == 1 ? 'Laporan Awal Masuk' : '-'),
                IconEntry::make('is_implement')
                    ->boolean(),
                IconEntry::make('is_type_problem')
                    ->boolean(),
                IconEntry::make('is_oncall')
                    ->boolean(),
                IconEntry::make('is_temporary')
                    ->boolean(),
                TextEntry::make('priority'),
                IconEntry::make('status')
                    ->boolean(),
                TextEntry::make('user_id')
                    ->placeholder('-'),
                TextEntry::make('note')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('create_user_id')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
