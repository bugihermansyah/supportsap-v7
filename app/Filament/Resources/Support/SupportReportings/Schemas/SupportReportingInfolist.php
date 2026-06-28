<?php

namespace App\Filament\Resources\Support\SupportReportings\Schemas;

use App\Models\Reporting;
use App\Models\ReportingUser;
use Fahiem\FilamentPinpoint\PinpointEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupportReportingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make([
                    Section::make('Details')
                        ->schema([
                            TextEntry::make('date_visit')
                                ->label('Action Date')
                                ->date('d M Y'),
                            TextEntry::make('work')
                                ->label('Action Type')
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'visit' => 'info',
                                    'remote' => 'warning',
                                    default => 'gray',
                                }),
                            TextEntry::make('cause')
                                ->label('Reason'),
                            TextEntry::make('status')
                                ->badge(),
                            TextEntry::make('action')
                                ->html()
                                ->columnSpanFull(),
                            TextEntry::make('note')
                                ->html()
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                        
                    Section::make('Support Journey')
                        ->schema([
                            RepeatableEntry::make('reportingUsers')
                                ->label('User Maps')
                                ->hiddenLabel()
                                ->schema([
                                    Group::make()
                                        ->inlineLabel()
                                        ->schema([
                                            TextEntry::make('user.name')
                                                ->label('Support'),
                                            TextEntry::make('distance')
                                                ->label('Distance (KM)'),
                                            TextEntry::make('duration')
                                                ->label('Duration')
                                                ->formatStateUsing(function ($state) {
                                                    if (!$state) return '-';
                                                    $totalMinutes = floor($state / 60);
                                                    $hours = floor($totalMinutes / 60);
                                                    $minutes = $totalMinutes % 60;
                                                    if ($hours > 0) {
                                                        return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";
                                                    }
                                                    return "{$minutes}m";
                                                }),
                                            TextEntry::make('origin_name')
                                                ->label('Origin')
                                                ->icon('heroicon-m-map-pin')
                                                ->iconColor('danger'),
                                            TextEntry::make('dest_name')
                                                ->label('Destination')
                                                ->icon('heroicon-m-map-pin')
                                                ->iconColor('success'),
                                        ]),
                                    PinpointEntry::make('map')
                                        ->hiddenLabel()
                                        ->latField('dest_lat')
                                        ->lngField('dest_lng')
                                        ->height(300)
                                        ->pins(function(ReportingUser $record) {
                                            $pins = [];
                                            if ($record->origin_lat && $record->origin_lng) {
                                                $pins[] = [
                                                    'lat' => (float)$record->origin_lat,
                                                    'lng' => (float)$record->origin_lng,
                                                    'label' => 'Origin: ' . $record->origin_name,
                                                    'color' => 'red',
                                                ];
                                            }
                                            if ($record->dest_lat && $record->dest_lng) {
                                                $pins[] = [
                                                    'lat' => (float)$record->dest_lat,
                                                    'lng' => (float)$record->dest_lng,
                                                    'label' => 'Destination: ' . $record->dest_name,
                                                    'color' => 'green',
                                                ];
                                            }
                                            return $pins;
                                        })
                                ])
                                ->columns(2)
                                ->columnSpanFull()
                        ]),
                ])
                ->columnSpan(['lg' => 2]),

                Group::make([
                    Section::make('Evaluation')
                        ->schema([
                            TextEntry::make('score')
                                ->label('Score (KPI)')
                                ->size('lg')
                                ->weight('bold')
                                ->color(fn($state) => $state >= 80 ? 'success' : ($state >= 60 ? 'warning' : 'danger')),
                            TextEntry::make('evaluation_note')
                                ->label('Evaluation Note')
                                ->placeholder('No evaluation note provided.')
                        ]),
                    Section::make('Attachments')
                        ->schema([
                            SpatieMediaLibraryImageEntry::make('attachments')
                                ->label('Photos')
                                ->collection('attachments')
                                ->hiddenLabel(),
                        ]),
                    Section::make('Form Support')
                        ->schema([
                            SpatieMediaLibraryImageEntry::make('form_support')
                                ->label('Forms')
                                ->collection('form_support')
                                ->hiddenLabel(),
                        ]),
                ])
                ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }
}
