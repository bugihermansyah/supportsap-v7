<x-filament::section :aside="false" heading="Simulasi KPI" description="Hitung simulasi skor KPI berdasarkan skenario.">
    <form wire:submit.prevent="simulateScore" class="space-y-6">
        {{ $this->form }}

        <div class="text-left mt-4">
            <x-filament::button type="submit">
                Hitung Simulasi
            </x-filament::button>
        </div>
    </form>
</x-filament::section>
