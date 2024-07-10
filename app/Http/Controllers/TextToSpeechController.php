<?php

namespace App\Http\Controllers;

use Google\Cloud\TextToSpeech\V1\TextToSpeechClient;
use Google\Cloud\TextToSpeech\V1\SynthesisInput;
use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;
use Google\Cloud\TextToSpeech\V1\AudioConfig;
use Illuminate\Http\Request;

class TextToSpeechController extends Controller
{
    public function synthesizeSpeech(Request $request)
    {
        $text = $request->input('text'); // リクエストからテキストを取得

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

        $outputFile = storage_path('app/public/output.mp3');
        file_put_contents($outputFile, $audioContent);

        $client->close();

        return response()->json(['message' => 'Audio content written to file', 'file' => asset('storage/output.mp3')]);
    }
}
