<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Route;

// Единственный UI проекта — админка Filament.
Route::get('/', fn () => Redirect::to('/admin'));
