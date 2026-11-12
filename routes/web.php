<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ArticlePhotoController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ClientCategoryController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Portal;
use App\Http\Controllers\PostalCodeController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VenueController;
use App\Http\Middleware\EnsureSuperadmin;
use Illuminate\Support\Facades\Route;

/*
| Web de clientes en la raíz (vivalavirgen.net) y backoffice en /app.
*/

// ---------- Web de clientes ----------
Route::name('portal.')->group(function () {
    Route::get('/', [Portal\CatalogController::class, 'index'])->name('home');
    Route::get('articulo/{reference}', [Portal\CatalogController::class, 'show'])->name('article');
    Route::view('privacidad', 'portal.privacy')->name('privacy');

    Route::get('pedido', [Portal\CartController::class, 'show'])->name('cart');
    Route::post('pedido', [Portal\CartController::class, 'update'])->name('cart.update');
    Route::post('pedido/articulo/{article}', [Portal\CartController::class, 'add'])->name('cart.add');
    Route::delete('pedido/articulo/{article}', [Portal\CartController::class, 'remove'])->name('cart.remove');
    Route::get('pedido/transporte', [Portal\CartController::class, 'transport'])->name('cart.transport')->middleware('throttle:30,1');
    Route::get('codigo-postal/{code}', PostalCodeController::class)->name('postal-codes')->where('code', '[0-9]{5}');

    Route::middleware('guest:client')->group(function () {
        Route::get('entrar', [Portal\AuthController::class, 'showLogin'])->name('login');
        Route::post('entrar', [Portal\AuthController::class, 'login']);
        Route::get('crear-cuenta', [Portal\AuthController::class, 'showRegister'])->name('register');
        Route::post('crear-cuenta', [Portal\AuthController::class, 'register']);
        Route::get('contrasena', [Portal\AuthController::class, 'showForgot'])->name('password.request');
        Route::post('contrasena', [Portal\AuthController::class, 'sendResetLink'])->name('password.email')->middleware('throttle:5,1');
        Route::get('contrasena/{token}', [Portal\AuthController::class, 'showReset'])->name('password.reset');
        Route::post('contrasena/nueva', [Portal\AuthController::class, 'reset'])->name('password.update');
    });
    Route::get('verificar/{id}/{hash}', [Portal\AuthController::class, 'verify'])->name('verification.verify')->middleware('signed');

    Route::middleware('auth:client')->group(function () {
        Route::post('salir', [Portal\AuthController::class, 'logout'])->name('logout');
        Route::post('verificar/reenviar', [Portal\AuthController::class, 'resendVerification'])->name('verification.send');
        Route::get('mi-cuenta', [Portal\AccountController::class, 'index'])->name('account');
        Route::get('mi-cuenta/pedidos/{number}', [Portal\AccountController::class, 'order'])->name('orders.show');
        Route::post('pedido/solicitar', [Portal\CartController::class, 'checkout'])->name('checkout');
    });
});

// ---------- Backoffice ----------
Route::prefix('app')->group(function () {
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

        // URL corta que va impresa en cada etiqueta QR (vivalavirgen.net/app/q/…): no cambiar
        Route::get('q/{token}', [UnitController::class, 'resolve'])->name('qr.resolve');
        Route::get('units/{unit}', [UnitController::class, 'show'])->name('units.show');
        Route::patch('units/{unit}', [UnitController::class, 'update'])->name('units.update');
        Route::delete('units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');
        Route::get('units/{unit}/qr.svg', [UnitController::class, 'qr'])->name('units.qr');

        Route::resource('clients', ClientController::class);
        Route::post('clients/{client}/approve', [ClientController::class, 'approve'])->name('clients.approve');
        Route::post('clients/{client}/revoke', [ClientController::class, 'revoke'])->name('clients.revoke');
        Route::resource('venues', VenueController::class)->except('show');

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
            Route::resource('client-categories', ClientCategoryController::class)->except('show');
        });
    });
});
