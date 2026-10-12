<?php

use App\Http\Controllers\Api\V1\CareersController;
use App\Http\Controllers\Api\V1\ConsentController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\EditorialController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\PreviewController;
use App\Http\Controllers\Api\V1\SubscriberController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.')->group(function () {
    Route::middleware('cache.headers:public;max_age=300;etag')->group(function () {
        Route::get('site', [ContentController::class, 'site'])->name('site');
        Route::get('services', [ContentController::class, 'services'])->name('services.index');
        Route::get('services/{path}', [ContentController::class, 'service'])->where('path', '.*')->name('services.show');
        Route::get('industries', [ContentController::class, 'industries'])->name('industries.index');
        Route::get('industries/{slug}', [ContentController::class, 'industry'])->name('industries.show');

        Route::get('posts', [EditorialController::class, 'posts'])->name('posts.index');
        Route::get('posts/{type}/{slug}', [EditorialController::class, 'post'])->name('posts.show');
        Route::get('events', [EditorialController::class, 'events'])->name('events.index');
        Route::get('events/{slug}', [EditorialController::class, 'event'])->name('events.show');
        Route::get('standards', [EditorialController::class, 'standards'])->name('standards.index');
        Route::get('standards/{path}', [EditorialController::class, 'standard'])->where('path', '.*')->name('standards.show');
        Route::get('pages', [EditorialController::class, 'pages'])->name('pages.index');
        Route::get('pages/{path}', [EditorialController::class, 'page'])->where('path', '.*')->name('pages.show');
        Route::get('landing-pages', [EditorialController::class, 'landingPages'])->name('landing-pages.index');
        Route::get('landing-pages/{path}', [EditorialController::class, 'landingPage'])->where('path', '.*')->name('landing-pages.show');
        Route::get('authors/{slug}', [EditorialController::class, 'author'])->name('authors.show');
        Route::get('faqs', [EditorialController::class, 'faqs'])->name('faqs');
        Route::get('redirects', [EditorialController::class, 'redirects'])->name('redirects');
        Route::get('vacancies', [CareersController::class, 'vacancies'])->name('vacancies.index');
    });

    // Every page refreshes its reviews from here in the browser, so a new review shows within a minute.
    Route::get('testimonials', [ContentController::class, 'testimonials'])
        ->middleware('cache.headers:public;max_age=60;etag')
        ->name('testimonials');

    Route::get('preview/{token}', [PreviewController::class, 'show'])
        ->where('token', '[A-Za-z0-9]{40}')
        ->middleware('throttle:120,1')
        ->name('preview');

    Route::post('applications', [CareersController::class, 'apply'])
        ->middleware('throttle:applications')
        ->name('applications.store');

    Route::post('leads', [LeadController::class, 'store'])
        ->middleware('throttle:leads')
        ->name('leads.store');

    Route::post('consents', [ConsentController::class, 'store'])
        ->middleware('throttle:consents')
        ->name('consents.store');

    Route::post('subscribers', [SubscriberController::class, 'store'])
        ->middleware('throttle:leads')
        ->name('subscribers.store');
    Route::get('subscribers/{subscriber}/confirm', [SubscriberController::class, 'confirm'])
        ->middleware('signed')
        ->name('subscribers.confirm');
    Route::get('subscribers/{subscriber}/unsubscribe', [SubscriberController::class, 'unsubscribe'])
        ->middleware('signed')
        ->name('subscribers.unsubscribe');
});
