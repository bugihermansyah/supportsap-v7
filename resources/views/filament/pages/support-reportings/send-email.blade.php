<x-filament-panels::page>
    <form wire:submit="send">
        {{ $this->form }}

        <div class="mt-6 flex flex-wrap gap-3">
            <x-filament::button type="submit" size="sm">
                Kirim Email
            </x-filament::button>
            
            <x-filament::button color="gray" size="sm" tag="a" :href="App\Filament\Resources\Support\SupportReportings\SupportReportingResource::getUrl('index', ['record' => $this->record])">
                Batal
            </x-filament::button>
        </div>
    </form>

    <div class="mt-8">
        <h3 class="text-lg font-medium mb-4">Riwayat Pengiriman Email</h3>
        {{ $this->table }}
    </div>
</x-filament-panels::page>
