<?php

namespace Common\Csv;

use Auth;
use Common\Auth\Jobs\ExportRoleUsersCsv;
use Common\Auth\Jobs\ExportUsersCsv;

class CommonCsvExportController extends BaseCsvExportController
{
    public function exportUsers()
    {
        return $this->exportUsing(new ExportUsersCsv(Auth::id()));
    }

    public function exportRoleUsers($roleId)
    {
        // Admin-only — same gate the roles admin screens use.
        if (!Auth::user() || !Auth::user()->hasPermission('admin')) {
            abort(403, 'Admin access required.');
        }
        return $this->exportUsing(new ExportRoleUsersCsv(Auth::id(), (int) $roleId));
    }
}
