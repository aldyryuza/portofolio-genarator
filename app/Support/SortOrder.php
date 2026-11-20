<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class SortOrder
{
    /**
     * Move $item one step up or down within the ordered $siblings query and
     * renumber `sort_order` 1..n so gaps and duplicates disappear.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $siblings  already ordered, scoped to the item's list
     */
    public static function move(Builder $siblings, Model $item, string $direction): void
    {
        DB::transaction(function () use ($siblings, $item, $direction): void {
            $ids = (clone $siblings)->lockForUpdate()->pluck($item->getKeyName())->all();
            $from = array_search($item->getKey(), $ids, true);

            if (! is_int($from)) {
                return;
            }

            $to = $direction === 'up' ? $from - 1 : $from + 1;

            if (! isset($ids[$to])) {
                return;
            }

            [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];

            foreach ($ids as $position => $id) {
                $item->newQuery()->whereKey($id)->where('sort_order', '!=', $position + 1)->update(['sort_order' => $position + 1]);
            }
        });
    }
}
