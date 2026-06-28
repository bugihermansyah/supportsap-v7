<?php

namespace App\Filament\Resources\Support\SupportReportings\Tables;

use App\Filament\Resources\Support\SupportReportings\SupportReportingResource;
use App\Models\Reporting;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupportReportingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();
                if ($user && $user->hasAnyRole(['head_support', 'support'])) {
                    $query->whereHas('outstanding.location', fn ($q) => $q->where('team_id', $user->team_id));
                }
            })
            ->recordUrl(
                fn (Reporting $record): string => SupportReportingResource::getUrl('send-email', ['record' => $record]),
            )
            ->columns([
                TextColumn::make('outstanding.location.name')
                    ->label('Location')
                    ->searchable(),
                TextColumn::make('outstanding.reporter')
                    ->label('Reporter')
                    ->searchable()
                    ->formatStateUsing(fn ($state) => ucwords($state)),
                TextColumn::make('users.name')
                    ->label('Support')
                    ->badge()
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->searchable(),
                TextColumn::make('date_visit')
                    ->label('Date Visit')
                    ->date('d M Y'),
                TextColumn::make('outstanding.title')
                    ->label('Problem')
                    ->searchable(),
                TextColumn::make('cause')
                    ->label('Cause')
                    ->searchable(),
                TextColumn::make('action')
                    ->label('Action')
                    ->searchable()
                    ->wrap()
                    ->lineClamp(2)
                    ->html(),
                TextColumn::make('note')
                    ->label('Note')
                    ->searchable()
                    ->wrap()
                    ->lineClamp(2)
                    ->html(),
                TextColumn::make('status'),
                TextColumn::make('score')
                    ->label('Score')
                    ->badge()
                    ->color(fn ($state) => $state >= 90 ? 'success' : ($state >= 80 ? 'warning' : 'danger')),
                TextColumn::make('reportingUsers.distance')
                    ->label('Dist (KM)')
                    ->numeric(decimalPlaces: 0),
                IconColumn::make('send_mail_at')
                    ->label('Notif')
                    ->boolean(),
            ])
            ->defaultSort('date_visit', 'desc')
            ->filters([
                SelectFilter::make('reporter')
                    ->label('Reporter')
                    ->options([
                        'client' => 'Client',
                        'preventif' => 'Preventive',
                        'support' => 'Internal'
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $value) => $query->whereHas(
                                'outstanding', 
                                fn (Builder $query) => $query->where('reporter', $value)
                            )
                        );
                    })
            ])
            ->recordActions([
                ViewAction::make()
                    ->hiddenLabel(),
                Action::make('sendMail')
                    ->hiddenLabel()
                    ->icon('heroicon-o-envelope')
                    ->color('success')
                    ->disabled(fn ($record) => empty($record->status))
                    ->url(fn ($record): string => SupportReportingResource::getUrl('send-email', ['record' => $record])),
                Action::make('evaluate')
                    ->label('Evaluate')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('info')
                    ->hiddenLabel()
                    ->fillForm(SupportReportingResource::getEvaluationFillFormCallback())
                    ->form(SupportReportingResource::getEvaluationFormSchema())
                    ->action(SupportReportingResource::getEvaluationActionCallback()),
                EditAction::make()
                    ->hiddenLabel(),
            ])
            ->toolbarActions([]);
    }
}
