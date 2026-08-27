<?php

namespace App\Filament\Resources\Quiz\QuizQuestions\Tables;

use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class QuizQuestionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('product')->withCount('options'))
            ->columns([
                SpatieMediaLibraryImageColumn::make('image')
                    ->label('Gambar')
                    ->collection('image')
                    ->circular(false)
                    ->height(40),
                TextColumn::make('question')
                    ->label('Pertanyaan')
                    ->wrap()
                    ->limit(120)
                    ->searchable(),
                TextColumn::make('product.name')
                    ->label('Kategori')
                    ->badge()
                    ->sortable(),
                TextColumn::make('options_count')
                    ->label('Pilihan')
                    ->badge()
                    ->color(fn ($state) => $state >= 2 ? 'gray' : 'danger'),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('creator.name')
                    ->label('Dibuat oleh')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('product_id')
                    ->label('Kategori')
                    ->options(fn () => Product::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
                TernaryFilter::make('is_active')
                    ->label('Aktif'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
