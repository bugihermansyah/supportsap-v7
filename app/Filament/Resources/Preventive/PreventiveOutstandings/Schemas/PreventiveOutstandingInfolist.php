<?php

namespace App\Filament\Resources\Preventive\PreventiveOutstandings\Schemas;

use Alsaloul\ImageGallery\Infolists\Entries\ImageGalleryEntry;
use Carbon\Carbon;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PreventiveOutstandingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make()
                            ->schema([
                                TextEntry::make('title')
                                    ->label('Problem'),
                                TextEntry::make('location.full_name')
                                    ->label('Location'),
                                TextEntry::make('product.name')
                                    ->label('Product'),
                                TextEntry::make('team.name')
                                    ->label('Team'),
                                TextEntry::make('reporter_name')
                                    ->label('Reporter'),
                                TextEntry::make('date_in')
                                    ->label('Date Info')
                                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('d M Y') : '-'),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->formatStateUsing(fn ($state) => $state ? $state->getLabel() : '-'),
                                TextEntry::make('is_type_problem')
                                    ->label('Type Problem')
                                    ->formatStateUsing(fn ($state) => $state ? $state->getLabel() : '-'),
                                TextEntry::make('user.name')
                                    ->label('Created By'),
                            ]),
                    ])
                    ->columnSpan(1),
                Group::make()
                    ->schema([
                        Section::make()
                            ->schema([
                                RepeatableEntry::make('reportings')
                                    ->label('Reportings')
                                    ->table([
                                        TableColumn::make('Date'),
                                        TableColumn::make('Reason'),
                                        TableColumn::make('Action'),
                                        TableColumn::make('Note'),
                                        TableColumn::make('Status'),
                                        TableColumn::make('Support'),
                                        TableColumn::make('Photo'),
                                    ])
                                    ->schema([
                                        TextEntry::make('date_visit')
                                            ->label('Date')
                                            ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('d M Y') : '-'),
                                        TextEntry::make('cause')
                                            ->label('Cause'),
                                        TextEntry::make('action')
                                            ->label('Action')
                                            ->html(),
                                        TextEntry::make('note')
                                            ->label('Note')
                                            ->html(),
                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->formatStateUsing(fn ($state) => \App\Enums\ReportStatus::tryFrom((string) $state)?->getLabel() ?? '-')
                                            ->color(fn ($state) => \App\Enums\ReportStatus::tryFrom((string) $state)?->getColor() ?? 'gray')
                                            ->icon(fn ($state) => \App\Enums\ReportStatus::tryFrom((string) $state)?->getIcon() ?? null),
                                        TextEntry::make('users.name')
                                            ->label('Support'),
                                        SpatieMediaLibraryImageEntry::make('attachments')
                                            ->collection('attachments') // Optional but good practice if you want
                                            ->imageGallery()
                                            ->imageHeight(20)
                                            ->stacked()
                                            ->ring(6)
                                            ->overlap(5)
                                            ->limit(2)
                                            ->limitedRemainingText()
                                            ->circular(),
                                    ])
                            ])
                    ])
                    ->columnSpan(4),
            ])
            ->columns(5);
                
    }
}
