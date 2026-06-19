<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2: lets users opt out of announcement EMAILS (in-app bell
 * notifications are always delivered). Default false = subscribed.
 */
class AddNewsletterUnsubscribedToUsers extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'newsletter_unsubscribed')) {
                $table->boolean('newsletter_unsubscribed')->default(false)->after('role');
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'newsletter_unsubscribed')) {
                $table->dropColumn('newsletter_unsubscribed');
            }
        });
    }
}
