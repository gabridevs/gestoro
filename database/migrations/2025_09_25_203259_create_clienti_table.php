<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('clienti', function (Blueprint $table) {
            $table->id();

            // Dati anagrafici
            $table->string('codice_fiscale', 16)->unique();
            $table->string('nome', 100);
            $table->string('cognome', 100);
            $table->date('data_nascita')->nullable();

            // Documento identità
            $table->enum('documento_tipo', ['CI', 'PATENTE', 'PASSAPORTO']);
            $table->string('documento_numero', 50);
            $table->date('documento_scadenza');

            // Contatti
            $table->string('email', 255)->nullable();
            $table->string('telefono', 20)->nullable();

            // Indirizzo
            $table->string('indirizzo', 255);
            $table->string('civico', 10);
            $table->string('cap', 5);
            $table->string('citta', 100);
            $table->string('provincia', 2);

            // Controlli antiriciclaggio
            $table->timestamp('ultimo_controllo_antiriciclaggio')->nullable();
            $table->enum('stato_antiriciclaggio', ['OK', 'DA_VERIFICARE', 'BLOCCATO'])->default('DA_VERIFICARE');
            $table->decimal('limite_contanti_annuo', 10, 2)->default(2999.99);
            $table->decimal('utilizzato_contanti_anno_corrente', 10, 2)->default(0);

            // Metadati
            $table->boolean('attivo')->default(true);
            $table->foreignId('creato_da_user_id')->constrained('users');
            $table->timestamps();

            // Indici
            $table->index(['cognome', 'nome']);
            $table->index(['stato_antiriciclaggio']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('clienti');
    }
};
