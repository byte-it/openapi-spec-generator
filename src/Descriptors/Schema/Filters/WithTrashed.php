<?php


namespace LaravelJsonApi\OpenApiSpec\Descriptors\Schema\Filters;

use LaravelJsonApi\Contracts\Schema\Filter;
use LaravelJsonApi\Eloquent\Filters\OnlyTrashed;
use LaravelJsonApi\Eloquent\Filters\WithTrashed as WithTrashedFilter;

class WithTrashed extends BooleanFilter
{
    public static function canDescribe($entity): bool
    {
        return $entity instanceof WithTrashedFilter;
    }

    protected function getDescriptionText(Filter $filter): string
    {
        return $filter instanceof OnlyTrashed ? 'Show only trashed records.': 'Include trashed records';
    }

}
