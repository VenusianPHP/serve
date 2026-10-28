<?php

namespace Venusian\Serve\Tests\Fixtures;

use Mariner\Contracts\Routing\UrlRoutable;
use Mariner\Routing\Concerns\BindsToRoutes;
use Voyager\Database\Instrument\Model;

/** A model that binds itself, overriding one of Laravel's binding methods. */
class PublishedArticle extends Model implements UrlRoutable
{
    use BindsToRoutes;

    protected $connection = 'binding';

    protected $table = 'articles';

    protected $guarded = [];

    public $timestamps = false;

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return $query->where($field ?? $this->getRouteKeyName(), $value)->where('published', true);
    }
}
