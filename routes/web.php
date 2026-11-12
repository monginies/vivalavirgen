<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ArticlePhotoController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PostalCodeController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureSuperadmin;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::resource('categories', CategoryController::class)->except('show');

    Route::resource('articles', ArticleController::class);
    Route::post('articles/{article}/units', [ArticleController::class, 'addUnits'])->name('articles.units.store');
    Route::get('articles/{article}/labels', [ArticleController::class, 'labels'])->name('articles.labels');
    Route::post('articles/{article}/photos', [ArticlePhotoController::class, 'store'])->name('article-photos.store');
    Route::delete('article-photos/{photo}', [ArticlePhotoController::class, 'destroy'])->name('article-photos.destroy');
    Route::post('article-photos/{photo}/main', [ArticlePhotoController::class, 'main'])->name('article-photos.main');

    // URL corta que va impresa en cada etiqueta QR
    Route::get('q/{token}', [UnitController::class, 'resolve'])->name('qr.resolve');
    Route::get('units/{unit}', [UnitController::class, 'show'])->name('units.show');
    Route::patch('units/{unit}', [UnitController::class, 'update'])->name('units.update');
    Route::delete('units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');
    Route::get('units/{unit}/qr.svg', [UnitController::class, 'qr'])->name('units.qr');

    Route::resource('clients', ClientController::class);

    Route::get('orders/distance', [OrderController::class, 'distance'])->name('orders.distance');
    Route::get('postal-codes/{code}', PostalCodeController::class)->name('postal-codes.show')->where('code', '[0-9]{5}');
    Route::resource('orders', OrderController::class)->except('destroy');
    Route::post('orders/{order}/status', [OrderController::class, 'transition'])->name('orders.transition');
    Route::post('orders/{order}/extend', [OrderController::class, 'extend'])->name('orders.extend');
    Route::get('calendar', CalendarController::class)->name('calendar');
    Route::get('availability', AvailabilityController::class)->name('availability');

    Route::middleware(EnsureSuperadmin::class)->group(function () {
        Route::resource('users', UserController::class)->except('show');
        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });
});
