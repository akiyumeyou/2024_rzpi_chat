<?php

// app/Http/Controllers/OpenAIController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OpenAIController extends Controller
{
    public function getApiKey()
    {
        return response()->json(['api_key' => env('OPENAI_API_KEY')]);
    }
}

