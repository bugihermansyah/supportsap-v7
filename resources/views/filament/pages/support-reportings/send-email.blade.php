<x-filament-panels::page>
    <form wire:submit="send">
        {{ $this->form }}

        <div class="mt-6 flex flex-wrap gap-3">
            <x-filament::button type="submit" size="sm">
                Kirim Email
            </x-filament::button>
            
            <x-filament::button color="gray" size="sm" tag="a" :href="App\Filament\Resources\Support\SupportReportings\SupportReportingResource::getUrl('view', ['record' => $this->record])">
                Batal
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
