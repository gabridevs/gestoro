<div class="space-y-6">
    {{-- Header Dashboard --}}
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Dashboard Gestionale Metalli</h1>
            <p class="text-gray-600">Panoramica operativa - {{ now()->format('d/m/Y H:i') }}</p>
        </div>

        <div class="flex gap-4">
            {{-- Filtri --}}
            <select wire:model.live="periodoSelezionato" wire:change="cambiaPeriodo"
                class="rounded-md border-gray-300">
                <option value="7">Ultimi 7 giorni</option>
                <option value="30">Ultimi 30 giorni</option>
                <option value="90">Ultimi 3 mesi</option>
                <option value="365">Ultimo anno</option>
            </select>

            <button wire:click="aggiornaPrezzi"
                class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Aggiorna Prezzi
            </button>
        </div>
    </div>

    {{-- Alert System --}}
    @if(count($alertSystem) > 0)
    <div class="grid grid-cols-1 gap-4">
        @foreach($alertSystem as $alert)
        <div class="p-4 rounded-lg border-l-4 {{ $alert['tipo'] === 'danger' ? 'bg-red-50 border-red-400' : ($alert['tipo'] === 'warning' ? 'bg-yellow-50 border-yellow-400' : 'bg-blue-50 border-blue-400') }}">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        @if($alert['icona'] === 'clock')
                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        @elseif($alert['icona'] === 'alert-triangle')
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        @else
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        @endif
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium">{{ $alert['titolo'] }}</h3>
                        <p class="text-sm">{{ $alert['messaggio'] }}</p>
                    </div>
                </div>
                <button class="text-sm px-3 py-1 rounded border hover:bg-white">
                    Gestisci
                </button>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Prezzi Metalli Real-time --}}
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold">Prezzi Metalli Real-time</h2>
            <div class="text-sm text-gray-500">
                Ultimo aggiornamento: {{ $prezziMetalli[0]['ultimo_aggiornamento'] ?? 'N/D' }}
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($prezziMetalli as $prezzo)
            <div class="border rounded-lg p-4">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="font-medium">{{ $prezzo['metallo'] }} {{ $prezzo['titolo'] }}</h3>
                        <p class="text-2xl font-bold">€{{ number_format($prezzo['prezzo'], 2) }}</p>
                        <p class="text-sm text-gray-600">per grammo</p>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs {{ 
                                $prezzo['trend']['trend'] === 'RIALZO' ? 'bg-green-100 text-green-800' : (
                                $prezzo['trend']['trend'] === 'RIBASSO' ? 'bg-red-100 text-red-800' : 
                                'bg-gray-100 text-gray-800') }}">
                            @if($prezzo['trend']['trend'] === 'RIALZO')
                            ↗ +{{ number_format($prezzo['trend']['differenza_percentuale'], 2) }}%
                            @elseif($prezzo['trend']['trend'] === 'RIBASSO')
                            ↘ {{ number_format($prezzo['trend']['differenza_percentuale'], 2) }}%
                            @else
                            → {{ $prezzo['trend']['trend'] }}
                            @endif
                        </span>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {{-- Operazioni --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-100 rounded-md flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-900">Operazioni</h3>
                    <p class="text-2xl font-bold text-blue-600">{{ number_format($statisticheGenerali['totale_operazioni'] ?? 0) }}</p>
                    <p class="text-sm text-gray-600">
                        A: {{ $statisticheGenerali['operazioni_acquisto'] ?? 0 }} |
                        V: {{ $statisticheGenerali['operazioni_vendita'] ?? 0 }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Valore Operazioni --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-100 rounded-md flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-900">Valore Operazioni</h3>
                    <p class="text-2xl font-bold text-green-600">€{{ number_format($statisticheGenerali['valore_totale_operazioni'] ?? 0, 0) }}</p>
                    <p class="text-sm text-gray-600">{{ number_format($statisticheGenerali['peso_totale_trattato'] ?? 0, 1) }}g trattati</p>
                </div>
            </div>
        </div>

        {{-- Fissaggi Attivi --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-100 rounded-md flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-900">Fissaggi Attivi</h3>
                    <p class="text-2xl font-bold text-purple-600">{{ number_format($statisticheGenerali['fissaggi_attivi'] ?? 0) }}</p>
                    <p class="text-sm text-gray-600">€{{ number_format($statisticheGenerali['valore_fissaggi_attivi'] ?? 0, 0) }} valore</p>
                </div>
            </div>
        </div>

        {{-- Clienti Attivi --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-orange-100 rounded-md flex items-center justify-center">
                        <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-900">Clienti Attivi</h3>
                    <p class="text-2xl font-bold text-orange-600">{{ number_format($statisticheGenerali['clienti_attivi'] ?? 0) }}</p>
                    <p class="text-sm text-gray-600">+{{ $statisticheGenerali['nuovi_clienti_periodo'] ?? 0 }} nuovi</p>
                </div>
            </div>
        </div>
    </div>
</div>