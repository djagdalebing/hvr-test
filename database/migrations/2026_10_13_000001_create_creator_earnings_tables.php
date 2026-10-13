<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Foundation for the HVN Creator Earnings Program.
 *
 * Three pieces:
 *
 *  1. video_plays.seconds_watched -- real watch time. The existing
 *     time_watched column cannot be used: it is a RESUME POINTER, and the
 *     player deliberately writes 0 when a video is finished, so a fully
 *     watched film is indistinguishable from one never started.
 *
 *  2. creator_earnings -- one row per eligible view, priced at the rate in
 *     force at that moment. Earnings are a ledger, not a recomputation:
 *     changing the rate later must never rewrite what a creator was already
 *     told they had earned. One row per play id, so replays of the same
 *     play can never double-credit.
 *
 *  3. creator_payouts -- what has actually been paid, separate from what has
 *     been earned.
 */
class CreateCreatorEarningsTables extends Migration
{
    public function up()
    {
        if (
            Schema::hasTable('video_plays') &&
            !Schema::hasColumn('video_plays', 'seconds_watched')
        ) {
            Schema::table('video_plays', function (Blueprint $table) {
                $table->float('seconds_watched')->unsigned()->default(0)->index();
                // Set once the play has been assessed, so the engine never
                // re-evaluates the same play on every heartbeat.
                $table->timestamp('assessed_at')->nullable()->index();
            });
        }

        if (!Schema::hasTable('creator_earnings')) {
            Schema::create('creator_earnings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('creator_id')->index();
                $table->unsignedBigInteger('title_id')->index();
                $table->unsignedBigInteger('video_id')->index();
                $table->unsignedBigInteger('play_id');
                $table->unsignedInteger('viewer_id')->nullable()->index();
                $table->unsignedInteger('seconds_watched')->default(0);
                // 6dp: the rate is $0.001 today and may go lower per view.
                $table->decimal('rate', 12, 6);
                $table->decimal('amount', 14, 6);
                // Set when this row is rolled into a payout.
                $table->unsignedBigInteger('payout_id')->nullable()->index();
                $table->timestamp('created_at')->nullable()->index();

                // One credit per play, full stop.
                $table->unique('play_id');
            });
        }

        if (!Schema::hasTable('creator_payouts')) {
            Schema::create('creator_payouts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('creator_id')->index();
                $table->decimal('amount', 14, 2);
                $table->string('status', 20)->default('pending')->index();
                $table->string('method', 40)->nullable();
                $table->string('reference', 190)->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('period_start')->nullable();
                $table->timestamp('period_end')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }

        // Defaults. Everything the program does is driven from here -- the rate
        // is never hard-coded, and the thresholds can be tightened later
        // without a deploy.
        $defaults = [
            'earnings.enabled' => '1',
            'earnings.rate_per_view' => '0.001',
            'earnings.min_seconds' => '30',
            // Percentage-of-runtime gate, off by default: a flat 30s applies to
            // every length. Raise this if sampling becomes a problem.
            'earnings.min_percent' => '0',
            'earnings.percent_cap_seconds' => '600',
            'earnings.dedupe_hours' => '24',
            'earnings.min_payout' => '25',
            'earnings.exclude_staff' => '1',
        ];

        foreach ($defaults as $name => $value) {
            if (!DB::table('settings')->where('name', $name)->exists()) {
                DB::table('settings')->insert([
                    'name' => $name,
                    'value' => $value,
                    'private' => 0,
                ]);
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('creator_payouts');
        Schema::dropIfExists('creator_earnings');
        DB::table('settings')->where('name', 'like', 'earnings.%')->delete();
    }
}
