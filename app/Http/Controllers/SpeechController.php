<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google\Cloud\TextToSpeech\V1\TextToSpeechClient;
use Google\Cloud\TextToSpeech\V1\SynthesisInput;
use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;
use Google\Cloud\TextToSpeech\V1\AudioConfig;
use Illuminate\Support\Facades\Http;
use App\Models\ConversationSummary;
use Illuminate\Support\Facades\Log;

class SpeechController extends Controller
{
    public function recognizeSpeech(Request $request)
    {
        $userInput = $request->input('input');
        $apiKey = env('OPENAI_API_KEY'); // OpenAIのAPIキーを取得

        // OpenAI APIへのリクエスト
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => 'あなたは高齢者に寄り添う会話の専門家です。'],
                ['role' => 'user', 'content' => $userInput]
            ],
            'max_tokens' => 100,
            'temperature' => 0.7,
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to get response from OpenAI'], 500);
        }

        $aiResponse = $response->json()['choices'][0]['message']['content'];

        return response()->json(['response' => $aiResponse]);
    }

    public function synthesizeSpeech(Request $request)
    {
        $credentialsPath = env('GOOGLE_APPLICATION_CREDENTIALS');
        Log::info('GOOGLE_APPLICATION_CREDENTIALS: ' . $credentialsPath);

        // 認証情報ファイルの存在確認
        if (!file_exists($credentialsPath)) {
            Log::error('File does not exist at path: ' . $credentialsPath);
            return response()->json(['error' => 'Credential file not found at ' . $credentialsPath], 500);
        } else {
            Log::info('File exists: Yes');
        }

        $text = $request->input('text');
        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $credentialsPath);

        try {
            $client = new TextToSpeechClient();
            $input = new SynthesisInput();
            $input->setText($text);

            $voice = new VoiceSelectionParams();
            $voice->setLanguageCode('ja-JP');
            $voice->setSsmlGender(VoiceSelectionParams\SsmlVoiceGender::NEUTRAL);

            $audioConfig = new AudioConfig();
            $audioConfig->setAudioEncoding(AudioConfig\AudioEncoding::MP3);

            $response = $client->synthesizeSpeech($input, $voice, $audioConfig);
            $audioContent = $response->getAudioContent();

            $audioFileName = 'audio_' . uniqid() . '.mp3';
            $audioFilePath = storage_path('app/public/' . $audioFileName);
            file_put_contents($audioFilePath, $audioContent);

            return response()->json(['audio_url' => asset('storage/' . $audioFileName)]);
        } catch (\Exception $e) {
            Log::error('Text-to-Speech error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to synthesize speech'], 500);
        }
    }

    public function saveSummary(Request $request)
    {
        $conversationHistory = $request->input('conversationHistory');
        $userText = '';
        $aiResponse = '';

        foreach ($conversationHistory as $entry) {
            if ($entry['role'] === 'user') {
                $userText .= "{$entry['content']}\n";
            } else if ($entry['role'] === 'assistant') {
                $aiResponse .= "{$entry['content']}\n";
            }
        }

        $summary = new ConversationSummary();
        $summary->user_text = $userText;
        $summary->ai_response = $aiResponse;
        $summary->summary = "ユーザーの発言:\n$userText\nAIの応答:\n$aiResponse";
        $summary->save();

        return response()->json(['message' => 'Conversation summary saved successfully.']);
    }
}
