<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\v1\ReservationController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Routes publiques pour le suivi de réservation (utilisées dans les emails)
Route::get('/reservations/{uuid}', [ReservationController::class, 'showPublic'])
    ->name('reservations.show');

Route::get('/reservations/{uuid}/add-options', [ReservationController::class, 'showAddOptions'])
    ->name('reservations.add-options');

Route::post('/reservations/{uuid}/add-options', [ReservationController::class, 'addOptionsPublic'])
    ->name('reservations.add-options.store');
