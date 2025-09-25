<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('fissaggio_consegne', function (Blueprint $table) {
            $table->id();

            $table->foreignId('fissaggio_id')->constrained('fissaggi')->cascadeOnDelete();
            $table->foreignId('operazione_id')->constrained('operazioni');

            // Dettagli consegna
            $table->date('data_consegna');
            $table->decimal('peso_consegnato_grammi', 10, 3);
            $table->decimal('prezzo_applicato_grammo', 8, 4);
            $table->decimal('valore_totale', 12, 2);

            // Documenti
            $table->string('ddt_numero', 50)->nullable();
            $table->string('fattura_numero', 50)->nullable();

            // Metadati
            $table->foreignId('registrata_da_user_id')->constrained('users');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['fissaggio_id', 'data_consegna']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('fissaggio_consegne');
    }
};
