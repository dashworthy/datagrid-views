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
 * matches. It reads config rather than the `Route::datagridViewSection()`
 * calls because routes are not loaded when they are cached, or in console and
 * queue work.
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
     * Every configured section case whose key is $key, in config order.
     *
     * More than one means two enums share a key, which `find()` cannot tell
     * apart; `Route::datagridViewSection()` uses this to refuse that at route
     * load.
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
