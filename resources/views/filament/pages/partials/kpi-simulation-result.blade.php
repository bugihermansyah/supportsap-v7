<div>

    @if($this->simResult)
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <!-- Breakdown Table -->
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                        <th class="p-3 text-left text-gray-600 dark:text-gray-300 font-semibold uppercase tracking-wider text-xs">Komponen</th>
                        <th class="p-3 text-right text-gray-600 dark:text-gray-300 font-semibold uppercase tracking-wider text-xs">Poin</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->simResult['breakdown'] as $item)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="p-3 text-gray-700 dark:text-gray-300">
                                @if($item['type'] === 'penalty')
                                    <span class="inline-block w-2 h-2 rounded-full bg-red-500 mr-2"></span>
                                @elseif($item['type'] === 'bonus')
                                    <span class="inline-block w-2 h-2 rounded-full bg-green-500 mr-2"></span>
                                @elseif($item['type'] === 'neutral')
                                    <span class="inline-block w-2 h-2 rounded-full bg-yellow-500 mr-2"></span>
                                @else
                                    <span class="inline-block w-2 h-2 rounded-full bg-blue-500 mr-2"></span>
                                @endif
                                {{ $item['label'] }}
                            </td>
                            <td class="p-3 text-right font-mono font-semibold
                                @if($item['type'] === 'penalty') text-red-600 dark:text-red-400
                                @elseif($item['type'] === 'bonus') text-green-600 dark:text-green-400
                                @else text-gray-700 dark:text-gray-300
                                @endif">
                                {{ $item['value'] >= 0 ? '+' : '' }}{{ $item['value'] }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 dark:bg-gray-900 border-t-2 border-gray-300 dark:border-gray-600">
                        <td class="p-3 font-bold text-gray-800 dark:text-gray-200">Skor Akhir (capped 0-100)</td>
                        <td class="p-3 text-right font-mono font-bold text-xl text-gray-800 dark:text-gray-200">{{ $this->simResult['finalScore'] }}</td>
                    </tr>
                </tfoot>
            </table>

            <!-- Grade Display -->
            <div class="p-6 text-center border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-2">Grade</p>
                <span class="inline-flex items-center justify-center w-20 h-20 rounded-full text-4xl font-extrabold
                    {{ $this->simResult['grade'] === 'A+' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-300' :
                       ($this->simResult['grade'] === 'A' ? 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300' :
                       ($this->simResult['grade'] === 'B' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300' :
                       ($this->simResult['grade'] === 'C' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300' :
                       'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300'))) }}">
                    {{ $this->simResult['grade'] }}
                </span>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                    Level: {{ $this->simResult['levelLabel'] }} &bull;
                    Raw Score: {{ $this->simResult['rawScore'] }}
                    @if($this->simResult['rawScore'] !== $this->simResult['finalScore'])
                        (capped to {{ $this->simResult['finalScore'] }})
                    @endif
                </p>
            </div>
        </div>
    @else
        <div class="bg-gray-50 dark:bg-gray-900 p-8 rounded-lg text-center border border-dashed border-gray-300 dark:border-gray-700">
            <x-heroicon-o-calculator class="w-12 h-12 mx-auto text-gray-400 dark:text-gray-500 mb-3" />
            <p class="text-gray-500 dark:text-gray-400">Atur skenario di atas lalu klik <strong>"Hitung Simulasi"</strong> untuk melihat rincian kalkulasi nilai KPI.</p>
        </div>
    @endif
</div>
