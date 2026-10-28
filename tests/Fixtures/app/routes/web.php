<?php

use Mariner\NutsAndBolts\Facades\Route;
use Venusian\Serve\Tests\Fixtures\FixtureController;

Route::get('/', fn () => 'home')->name('home');

Route::get('/users/{id}', [FixtureController::class, 'show'])->whereNumber('id')->name('users.show');

Route::middleware('web')->group(function () {
    Route::post('/posts', fn () => 'stored')->name('posts.store');
});

Route::get('/fibers/{id}', [\Venusian\Serve\Tests\Fixtures\FiberController::class, 'show'])->name('fibers.show');

Route::get('/uri-redirect', fn () => request()->uri()->withQuery(['page' => 2]))->name('uri.redirect');
