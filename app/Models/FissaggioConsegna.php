<?php

namespace App\Models;

use Illuminate\Database\Eloquent\{Factories\HasFactory, Model, Relations\BelongsTo};

class FissaggioConsegna extends Model
{
    use HasFactory;

    protected $table = 'fissaggio_consegne';

    protected $fillable = [
        'fissaggio_id',
        'operazione_id',
        'data_consegna',
        'peso_consegnato_grammi',
        'prezzo_applicato_grammo',
        'valore_totale',
        'ddt_numero',
        'fattura_numero',
        'registrata_da_user_id',
        'note'
    ];

    protected $casts = [
        'data_consegna' => 'date',
        'peso_consegnato_grammi' => 'decimal:3',
        'prezzo_applicato_grammo' => 'decimal:4',
        'valore_totale' => 'decimal:2'
    ];

    // Relazioni
    public function fissaggio(): BelongsTo
    {
        return $this->belongsTo(Fissaggio::class);
    }

    public function operazione(): BelongsTo
    {
        return $this->belongsTo(Operazione::class);
    }

    public function registrataDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrata_da_user_id');
    }

    // Scopes
    public function scopeNelPeriodo($query, $da, $a)
    {
        return $query->whereBetween('data_consegna', [$da, $a]);
    }

    public function scopePerFissaggio($query, $fissaggioId)
    {
        return $query->where('fissaggio_id', $fissaggioId);
    }

    // Accessors
    public function getDescrizioneCompletaAttribute(): string
    {
        return "Consegna " . number_format($this->peso_consegnato_grammi, 1) . "g per " .
            $this->fissaggio->numero_contratto ?? 'N/D';
    }

    // Business Logic
    public function calcolaRisparmioVsMercato(): array
    {
        $prezzoMercatoAttuale = $this->fissaggio->ottieniPrezzoMercatoAttuale();
        $valoreAMercato = $this->peso_consegnato_grammi * $prezzoMercatoAttuale;
        $risparmio = $this->valore_totale - $valoreAMercato;

        return [
            'valore_fissaggio' => $this->valore_totale,
            'valore_mercato' => $valoreAMercato,
            'risparmio_assoluto' => $risparmio,
            'risparmio_percentuale' => $valoreAMercato > 0 ? ($risparmio / $valoreAMercato) * 100 : 0,
            'tipo' => $risparmio >= 0 ? 'GUADAGNO' : 'PERDITA'
        ];
    }
}
