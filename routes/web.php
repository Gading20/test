<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\KategoriItemsController;
use App\Http\Controllers\MasterItemsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes();

Route::get('/home', [HomeController::class, 'index'])->name('home');
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/master-items', [MasterItemsController::class, 'index']);
Route::get('/master-items/search', [MasterItemsController::class, 'search']);
Route::get('/master-items/excel', [MasterItemsController::class, 'downloadExcel']);
Route::get('/master-items/form/{method}/{id?}', [MasterItemsController::class, 'formView']);
Route::post('/master-items/form/{method}/{id?}', [MasterItemsController::class, 'formSubmit']);

Route::get('/master-items/view/{kode}', [MasterItemsController::class, 'singleView']);
Route::post('/master-items/delete/{id}', [MasterItemsController::class, 'delete']);

Route::get('/master-items/update-random-data', [MasterItemsController::class, 'updateRandomData']);

// Kategori Items (many-to-many dengan Master Items)
Route::get('/kategori-items', [KategoriItemsController::class, 'index']);
Route::get('/kategori-items/search', [KategoriItemsController::class, 'search']);
Route::get('/kategori-items/form/{method}/{id?}', [KategoriItemsController::class, 'formView']);
Route::post('/kategori-items/form/{method}/{id?}', [KategoriItemsController::class, 'formSubmit']);
Route::get('/kategori-items/view/{kode}', [KategoriItemsController::class, 'singleView']);
Route::get('/kategori-items/pdf/{kode}', [KategoriItemsController::class, 'cetakPdf']);
Route::post('/kategori-items/delete/{id}', [KategoriItemsController::class, 'delete']);
