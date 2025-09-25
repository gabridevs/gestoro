<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('operazioni', function (Blueprint $table) {
            $table->id();

            // Identificativi
            $table->string('numero_operazione', 20)->unique();
            $table->foreignId('cliente_id')->nullable()->constrained('clienti');
            $table->foreignId('fornitore_id')->nullable()->constrained('fornitori');

            // Tipo operazione
            $table->enum('tipo', ['ACQUISTO', 'VENDITA', 'FUSIONE', 'AFFINAZIONE']);
            $table->enum('controparte_tipo', ['CLIENTE', 'FORNITORE', 'INTERNO']);

            // Dettagli metallo
            $table->enum('metallo_tipo', ['ORO', 'ARGENTO', 'PLATINO', 'PALLADIO']);
            $table->smallInteger('titolo'); // 750, 585, 999
            $table->string('descrizione_oggetto', 500)->nullable();

            // Pesi e valori
            $table->decimal('peso_lordo_grammi', 10, 3);
            $table->decimal('peso_netto_grammi', 10, 3);
            $table->decimal('prezzo_mercato_grammo', 8, 4);
            $table->decimal('prezzo_applicato_grammo', 8, 4);
            $table->decimal('valore_totale_operazione', 10, 2);

            // Pagamento
            $table->enum('modalita_pagamento', ['CONTANTI', 'BONIFICO', 'ASSEGNO']);
            $table->decimal('importo_contanti', 10, 2)->default(0);
            $table->decimal('importo_bonifico', 10, 2)->default(0);
            $table->boolean('pagamento_completato')->default(false);

            // Date
            $table->datetime('data_operazione');
            $table->date('data_giacenza_obbligatoria')->nullable();

            // Documenti
            $table->string('ddt_numero', 50)->nullable();
            $table->string('fattura_numero', 50)->nullable();

            // Controlli normativi
            $table->boolean('controllo_antiriciclaggio_ok')->default(false);
            $table->boolean('comunicazione_bancaitalia_inviata')->default(false);

            // Stato
            $table->enum('stato', ['BOZZA', 'CONFERMATA', 'COMPLETATA', 'ANNULLATA'])->default('BOZZA');
            $table->text('note_interne')->nullable();

            // Metadati
            $table->foreignId('registrata_da_user_id')->constrained('users');
            $table->timestamps();

            // Indici
            $table->index(['data_operazione', 'tipo']);
            $table->index(['metallo_tipo', 'titolo']);
            $table->index(['stato']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('operazioni');
    }
};
