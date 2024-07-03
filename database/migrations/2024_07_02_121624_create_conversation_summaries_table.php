<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConversationSummariesTable extends Migration
{
    public function up()
    {
        Schema::create('conversation_summaries', function (Blueprint $table) {
            $table->id();
            $table->text('user_text');
            $table->text('ai_response');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('conversation_summaries');
    }
}
