<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('fornitori', function (Blueprint $table) {
            $table->id();

            // Dati aziendali
            $table->string('ragione_sociale', 255);
            $table->string('partita_iva', 11)->unique();
            $table->string('codice_fiscale', 16)->nullable();
            $table->enum('tipo_fornitore', ['FONDERIA', 'COMPRO_ORO', 'GROSSISTA']);

            // Metalli trattati
            $table->json('metalli_trattati')->nullable(); // ['ORO', 'ARGENTO']
            $table->json('titoli_accettati')->nullable(); // [750, 585, 999]

            // Contatti
            $table->string('email', 255)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('indirizzo', 255);
            $table->string('civico', 10);
            $table->string('cap', 5);
            $table->string('citta', 100);
            $table->string('provincia', 2);

            // Condizioni commerciali
            $table->decimal('sconto_percentuale_default', 5, 2)->default(0);
            $table->integer('giorni_pagamento_default')->default(30);
            $table->decimal('limite_credito', 12, 2)->default(0);
            $table->decimal('rating', 3, 2)->default(5); // 0-10

            // Controlli
            $table->boolean('verificato_antiriciclaggio')->default(false);
            $table->boolean('attivo')->default(true);
            $table->foreignId('creato_da_user_id')->constrained('users');
            $table->timestamps();

            $table->index(['tipo_fornitore', 'attivo']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('fornitori');
    }
};
