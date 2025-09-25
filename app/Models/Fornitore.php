<?php

namespace App\Models;

use Illuminate\Database\Eloquent\{Factories\HasFactory, Model, Relations\HasMany, Relations\BelongsTo};

class Fornitore extends Model
{
    use HasFactory;

    protected $table = 'fornitori';

    protected $fillable = [
        'ragione_sociale',
        'partita_iva',
        'codice_fiscale',
        'tipo_fornitore',
        'metalli_trattati',
        'titoli_accettati',
        'email',
        'telefono',
        'indirizzo',
        'civico',
        'cap',
        'citta',
        'provincia',
        'sconto_percentuale_default',
        'giorni_pagamento_default',
        'limite_credito',
        'rating',
        'verificato_antiriciclaggio',
        'attivo',
        'creato_da_user_id'
    ];

    protected $casts = [
        'metalli_trattati' => 'array',
        'titoli_accettati' => 'array',
        'sconto_percentuale_default' => 'decimal:2',
        'limite_credito' => 'decimal:2',
        'rating' => 'decimal:2',
        'verificato_antiriciclaggio' => 'boolean',
        'attivo' => 'boolean'
    ];

    // Relazioni
    public function operazioni(): HasMany
    {
        return $this->hasMany(Operazione::class);
    }

    public function fissaggi(): HasMany
    {
        return $this->hasMany(Fissaggio::class, 'fonderia_id');
    }

    // Scopes
    public function scopeAttivi($query)
    {
        return $query->where('attivo', true);
    }

    public function scopePerTipo($query, string $tipo)
    {
        return $query->where('tipo_fornitore', strtoupper($tipo));
    }

    public function scopeConMetallo($query, string $metallo)
    {
        return $query->whereJsonContains('metalli_trattati', strtoupper($metallo));
    }

    // Accessors
    public function getIndirizzoCompletoAttribute(): string
    {
        return $this->indirizzo . ' ' . $this->civico . ', ' . $this->cap . ' ' . $this->citta . ' (' . $this->provincia . ')';
    }

    public function getRatingClasseAttribute(): string
    {
        if ($this->rating >= 8) return 'ECCELLENTE';
        if ($this->rating >= 6) return 'BUONO';
        if ($this->rating >= 4) return 'SUFFICIENTE';
        return 'INSUFFICIENTE';
    }

    // Business Logic
    public function trattaMetallo(string $metallo, int $titolo = null): bool
    {
        if (!in_array(strtoupper($metallo), $this->metalli_trattati ?? [])) {
            return false;
        }

        if ($titolo && !in_array($titolo, $this->titoli_accettati ?? [])) {
            return false;
        }

        return true;
    }

    public function calcolaPrezzo(float $prezzoBase): array
    {
        $scontoApplicato = $this->sconto_percentuale_default;
        $prezzoFinale = $prezzoBase * (1 - $scontoApplicato / 100);

        return [
            'prezzo_base' => $prezzoBase,
            'sconto_percentuale' => $scontoApplicato,
            'prezzo_finale' => $prezzoFinale
        ];
    }
}
