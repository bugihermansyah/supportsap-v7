<?php

namespace App\Filament\Widgets\Admin;

use App\Models\BorrowRequestUnit;
use App\Enums\BorrowRequestStatus;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class BorrowDeliveredTable extends BaseWidget
{
    protected static ?string $heading = 'Delivered Units';
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 3;

    public static function canView(): bool
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
                    ->whereHas('borrowRequest', function (Builder $query) {
                        $query->where('log_status', BorrowRequestStatus::Delivered);
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
                    ->label('Unit Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('qty')
                    ->label('Qty')
                    ->sortable(),
                TextColumn::make('borrowRequest.log_at')
                    ->label('Delivered At')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
