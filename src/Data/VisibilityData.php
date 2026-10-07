<?php

namespace Dashworthy\DatagridViews\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Whether one grid column is hidden, in the `{field, is_hidden}` wire shape
 * `dashboard_widgets.visibility` uses. The visualizations package defines the
 * filter and sort shapes; this is the one piece of grid state it does not.
 *
 * @implements Arrayable<string, string|bool>
 */
final readonly class VisibilityData implements Arrayable
{
    public function __construct(
        public string $field,
        public bool $isHidden,
    ) {}

    /**
     * @return array{field: string, is_hidden: bool}
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'is_hidden' => $this->isHidden,
        ];
    }
}
