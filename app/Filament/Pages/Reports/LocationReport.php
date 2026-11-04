<?php

namespace App\Filament\Pages\Reports;

use App\Enums\LocationStatus;
use App\Filament\Clusters\Locations\LocationCluster;
use App\Filament\Widgets\LocationProductGroupStats;
use App\Models\Company;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LocationReport extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_any_location') ?? false;
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $cluster = LocationCluster::class;

    protected string $view = 'filament.pages.reports.location-report';

    protected static ?string $title = 'Location Reporting';

    protected static ?string $navigationLabel = 'Ringkasan';

    protected static ?int $navigationSort = 20;

    protected function getHeaderWidgets(): array
    {
        return [
            LocationProductGroupStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Jumlah lokasi per company')
            ->query(Company::query()->withCount([
                'locations' => fn (Builder $query): Builder => $this->scopeLocationsToUserTeam($query),
                'locations as active_locations_count' => fn (Builder $query): Builder => $this->scopeLocationsToUserTeam($query)
                    ->where('status', LocationStatus::Active->value),
            ]))
            ->columns([
                TextColumn::make('alias')
                    ->label('Company')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('locations_count')
                    ->label('Total Lokasi')
                    ->sortable(),
                TextColumn::make('active_locations_count')
                    ->label('Lokasi Aktif')
                    ->sortable()
                    ->badge()
                    ->color('success'),
            ])
            ->defaultSort('active_locations_count', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50]);
    }

    private function scopeLocationsToUserTeam(Builder $query): Builder
    {
        $user = auth()->user();

        if ($user instanceof User && $user->hasTeamScopedSupportRole() && $user->team_id) {
            $query->where('team_id', $user->team_id);
        }

        return $query;
    }
}
