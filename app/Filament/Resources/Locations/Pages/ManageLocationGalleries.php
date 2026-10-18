<?php

namespace App\Filament\Resources\Locations\Pages;

use App\Filament\Resources\Locations\LocationResource;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ManageLocationGalleries extends ManageRelatedRecords
{
    protected static string $resource = LocationResource::class;

    protected static string $relationship = 'reportings';

    protected static ?string $relationshipTitle = 'Gallery Report';

    public function table(Table $table): Table
    {
        $reportPhotoCollections = ['default', 'attachments'];

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->whereHas('media', fn (Builder $mediaQuery): Builder => $mediaQuery
                    ->whereIn('collection_name', [...$reportPhotoCollections, 'form_support'])))
            ->columns([
                TextColumn::make('outstanding.title')
                    ->label('Problem')
                    ->wrap(),
                SpatieMediaLibraryImageColumn::make('attachments')
                    ->label('Foto Report')
                    ->allCollections()
                    ->filterMediaUsing(fn ($media) => $media->whereIn('collection_name', $reportPhotoCollections))
                    ->imageGallery()
                    ->imageSize(88)
                    ->limit(6)
                    ->limitedRemainingText(),
                SpatieMediaLibraryImageColumn::make('form_support')
                    ->label('Form Support')
                    ->collection('form_support')
                    ->imageGallery()
                    ->imageSize(88)
                    ->limit(6)
                    ->limitedRemainingText(),
            ])
            ->groups([
                Group::make('date_visit')
                    ->label('Tanggal kunjungan')
                    ->date()
                    ->orderQueryUsing(fn (Builder $query): Builder => $query->orderBy('date_visit', 'desc')),
            ])
            ->defaultGroup('date_visit')
            ->defaultSort('date_visit', 'desc')
            ->groupingSettingsHidden()
            ->groupingDirectionSettingHidden()
            ->emptyStateHeading('Belum ada foto laporan')
            ->emptyStateDescription('Foto Report dan Form Support untuk lokasi ini akan muncul di sini, dikelompokkan berdasarkan tanggal kunjungan.')
            ->paginated([10, 25, 50]);
    }
}
