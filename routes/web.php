<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GeminiController;
use App\Http\Controllers\OpenAIController;


Route::get('/', function () {
    return view('index');
});


Route::post('/api/gemini', [GeminiController::class, 'process']);


Route::get('/api/openai-key', [OpenAIController::class, 'getApiKey']);
