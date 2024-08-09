<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GeminiController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\OpenAIController;
use App\Http\Controllers\TextToSpeechController;


Route::get('/', function () {
    return view('index');
});

Route::get('/conversation-summaries', [ConversationController::class, 'index'])->name('sum.index');
Route::post('/conversation-summaries', [ConversationController::class, 'store'])->name('sum.store');
Route::post('/api/summarize-conversation', [ConversationController::class, 'summarizeConversation']);

Route::post('/api/text-to-speech', [TextToSpeechController::class, 'convertTextToSpeech']);
Route::post('/api/get-ai-response', [OpenAIController::class, 'getAIResponse']);

Route::get('/chat/config', [TextToSpeechController::class, 'getChatConfig']);
Route::post('/text-to-speech', [TextToSpeechController::class, 'textToSpeech']);
Route::post('/api/get-summary', [ConversationController::class, 'getSummary']);
