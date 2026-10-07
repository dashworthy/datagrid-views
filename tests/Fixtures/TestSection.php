<?php

namespace Dashworthy\DatagridViews\Tests\Fixtures;

use Dashworthy\DatagridViews\Contracts\DefinesDatagridViewSection;
use Dashworthy\DatagridViews\Contracts\GovernsDatagridViewAccess;

/**
 * The sections the package's own suite mounts, standing in for a host
 * application's admin (central database) and app (tenant database) sections.
 */
enum TestSection: string implements DefinesDatagridViewSection
{
    case Admin = 'admin';
    case App = 'app';

    public function getSectionKey(): string
    {
        return $this->value;
    }

    public function datagridViewAccess(): GovernsDatagridViewAccess
    {
        return new OwnerOnlyAccess;
    }
}
