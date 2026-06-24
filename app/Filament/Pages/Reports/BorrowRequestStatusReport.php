<?php

namespace App\Filament\Pages\Reports;

use App\Models\BorrowRequestUnit;
use App\Enums\BorrowRequestStatus;
use App\Models\BorrowRequest;
use Filament\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use pxlrbt\FilamentExcel\Actions\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class BorrowRequestStatusReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';
    protected static string|\UnitEnum|null $navigationGroup = 'Borrow Reports';
    protected static ?string $title = 'Report Borrow Request';
    protected string $view = "filament.pages.reports.borrow-request-status-report";
    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                BorrowRequestUnit::query()
                    ->with([
                        'borrowRequest.requester',
                        'borrowRequest.location.company',
                        'borrowRequest.logs',
                        'unit',
                    ])
                    ->whereHas('borrowRequest', function (Builder $query) {
                        $query->where('status', '!=', BorrowRequestStatus::Draft->value)
                            ->where('warehouse_id', 1);
                    })
            )
            ->columns([
                TextColumn::make('approved_date')
                    ->label('Request Date')
                    ->state(function (BorrowRequestUnit $record): ?string {
                        return $record->borrowRequest?->created_at?->format('d/m/Y');
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            BorrowRequest::select('created_at')
                                ->whereColumn('borrow_requests.id', 'borrow_request_units.borrow_request_id')
                                ->limit(1),
                            $direction
                        );
                    }),

                TextColumn::make('unit.name')
                    ->label('Unit')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('borrowRequest.requester.name')
                    ->label('Requester')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('borrowRequest.location.full_name')
                    ->label('Location')
                    ->limit(22)
                    ->tooltip(fn ($record) => $record->borrowRequest?->location?->full_name)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('borrowRequest.location', function (Builder $q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            \App\Models\Location::select('name')
                                ->join('borrow_requests', 'borrow_requests.location_id', '=', 'locations.id')
                                ->whereColumn('borrow_requests.id', 'borrow_request_units.borrow_request_id')
                                ->limit(1),
                            $direction
                        );
                    }),

                TextColumn::make('target_kirim')
                    ->label('Target Delivery')
                    ->state(function (BorrowRequestUnit $record): ?string {
                        $log = $record->borrowRequest?->logs
                            ->where('action', BorrowRequestStatus::DeliveryScheduled)
                            ->first();
                        return $log?->date ? \Carbon\Carbon::parse($log->date)->format('d/m/Y') : null;
                    }),

                TextColumn::make('tgl_realisasi')
                    ->label('Actual Delivery')
                    ->state(function (BorrowRequestUnit $record): ?string {
                        $log = $record->borrowRequest?->logs
                            ->where('action', BorrowRequestStatus::Delivered)
                            ->first();
                        return $log?->date ? \Carbon\Carbon::parse($log->date)->format('d/m/Y') : null;
                    }),

                // TextColumn::make('target_ambil')
                //     ->label('Target Pickup')
                //     ->state(function (BorrowRequestUnit $record): ?string {
                //         $log = $record->borrowRequest?->logs
                //             ->where('action', BorrowRequestStatus::PickupScheduled)
                //             ->first();
                //         return $log?->date ? \Carbon\Carbon::parse($log->date)->format('d/m/Y') : null;
                //     }),

                // TextColumn::make('tgl_realisasi_ambil')
                //     ->label('Actual Pickup')
                //     ->state(function (BorrowRequestUnit $record): ?string {
                //         $log = $record->borrowRequest?->logs
                //             ->where('action', BorrowRequestStatus::PickedUp)
                //             ->first();
                //         return $log?->date ? \Carbon\Carbon::parse($log->date)->format('d/m/Y') : null;
                //     }),

                TextColumn::make('qty')
                    ->label('Qty')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('borrowRequest.rp_no')
                    ->label('NO RP')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('RP Copied!'),
                TextColumn::make('borrowRequest.send_no')
                    ->label('REQ KRM')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('REQ KRM Copied!'),
                TextColumn::make('borrowRequest.take_no')
                    ->label('REQ AMB')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('REQ AMB Copied!'),
                TextColumn::make('borrowRequest.status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(function ($state, \App\Models\BorrowRequestUnit $record) {
                        $statusLabel = $state?->getLabel();
                        
                        if (in_array($state?->value, ['cancelled', 'rejected'])) {
                            $log = $record->borrowRequest->logs()
                                ->whereIn('action', ['cancelled', 'rejected'])
                                ->whereNotNull('note')
                                ->latest()
                                ->first();
                                
                            if ($log && $log->note) {
                                return $statusLabel . ' (' . $log->note . ')';
                            }
                        }
                        
                        return $statusLabel;
                    })
                    ->color(function ($state) {
                        return $state?->getColor();
                    }),
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
                            ->label('From Date Request'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['approved_from'],
                            fn (Builder $query, $date): Builder => $query->whereHas('borrowRequest.logs', function (Builder $q) use ($date) {
                                $q->where('action', BorrowRequestStatus::Approved->value)
                                  ->whereDate('created_at', '>=', $date);
                            })
                        );
                    }),
                Filter::make('approved_until')
                    ->schema([
                        DatePicker::make('approved_until')
                            ->label('Until Date Request'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['approved_until'],
                            fn (Builder $query, $date): Builder => $query->whereHas('borrowRequest.logs', function (Builder $q) use ($date) {
                                $q->where('action', BorrowRequestStatus::Approved->value)
                                  ->whereDate('created_at', '<=', $date);
                            })
                        );
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->defaultSort('created_at', 'desc')
            ->persistFiltersInSession()
            ->headerActions([
                ExportAction::make()->exports([
                    ExcelExport::make()
                        ->askForWriterType()
                        ->withFilename(date('Y-m-d H:i:s') . ' - recap-borrow-request-status')
                        ->fromTable()
                ])
                    
            ]);
    }
}
