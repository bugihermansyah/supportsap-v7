<?php

namespace App\Filament\Resources\Support\SupportReportings\Schemas;

use App\Enums\OutstandingTypeProblem;
use App\Enums\ReportStatus;
use App\Models\Unit;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class SupportReportingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Details')
                        ->schema([
                            DatePicker::make('date_visit')
                                ->label('Action Date')
                                ->hiddenLabel()
                                ->default(Carbon::now())
                                ->native(false)
                                ->prefix('Action')
                                ->columnSpanFull()
                                ->required(),
                            ToggleButtons::make('work')
                                ->label('Action Type')
                                ->inline()
                                ->hiddenLabel()
                                ->options([
                                    'visit' => 'Visit',
                                    'remote' => 'Remote'
                                ])
                                ->colors([
                                    'visit' => 'info',
                                    'remote' => 'warning',
                                ])
                                ->default('visit')
                                ->grouped()
                                ->required(),
                            TextInput::make('cause')
                                ->label('Reason')
                                ->hiddenLabel()
                                ->placeholder('Reason')
                                ->required(),
                            RichEditor::make('action')
                                ->label('Action')
                                ->required()
                                ->toolbarButtons([
                                    'bold',
                                    'bulletList',
                                    'italic',
                                    'orderedList',
                                ])
                                ->extraInputAttributes([
                                    'style' => 'min-height: 90px;',
                                ]),
                            RichEditor::make('note')
                                ->label('Note')
                                ->toolbarButtons([
                                    'bold',
                                    'bulletList',
                                    'italic',
                                    'orderedList',
                                ])
                                ->extraInputAttributes([
                                    'style' => 'min-height: 50px;',
                                ])
                                ->columnSpanFull(),
                        ]),
                    Step::make('Statuss')
                        ->schema([
                            ToggleButtons::make('status')
                                ->inline()
                                ->live()
                                ->options(ReportStatus::class)
                                ->helperText(new HtmlString('Jika selain <strong>Finish</strong> wajib isi next target'))
                                ->required(),
                            DatePicker::make('revisit')
                                ->label('Revisit')
                                ->hiddenLabel()
                                ->placeholder('Date next target')
                                ->requiredIf('status', ['0', '2', '3', '4'])
                                ->hidden(function (Get $get) {
                                    $status = $get('status');

                                    if ($status instanceof ReportStatus) {
                                        return $status === ReportStatus::Finish;
                                    }

                                    return $status === '1';
                                })
                                ->native(true),
                            ToggleButtons::make('is_type_problem')
                                ->label('Problem Type')
                                ->hiddenLabel()
                                ->helperText(new HtmlString('Setiap <strong>tipe problem</strong> wajib menyertakan kerusakan unit'))
                                ->required()
                                ->options(OutstandingTypeProblem::class)
                                ->formatStateUsing(fn(Model $record) => $record->outstanding->is_type_problem ?? 'NON')
                                ->inline(),
                            Placeholder::make('table_repeater_style')
                                ->hiddenLabel()
                                ->content(new \Illuminate\Support\HtmlString('
                                    <style>
                                        .force-table-repeater > table { display: table !important; width: 100% !important; }
                                        .force-table-repeater > table > thead { display: table-header-group !important; }
                                        .force-table-repeater > table > tbody { display: table-row-group !important; }
                                        .force-table-repeater > table > tbody > tr { display: table-row !important; border:none !important; }
                                        .force-table-repeater > table > tbody > tr > td { display: table-cell !important; padding: 0.5rem 0.75rem !important; vertical-align: middle; }
                                        .force-table-repeater .fi-fo-field-label-content { display: none !important; }
                                        .force-table-repeater .fi-in-entry-label { display: none !important; }
                                        .force-table-repeater > table > tbody > tr > td.fi-hidden { display: none !important; }
                                        .force-table-repeater > table > tbody > tr > td:last-child { width: 1% !important; padding: 0 0.5rem !important; white-space: nowrap; }
                                        .force-table-repeater > table > thead > tr > th:last-child { width: 1% !important; padding: 0 !important; }
                                        .force-table-repeater > table > tbody > tr > td > .fi-fo-table-repeater-actions { padding: 0 !important; width: auto !important; margin: 0 !important; justify-content: center; }
                                    </style>
                                ')),
                            Repeater::make('outstandingUnits')
                                ->label('Unit')
                                ->hiddenLabel()
                                ->relationship()
                                ->extraAttributes(['class' => 'force-table-repeater'])
                                ->reorderable(false)
                                ->defaultItems(1)
                                ->minItems(1)
                                ->mutateRelationshipDataBeforeCreateUsing(function (array $data, Model $record): array {
                                    $data['location_id'] = $record->outstanding?->location_id;

                                    return $data;
                                })
                                ->table([
                                    TableColumn::make('Name'),
                                    TableColumn::make('Qty')
                                        ->width('70px'),
                                ])
                                ->schema([
                                    Select::make('unit_id')
                                        ->label('Unit')
                                        ->options(Unit::where('is_visible', 1)->pluck('name', 'id'))
                                        ->searchable()
                                        ->placeholder('Select unit')
                                        ->live(onBlur: true)
                                        ->required()
                                        ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                    TextInput::make('qty')
                                        ->numeric()
                                        ->default(1)
                                        ->required()
                                        ->maxValue(20)
                                        ->minValue(1),
                                ])
                                ->columns(2)
                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                                ->collapsible(),
                            SpatieMediaLibraryFileUpload::make('attachments')
                                ->image()
                                ->acceptedFileTypes(['image/jpeg', 'image/jpg', 'image/png'])
                                ->multiple()
                                ->maxSize(10240)
                                ->optimize('jpg', 70)
                                ->resize(70)
                                ->imageEditor()
                                ->panelLayout('grid')
                                ->openable()
                                ->collection('attachments')
                                ->downloadable()
                                ->maxImageWidth(1360)
                                // ->maxImageHeight(1080)
                                ->maxFiles(10)
                                ->preserveFilenames()
                                ->columnSpanFull(),
                        ]),
                ])
            ]);
    }
}
