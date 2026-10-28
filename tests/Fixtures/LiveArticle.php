<?php

namespace Venusian\Serve\Tests\Fixtures;

use Voyager\Database\Instrument\Model;

/** A plain Instrument model narrowing its own binding query, which Mariner honours. */
class LiveArticle extends Model
{
    protected $connection = 'binding';

    protected $table = 'articles';

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return $query->where($field ?? $this->getRouteKeyName(), $value)->where('published', true);
    }
}
