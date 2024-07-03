<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConversationSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_text',
        'ai_response',
        'summary'
    ];
}
