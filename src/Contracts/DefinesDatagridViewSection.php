<?php

namespace Dashworthy\DatagridViews\Contracts;

/**
 * One area of the host application whose data grids keep saved views, such as
 * an admin area on the central database and an app area on a tenant's.
 *
 * Implemented by the host's section enum and listed in
 * `config('datagrid-views.sections')`; a section is mounted with
 * `Route::datagridViewSection()`.
 */
interface DefinesDatagridViewSection
{
    /**
     * The key stored on every view saved in this section, and the URL prefix
     * and route-name prefix its endpoints are mounted under.
     */
    public function getSectionKey(): string;

    /**
     * The ownership and visibility rules for views in this section.
     */
    public function datagridViewAccess(): GovernsDatagridViewAccess;
}
