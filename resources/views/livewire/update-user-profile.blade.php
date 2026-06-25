<x-filament::section :aside="true" heading="Map Coordinates" description="Update your map coordinates.">
    <form wire:submit.prevent="submit" class="space-y-6">

        {{ $this->form }}

        <div class="text-right">
            <x-filament::button type="submit" form="submit" class="align-right">
                Save
            </x-filament::button>
        </div>
    </form>
</x-filament::section>
