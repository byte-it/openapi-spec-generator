<?php


namespace LaravelJsonApi\OpenApiSpec\Descriptors\Schema\Filters;

use LaravelJsonApi\Contracts\Schema\Filter;
use LaravelJsonApi\Eloquent\Filters\Scope as ScopeFilter;

class Scope extends BooleanFilter
{
    public static function canDescribe($entity): bool
    {
        return $entity instanceof ScopeFilter;
    }

    protected function getDescriptionText(Filter $filter): string
    {
        return "Applies the {$filter->key()} scope.";
    }

}
