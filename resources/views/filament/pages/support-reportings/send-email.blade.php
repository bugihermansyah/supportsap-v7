<x-filament-panels::page>
    <style>
        .support-reporting-email-layout {
            display: grid;
            grid-template-columns: minmax(18rem, 1fr) minmax(0, 3fr);
            gap: 1.5rem;
            align-items: start;
        }

        .support-reporting-email-layout > form {
            min-width: 0;
        }

        @media (max-width: 1199px) {
            .support-reporting-email-layout {
                grid-template-columns: minmax(0, 1fr);
            }
        }
    </style>

    <div class="support-reporting-email-layout">
        <form wire:submit="saveTicketDetails" novalidate>
            {{ $this->ticketDetailsForm }}

            <div class="mt-6">
                <x-filament::button
                    type="submit"
                    size="sm"
                    wire:loading.attr="disabled"
                    wire:target="saveTicketDetails"
                    :disabled="! $this->canEditTicketDetails()"
                    class="min-w-36"
                >
                    Save Changes
                </x-filament::button>
            </div>
        </form>

        <form wire:submit="send" novalidate>
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
    </div>

    <div class="mt-8">
        <h3 class="text-lg font-medium mb-4">Riwayat Pengiriman Email</h3>
        {{ $this->table }}
    </div>
</x-filament-panels::page>
