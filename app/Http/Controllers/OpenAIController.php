<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Conversation;
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

            // プロンプトの追加
            $systemMessage = [
                'role' => 'system',
                'content' => 'あなたは高齢者に寄り添う会話の専門家です。ユーザーが話すことに対して、短い相槌やおうむ返しを使い、相手の話を促進するようにしてください。話が途切れた場合には、しばらく待って呼びかけをしてください。必ず短く的確に応えてください。'
            ];

            // 初回メッセージにシステムメッセージを追加
            if (count($messages) === 1 && $messages[0]['role'] === 'user') {
                array_unshift($messages, $systemMessage);
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

                // 手動でキーワードを抽出して要約を生成
                $summary = $this->generateManualSummary($userMessages);
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
                'max_tokens' => 100,
                'stop' => ["。", "！", "？"], // 応答を一文で終了する
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

    private function generateManualSummary($userMessages)
    {
        try {
            // ユーザーの発話内容を取得
            $allText = implode(' ', array_column($userMessages, 'content'));

            Log::info('Generating manual summary for text:', ['text' => $allText]);

            // 手動でキーワードを抽出し、50文字程度で要約
            $allText = preg_replace('/\A[\p{Z}\p{Cc}\p{Cf}\p{Cs}\p{Co}\p{Cn}]++|[\p{Z}\p{Cc}\p{Cf}\p{Cs}\p{Co}\p{Cn}]++\z/u', '', $allText); // trim equivalent for multibyte strings
            $words = mb_split('\s+', $allText);
            $filteredWords = array_filter($words, function($word) {
                return mb_strlen($word) > 1; // 1文字の単語を除外
            });
            $filteredWords = array_slice($filteredWords, 0, 10); // 最初の10単語を使用
            $summaryText = implode(' ', $filteredWords);
            $summary = "今日は「" . mb_strimwidth($summaryText, 0, 50, '...') . "」について話しました。";

            Log::info('Generated manual summary:', ['summary' => $summary]);
            return $summary;
        } catch (\Exception $e) {
            Log::error('An error occurred during manual summary generation', ['exception' => $e->getMessage()]);
            return '要約の生成に失敗しました';
        }
    }
}
