<?php

namespace App\Listeners;

use App\ListModel;
use App\Services\Lists\DeleteLists;
use Common\Auth\Events\UserCreated;
use Common\Auth\Events\UsersDeleted;

class DeleteUserLists
{
    /**
     * @var ListModel
     */
    private $list;

    /**
     * @param ListModel $list
     */
    public function __construct(ListModel $list)
    {
        $this->list = $list;
    }

    /**
     * @param UsersDeleted $event
     */
    public function handle(UsersDeleted $event)
    {
        $listIds = $this->list->whereIn('user_id', $event->users->pluck('id'))->pluck('id');

        // Never take the homepage down with a user. These lists happen to be
        // owned by whoever created them, and deleting that account used to
        // delete the lists, their items and their ids in the homepage.lists
        // setting -- the entire homepage, unrecoverably, as a side effect of
        // removing one user. Hand the orphans to an admin instead.
        $homepageListIds = collect(
            app(\Common\Settings\Settings::class)->getJson('homepage.lists') ?:
            [],
        )->map(fn($id) => (int) $id);

        $protected = $listIds->filter(fn($id) => $homepageListIds->contains((int) $id));

        if ($protected->isNotEmpty()) {
            $newOwner = \App\User::whereHas('permissions', function ($q) {
                $q->where('name', 'admin');
            })->value('id');

            $this->list
                ->whereIn('id', $protected)
                ->update(['user_id' => $newOwner]);

            \Log::warning(
                'Reassigned homepage lists away from a deleted user instead of deleting them: ' .
                    $protected->implode(','),
            );
        }

        app(DeleteLists::class)->execute(
            $listIds->reject(fn($id) => $homepageListIds->contains((int) $id))->values(),
        );
    }
}
