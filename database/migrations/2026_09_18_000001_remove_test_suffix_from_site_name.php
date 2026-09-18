<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The site ran as "Her Vision Network — TEST" while it lived on the temporary
 * Hostinger domain. It is now the production site on hervisionnetwork.com, so
 * drop the TEST suffix from the branding name (it shows in the page <title>,
 * the footer and outgoing email).
 */
class RemoveTestSuffixFromSiteName extends Migration
{
    public function up()
    {
        if (!DB::getSchemaBuilder()->hasTable('settings')) {
            return;
        }

        $row = DB::table('settings')->where('name', 'branding.site_name')->first();
        if (!$row) {
            return;
        }

        $clean = trim(preg_replace('/\s*[—–-]\s*TEST\s*$/iu', '', (string) $row->value));
        if ($clean !== '' && $clean !== (string) $row->value) {
            DB::table('settings')
                ->where('name', 'branding.site_name')
                ->update(['value' => $clean]);
        }
    }

    public function down()
    {
        // Intentionally not reversible — we don't want to re-add "— TEST".
    }
}
