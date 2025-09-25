<?php

use App\Livewire\Dashboard;
// use App\Livewire\GestioneFissaggi; // Commentato temporaneamente
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/dashboard');
});

// Dashboard principale
Route::get('/dashboard', Dashboard::class)->name('dashboard');

// Gestione Fissaggi - TEMPORANEAMENTE DISABILITATO
// Route::get('/fissaggi', GestioneFissaggi::class)->name('fissaggi.index');
// Route::get('/fissaggi/create', GestioneFissaggi::class)->name('fissaggi.create');

// Placeholder routes temporanee
Route::get('/fissaggi', function () {
    return redirect('/dashboard')->with('message', 'Sezione Fissaggi in sviluppo');
})->name('fissaggi.index');

Route::get('/fissaggi/create', function () {
    return redirect('/dashboard')->with('message', 'Creazione Fissaggio in sviluppo');
})->name('fissaggi.create');

Route::get('/fissaggi/{id}/dettaglio', function ($id) {
    return redirect('/dashboard')->with('message', 'Dettaglio Fissaggio in sviluppo');
})->name('fissaggi.dettaglio');

// Altre routes placeholder
Route::get('/acquisti', function () {
    return redirect('/dashboard')->with('message', 'Sezione Acquisti in sviluppo');
})->name('acquisti.index');

Route::get('/acquisti/create', function () {
    return redirect('/dashboard')->with('message', 'Creazione Acquisto in sviluppo');
})->name('acquisti.create');

Route::get('/operazioni', function () {
    return redirect('/dashboard')->with('message', 'Sezione Operazioni in sviluppo');
})->name('operazioni.index');

Route::get('/clienti', function () {
    return redirect('/dashboard')->with('message', 'Sezione Clienti in sviluppo');
})->name('clienti.index');

// Test routes per sviluppo
Route::get('/test-prezzi', function () {
    try {
        $prezziService = app(\App\Services\PrezziMetalliService::class);

        return response()->json([
            'status' => 'success',
            'oro_750' => $prezziService->getPrezzoAttuale('ORO', 750),
            'oro_585' => $prezziService->getPrezzoAttuale('ORO', 585),
            'argento_925' => $prezziService->getPrezziAttuale('ARGENTO', 925),
            'trend_oro' => $prezziService->calcolaDifferenzaPercentuale('ORO', 750),
            'timestamp' => now()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
})->name('test.prezzi');

Route::get('/test-antiriciclaggio', function () {
    try {
        $amlService = app(\App\Services\AntiriciclaggioService::class);

        return response()->json([
            'status' => 'success',
            'verifica_cf_valido' => $amlService->verificaListeUIF('RSSMRO75A01H501Z'),
            'verifica_cf_bloccato' => $amlService->verificaListeUIF('RSSMRO80A01H501Z'),
            'verifica_piva' => $amlService->verificaPartitaIva('12345678901'),
            'timestamp' => now()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
})->name('test.aml');
