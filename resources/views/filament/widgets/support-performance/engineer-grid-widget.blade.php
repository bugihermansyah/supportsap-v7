<x-filament-widgets::widget>
    <div wire:poll.30s>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            @foreach($this->engineers as $engineer)
                <x-filament::section class="h-full flex flex-col">
                    <!-- Header: Avatar, Name, Role -->
                    <div class="flex items-center space-x-4 mb-4">
                        <x-filament::avatar
                            src="https://api.dicebear.com/7.x/adventurer/svg?seed={{ urlencode($engineer->name) }}"
                            size="lg"
                            alt="{{ $engineer->email }}"
                        />
                        <div>
                            <h3 class="text-lg font-bold">{{ $engineer->name }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $engineer->roles->first()?->name ?? 'Engineer' }}
                            </p>
                        </div>
                    </div>

                    <!-- Metrics Grid -->
                    <div class="grid grid-cols-2 gap-2 mb-4 text-sm">
                        <div class="flex flex-col">
                            <span class="text-gray-500">KPI</span>
                            <span class="font-semibold">{{ number_format($engineer->kpi, 1) }}</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-gray-500">Distance</span>
                            <span class="font-semibold">{{ number_format($engineer->distance, 2) }} KM</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-gray-500">Reporting</span>
                            <span class="font-semibold">{{ $engineer->reporting_count }}</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-gray-500">Outstanding</span>
                            <span class="font-semibold">{{ $engineer->outstanding_count }}</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-gray-500">Borrow</span>
                            <span class="font-semibold">{{ $engineer->borrow_count }}</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-gray-500">Status</span>
                            <span>
                                <x-filament::badge :color="$engineer->status_color" size="sm">
                                    {{ $engineer->status_label }}
                                </x-filament::badge>
                            </span>
                        </div>
                    </div>

                    <!-- Last Reporting & KPI Progress -->
                    <div class="mb-4 space-y-2">
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-500">Last Reporting</span>
                            <span class="font-medium">{{ $engineer->last_reporting }}</span>
                        </div>
                        
                        <div class="space-y-1">
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-500">Progress KPI</span>
                                <span class="font-medium">{{ min(100, number_format($engineer->kpi, 1)) }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                <div class="bg-primary-600 h-2 rounded-full" style="width: {{ min(100, $engineer->kpi) }}%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="mt-auto pt-4 border-t border-gray-200 dark:border-gray-700">
                        <x-filament::button 
                            tag="a" 
                            href="{{ \App\Filament\Pages\UserKpiReport::getUrl(['user_id' => $engineer->id]) }}" 
                            color="gray" 
                            size="sm" 
                            class="w-full justify-center"
                        >
                            View Detail
                        </x-filament::button>
                    </div>
                </x-filament::section>
            @endforeach
        </div>
        
        @if($this->engineers->isEmpty())
            <div class="flex items-center justify-center p-6 bg-white rounded-xl border border-gray-200 dark:bg-gray-900 dark:border-gray-800">
                <p class="text-gray-500">No engineers found matching the criteria.</p>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
