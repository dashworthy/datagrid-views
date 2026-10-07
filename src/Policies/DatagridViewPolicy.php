<?php

namespace Dashworthy\DatagridViews\Policies;

use Dashworthy\DatagridViews\Contracts\GovernsDatagridViewAccess;
use Dashworthy\DatagridViews\Models\DatagridView;
use Dashworthy\DatagridViews\Support\DatagridViewSections;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Every answer comes from the section's access rule: an actor may do anything
 * with a view only while the section lets them use views at all
 * ({@see GovernsDatagridViewAccess::canUse()}) and the view is one the rule
 * shows them ({@see DatagridView::scopeVisibleTo()}), which is only their own.
 *
 * Over HTTP, a view outside the actor's visible set never reaches this policy:
 * the `{datagridView}` route binding resolves inside the same scope and 404s
 * first. An actor the section shuts out of their own view gets a 403 here.
 */
class DatagridViewPolicy
{
    /**
     * Listing takes the section rather than a view.
     */
    public function viewAny(Authenticatable&Model $user, string $section): bool
    {
        return $this->canUse($user, $section);
    }

    /**
     * Creating takes the section rather than a view.
     */
    public function create(Authenticatable&Model $user, string $section): bool
    {
        return $this->canUse($user, $section);
    }

    public function view(Authenticatable&Model $user, DatagridView $view): bool
    {
        return $this->canUse($user, $view->section)
            && DatagridView::query()->visibleTo($user, $view->section)->whereKey($view->getKey())->exists();
    }

    public function update(Authenticatable&Model $user, DatagridView $view): bool
    {
        return $this->view($user, $view);
    }

    public function delete(Authenticatable&Model $user, DatagridView $view): bool
    {
        return $this->view($user, $view);
    }

    private function canUse(Authenticatable&Model $user, string $section): bool
    {
        return app(DatagridViewSections::class)->find($section)->datagridViewAccess()->canUse($user);
    }
}
