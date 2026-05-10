<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommunityCommentLikesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('community_comment_likes')) return;
        Schema::create('community_comment_likes', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('comment_id');
            $t->unsignedBigInteger('user_id');
            $t->timestamp('created_at')->nullable();
            $t->unique(['comment_id', 'user_id']);
            $t->index('comment_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('community_comment_likes');
    }
}
