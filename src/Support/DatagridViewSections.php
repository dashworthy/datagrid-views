<?php

namespace Dashworthy\DatagridViews\Support;

use Dashworthy\DatagridViews\Contracts\DefinesDatagridViewSection;
use InvalidArgumentException;
use UnitEnum;

/**
 * Finds a section from the key stored on a view row.
 *
 * A view carries its section only as a string, but its access rules live on
 * the section object. The host lists its section enums in
 * `config('datagrid-views.sections')`, and this finds the case whose key
 * matches. It reads config rather than asking the grids that keep views,
 * because a stored row names no grid class, and console and queue work load
 * no routes.
 *
 * An unknown key throws rather than returning null: every caller is deciding
 * access, and there is no safe default to fall back to.
 */
class DatagridViewSections
{
    public function find(string $key): DefinesDatagridViewSection
    {
        return $this->claiming($key)[0] ?? throw $this->notListed($key);
    }

    /**
     * The key of $section, a grid's declared section, once it is safe to file
     * and look up views under: $section must be the one configured case
     * claiming that key.
     *
     * A section missing from config has no access rules `find()` can reach.
     * Two configured enums sharing a key would make `find()` resolve to
     * whichever is listed first, putting one section's views under the other's
     * rules. Either way this throws, so a misconfigured grid fails closed on
     * every view request rather than borrowing another section's rules.
     */
    public function keyOf(DefinesDatagridViewSection $section): string
    {
        $key = $section->getSectionKey();
        $claiming = $this->claiming($key);

        if (count($claiming) > 1) {
            throw new InvalidArgumentException(
                "Datagrid view section key [{$key}] is registered by more than one enum in config('datagrid-views.sections')."
            );
        }

        if (($claiming[0] ?? null) !== $section) {
            throw $this->notListed($key);
        }

        return $key;
    }

    /**
     * Every configured section case whose key is $key, in config order.
     *
     * More than one means two enums share a key, which `find()` cannot tell
     * apart; `keyOf()` refuses that.
     *
     * @return list<DefinesDatagridViewSection>
     */
    public function claiming(string $key): array
    {
        /** @var list<class-string<DefinesDatagridViewSection&UnitEnum>> $enums */
        $enums = config('datagrid-views.sections', []);

        $claiming = [];

        foreach ($enums as $enum) {
            foreach ($enum::cases() as $section) {
                if ($section->getSectionKey() === $key) {
                    $claiming[] = $section;
                }
            }
        }

        return $claiming;
    }

    public function notListed(string $key): InvalidArgumentException
    {
        return new InvalidArgumentException(
            "Datagrid view section [{$key}] is not listed in config('datagrid-views.sections')."
        );
    }
}
