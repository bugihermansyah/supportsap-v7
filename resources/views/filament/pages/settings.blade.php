<x-filament-panels::page>
    @php
        $components = $this->getRegisteredMyProfileComponents();
        $firstKey = array_key_first($components);
    @endphp
    
    <div x-data="{ activeTab: '{{ $firstKey }}' }"
         x-effect="$nextTick(() => setTimeout(() => window.dispatchEvent(new Event('resize')), 300))"
         class="space-y-6">
        <x-filament::tabs>
            @foreach ($components as $key => $component)
                @unless(is_null($component))
                    <x-filament::tabs.item
                        alpine-active="activeTab === '{{ $key }}'"
                        x-on:click="activeTab = '{{ $key }}'; $nextTick(() => setTimeout(() => window.dispatchEvent(new Event('resize')), 300))"
                    >
                        {{ str($key)->replace('_', ' ')->title() }}
                    </x-filament::tabs.item>
                @endunless
            @endforeach
        </x-filament::tabs>

        <div class="relative">
            @foreach ($components as $key => $component)
                @unless(is_null($component))
                    <div
                        x-bind:class="activeTab === '{{ $key }}' ? 'relative' : 'invisible h-0 overflow-hidden absolute'"
                    >
                        @livewire($component)
                    </div>
                @endunless
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
