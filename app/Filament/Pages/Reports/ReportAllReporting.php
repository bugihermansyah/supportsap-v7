<?php

namespace App\Filament\Pages\Reports;

use App\Models\Location;
use App\Models\Product;
use App\Models\Reporting;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ReportAllReporting extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected string $view = 'filament.pages.reports.report-count-outstanding'; // Reusing the simple table wrapper view

    protected static ?string $title = 'Report All Reporting';

    protected static ?string $navigationLabel = 'All Reporting Detail';

    protected static string|\UnitEnum|null $navigationGroup = 'Support Reports';

    protected static ?int $navigationSort = 16;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Reporting::query()
                    ->with(['outstanding.location.company', 'outstanding.product', 'users', 'outstanding.outstandingUnits.unit'])
                    ->whereHas('outstanding', function (Builder $q) {
                        $user = auth()->user();
                        if ($user->hasRole('head_support')) {
                            $q->whereHas('location', function ($lq) use ($user) {
                                $lq->where('team_id', $user->team_id);
                            });
                        }
                    })
            )
            ->columns([
                TextColumn::make('outstanding.number')
                    ->label('No. Tiket')
                    ->limit(13)
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('outstanding.location.company.alias')
                    ->label('Group')
                    ->limit(13)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('outstanding.location.name')
                    ->label('Lokasi')
                    ->limit(10)
                    ->sortable(),
                TextColumn::make('outstanding.product.name')
                    ->label('Produk')
                    ->limit(15)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('outstanding.reporter')
                    ->label('Pelapor')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->formatStateUsing(fn ($state) => ucfirst($state)),
                TextColumn::make('outstanding.date_in')
                    ->label('Lapor')
                    ->date()
                    ->sortable(),
                TextColumn::make('outstanding.date_visit')
                    ->label('SLA Visit')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('outstanding.date_finish')
                    ->label('SLA Selesai')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('users.name')
                    ->label('Support')
                    ->searchable(),
                TextColumn::make('work')
                    ->label('Tipe')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->formatStateUsing(fn ($state) => ucfirst($state)),
                TextColumn::make('date_visit')
                    ->label('Visit')
                    ->date()
                    ->sortable(),
                TextColumn::make('outstanding.title')
                    ->label('Masalah')
                    ->searchable()
                    ->limit(20),
                TextColumn::make('cause')
                    ->label('Sebab')
                    ->limit(20)
                    ->html(),
                TextColumn::make('action')
                    ->label('Aksi')
                    ->limit(20)
                    ->html(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('outstanding.is_type_problem')
                    ->label('Tipe Problem')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->badge(),
                TextColumn::make('outstanding.outstandingUnits.unit.name')
                    ->label('Unit')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->badge(),
                TextColumn::make('note')
                    ->label('Ket.')
                    ->limit(20)
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->html(),
            ])
            ->filtersFormColumns(2)
            ->filters([
                SelectFilter::make('team')
                    ->label('Team')
                    ->options(fn () => \App\Models\Team::pluck('name', 'id'))
                    ->visible(fn () => auth()->user()?->hasRole('helpdesk'))
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['value'])) {
                            $query->whereHas('outstanding.location', function ($q) use ($data) {
                                $q->where('team_id', $data['value']);
                            });
                        }
                    }),
                SelectFilter::make('company_id')
                    ->label('Group')
                    ->multiple()
                    ->options(function () {
                        $q = Location::with('company');
                        $user = auth()->user();
                        if ($user->hasRole('head_support')) {
                            $q->where('team_id', $user->team_id);
                        }
                        return $q->get()->pluck('company.alias', 'company.id')->filter();
                    })
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['values'])) {
                            $query->whereHas('outstanding.location', function ($q) use ($data) {
                                $q->whereIn('company_id', $data['values']);
                            });
                        }
                    }),
                SelectFilter::make('location_id')
                    ->label('Location')
                    ->multiple()
                    ->options(function () {
                        $q = Location::query();
                        $user = auth()->user();
                        if ($user->hasRole('head_support')) {
                            $q->where('team_id', $user->team_id);
                        }
                        return $q->pluck('name', 'id');
                    })
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['values'])) {
                            $query->whereHas('outstanding', function ($q) use ($data) {
                                $q->whereIn('location_id', $data['values']);
                            });
                        }
                    }),
                SelectFilter::make('product_id')
                    ->label('Product')
                    ->multiple()
                    ->options(Product::pluck('name', 'id'))
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['values'])) {
                            $query->whereHas('outstanding', function ($q) use ($data) {
                                $q->whereIn('product_id', $data['values']);
                            });
                        }
                    }),
                SelectFilter::make('user_id')
                    ->label('Support')
                    ->multiple()
                    ->options(function () {
                        $q = User::query();
                        $user = auth()->user();
                        if ($user->hasRole('head_support')) {
                            $q->where('team_id', $user->team_id);
                        }
                        return $q->pluck('name', 'id');
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['values'])) {
                            return $query;
                        }
                        return $query->whereHas('users', function (Builder $q) use ($data) {
                            $q->whereIn('users.id', $data['values']);
                        });
                    }),
                SelectFilter::make('reporter')
                    ->label('Reporter')
                    ->multiple()
                    ->options([
                        'client' => 'Client',
                        'preventif' => 'Preventive',
                        'support' => 'Internal'
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['values'])) {
                            $query->whereHas('outstanding', function ($q) use ($data) {
                                $q->whereIn('reporter', $data['values']);
                            });
                        }
                    }),
                Filter::make('report_date')
                    ->form([
                        DatePicker::make('reported_from')->label('Report From'),
                        DatePicker::make('reported_until')->label('Report Until'),
                    ])
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['reported_from'] ?? null) {
                            $indicators[] = Indicator::make('Reported from ' . Carbon::parse($data['reported_from'])->toFormattedDateString())
                                ->removeField('reported_from');
                        }
                        if ($data['reported_until'] ?? null) {
                            $indicators[] = Indicator::make('Reported until ' . Carbon::parse($data['reported_until'])->toFormattedDateString())
                                ->removeField('reported_until');
                        }
                        return $indicators;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->whereHas('outstanding', function ($q) use ($data) {
                            $q->when(
                                $data['reported_from'],
                                fn (Builder $q, $date) => $q->whereDate('date_in', '>=', $date)
                            )->when(
                                $data['reported_until'],
                                fn (Builder $q, $date) => $q->whereDate('date_in', '<=', $date)
                            );
                        });
                    }),
                TernaryFilter::make('lpm')
                    ->label('Laporan /1 masuk')
                    ->query(function (Builder $query, array $data) {
                        if ($data['value'] === null) return $query;
                        $val = $data['value'] === true || $data['value'] === '1';
                        return $query->whereHas('outstanding', fn ($q) => $q->where('lpm', $val));
                    }),
                TernaryFilter::make('is_implement')
                    ->label('Implement')
                    ->query(function (Builder $query, array $data) {
                        if ($data['value'] === null) return $query;
                        $val = $data['value'] === true || $data['value'] === '1';
                        return $query->whereHas('outstanding', fn ($q) => $q->where('is_implement', $val));
                    }),
                TernaryFilter::make('is_oncall')
                    ->label('On Call')
                    ->query(function (Builder $query, array $data) {
                        if ($data['value'] === null) return $query;
                        $val = $data['value'] === true || $data['value'] === '1';
                        return $query->whereHas('outstanding', fn ($q) => $q->where('is_oncall', $val));
                    }),
                SelectFilter::make('sla_visit')
                    ->label('SLA Visit')
                    ->options([
                        'sla1' => 'SLA 1',
                        'sla2' => 'SLA 2',
                        'sla3' => 'SLA 3',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) return $query;
                        return $query->whereHas('outstanding', function (Builder $q) use ($data) {
                            switch ($data['value']) {
                                case 'sla1': return $q->whereRaw('DATEDIFF(date_visit, date_in) <= 1');
                                case 'sla2': return $q->whereRaw('DATEDIFF(date_visit, date_in) BETWEEN 2 AND 3');
                                case 'sla3': return $q->whereRaw('DATEDIFF(date_visit, date_in) > 3');
                            }
                        });
                    }),
                SelectFilter::make('sla_finish')
                    ->label('SLA Finish')
                    ->options([
                        'sla1' => 'SLA 1',
                        'sla2' => 'SLA 2',
                        'sla3' => 'SLA 3',
                        'sla4' => 'null',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) return $query;
                        return $query->whereHas('outstanding', function (Builder $q) use ($data) {
                            switch ($data['value']) {
                                case 'sla1': return $q->whereRaw('DATEDIFF(date_finish, date_in) <= 3');
                                case 'sla2': return $q->whereRaw('DATEDIFF(date_finish, date_in) BETWEEN 4 AND 7');
                                case 'sla3': return $q->whereRaw('DATEDIFF(date_finish, date_in) > 7');
                                case 'sla4': return $q->whereNull('date_finish');
                            }
                        });
                    }),
                SelectFilter::make('work')
                    ->options([
                        'visit' => 'Visit',
                        'remote' => 'Remote'
                    ]),
                Filter::make('date_visit')
                    ->form([
                        DatePicker::make('visited_from')->label('Visit From'),
                        DatePicker::make('visited_until')->label('Visit Until'),
                    ])
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['visited_from'] ?? null) {
                            $indicators[] = Indicator::make('Visited from ' . Carbon::parse($data['visited_from'])->toFormattedDateString())->removeField('visited_from');
                        }
                        if ($data['visited_until'] ?? null) {
                            $indicators[] = Indicator::make('Visited until ' . Carbon::parse($data['visited_until'])->toFormattedDateString())->removeField('visited_until');
                        }
                        return $indicators;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['visited_from'], fn (Builder $q, $date) => $q->whereDate('date_visit', '>=', $date))
                            ->when($data['visited_until'], fn (Builder $q, $date) => $q->whereDate('date_visit', '<=', $date));
                    }),
            ], layout: FiltersLayout::Modal)
            ->filtersFormSchema(fn (array $filters): array => array_filter([
                Fieldset::make('Outstanding')
                    ->schema(array_filter([
                        $filters['team'] ?? null,
                        $filters['company_id'],
                        $filters['location_id'],
                        $filters['product_id'],
                        $filters['reporter'],
                        $filters['sla_visit'],
                        $filters['sla_finish'],
                        $filters['report_date'],
                    ]))
                    ->columns(2)
                    ->columnSpanFull(),
                Fieldset::make('Status')
                    ->schema([
                        $filters['lpm'],
                        $filters['is_implement'],
                        $filters['is_oncall'],
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Fieldset::make('Reporting')
                    ->schema([
                        $filters['user_id'],
                        $filters['work'],
                        $filters['date_visit'],
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]))
            ->deferFilters()
            ->filtersTriggerAction(
                fn (Action $action) => $action->button()->label('Filter')
            )
            ->headerActions([
                ExportAction::make()->exports([
                    ExcelExport::make()
                        ->askForWriterType()
                        ->withFilename(date('Y-m-d H:i:s') . ' - report-all-reporting')
                        ->fromTable()
                ])
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['head_support', 'helpdesk']) ?? false;
    }
}
