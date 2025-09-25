<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class AntiriciclaggioService
{
    /**
     * Verifica se un codice fiscale è presente nelle liste UIF
     */
    public function verificaListeUIF(string $codiceFiscale): array
    {
        try {
            // In produzione qui ci sarebbe la chiamata alle API UIF ufficiali
            // Per ora simuliamo il controllo

            Log::info("Verifica liste UIF per CF: " . $codiceFiscale);

            // Simulazione controllo (in produzione usare API reali)
            $presente = false;
            $tipoLista = null;

            // Lista simulata di CF bloccati per demo
            $cfBloccati = [
                'RSSMRO80A01H501Z', // Esempio CF bloccato
                'VRDNNA75B12F205Y'  // Altro esempio
            ];

            if (in_array($codiceFiscale, $cfBloccati)) {
                $presente = true;
                $tipoLista = 'LISTA_TERRORISMO';
            }

            return [
                'presente' => $presente,
                'tipo_lista' => $tipoLista,
                'data_controllo' => now(),
                'fonte' => 'UIF_API'
            ];
        } catch (\Exception $e) {
            Log::error("Errore verifica liste UIF: " . $e->getMessage());

            return [
                'presente' => false,
                'errore' => $e->getMessage(),
                'data_controllo' => now()
            ];
        }
    }

    /**
     * Verifica validità partita IVA
     */
    public function verificaPartitaIva(string $partitaIva): array
    {
        try {
            // Rimuovi spazi e converti in maiuscolo
            $partitaIva = strtoupper(str_replace(' ', '', $partitaIva));

            // Controllo formato base
            if (!preg_match('/^IT\d{11}$/', $partitaIva) && !preg_match('/^\d{11}$/', $partitaIva)) {
                return [
                    'valida' => false,
                    'errore' => 'Formato partita IVA non valido'
                ];
            }

            // Estrai solo i numeri
            $numeri = preg_replace('/[^0-9]/', '', $partitaIva);

            // Controllo checksum partita IVA italiana
            if (strlen($numeri) === 11) {
                $checksum = $this->calcolaChecksumPartitaIva($numeri);

                if ($checksum) {
                    // In produzione qui faresti la chiamata all'API dell'Agenzia delle Entrate
                    return [
                        'valida' => true,
                        'formato_corretto' => true,
                        'attiva' => true, // Da verificare con API reale
                        'data_verifica' => now()
                    ];
                }
            }

            return [
                'valida' => false,
                'errore' => 'Checksum partita IVA non valido'
            ];
        } catch (\Exception $e) {
            Log::error("Errore verifica partita IVA: " . $e->getMessage());

            return [
                'valida' => false,
                'errore' => $e->getMessage()
            ];
        }
    }

    /**
     * Calcola checksum partita IVA italiana
     */
    private function calcolaChecksumPartitaIva(string $partitaIva): bool
    {
        if (strlen($partitaIva) !== 11) {
            return false;
        }

        $somma = 0;
        for ($i = 0; $i < 10; $i++) {
            $cifra = intval($partitaIva[$i]);
            if ($i % 2 === 1) {
                $cifra *= 2;
                if ($cifra > 9) {
                    $cifra = intval($cifra / 10) + ($cifra % 10);
                }
            }
            $somma += $cifra;
        }

        $checkDigit = (10 - ($somma % 10)) % 10;

        return $checkDigit === intval($partitaIva[10]);
    }

    /**
     * Verifica nel registro delle imprese
     */
    public function verificaRegistroImprese(string $partitaIva): array
    {
        try {
            // In produzione qui ci sarebbe la chiamata alle API del Registro Imprese
            Log::info("Verifica registro imprese per P.IVA: " . $partitaIva);

            // Simulazione per demo
            return [
                'attiva' => true,
                'denominazione' => 'SOCIETÀ ESEMPIO SRL',
                'sede' => 'Milano (MI)',
                'attivita' => 'Commercio metalli preziosi',
                'data_verifica' => now()
            ];
        } catch (\Exception $e) {
            Log::error("Errore verifica registro imprese: " . $e->getMessage());

            return [
                'attiva' => false,
                'errore' => $e->getMessage()
            ];
        }
    }

    /**
     * Genera scheda antiriciclaggio digitale
     */
    public function generaScheda($cliente): string
    {
        try {
            $dati = [
                'cliente' => $cliente,
                'data_generazione' => now(),
                'numero_scheda' => 'AML-' . date('Y') . '-' . str_pad($cliente->id, 6, '0', STR_PAD_LEFT)
            ];

            // Genera PDF con DomPDF
            $pdf = Pdf::loadView('pdf.scheda-antiriciclaggio', $dati);

            // Salva il file
            $filename = 'scheda_aml_' . $cliente->codice_fiscale . '_' . date('Y_m_d') . '.pdf';
            $filepath = storage_path('app/antiriciclaggio/' . $filename);

            // Crea directory se non esiste
            if (!is_dir(dirname($filepath))) {
                mkdir(dirname($filepath), 0755, true);
            }

            $pdf->save($filepath);

            Log::info("Scheda antiriciclaggio generata: " . $filename);

            return $filepath;
        } catch (\Exception $e) {
            Log::error("Errore generazione scheda antiriciclaggio: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Controllo automatico soglie contanti
     */
    public function verificaSoglieContanti(float $importo, $cliente = null): array
    {
        $sogliaMax = config('metalli.soglie_antiriciclaggio.contanti_max', 2999.99);

        $controlli = [
            'importo_valido' => $importo <= $sogliaMax,
            'soglia_massima' => $sogliaMax,
            'importo_richiesto' => $importo,
            'eccedenza' => max(0, $importo - $sogliaMax)
        ];

        if ($cliente) {
            $disponibileCliente = $cliente->contanti_disponibili_anno ?? $sogliaMax;
            $controlli['disponibile_cliente'] = $disponibileCliente;
            $controlli['cliente_autorizzato'] = $importo <= $disponibileCliente;
        }

        return $controlli;
    }

    /**
     * Prepara comunicazione Banca d'Italia
     */
    public function preparaComunicazioneBancaItalia($operazione): array
    {
        $soglia = config('metalli.soglie_antiriciclaggio.comunicazione_bancaitalia_soglia', 10000);

        if ($operazione->valore_totale_operazione < $soglia) {
            return ['richiesta' => false, 'motivo' => 'Sotto soglia'];
        }

        $datiComunicazione = [
            'numero_operazione' => $operazione->numero_operazione,
            'data_operazione' => $operazione->data_operazione->format('Y-m-d'),
            'tipo_operazione' => $operazione->tipo,
            'metallo' => $operazione->metallo_tipo,
            'quantita' => $operazione->peso_netto_grammi,
            'valore' => $operazione->valore_totale_operazione,
            'cliente' => [
                'codice_fiscale' => $operazione->cliente->codice_fiscale ?? null,
                'nome' => $operazione->cliente->nome_completo ?? null
            ]
        ];

        Log::info("Preparata comunicazione Banca d'Italia", $datiComunicazione);

        return [
            'richiesta' => true,
            'dati' => $datiComunicazione,
            'formato_xml' => $this->generaXMLBancaItalia($datiComunicazione)
        ];
    }

    /**
     * Genera XML per comunicazione Banca d'Italia
     */
    private function generaXMLBancaItalia(array $dati): string
    {
        // Formato XML semplificato per demo
        $xml = "<?xml version='1.0' encoding='UTF-8'?>\n";
        $xml .= "<ComunicazioneOro>\n";
        $xml .= "  <NumeroOperazione>{$dati['numero_operazione']}</NumeroOperazione>\n";
        $xml .= "  <DataOperazione>{$dati['data_operazione']}</DataOperazione>\n";
        $xml .= "  <TipoOperazione>{$dati['tipo_operazione']}</TipoOperazione>\n";
        $xml .= "  <Metallo>{$dati['metallo']}</Metallo>\n";
        $xml .= "  <Quantita>{$dati['quantita']}</Quantita>\n";
        $xml .= "  <Valore>{$dati['valore']}</Valore>\n";
        $xml .= "  <Cliente>\n";
        $xml .= "    <CodiceFiscale>{$dati['cliente']['codice_fiscale']}</CodiceFiscale>\n";
        $xml .= "    <Nome>{$dati['cliente']['nome']}</Nome>\n";
        $xml .= "  </Cliente>\n";
        $xml .= "</ComunicazioneOro>\n";

        return $xml;
    }
}
