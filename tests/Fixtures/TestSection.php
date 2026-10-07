<?php

namespace Dashworthy\DatagridViews\Tests\Fixtures;

use Dashworthy\DatagridViews\Contracts\DefinesDatagridViewSection;
use Dashworthy\DatagridViews\Contracts\GovernsDatagridViewAccess;

/**
 * The sections the package's own suite mounts, standing in for a host
 * application's admin (central database) and app (tenant database) sections.
 * `Scripted` lets a test shut an actor out of the section; `Parameterized` is
 * mounted behind a `{tenant}` URL parameter, as a multi-tenant host nests it.
 */
enum TestSection: string implements DefinesDatagridViewSection
{
    case Admin = 'admin';
    case App = 'app';
    case Scripted = 'scripted';
    case Parameterized = 'parameterized';

    public function getSectionKey(): string
    {
        return $this->value;
    }

    public function datagridViewAccess(): GovernsDatagridViewAccess
    {
        return match ($this) {
            self::Scripted => new ScriptedAccess,
            default => new OwnerOnlyAccess,
        };
    }
}
