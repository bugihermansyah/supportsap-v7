<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Header & Stats -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <!-- Profile Card -->
            <x-filament::section class="md:col-span-1 h-full">
                <div class="flex flex-col items-center justify-center h-full p-4 text-center">
                    <img src="https://api.dicebear.com/7.x/adventurer/svg?seed={{ $user->name }}" alt="{{ $user->name }}" class="w-32 h-32 rounded-full border-4 border-primary-500 shadow-md">
                    <h2 class="mt-4 text-xl font-bold">{{ $user->name }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                    
                    <div class="mt-4 flex gap-2 flex-wrap justify-center">
                        @foreach($user->roles as $role)
                            <x-filament::badge color="primary">{{ $role->name }}</x-filament::badge>
                        @endforeach
                    </div>
                </div>
            </x-filament::section>

            <!-- Stats -->
            <div class="md:col-span-2 grid grid-cols-2 gap-4">
                <x-filament::section class="flex flex-col items-center justify-center">
                    <div class="text-center">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg KPI Score</p>
                        <h3 class="text-4xl font-bold text-primary-600 dark:text-primary-400">{{ $avgScore }}</h3>
                    </div>
                </x-filament::section>
                <x-filament::section class="flex flex-col items-center justify-center">
                    <div class="text-center">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Reportings</p>
                        <h3 class="text-4xl font-bold text-success-600 dark:text-success-400">{{ $totalReportings }}</h3>
                    </div>
                </x-filament::section>
                <x-filament::section class="flex flex-col items-center justify-center">
                    <div class="text-center">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Outstandings</p>
                        <h3 class="text-4xl font-bold text-warning-600 dark:text-warning-400">{{ $totalOutstandings }}</h3>
                    </div>
                </x-filament::section>
                <x-filament::section class="flex flex-col items-center justify-center">
                    <div class="text-center">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Borrow Requests</p>
                        <h3 class="text-4xl font-bold text-danger-600 dark:text-danger-400">{{ $totalBorrows }}</h3>
                    </div>
                </x-filament::section>
            </div>
        </div>

        <!-- Activities & Settings -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <!-- Timeline -->
            <x-filament::section heading="Recent Activities" class="md:col-span-1 h-fit">
                <div class="flow-root">
                    <ul role="list" class="-mb-8">
                        @forelse($recentActivities as $index => $activity)
                            <li>
                                <div class="relative pb-8">
                                    @if(!$loop->last)
                                        <span class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700" aria-hidden="true"></span>
                                    @endif
                                    <div class="relative flex space-x-3">
                                        <div>
                                            <span class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white dark:ring-gray-900 {{ $activity['type'] == 'reporting' ? 'bg-success-500' : 'bg-primary-500' }}">
                                                @if($activity['type'] == 'reporting')
                                                    <x-heroicon-m-check-circle class="w-5 h-5 text-white" />
                                                @else
                                                    <x-heroicon-m-clipboard-document-list class="w-5 h-5 text-white" />
                                                @endif
                                            </span>
                                        </div>
                                        <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                            <div>
                                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $activity['title'] }} <br><span class="font-medium text-gray-900 dark:text-white">{{ $activity['description'] }}</span></p>
                                                @if($activity['score'])
                                                    <p class="mt-1 text-sm"><x-filament::badge color="success">Score: {{ $activity['score'] }}</x-filament::badge></p>
                                                @endif
                                            </div>
                                            <div class="whitespace-nowrap text-right text-xs text-gray-500 dark:text-gray-400">
                                                @if($activity['date'])
                                                    <time datetime="{{ $activity['date'] }}">{{ \Carbon\Carbon::parse($activity['date'])->diffForHumans() }}</time>
                                                @else
                                                    <span>Unknown</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">No recent activities found.</p>
                        @endforelse
                    </ul>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
