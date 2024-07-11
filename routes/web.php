<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GeminiController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\OpenAIController;


Route::get('/', function () {
    return view('index');
});

Route::post('/api/get-ai-response', [OpenAIController::class, 'getAIResponse']);

Route::get('/test', function () {
    return view('test');
});

Route::post('/api/gemini', [GeminiController::class, 'process']);
Route::post('/save-summary', [ConversationController::class, 'saveSummary']);
