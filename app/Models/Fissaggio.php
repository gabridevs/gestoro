<?php

namespace App\Models;

use Illuminate\Database\Eloquent\{Factories\HasFactory, Model, Relations\BelongsTo, Relations\HasMany};
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Fissaggio extends Model
{
    use HasFactory;

    protected $table = 'fissaggi';

    protected $fillable = [
        'numero_contratto',
        'cliente_id',
        'fonderia_id',
        'metallo_tipo',
        'titolo',
        'quantita_totale_grammi',
        'quantita_consegnata_grammi',
        'prezzo_fisso_grammo',
        'data_contratto',
        'data_inizio_validita',
        'data_scadenza',
        'consegna_minima_grammi',
        'modalita_pagamento',
        'stato',
        'note',
        'creato_da_user_id'
    ];

    protected $casts = [
        'quantita_totale_grammi' => 'decimal:3',
        'quantita_consegnata_grammi' => 'decimal:3',
        'prezzo_fisso_grammo' => 'decimal:4',
        'consegna_minima_grammi' => 'decimal:3',
        'data_contratto' => 'date',
        'data_inizio_validita' => 'date',
        'data_scadenza' => 'date'
    ];

    // Relazioni
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function fonderia(): BelongsTo
    {
        return $this->belongsTo(Fornitore::class, 'fonderia_id');
    }

    public function consegne(): HasMany
    {
        return $this->hasMany(FissaggioConsegna::class);
    }

    public function creatoDA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creato_da_user_id');
    }

    // Scopes
    public function scopeAttivi($query)
    {
        return $query->where('stato', 'ATTIVO')
            ->where('data_scadenza', '>=', now());
    }

    public function scopeInScadenza($query, int $giorni = 7)
    {
        return $query->where('stato', 'ATTIVO')
            ->whereBetween('data_scadenza', [now(), now()->addDays($giorni)]);
    }

    public function scopePerMetallo($query, string $metallo)
    {
        return $query->where('metallo_tipo', strtoupper($metallo));
    }

    public function scopePerCliente($query, int $clienteId)
    {
        return $query->where('cliente_id', $clienteId);
    }

    // Accessors
    public function getQuantitaRimanenteGrammiAttribute(): float
    {
        return $this->quantita_totale_grammi - $this->quantita_consegnata_grammi;
    }

    public function getPercentualeUtilizzatoAttribute(): float
    {
        if ($this->quantita_totale_grammi == 0) return 0;
        return ($this->quantita_consegnata_grammi / $this->quantita_totale_grammi) * 100;
    }

    public function getGiorniScadenzaAttribute(): int
    {
        return now()->diffInDays($this->data_scadenza, false);
    }

    public function getStatoCalcolatoAttribute(): string
    {
        if ($this->quantita_consegnata_grammi >= $this->quantita_totale_grammi) {
            return 'COMPLETATO';
        }

        if ($this->data_scadenza < now()) {
            return 'SCADUTO';
        }

        if ($this->giorni_scadenza <= 7 && $this->giorni_scadenza >= 0) {
            return 'IN_SCADENZA';
        }

        return $this->stato;
    }

    public function getValoreResiduoAttribute(): float
    {
        return $this->quantita_rimanente_grammi * $this->prezzo_fisso_grammo;
    }

    // Business Logic Avanzata
    public function puoAccettareConsegna(float $pesoGrammi): array
    {
        $errori = [];

        if ($this->stato !== 'ATTIVO') {
            $errori[] = 'Fissaggio non attivo';
        }

        if ($this->data_scadenza < now()) {
            $errori[] = 'Fissaggio scaduto';
        }

        if ($pesoGrammi < $this->consegna_minima_grammi) {
            $errori[] = "Quantità minima per consegna: {$this->consegna_minima_grammi}g";
        }

        if ($pesoGrammi > $this->quantita_rimanente_grammi) {
            $errori[] = "Quantità eccede il rimanente: {$this->quantita_rimanente_grammi}g";
        }

        return [
            'valido' => empty($errori),
            'errori' => $errori
        ];
    }

    public function registraConsegna(array $dati): FissaggioConsegna
    {
        $validazione = $this->puoAccettareConsegna($dati['peso_grammi']);

        if (!$validazione['valido']) {
            throw new \Exception('Consegna non valida: ' . implode(', ', $validazione['errori']));
        }

        // Crea la consegna
        $consegna = $this->consegne()->create([
            'operazione_id' => $dati['operazione_id'],
            'data_consegna' => $dati['data_consegna'] ?? today(),
            'peso_consegnato_grammi' => $dati['peso_grammi'],
            'prezzo_applicato_grammo' => $this->prezzo_fisso_grammo,
            'valore_totale' => $dati['peso_grammi'] * $this->prezzo_fisso_grammo,
            'ddt_numero' => $dati['ddt_numero'] ?? null,
            'registrata_da_user_id' => auth()->id(),
            'note' => $dati['note'] ?? null
        ]);

        // Aggiorna quantità consegnata
        $this->increment('quantita_consegnata_grammi', $dati['peso_grammi']);

        // Aggiorna stato se completato
        if ($this->quantita_consegnata_grammi >= $this->quantita_totale_grammi) {
            $this->update(['stato' => 'COMPLETATO']);
        }

        return $consegna;
    }

    public function calcolaGuadagnoPerdita(float $prezzoMercatoAttuale = null): array
    {
        // Simula prezzo mercato attuale (da implementare con servizio reale)
        $prezzoMercato = $prezzoMercatoAttuale ?? $this->ottieniPrezzoMercatoAttuale();

        $differenzaGrammo = $this->prezzo_fisso_grammo - $prezzoMercato;
        $differenzaTotale = $differenzaGrammo * $this->quantita_rimanente_grammi;
        $percentuale = $prezzoMercato > 0 ? (($differenzaGrammo / $prezzoMercato) * 100) : 0;

        return [
            'prezzo_fisso' => $this->prezzo_fisso_grammo,
            'prezzo_mercato' => $prezzoMercato,
            'differenza_grammo' => $differenzaGrammo,
            'differenza_totale' => $differenzaTotale,
            'differenza_percentuale' => $percentuale,
            'tipo' => $differenzaGrammo >= 0 ? 'GUADAGNO' : 'PERDITA'
        ];
    }

    private function ottieniPrezzoMercatoAttuale(): float
    {
        // Simulazione - da implementare con servizio reale
        $basePrices = [
            'ORO' => ['750' => 45.50, '585' => 35.25, '999' => 60.75],
            'ARGENTO' => ['925' => 0.75, '999' => 0.81],
            'PLATINO' => ['999' => 32.50]
        ];

        return $basePrices[$this->metallo_tipo][$this->titolo] ?? 1.0;
    }

    public function rinnovaAutomatico(array $nuoveCondizioni = []): self
    {
        if ($this->stato !== 'ATTIVO') {
            throw new \Exception('Solo fissaggi attivi possono essere rinnovati');
        }

        $quantitaRimanente = $this->quantita_rimanente_grammi;

        if ($quantitaRimanente <= 0) {
            throw new \Exception('Nessuna quantità rimanente da rinnovare');
        }

        $nuovoPrezzo = $nuoveCondizioni['prezzo_fisso_grammo'] ?? $this->ottieniPrezzoMercatoAttuale();

        $nuovoFissaggio = static::create([
            'numero_contratto' => static::generaNuovoNumeroContratto(),
            'cliente_id' => $this->cliente_id,
            'fonderia_id' => $this->fonderia_id,
            'metallo_tipo' => $this->metallo_tipo,
            'titolo' => $this->titolo,
            'quantita_totale_grammi' => $quantitaRimanente,
            'prezzo_fisso_grammo' => $nuovoPrezzo,
            'data_contratto' => now()->toDateString(),
            'data_inizio_validita' => $nuoveCondizioni['data_inizio'] ?? now()->addDay()->toDateString(),
            'data_scadenza' => $nuoveCondizioni['data_scadenza'] ?? now()->addMonths(3)->toDateString(),
            'consegna_minima_grammi' => $this->consegna_minima_grammi,
            'modalita_pagamento' => $this->modalita_pagamento,
            'stato' => 'ATTIVO',
            'creato_da_user_id' => auth()->id(),
            'note' => 'Rinnovo automatico da contratto ' . $this->numero_contratto
        ]);

        // Aggiorna stato originale
        $this->update(['stato' => 'COMPLETATO']);

        return $nuovoFissaggio;
    }

    // Metodi statici per analytics
    public static function generaNuovoNumeroContratto(): string
    {
        $anno = date('Y');
        $ultimoNumero = static::whereYear('created_at', $anno)->max('numero_contratto');

        if ($ultimoNumero) {
            $numero = intval(substr($ultimoNumero, -3)) + 1;
        } else {
            $numero = 1;
        }

        return sprintf('FIS-%s-%03d', $anno, $numero);
    }

    public static function riassuntoGiornaliero(Carbon $data = null): array
    {
        $data = $data ?? today();

        return [
            'scaduti_oggi' => static::whereDate('data_scadenza', $data)->where('stato', 'ATTIVO')->count(),
            'in_scadenza_7gg' => static::inScadenza(7)->count(),
            'consegne_oggi' => FissaggioConsegna::whereDate('data_consegna', $data)->count(),
            'valore_consegne_oggi' => FissaggioConsegna::whereDate('data_consegna', $data)->sum('valore_totale'),
            'nuovi_contratti_settimana' => static::where('created_at', '>=', now()->startOfWeek())->count(),
            'valore_residuo_totale' => static::attivi()->sum(DB::raw('(quantita_totale_grammi - quantita_consegnata_grammi) * prezzo_fisso_grammo'))
        ];
    }

    public static function analisiPerformance(Carbon $da = null, Carbon $a = null): array
    {
        $da = $da ?? now()->startOfMonth();
        $a = $a ?? now();

        $fissaggi = static::whereBetween('created_at', [$da, $a])->get();

        $totalGuadagno = 0;
        $totalPerdita = 0;
        $volumeTotale = 0;

        foreach ($fissaggi as $fissaggio) {
            $analisi = $fissaggio->calcolaGuadagnoPerdita();

            if ($analisi['tipo'] === 'GUADAGNO') {
                $totalGuadagno += $analisi['differenza_totale'];
            } else {
                $totalPerdita += abs($analisi['differenza_totale']);
            }

            $volumeTotale += $fissaggio->quantita_consegnata_grammi * $fissaggio->prezzo_fisso_grammo;
        }

        return [
            'numero_fissaggi' => $fissaggi->count(),
            'volume_totale' => $volumeTotale,
            'guadagno_totale' => $totalGuadagno,
            'perdita_totale' => $totalPerdita,
            'saldo_netto' => $totalGuadagno - $totalPerdita,
            'margine_percentuale' => $volumeTotale > 0 ? (($totalGuadagno - $totalPerdita) / $volumeTotale) * 100 : 0,
            'fissaggi_in_guadagno' => $fissaggi->filter(fn($f) => $f->calcolaGuadagnoPerdita()['tipo'] === 'GUADAGNO')->count(),
            'fissaggi_in_perdita' => $fissaggi->filter(fn($f) => $f->calcolaGuadagnoPerdita()['tipo'] === 'PERDITA')->count()
        ];
    }

    public static function reportScadenze(): array
    {
        return [
            'scaduti' => static::where('data_scadenza', '<', now())
                ->where('stato', 'ATTIVO')
                ->with('cliente')
                ->get(),
            'scadenza_oggi' => static::whereDate('data_scadenza', today())
                ->where('stato', 'ATTIVO')
                ->with('cliente')
                ->get(),
            'scadenza_settimana' => static::whereBetween('data_scadenza', [now(), now()->addWeek()])
                ->where('stato', 'ATTIVO')
                ->with('cliente')
                ->get(),
            'scadenza_mese' => static::whereBetween('data_scadenza', [now()->addWeek(), now()->addMonth()])
                ->where('stato', 'ATTIVO')
                ->with('cliente')
                ->get()
        ];
    }

    // Eventi del modello
    protected static function booted()
    {
        static::creating(function ($fissaggio) {
            if (!$fissaggio->numero_contratto) {
                $fissaggio->numero_contratto = static::generaNuovoNumeroContratto();
            }

            if (!$fissaggio->data_contratto) {
                $fissaggio->data_contratto = now()->toDateString();
            }
        });

        static::saving(function ($fissaggio) {
            if ($fissaggio->data_scadenza < now() && $fissaggio->stato === 'ATTIVO') {
                $fissaggio->stato = 'SCADUTO';
            }

            if ($fissaggio->quantita_consegnata_grammi >= $fissaggio->quantita_totale_grammi && $fissaggio->stato === 'ATTIVO') {
                $fissaggio->stato = 'COMPLETATO';
            }
        });

        static::updated(function ($fissaggio) {
            if ($fissaggio->isDirty('stato')) {
                activity()
                    ->performedOn($fissaggio)
                    ->withProperties([
                        'vecchio_stato' => $fissaggio->getOriginal('stato'),
                        'nuovo_stato' => $fissaggio->stato
                    ])
                    ->log('Cambio stato fissaggio');
            }
        });
    }
}
