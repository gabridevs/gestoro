<?php

namespace App\Models;

use Illuminate\Database\Eloquent\{Factories\HasFactory, Model, Relations\BelongsTo, Relations\HasOne};
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Operazione extends Model
{
    use HasFactory;

    protected $table = 'operazioni';

    protected $fillable = [
        'numero_operazione',
        'cliente_id',
        'fornitore_id',
        'tipo',
        'controparte_tipo',
        'metallo_tipo',
        'titolo',
        'descrizione_oggetto',
        'peso_lordo_grammi',
        'peso_netto_grammi',
        'prezzo_mercato_grammo',
        'prezzo_applicato_grammo',
        'valore_totale_operazione',
        'modalita_pagamento',
        'importo_contanti',
        'importo_bonifico',
        'pagamento_completato',
        'data_operazione',
        'data_giacenza_obbligatoria',
        'ddt_numero',
        'fattura_numero',
        'controllo_antiriciclaggio_ok',
        'comunicazione_bancaitalia_inviata',
        'stato',
        'note_interne',
        'registrata_da_user_id'
    ];

    protected $casts = [
        'peso_lordo_grammi' => 'decimal:3',
        'peso_netto_grammi' => 'decimal:3',
        'prezzo_mercato_grammo' => 'decimal:4',
        'prezzo_applicato_grammo' => 'decimal:4',
        'valore_totale_operazione' => 'decimal:2',
        'importo_contanti' => 'decimal:2',
        'importo_bonifico' => 'decimal:2',
        'data_operazione' => 'datetime',
        'data_giacenza_obbligatoria' => 'date',
        'controllo_antiriciclaggio_ok' => 'boolean',
        'comunicazione_bancaitalia_inviata' => 'boolean',
        'pagamento_completato' => 'boolean'
    ];

    // Relazioni
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function fornitore(): BelongsTo
    {
        return $this->belongsTo(Fornitore::class);
    }

    public function fissaggioConsegna(): HasOne
    {
        return $this->hasOne(FissaggioConsegna::class);
    }

    // Scopes
    public function scopeAcquisti($query)
    {
        return $query->where('tipo', 'ACQUISTO');
    }

    public function scopeVendite($query)
    {
        return $query->where('tipo', 'VENDITA');
    }

    public function scopeNelPeriodo($query, Carbon $daData, Carbon $aData)
    {
        return $query->whereBetween('data_operazione', [$daData, $aData]);
    }

    public function scopePerMetallo($query, string $metallo)
    {
        return $query->where('metallo_tipo', strtoupper($metallo));
    }

    // Accessors
    public function getContoparteNomeAttribute(): string
    {
        if ($this->cliente) {
            return $this->cliente->nome_completo;
        } elseif ($this->fornitore) {
            return $this->fornitore->ragione_sociale;
        }
        return 'N/D';
    }

    public function getMarginePercentualeAttribute(): float
    {
        if ($this->prezzo_mercato_grammo == 0) return 0;

        return (($this->prezzo_applicato_grammo - $this->prezzo_mercato_grammo) / $this->prezzo_mercato_grammo) * 100;
    }

    public function getRichiedeComunicazioneBancaItaliaAttribute(): bool
    {
        return $this->valore_totale_operazione >= config('metalli.soglie_antiriciclaggio.comunicazione_bancaitalia_soglia')
            && in_array($this->metallo_tipo, ['ORO']);
    }

    // Business Logic
    public static function generaNuovoNumero(): string
    {
        $anno = date('Y');
        $ultimoNumero = self::whereYear('created_at', $anno)->max('numero_operazione');

        if ($ultimoNumero) {
            $numero = intval(substr($ultimoNumero, -6)) + 1;
        } else {
            $numero = 1;
        }

        return sprintf('OP-%s-%06d', $anno, $numero);
    }

    public function calcolaValori(): void
    {
        // Valore totale = peso * prezzo
        $this->valore_totale_operazione = $this->peso_netto_grammi * $this->prezzo_applicato_grammo;
    }

    public function conferma(): array
    {
        try {
            DB::transaction(function () {
                // Controlli antiriciclaggio se necessario
                if ($this->importo_contanti > 0 && $this->cliente) {
                    $controlloContanti = $this->cliente->puoOperareInContanti($this->importo_contanti);
                    if (!$controlloContanti['autorizzato']) {
                        throw new \Exception('Controllo antiriciclaggio fallito');
                    }

                    $this->cliente->registraUtilizzoContanti($this->importo_contanti);
                    $this->controllo_antiriciclaggio_ok = true;
                }

                // Imposta giacenza obbligatoria se acquisto da cliente
                if ($this->tipo === 'ACQUISTO' && $this->cliente) {
                    $this->data_giacenza_obbligatoria = now()->addDays(
                        config('metalli.giacenza_obbligatoria.giorni_default')
                    );
                }

                $this->stato = 'CONFERMATA';
                $this->save();
            });

            return ['successo' => true, 'messaggio' => 'Operazione confermata'];
        } catch (\Exception $e) {
            return ['successo' => false, 'errore' => $e->getMessage()];
        }
    }

    // Eventi del modello
    protected static function booted()
    {
        static::creating(function ($operazione) {
            if (!$operazione->numero_operazione) {
                $operazione->numero_operazione = self::generaNuovoNumero();
            }

            if (!$operazione->data_operazione) {
                $operazione->data_operazione = now();
            }
        });

        static::saving(function ($operazione) {
            $operazione->calcolaValori();
        });
    }
}
