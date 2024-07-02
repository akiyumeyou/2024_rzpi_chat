<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;

class GeminiController extends Controller
{
    public function process(Request $request)
    {
        $text = $request->input('text');
        $apiKey = env('GOOGLE_GEMINI_API_KEY');

        $client = new Client();
        $response = $client->post('https://gemini.googleapis.com/v1/llm/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'your_gemini_model',
                'prompt' => $text,
                'max_tokens' => 150,
                'temperature' => 0.9,
            ],
        ]);

        $body = json_decode((string) $response->getBody(), true);
        return response()->json(['response' => $body['choices'][0]['text']]);
    }
}
