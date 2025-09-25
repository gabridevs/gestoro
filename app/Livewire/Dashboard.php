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
                ['metallo' => 'ORO', 'titolo' => 750, 'prezzo' => 0, 'trend' => ['trend' => 'ERRORE']]
            ];
        }
    }

    private function caricaStatisticheGenerali()
    {
        $dataInizio = now()->subDays(intval($this->periodoSelezionato));

        // Statistiche operazioni
        $operazioni = Operazione::nelPeriodo($dataInizio, now());

        if ($this->metalloSelezionato !== 'TUTTI') {
            $operazioni = $operazioni->perMetallo($this->metalloSelezionato);
        }

        $this->statisticheGenerali = [
            // KPI Operazioni
            'totale_operazioni' => $operazioni->count(),
            'operazioni_acquisto' => $operazioni->clone()->acquisti()->count(),
            'operazioni_vendita' => $operazioni->clone()->vendite()->count(),
            'valore_totale_operazioni' => $operazioni->sum('valore_totale_operazione'),
            'peso_totale_trattato' => $operazioni->sum('peso_netto_grammi'),

            // KPI Fissaggi
            'fissaggi_attivi' => Fissaggio::attivi()->count(),
            'valore_fissaggi_attivi' => Fissaggio::attivi()
                ->sum(DB::raw('(quantita_totale_grammi - quantita_consegnata_grammi) * prezzo_fisso_grammo')),
            'fissaggi_in_scadenza_7gg' => Fissaggio::inScadenza(7)->count(),

            // KPI Clienti e Fornitori
            'clienti_attivi' => Cliente::attivi()->count(),
            'nuovi_clienti_periodo' => Cliente::where('created_at', '>=', $dataInizio)->count(),
            'fornitori_attivi' => Fornitore::attivi()->count(),

            // Performance finanziarie
            'margine_medio_percentuale' => $operazioni->avg('margine_percentuale') ?? 0,
            'operazioni_contanti' => $operazioni->where('importo_contanti', '>', 0)->count(),
            'valore_contanti' => $operazioni->sum('importo_contanti'),

            // Compliance
            'operazioni_da_comunicare_bi' => $operazioni->where('comunicazione_bancaitalia_inviata', false)
                ->where('valore_totale_operazione', '>=', config('metalli.soglie_antiriciclaggio.comunicazione_bancaitalia_soglia'))
                ->count(),
            'clienti_controllo_scaduto' => Cliente::controlloAntiriciclaggioScaduto()->count(),
            'documenti_in_scadenza' => Cliente::documentoInScadenza(30)->count()
        ];
    }

    private function caricaOperazioniRecenti()
    {
        $this->operazioniRecenti = Operazione::with(['cliente', 'fornitore'])
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(function ($operazione) {
                return [
                    'id' => $operazione->id,
                    'numero' => $operazione->numero_operazione,
                    'tipo' => $operazione->tipo,
                    'controparte' => $operazione->controparte_nome,
                    'metallo' => $operazione->metallo_tipo . ' ' . $operazione->titolo,
                    'peso' => number_format($operazione->peso_netto_grammi, 1) . 'g',
                    'valore' => '€' . number_format($operazione->valore_totale_operazione, 0),
                    'stato' => $operazione->stato,
                    'data' => $operazione->created_at->format('d/m H:i'),
                    'classe_stato' => $this->getClasseStato($operazione->stato)
                ];
            });
    }

    private function caricaFissaggiInScadenza()
    {
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
                    'cliente' => $fissaggio->cliente->nome_completo ?? 'N/D',
                    'metallo' => $fissaggio->metallo_tipo . ' ' . $fissaggio->titolo,
                    'scadenza' => $fissaggio->data_scadenza->format('d/m/Y'),
                    'giorni_rimanenti' => $fissaggio->giorni_scadenza,
                    'valore_residuo' => '€' . number_format($fissaggio->valore_residuo, 0),
                    'classe_urgenza' => $this->getClasseUrgenza($fissaggio->giorni_scadenza)
                ];
            });
    }

    private function caricaAlert()
    {
        $this->alertSystem = [];

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

        // Alert controlli antiriciclaggio
        $controlliScaduti = Cliente::controlloAntiriciclaggioScaduto()->count();
        if ($controlliScaduti > 0) {
            $this->alertSystem[] = [
                'tipo' => 'info',
                'titolo' => 'Controlli AML Scaduti',
                'messaggio' => "{$controlliScaduti} clienti necessitano verifica antiriciclaggio",
                'icona' => 'shield',
                'azione' => 'gestione-clienti'
            ];
        }

        // Alert prezzi metalli non aggiornati
        if (empty($this->prezziMetalli) || $this->prezziMetalli[0]['trend']['trend'] === 'ERRORE') {
            $this->alertSystem[] = [
                'tipo' => 'warning',
                'titolo' => 'Prezzi Non Aggiornati',
                'messaggio' => 'Problemi nel recupero prezzi metalli - usando prezzi fallback',
                'icona' => 'wifi-off',
                'azione' => 'aggiorna-prezzi'
            ];
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
            // Forza aggiornamento prezzi
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
        $this->dispatch('periodo-cambiato', periodo: $this->periodoSelezionato);
    }

    public function cambiaMetallo()
    {
        $this->caricaStatisticheGenerali();
        $this->dispatch('metallo-cambiato', metallo: $this->metalloSelezionato);
    }

    // Quick Actions
    public function nuovaOperazione()
    {
        return redirect()->route('acquisti.create');
    }

    public function nuovoFissaggio()
    {
        return redirect()->route('fissaggi.create');
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

    // Listeners per aggiornamenti real-time
    protected $listeners = [
        'operazione-creata' => 'caricaDatiDashboard',
        'fissaggio-creato' => 'caricaDatiDashboard',
        'prezzi-aggiornati' => 'caricaPrezziMetalli'
    ];

    // Refresh automatico ogni 5 minuti (opzionale)
    public function refreshData()
    {
        $this->caricaDatiDashboard();
        $this->dispatch('dashboard-aggiornato');
    }
}
