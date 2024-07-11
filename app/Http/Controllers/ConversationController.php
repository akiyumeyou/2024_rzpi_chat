<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class OpenAIController extends Controller
{
    public function getAIResponse(Request $request)
    {
        \Log::info('getAIResponse called');

        $request->validate([
            'messages' => 'required|array',
            'messages.*.role' => 'required|string|in:user,assistant,system',
            'messages.*.content' => 'required|string',
        ]);

        $messages = $request->input('messages');
        $apiKey = env('OPENAI_API_KEY'); // 環境変数からAPIキーを取得

        if (!$apiKey) {
            \Log::error('OpenAI API key is missing');
            return response()->json(['error' => 'OpenAI API key is missing'], 500);
        }

        try {
            \Log::info('Sending request to OpenAI API');
            $response = Http::withHeaders([
                'Authorization' => "Bearer $apiKey",
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => $messages,
            ]);

            if ($response->failed()) {
                \Log::error('Failed to fetch AI response', ['response' => $response->body()]);
                return response()->json(['error' => 'Failed to fetch AI response', 'details' => $response->json()], 500);
            }

            \Log::info('Received response from OpenAI API');
            return response()->json(['message' => $response->json()['choices'][0]['message']['content']]);
        } catch (\Exception $e) {
            \Log::error('An error occurred', ['exception' => $e->getMessage()]);
            return response()->json(['error' => 'An error occurred', 'details' => $e->getMessage()], 500);
        }
    }
}
