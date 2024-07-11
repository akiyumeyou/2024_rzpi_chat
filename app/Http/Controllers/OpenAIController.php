<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Conversation;
use App\Models\ConversationSummary;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class OpenAIController extends Controller
{
    public function getAIResponse(Request $request)
    {
        try {
            Log::info('getAIResponse called');

            $request->validate([
                'messages' => 'required|array',
                'messages.*.role' => 'required|string|in:user,assistant,system',
                'messages.*.content' => 'required|string',
            ]);

            $messages = $request->input('messages');
            Log::info('Received messages:', ['messages' => $messages]);

            $apiKey = env('OPENAI_API_KEY');
            if (!$apiKey) {
                Log::error('OpenAI API key is missing');
                return response()->json(['error' => 'OpenAI API key is missing'], 500);
            }

            // 終了コマンドのチェック
            $endCommand = false;
            foreach ($messages as $message) {
                if ($message['content'] === '終了') {
                    $endCommand = true;
                    break;
                }
            }

            if ($endCommand) {
                Log::info('End command detected. Saving conversation.');
                $conversation = new Conversation();
                $userMessages = array_filter($messages, function ($message) {
                    return $message['role'] === 'user';
                });
                $aiMessages = array_filter($messages, function ($message) {
                    return $message['role'] === 'assistant';
                });

                $conversation->user_text = implode("\n", array_column($userMessages, 'content'));
                $conversation->ai_response = implode("\n", array_column($aiMessages, 'content'));

                // 会話の要約を生成
                $summary = $this->generateSummary($messages);
                $conversation->conversation_summary = $summary;
                $conversation->save();

                Log::info('Conversation saved successfully.');
                return response()->json(['message' => '会話を終了しました。']);
            }

            Log::info('Sending request to OpenAI API');
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => $messages,
            ]);

            if ($response->failed()) {
                Log::error('Failed to fetch AI response', ['response' => $response->body()]);
                return response()->json(['error' => 'Failed to fetch AI response', 'details' => $response->json()], 500);
            }

            Log::info('Received response from OpenAI API');
            return response()->json(['message' => $response->json()['choices'][0]['message']['content']]);
        } catch (\Exception $e) {
            Log::error('An error occurred', ['exception' => $e->getMessage()]);
            return response()->json(['error' => 'An error occurred', 'details' => $e->getMessage()], 500);
        }
    }

    private function generateSummary($messages)
    {
        // シンプルな要約生成ロジックの例
        $userMessages = array_filter($messages, function ($message) {
            return $message['role'] === 'user';
        });
        $summary = implode(' ', array_column($userMessages, 'content'));
        return '会話の要約: ' . $summary;
    }
}
