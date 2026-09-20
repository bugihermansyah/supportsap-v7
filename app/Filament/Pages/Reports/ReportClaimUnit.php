<?php

namespace App\Filament\Pages\Reports;

use App\Enums\BorrowRequestStatus;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use App\Models\BorrowRequestUnit;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ReportClaimUnit extends Page implements HasTable
{
    use InteractsWithTable;
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';
    
    protected static string|\UnitEnum|null $navigationGroup = 'Borrow Reports';

    protected string $view = 'filament.pages.report-claim-unit';

    protected static ?string $navigationLabel = 'Report Claim Unit';

    protected ?string $heading = 'Report Claim Unit';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                BorrowRequestUnit::query()
                    ->with(['borrowRequest', 'unit'])
                    ->where('is_claim', true)
                    ->whereHas('borrowRequest', function ($query) {
                        $query->whereNotIn('status', ['cancelled', 'rejected']);
                    })
            )
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('borrowRequest.rp_no')
                    ->label('No. RP')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('borrowRequest.location.name')
                    ->label('Location')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('unit.name')
                    ->label('Unit')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('qty')
                    ->label('Qty')
                    ->sortable(),
                TextColumn::make('damage')
                    ->label('Condition')
                    ->searchable(),
                TextColumn::make('borrowRequest.so_no')
                    ->label('No. SO')
                    ->searchable(),
                TextColumn::make('borrowRequest.created_at')
                    ->label('Date Requested')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('requester')
                    ->label('Requester')
                    ->relationship('borrowRequest.requester', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('location')
                    ->label('Location')
                    ->relationship('borrowRequest.location', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('approved_from')
                    ->schema([
                        DatePicker::make('approved_from')
                            ->label('From Date Request')
                            ->default(now()->startOfMonth()->toDateString()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['approved_from'] ?? null,
                            fn (Builder $query, $date): Builder => $query->whereHas('borrowRequest.logs', function (Builder $q) use ($date) {
                                $q->where('action', BorrowRequestStatus::Approved->value)
                                    ->whereDate('created_at', '>=', $date);
                            })
                        );
                    }),
                Filter::make('approved_until')
                    ->schema([
                        DatePicker::make('approved_until')
                            ->label('Until Date Request')
                            ->default(now()->endOfMonth()->toDateString()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['approved_until'] ?? null,
                            fn (Builder $query, $date): Builder => $query->whereHas('borrowRequest.logs', function (Builder $q) use ($date) {
                                $q->where('action', BorrowRequestStatus::Approved->value)
                                    ->whereDate('created_at', '<=', $date);
                            })
                        );
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                ExportAction::make()->exports([
                    ExcelExport::make()
                        ->askForWriterType()
                        ->withFilename(date('Y-m-d H:i:s') . ' - recap-claim-unit')
                        ->fromTable()
                ])
                    
            ]);
    }
}
