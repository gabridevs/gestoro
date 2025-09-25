<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PrezziMetalliService
{
    private $cachePrefix = 'prezzi_metalli_';
    private $cacheDuration;
    private $apiKey;
    private $apiEndpoints;

    public function __construct()
    {
        $this->cacheDuration = config('metalli.api_prezzi.cache_minuti', 5) * 60; // Converti in secondi
        $this->apiKey = config('metalli.api_prezzi.api_key');

        // API endpoints (esempio - sostituire con provider reali)
        $this->apiEndpoints = [
            'primary' => 'https://api.metalpriceapi.com/v1/latest',
            'backup' => 'https://api.currencyscoop.com/v1/latest'
        ];
    }

    /**
     * Ottieni il prezzo attuale di un metallo
     */
    public function getPrezzoAttuale(string $metallo, int $titolo): float
    {
        $cacheKey = $this->cachePrefix . strtolower($metallo) . '_' . $titolo;

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($metallo, $titolo) {
            try {
                // Prova prima API principale
                $prezzo = $this->fetchFromPrimaryAPI($metallo, $titolo);

                if ($prezzo === null) {
                    // Fallback su API secondaria
                    $prezzo = $this->fetchFromBackupAPI($metallo, $titolo);
                }

                if ($prezzo === null) {
                    // Fallback su prezzi statici di emergenza
                    $prezzo = $this->getFallbackPrice($metallo, $titolo);
                }

                // Salva nel database per storico (se disponibile)
                $this->salvaPrezzoStorico($metallo, $titolo, $prezzo);

                return $prezzo;
            } catch (\Exception $e) {
                Log::error("Errore nel recupero prezzi metalli: " . $e->getMessage());
                return $this->getFallbackPrice($metallo, $titolo);
            }
        });
    }

    /**
     * API primaria - esempio MetalPriceAPI
     */
    private function fetchFromPrimaryAPI(string $metallo, int $titolo): ?float
    {
        try {
            if (!$this->apiKey) {
                return null; // Nessuna API key configurata
            }

            $symbol = $this->getAPISymbol($metallo);

            $response = Http::timeout(10)
                ->withHeaders([
                    'X-API-KEY' => $this->apiKey
                ])
                ->get($this->apiEndpoints['primary'], [
                    'base' => 'EUR',
                    'symbols' => $symbol
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $prezzoOncia = $data['rates'][$symbol] ?? null;

                if ($prezzoOncia) {
                    // Converti da oncia a grammo e applica il titolo
                    return $this->convertToGramWithPurity($prezzoOncia, $metallo, $titolo);
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::warning("Primary API fallita per {$metallo}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * API backup
     */
    private function fetchFromBackupAPI(string $metallo, int $titolo): ?float
    {
        try {
            // Implementazione API backup simile
            return null; // Per ora usa solo fallback

        } catch (\Exception $e) {
            Log::warning("Backup API fallita per {$metallo}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Converti da oncia Troy a grammo con purezza
     */
    private function convertToGramWithPurity(float $prezzoOncia, string $metallo, int $titolo): float
    {
        // 1 oncia Troy = 31.1034768 grammi
        $grammiPerOncia = 31.1034768;
        $prezzoGrammo = $prezzoOncia / $grammiPerOncia;

        // Applica la purezza del metallo
        $fattorePurezza = $this->calcolaFattorePurezza($metallo, $titolo);

        // Applica margine commerciale (configurabile)
        $margine = $this->getMargineCommerciale($metallo, $titolo);

        return round($prezzoGrammo * $fattorePurezza * (1 - $margine), 4);
    }

    /**
     * Calcola fattore purezza basato sul titolo
     */
    private function calcolaFattorePurezza(string $metallo, int $titolo): float
    {
        switch ($metallo) {
            case 'ORO':
                return $titolo / 1000; // 750 = 75% = 0.75

            case 'ARGENTO':
                return $titolo / 1000; // 925 = 92.5% = 0.925

            case 'PLATINO':
            case 'PALLADIO':
                return $titolo / 1000;

            default:
                return 1.0;
        }
    }

    /**
     * Margine commerciale per tipo di metallo
     */
    private function getMargineCommerciale(string $metallo, int $titolo): float
    {
        $margini = config('metalli.margini_commerciali', []);
        $marginemetallo = $margini[strtolower($metallo)] ?? ['default' => 0.05];

        return $marginemetallo[$titolo] ?? $marginemetallo['default'];
    }

    /**
     * Simboli API per i metalli
     */
    private function getAPISymbol(string $metallo): string
    {
        $simboli = [
            'ORO' => 'XAU',
            'ARGENTO' => 'XAG',
            'PLATINO' => 'XPT',
            'PALLADIO' => 'XPD'
        ];

        return $simboli[$metallo] ?? 'XAU';
    }

    /**
     * Prezzi di fallback in caso di errore API
     */
    private function getFallbackPrice(string $metallo, int $titolo): float
    {
        // Prezzi statici di emergenza (da aggiornare periodicamente)
        $prezziFallback = [
            'ORO' => [
                '999' => 65.00,
                '750' => 48.75,
                '585' => 38.03,
                '375' => 24.38
            ],
            'ARGENTO' => [
                '999' => 0.85,
                '925' => 0.79,
                '800' => 0.68
            ],
            'PLATINO' => [
                '999' => 32.00,
                '950' => 30.40
            ]
        ];

        $prezziMetallo = $prezziFallback[$metallo] ?? ['999' => 1.00];

        return $prezziMetallo[$titolo] ?? $prezziMetallo['999'];
    }

    /**
     * Salva prezzo nel database per storico
     */
    private function salvaPrezzoStorico(string $metallo, int $titolo, float $prezzo): void
    {
        try {
            // Qui salveresti nella tabella prezzi_storici se l'avessi creata
            Log::info("Prezzo {$metallo} {$titolo}: €{$prezzo}/g");
        } catch (\Exception $e) {
            Log::error("Errore salvataggio prezzo storico: " . $e->getMessage());
        }
    }

    /**
     * Ottieni prezzi multipli in una chiamata
     */
    public function getPrezziMultipli(array $richieste): array
    {
        $risultati = [];

        foreach ($richieste as $richiesta) {
            $metallo = $richiesta['metallo'];
            $titolo = $richiesta['titolo'];

            $risultati[] = [
                'metallo' => $metallo,
                'titolo' => $titolo,
                'prezzo' => $this->getPrezzoAttuale($metallo, $titolo),
                'timestamp' => now()
            ];
        }

        return $risultati;
    }

    /**
     * Calcola differenza percentuale vs prezzo precedente
     */
    public function calcolaDifferenzaPercentuale(string $metallo, int $titolo): array
    {
        $prezzoAttuale = $this->getPrezzoAttuale($metallo, $titolo);

        // Simula prezzo precedente (da implementare con database storico)
        $prezzoIeri = $prezzoAttuale * (1 + (mt_rand(-50, 50) / 1000)); // Variazione casuale per demo

        $differenzaAssoluta = $prezzoAttuale - $prezzoIeri;
        $differenzaPercentuale = $prezzoIeri > 0 ? ($differenzaAssoluta / $prezzoIeri) * 100 : 0;

        $trend = 'STABILE';
        if (abs($differenzaPercentuale) > 0.5) {
            $trend = $differenzaPercentuale > 0 ? 'RIALZO' : 'RIBASSO';
        }

        return [
            'prezzo_attuale' => $prezzoAttuale,
            'prezzo_precedente' => $prezzoIeri,
            'differenza_assoluta' => round($differenzaAssoluta, 4),
            'differenza_percentuale' => round($differenzaPercentuale, 2),
            'trend' => $trend
        ];
    }

    /**
     * Forza aggiornamento prezzi (bypass cache)
     */
    public function forzaAggiornamento(string $metallo = null, int $titolo = null): void
    {
        if ($metallo && $titolo) {
            // Cancella cache specifica
            $cacheKey = $this->cachePrefix . strtolower($metallo) . '_' . $titolo;
            Cache::forget($cacheKey);
        } else {
            // Cancella tutta la cache prezzi
            $pattern = $this->cachePrefix . '*';
            // Implementazione dipende dal driver cache utilizzato
            Cache::flush(); // Attenzione: cancella TUTTA la cache
        }
    }
}
