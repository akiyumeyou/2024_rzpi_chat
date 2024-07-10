<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OpenAIController extends Controller
{
    public function getApiKey()
    {
        // 環境変数や設定ファイルからAPIキーを取得
        $apiKey = config('services.openai.key');

        // APIキーをJSON形式で返す
        return response()->json(['api_key' => $apiKey]);
    }
}
