<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores beta-tester feedback submitted from the site-wide "Send Feedback"
 * button. Captures where the user was (route + page title) so reports are
 * actionable without the tester having to describe the page.
 */
class CreateHvnFeedbackTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('hvn_feedback')) {
            return;
        }

        Schema::create('hvn_feedback', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->string('type', 30)->default('bug')->index();
            $table->text('message');
            $table->string('page_route', 500)->nullable();
            $table->string('page_title', 250)->nullable();
            $table->string('page_url', 1000)->nullable();
            $table->string('contact_email', 190)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('status', 20)->default('new')->index();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('hvn_feedback');
    }
}
