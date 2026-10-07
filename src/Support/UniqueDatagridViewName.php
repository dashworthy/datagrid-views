<?php

namespace Dashworthy\DatagridViews\Support;

use Closure;
use Dashworthy\DatagridViews\Models\DatagridView;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/**
 * Refuses a view name its owner already uses on the same grid in the same
 * section. Another grid, section or owner may reuse it.
 *
 * The comparison ignores case. The table's unique index on
 * (section, grid_key, owner_type, owner_id, name) is the final word, and under
 * MySQL/MariaDB's default case-insensitive collations it treats "Active" and
 * "active" as the same name; checking case-sensitively here would let such a
 * name past validation only for the insert to fail. The actions still catch
 * the index's refusal for what this cannot see (a concurrent save, a
 * collation that also folds accents) and report it the same way.
 */
final class UniqueDatagridViewName implements ValidationRule
{
    public const string MESSAGE = 'You already have a view with this name on this grid.';

    public function __construct(
        private readonly string $section,
        private readonly string $gridKey,
        private readonly string $ownerType,
        private readonly int|string $ownerId,
        private readonly ?int $ignoreId = null,
    ) {}

    /**
     * For a new view $owner would save on the grid.
     */
    public static function forNewView(string $section, string $gridKey, Model $owner): self
    {
        return new self($section, $gridKey, $owner->getMorphClass(), $owner->getKey());
    }

    /**
     * For renaming $view, which may keep its own name.
     */
    public static function forRenaming(DatagridView $view): self
    {
        return new self($view->section, $view->grid_key, $view->owner_type, $view->owner_id, $view->id);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $taken = DatagridView::query()
            ->forGrid($this->section, $this->gridKey)
            ->where('owner_type', $this->ownerType)
            ->where('owner_id', $this->ownerId)
            ->whereRaw('lower(name) = ?', [mb_strtolower($value)])
            ->when($this->ignoreId !== null, fn ($query) => $query->whereKeyNot($this->ignoreId))
            ->exists();

        if ($taken) {
            $fail(self::MESSAGE);
        }
    }
}
