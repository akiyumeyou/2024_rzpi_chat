<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Google\Cloud\TextToSpeech\V1\TextToSpeechClient;
use Google\Cloud\TextToSpeech\V1\SynthesisInput;
use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;
use Google\Cloud\TextToSpeech\V1\AudioConfig;
use Google\Cloud\TextToSpeech\V1\AudioEncoding;
use Illuminate\Support\Facades\Http;

class TextToSpeechController extends Controller
{
    public function textToSpeech(Request $request)
    {
        try {
            putenv('GOOGLE_APPLICATION_CREDENTIALS=' . env('GOOGLE_APPLICATION_CREDENTIALS'));

            Log::info('GOOGLE_APPLICATION_CREDENTIALS: ' . env('GOOGLE_APPLICATION_CREDENTIALS'));

            $text = $request->input('text');
            $voiceType = $request->input('voiceType', 'male');  // デフォルトは男性の声

            Log::info('Received text: ' . $text);
            Log::info('Voice type: ' . $voiceType);

            if ($voiceType == 'clone') {
                return $this->generateCloneVoice($text);
            } else {
                return $this->generateGoogleVoice($text);
            }
        } catch (\Exception $e) {
            Log::error('TextToSpeech error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function generateGoogleVoice($text)
    {
        try {
            $client = new TextToSpeechClient();
            $inputText = new SynthesisInput();
            $inputText->setText($text);

            $voice = new VoiceSelectionParams();
            $voice->setLanguageCode('ja-JP');
            $voice->setName('ja-JP-Standard-C');

            $audioConfig = new AudioConfig();
            $audioConfig->setAudioEncoding(AudioEncoding::MP3);
            $audioConfig->setSpeakingRate(1.0); // スピードを調整
            $audioConfig->setPitch(0); // ピッチを調整

            Log::info('Calling Google TTS API');
            $response = $client->synthesizeSpeech($inputText, $voice, $audioConfig);
            Log::info('Google TTS API called successfully');

            $filename = storage_path('app/output.mp3');
            file_put_contents($filename, $response->getAudioContent());

            return response()->download($filename)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Google Voice Synthesis Error: ' . $e->getMessage());
            return response()->json(['error' => 'Google Voice Synthesis Error: ' . $e->getMessage()], 500);
        }
    }

    private function generateCloneVoice($text)
    {
        try {
            $apiKey = env('ELEVENLABS_API_KEY');
            $voiceId = "FeaM2xaHKiX1yiaPxvwe";
            $url = "https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}";

            Log::info('Calling ElevenLabs API');
            $response = Http::withHeaders([
                'xi-api-key' => $apiKey,
                'Content-Type' => 'application/json'
            ])->post($url, [
                'text' => $text,
                'model_id' => 'eleven_multilingual_v2',
                'voice_settings' => [
                    'stability' => 0.5,
                    'similarity_boost' => 1.0,
                    'style' => 0.2,
                    'use_speaker_boost' => true,
                    'speed' => 1.0,
                    'pitch' => 1.0
                ]
            ]);

            if ($response->failed()) {
                throw new \Exception('Failed to fetch ElevenLabs API');
            }

            Log::info('ElevenLabs API called successfully');
            $audioContent = $response->body();

            $filename = storage_path('app/output.mp3');
            file_put_contents($filename, $audioContent);

            return response()->download($filename)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Clone Voice Synthesis Error: ' . $e->getMessage());
            return response()->json(['error' => 'Clone Voice Synthesis Error: ' . $e->getMessage()], 500);
        }
    }
}

