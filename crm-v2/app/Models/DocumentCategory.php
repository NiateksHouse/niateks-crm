<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentCategory extends Model
{
    protected $guarded = ['id'];

    public static function tree(): array
    {
        $groups = static::query()->get()->groupBy(fn ($c) => $c->parent_id ?? 0);
        $collator = new \Collator('tr_TR');
        $build = function ($parent, $depth = 0) use (&$build, $groups, $collator) {
            if ($depth > 8) {
                return [];
            }
            $items = ($groups[$parent] ?? collect())->all();
            usort($items, fn ($a, $b) => $collator->compare($a->name, $b->name) ?: $a->id <=> $b->id);

            return array_map(fn ($c) => ['category' => $c, 'children' => $build($c->id, $depth + 1)], $items);
        };

        return $build(0);
    }
}
