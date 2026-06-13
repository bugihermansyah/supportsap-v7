<?php

namespace App\Filament\Resources\Units\Schemas;

use App\Models\Unit;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class UnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make([
                    FileUpload::make('image')
                        ->image(),
                    TextInput::make('name')
                        ->required(),
                    Select::make('parent_id')
                        ->label('Parent Unit')
                        ->relationship('parent', 'name', fn (Builder $query, ?Unit $record) =>
                            $query->when($record, fn (Builder $q) =>
                                $q->where('id', '!=', $record->id)
                                  ->where(fn (Builder $sub) =>
                                      $sub->whereNull('parent_id')
                                          ->orWhere('parent_id', '!=', $record->id)
                                  )
                            )
                        )
                        ->placeholder('Select parent unit')
                        ->searchable()
                        ->preload(),
                    DatePicker::make('discontinued_at')
                        ->displayFormat('d M Y')
                        ->native(false)
                        ->label('Discontinue Date'),
                ])
            ]);
    }
}
