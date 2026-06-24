<?php

namespace App\Filament\Pages\Reports;

use Filament\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use App\Models\BorrowRequestUnit;
use Filament\Tables\Columns\TextColumn;

class ReportClaimUnit extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';
    
    protected static string|\UnitEnum|null $navigationGroup = 'Borrow Reports';

    protected string $view = 'filament.pages.report-claim-unit';

    protected static ?string $navigationLabel = 'Report Claim Unit';

    protected ?string $heading = 'Report Claim Unit';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(['super_admin', 'admin']) ?? false;
    }

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
                    ->label('Condition / Keterangan')
                    ->searchable(),
                TextColumn::make('borrowRequest.created_at')
                    ->label('Date Requested')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
