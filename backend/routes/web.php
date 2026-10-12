<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->away(rtrim(config('instacertify.frontend_url'), '/').'/'));
