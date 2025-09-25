<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\{Cliente, Fornitore, Operazione, Fissaggio};
use App\Services\PrezziMetalliService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Dashboard extends Component
{
    // Proprietà per dati real-time
    public $prezziMetalli = [];
    public $statisticheGenerali = [];
    public $operazioniRecenti = [];
    public $fissaggiInScadenza = [];
    public $alertSystem = [];

    // Proprietà per filtri dashboard
    public $periodoSelezionato = '30'; // Ultimi 30 giorni
    public $metalloSelezionato = 'TUTTI';

    public function mount()
    {
        $this->caricaDatiDashboard();
    }

    public function render()
    {
        return view('livewire.dashboard')->layout('layouts.app');
    }

    public function caricaDatiDashboard()
    {
        $this->caricaPrezziMetalli();
        $this->caricaStatisticheGenerali();
        $this->caricaOperazioniRecenti();
        $this->caricaFissaggiInScadenza();
        $this->caricaAlert();
    }

    private function caricaPrezziMetalli()
    {
        try {
            $prezziService = app(PrezziMetalliService::class);

            $metalli = [
                ['metallo' => 'ORO', 'titolo' => 750],
                ['metallo' => 'ORO', 'titolo' => 585],
                ['metallo' => 'ARGENTO', 'titolo' => 925],
                ['metallo' => 'PLATINO', 'titolo' => 999]
            ];

            $this->prezziMetalli = [];

            foreach ($metalli as $metallo) {
                $prezzo = $prezziService->getPrezzoAttuale($metallo['metallo'], $metallo['titolo']);
                $trend = $prezziService->calcolaDifferenzaPercentuale($metallo['metallo'], $metallo['titolo']);

                $this->prezziMetalli[] = [
                    'metallo' => $metallo['metallo'],
                    'titolo' => $metallo['titolo'],
                    'prezzo' => $prezzo,
                    'trend' => $trend,
                    'ultimo_aggiornamento' => now()->format('H:i')
                ];
            }
        } catch (\Exception $e) {
            $this->prezziMetalli = [
                ['metallo' => 'ORO', 'titolo' => 750, 'prezzo' => 48.75, 'trend' => ['trend' => 'FALLBACK']]
            ];
        }
    }

    private function caricaStatisticheGenerali()
    {
        try {
            $dataInizio = now()->subDays(intval($this->periodoSelezionato));

            // Statistiche operazioni - SEMPLIFIED
            $operazioni = Operazione::whereBetween('data_operazione', [$dataInizio, now()]);

            if ($this->metalloSelezionato !== 'TUTTI') {
                $operazioni = $operazioni->where('metallo_tipo', $this->metalloSelezionato);
            }

            // Calcoli base senza accessors problematici
            $this->statisticheGenerali = [
                // KPI Operazioni
                'totale_operazioni' => $operazioni->count(),
                'operazioni_acquisto' => (clone $operazioni)->where('tipo', 'ACQUISTO')->count(),
                'operazioni_vendita' => (clone $operazioni)->where('tipo', 'VENDITA')->count(),
                'valore_totale_operazioni' => $operazioni->sum('valore_totale_operazione'),
                'peso_totale_trattato' => $operazioni->sum('peso_netto_grammi'),

                // KPI Fissaggi - SIMPLIFICATO
                'fissaggi_attivi' => $this->contaFissaggiAttivi(),
                'valore_fissaggi_attivi' => $this->calcolaValoreFissaggiAttivi(),
                'fissaggi_in_scadenza_7gg' => $this->contaFissaggiInScadenza(),

                // KPI Clienti e Fornitori
                'clienti_attivi' => Cliente::where('attivo', true)->count(),
                'nuovi_clienti_periodo' => Cliente::where('created_at', '>=', $dataInizio)->count(),
                'fornitori_attivi' => Fornitore::where('attivo', true)->count(),

                // Performance finanziarie - SEMPLIFICATO
                'margine_medio_percentuale' => 0, // Calcoleremo dopo
                'operazioni_contanti' => (clone $operazioni)->where('importo_contanti', '>', 0)->count(),
                'valore_contanti' => $operazioni->sum('importo_contanti'),

                // Compliance
                'operazioni_da_comunicare_bi' => $this->contaOperazioniDaComunicare($operazioni),
                'clienti_controllo_scaduto' => Cliente::where('stato_antiriciclaggio', '!=', 'OK')->count(),
                'documenti_in_scadenza' => Cliente::where('documento_scadenza', '<=', now()->addDays(30))->count()
            ];
        } catch (\Exception $e) {
            // Fallback con dati statici se ci sono errori
            $this->statisticheGenerali = [
                'totale_operazioni' => 0,
                'operazioni_acquisto' => 0,
                'operazioni_vendita' => 0,
                'valore_totale_operazioni' => 0,
                'peso_totale_trattato' => 0,
                'fissaggi_attivi' => 0,
                'valore_fissaggi_attivi' => 0,
                'fissaggi_in_scadenza_7gg' => 0,
                'clienti_attivi' => 0,
                'nuovi_clienti_periodo' => 0,
                'fornitori_attivi' => 0,
                'margine_medio_percentuale' => 0,
                'operazioni_contanti' => 0,
                'valore_contanti' => 0,
                'operazioni_da_comunicare_bi' => 0,
                'clienti_controllo_scaduto' => 0,
                'documenti_in_scadenza' => 0
            ];
        }
    }

    // Metodi helper per evitare errori SQL
    private function contaFissaggiAttivi()
    {
        try {
            return Fissaggio::where('stato', 'ATTIVO')
                ->where('data_scadenza', '>=', now())
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function calcolaValoreFissaggiAttivi()
    {
        try {
            return Fissaggio::where('stato', 'ATTIVO')
                ->where('data_scadenza', '>=', now())
                ->get()
                ->sum(function ($fissaggio) {
                    return ($fissaggio->quantita_totale_grammi - $fissaggio->quantita_consegnata_grammi)
                        * $fissaggio->prezzo_fisso_grammo;
                });
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function contaFissaggiInScadenza()
    {
        try {
            return Fissaggio::where('stato', 'ATTIVO')
                ->whereBetween('data_scadenza', [now(), now()->addDays(7)])
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function contaOperazioniDaComunicare($operazioni)
    {
        try {
            $soglia = config('metalli.soglie_antiriciclaggio.comunicazione_bancaitalia_soglia', 10000);
            return (clone $operazioni)->where('comunicazione_bancaitalia_inviata', false)
                ->where('valore_totale_operazione', '>=', $soglia)
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function caricaOperazioniRecenti()
    {
        try {
            $this->operazioniRecenti = Operazione::with(['cliente', 'fornitore'])
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get()
                ->map(function ($operazione) {
                    return [
                        'id' => $operazione->id,
                        'numero' => $operazione->numero_operazione,
                        'tipo' => $operazione->tipo,
                        'controparte' => $this->getContoparteNome($operazione),
                        'metallo' => $operazione->metallo_tipo . ' ' . $operazione->titolo,
                        'peso' => number_format($operazione->peso_netto_grammi, 1) . 'g',
                        'valore' => '€' . number_format($operazione->valore_totale_operazione, 0),
                        'stato' => $operazione->stato,
                        'data' => $operazione->created_at->format('d/m H:i'),
                        'classe_stato' => $this->getClasseStato($operazione->stato)
                    ];
                });
        } catch (\Exception $e) {
            $this->operazioniRecenti = [];
        }
    }

    private function getContoparteNome($operazione)
    {
        if ($operazione->cliente) {
            return $operazione->cliente->cognome . ', ' . $operazione->cliente->nome;
        } elseif ($operazione->fornitore) {
            return $operazione->fornitore->ragione_sociale;
        }
        return 'N/D';
    }

    private function caricaFissaggiInScadenza()
    {
        try {
            $this->fissaggiInScadenza = Fissaggio::with('cliente')
                ->where('stato', 'ATTIVO')
                ->where('data_scadenza', '<=', now()->addDays(15))
                ->orderBy('data_scadenza')
                ->take(8)
                ->get()
                ->map(function ($fissaggio) {
                    return [
                        'id' => $fissaggio->id,
                        'numero_contratto' => $fissaggio->numero_contratto,
                        'cliente' => $fissaggio->cliente->cognome . ', ' . $fissaggio->cliente->nome ?? 'N/D',
                        'metallo' => $fissaggio->metallo_tipo . ' ' . $fissaggio->titolo,
                        'scadenza' => $fissaggio->data_scadenza->format('d/m/Y'),
                        'giorni_rimanenti' => now()->diffInDays($fissaggio->data_scadenza, false),
                        'valore_residuo' => '€' . number_format(
                            ($fissaggio->quantita_totale_grammi - $fissaggio->quantita_consegnata_grammi)
                                * $fissaggio->prezzo_fisso_grammo,
                            0
                        ),
                        'classe_urgenza' => $this->getClasseUrgenza(now()->diffInDays($fissaggio->data_scadenza, false))
                    ];
                });
        } catch (\Exception $e) {
            $this->fissaggiInScadenza = [];
        }
    }

    private function caricaAlert()
    {
        $this->alertSystem = [];

        try {
            // Alert operazioni da approvare
            $operazioniDaApprovare = Operazione::where('stato', 'CONFERMATA')->count();
            if ($operazioniDaApprovare > 0) {
                $this->alertSystem[] = [
                    'tipo' => 'warning',
                    'titolo' => 'Operazioni da Approvare',
                    'messaggio' => "{$operazioniDaApprovare} operazioni in attesa di approvazione",
                    'icona' => 'clock',
                    'azione' => 'gestione-operazioni'
                ];
            }

            // Alert fissaggi scaduti
            $fissaggiScaduti = Fissaggio::where('data_scadenza', '<', now())
                ->where('stato', 'ATTIVO')->count();
            if ($fissaggiScaduti > 0) {
                $this->alertSystem[] = [
                    'tipo' => 'danger',
                    'titolo' => 'Fissaggi Scaduti',
                    'messaggio' => "{$fissaggiScaduti} contratti scaduti richiedono attenzione",
                    'icona' => 'alert-triangle',
                    'azione' => 'gestione-fissaggi'
                ];
            }

            // Alert prezzi metalli non aggiornati
            if (empty($this->prezziMetalli) || $this->prezziMetalli[0]['trend']['trend'] === 'FALLBACK') {
                $this->alertSystem[] = [
                    'tipo' => 'info',
                    'titolo' => 'Prezzi Fallback',
                    'messaggio' => 'Utilizzo prezzi di emergenza - API non disponibile',
                    'icona' => 'wifi-off',
                    'azione' => 'aggiorna-prezzi'
                ];
            }
        } catch (\Exception $e) {
            // Ignore alert errors
        }
    }

    private function getClasseStato(string $stato): string
    {
        return match ($stato) {
            'COMPLETATA' => 'bg-green-100 text-green-800',
            'CONFERMATA' => 'bg-blue-100 text-blue-800',
            'BOZZA' => 'bg-yellow-100 text-yellow-800',
            'ANNULLATA' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    private function getClasseUrgenza(int $giorni): string
    {
        if ($giorni < 0) return 'border-red-500 bg-red-50';
        if ($giorni <= 3) return 'border-orange-500 bg-orange-50';
        if ($giorni <= 7) return 'border-yellow-500 bg-yellow-50';
        return 'border-blue-500 bg-blue-50';
    }

    // Metodi per aggiornamento dati
    public function aggiornaPrezzi()
    {
        try {
            app(PrezziMetalliService::class)->forzaAggiornamento();
            $this->caricaPrezziMetalli();

            session()->flash('message', 'Prezzi aggiornati con successo!');
        } catch (\Exception $e) {
            session()->flash('error', 'Errore aggiornamento prezzi: ' . $e->getMessage());
        }
    }

    public function cambiaPeriodo()
    {
        $this->caricaStatisticheGenerali();
    }

    public function cambiaMetallo()
    {
        $this->caricaStatisticheGenerali();
    }

    // Quick Actions - redirect temporanei
    public function nuovaOperazione()
    {
        session()->flash('message', 'Funzione in sviluppo - Nuova Operazione');
    }

    public function nuovoFissaggio()
    {
        session()->flash('message', 'Funzione in sviluppo - Nuovo Fissaggio');
    }

    public function vaiAOperazioni()
    {
        return redirect()->route('operazioni.index');
    }

    public function vaiAFissaggi()
    {
        return redirect()->route('fissaggi.index');
    }

    public function vaiAClienti()
    {
        return redirect()->route('clienti.index');
    }
}
