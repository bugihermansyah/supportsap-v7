<?php

namespace App\Livewire;

use Fahiem\FilamentPinpoint\Pinpoint;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Jeffgreco13\FilamentBreezy\Livewire\MyProfileComponent;

class UpdateUserProfile extends MyProfileComponent
{
    protected string $view = 'livewire.update-user-profile';

    public ?array $data = [];

    public static $sort = 11;

    public function mount(): void
    {
        $user = filament()->auth()->user();
        if ($user->profile) {
            $this->form->fill($user->profile->toArray());
        } else {
            $this->form->fill();
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Pinpoint::make('location')
                    ->dehydrated(false)
                    ->label('Map')
                    ->latField('lat')
                    ->lngField('lng')
                    ->draggable()
                    ->searchable()
                    ->addressField('address')
                    ->columnSpanFull(),
                Textarea::make('address')
                    ->label('Address')
                    ->required(),
                TextInput::make('lat')
                    ->label('Latitude')
                    ->required()
                    ->readOnly(),

                TextInput::make('lng')
                    ->label('Longitude')
                    ->required()
                    ->readOnly(),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        $user = filament()->auth()->user();
        
        if ($user->profile) {
            $user->profile->update($data);
        } else {
            $user->profile()->create($data);
        }

        Notification::make()
            ->success()
            ->title('Map coordinates updated successfully.')
            ->send();
    }
}
