<?php

namespace App\Models;

use Illuminate\Database\Eloquent\{Factories\HasFactory, Model, Relations\HasMany, Relations\BelongsTo};
use Carbon\Carbon;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clienti';

    protected $fillable = [
        'codice_fiscale',
        'nome',
        'cognome',
        'data_nascita',
        'documento_tipo',
        'documento_numero',
        'documento_scadenza',
        'email',
        'telefono',
        'indirizzo',
        'civico',
        'cap',
        'citta',
        'provincia',
        'ultimo_controllo_antiriciclaggio',
        'stato_antiriciclaggio',
        'limite_contanti_annuo',
        'utilizzato_contanti_anno_corrente',
        'attivo',
        'creato_da_user_id'
    ];

    protected $casts = [
        'data_nascita' => 'date',
        'documento_scadenza' => 'date',
        'ultimo_controllo_antiriciclaggio' => 'datetime',
        'limite_contanti_annuo' => 'decimal:2',
        'utilizzato_contanti_anno_corrente' => 'decimal:2',
        'attivo' => 'boolean'
    ];

    // Relazioni
    public function operazioni(): HasMany
    {
        return $this->hasMany(Operazione::class);
    }

    public function fissaggi(): HasMany
    {
        return $this->hasMany(Fissaggio::class);
    }

    public function creatoDA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creato_da_user_id');
    }

    // Scopes
    public function scopeAttivi($query)
    {
        return $query->where('attivo', true);
    }

    public function scopeDocumentoInScadenza($query, int $giorni = 30)
    {
        return $query->whereBetween('documento_scadenza', [now(), now()->addDays($giorni)]);
    }

    public function scopeControlloAntiriciclaggioScaduto($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('ultimo_controllo_antiriciclaggio')
                ->orWhere('ultimo_controllo_antiriciclaggio', '<', now()->subYear());
        });
    }

    // Accessors
    public function getNomeCompletoAttribute(): string
    {
        return $this->cognome . ', ' . $this->nome;
    }

    public function getIndirizzoCompletoAttribute(): string
    {
        return $this->indirizzo . ' ' . $this->civico . ', ' . $this->cap . ' ' . $this->citta . ' (' . $this->provincia . ')';
    }

    public function getDocumentoValidoAttribute(): bool
    {
        return $this->documento_scadenza && $this->documento_scadenza->isFuture();
    }

    public function getContantiDisponibiliAnnoAttribute(): float
    {
        return $this->limite_contanti_annuo - $this->utilizzato_contanti_anno_corrente;
    }

    // Business Logic
    public function puoOperareInContanti(float $importo): array
    {
        $errori = [];

        if (!$this->documento_valido) {
            $errori[] = 'Documento di identità scaduto';
        }

        if ($this->stato_antiriciclaggio !== 'OK') {
            $errori[] = 'Controlli antiriciclaggio non completati';
        }

        if ($importo > $this->contanti_disponibili_anno) {
            $errori[] = 'Limite contanti annuale superato';
        }

        return [
            'autorizzato' => empty($errori),
            'errori' => $errori,
            'contanti_rimanenti' => $this->contanti_disponibili_anno
        ];
    }

    public function registraUtilizzoContanti(float $importo): void
    {
        $this->utilizzato_contanti_anno_corrente += $importo;
        $this->save();

        activity()
            ->performedOn($this)
            ->withProperties(['importo' => $importo])
            ->log('Utilizzo contanti registrato');
    }

    public function eseguiControlloAntiriciclaggio(): array
    {
        // Simula controllo (da implementare con servizi reali)
        $this->update([
            'ultimo_controllo_antiriciclaggio' => now(),
            'stato_antiriciclaggio' => 'OK'
        ]);

        return ['esito' => 'SUCCESS'];
    }
}
