<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use App\Models\ConversationSummary;

class ConversationController extends Controller
{
    public function saveSummary(Request $request)
    {
        $request->validate([
            'summary' => 'required|string|max:100',
            'user_text' => 'required|string',
            'ai_response' => 'required|string'
        ]);

        try {
            $summaryText = $request->input('summary');
            $userText = $request->input('user_text');
            $aiResponse = $request->input('ai_response');

            // ログ出力
            Log::info('Received summary: ' . $summaryText);
            Log::info('Received user_text: ' . $userText);
            Log::info('Received ai_response: ' . $aiResponse);

            $summary = new ConversationSummary();
            $summary->summary = $summaryText;
            $summary->user_text = $userText;
            $summary->ai_response = $aiResponse;
            $summary->save();

            return response()->json(['message' => 'Summary saved successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to save summary: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save summary', 'details' => $e->getMessage()], 500);
        }
    }

    public function getAIResponse(Request $request)
    {
        $text = $request->input('text');

        // null チェックを追加
        if (empty($text)) {
            return response()->json(['error' => 'Input text cannot be empty'], 400);
        }

        $client = new Client();
        $url = 'https://api.openai.com/v1/chat/completions'; // 正しいエンドポイントに修正

        try {
            $response = $client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'), // 環境変数からAPIキーを取得
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-3.5-turbo', // 最新のモデルを指定
                    'messages' => [
                        ['role' => 'system', 'content' => 'あなたは積極的傾聴の専門家です。相手の話を促進し、短く要約した形で反応してください。'],
                        ['role' => 'user', 'content' => $text]
                    ],
                    'max_tokens' => 50,
                    'temperature' => 0.7,
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            return response()->json(['response' => $data['choices'][0]['message']['content']]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $responseBody = $e->getResponse()->getBody()->getContents();
            Log::error('Client error: ' . $responseBody);
            return response()->json(['error' => 'Client error occurred', 'details' => json_decode($responseBody, true)], 400);
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            $responseBody = $e->getResponse()->getBody()->getContents();
            Log::error('Server error: ' . $responseBody);
            return response()->json(['error' => 'Server error occurred', 'details' => json_decode($responseBody, true)], 500);
        } catch (\Exception $e) {
            Log::error('General error: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred', 'details' => $e->getMessage()], 500);
        }
    }
}
