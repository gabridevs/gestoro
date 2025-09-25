<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('fissaggi', function (Blueprint $table) {
            $table->id();

            // Identificativi
            $table->string('numero_contratto', 20)->unique();
            $table->foreignId('cliente_id')->constrained('clienti');
            $table->foreignId('fonderia_id')->nullable()->constrained('fornitori');

            // Metallo
            $table->enum('metallo_tipo', ['ORO', 'ARGENTO', 'PLATINO', 'PALLADIO']);
            $table->smallInteger('titolo');

            // Quantità e prezzi
            $table->decimal('quantita_totale_grammi', 12, 3);
            $table->decimal('quantita_consegnata_grammi', 12, 3)->default(0);
            $table->decimal('prezzo_fisso_grammo', 8, 4);

            // Date
            $table->date('data_contratto');
            $table->date('data_inizio_validita');
            $table->date('data_scadenza');

            // Condizioni
            $table->decimal('consegna_minima_grammi', 10, 3)->default(0);
            $table->enum('modalita_pagamento', ['CONTANTI', 'BONIFICO', 'ASSEGNO'])->default('BONIFICO');

            // Stato
            $table->enum('stato', ['BOZZA', 'ATTIVO', 'SOSPESO', 'SCADUTO', 'COMPLETATO', 'ANNULLATO'])->default('BOZZA');
            $table->text('note')->nullable();

            // Metadati
            $table->foreignId('creato_da_user_id')->constrained('users');
            $table->timestamps();

            // Indici
            $table->index(['cliente_id', 'stato']);
            $table->index(['data_scadenza', 'stato']);
            $table->index(['metallo_tipo', 'titolo']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('fissaggi');
    }
};
