<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('game-installs', 'pages::game-installs.index')->name('game-installs.index');

    Route::livewire('servers', 'pages::servers.index')->name('servers.index');

    Route::livewire('mods', 'pages::mods.index')->name('mods.index');

    Route::livewire('presets', 'pages::presets.index')->name('presets.index');
    Route::livewire('presets/create', 'pages::presets.create')->name('presets.create');
    Route::livewire('presets/{modPreset}/edit', 'pages::presets.edit')->name('presets.edit');

    Route::livewire('steam-settings', 'pages::steam-settings')->name('steam-settings');
});

require __DIR__.'/settings.php';
