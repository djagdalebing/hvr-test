<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAnnouncementsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('announcements')) {
            return;
        }

        Schema::create('announcements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 200);
            $table->text('body')->nullable();
            // new_content | platform_update | featured_creator | company_news | general
            $table->string('type', 40)->default('general')->index();
            $table->string('link_url', 500)->nullable();
            $table->string('image_path', 500)->nullable();
            // all | viewers | creators
            $table->string('audience', 20)->default('all');
            // JSON list of delivery channels actually used, e.g. ["in_app"]
            $table->json('channels')->nullable();
            $table->string('status', 20)->default('draft')->index(); // draft | sent
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('created_by')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('announcements');
    }
}
