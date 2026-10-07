<?php

namespace Dashworthy\DatagridViews\Tests\Fixtures;

use Dashworthy\DatagridViews\Contracts\DefinesDatagridViewSection;
use Dashworthy\DatagridViews\Contracts\GovernsDatagridViewAccess;

/**
 * A second section enum whose only case reuses TestSection::App's key, so the
 * suite can prove two configured enums sharing a key are rejected rather than
 * silently resolved to whichever is listed first.
 */
enum CollidingSection: string implements DefinesDatagridViewSection
{
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
