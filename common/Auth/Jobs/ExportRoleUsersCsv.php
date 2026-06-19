<?php

namespace Common\Auth\Jobs;

use App\User;
use Common\Csv\BaseCsvExportJob;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Exports the users assigned to a single role (via the user_role pivot)
 * to CSV — same columns as the all-users export.
 */
class ExportRoleUsersCsv extends BaseCsvExportJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $requesterId;
    protected $roleId;

    public function __construct(int $requesterId, int $roleId)
    {
        $this->requesterId = $requesterId;
        $this->roleId = $roleId;
    }

    public function cacheName(): string
    {
        return 'role-' . $this->roleId . '-users';
    }

    protected function generateLines()
    {
        $selectCols = [
            'users.id',
            'email',
            'username',
            'first_name',
            'last_name',
            'avatar',
            'users.created_at',
            'language',
            'country',
            'timezone',
        ];

        User::select($selectCols)
            ->whereHas('roles', function ($q) {
                $q->where('roles.id', $this->roleId);
            })
            ->chunkById(100, function (Collection $chunk) {
                $chunk->each(function (User $user) {
                    $data = $user->toArray();
                    unset($data['display_name'], $data['has_password']);
                    $this->writeLineToCsv($data);
                });
            }, 'users.id', 'id');
    }
}
