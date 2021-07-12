<?php


namespace LaravelJsonApi\OpenApiSpec\Descriptors\Actions;


use LaravelJsonApi\Contracts\Schema\Schema;
use LaravelJsonApi\OpenApiSpec\Actions\GenerateOpenAPISpec;

class CustomAction extends ActionsDescriptor
{

    protected function describeRoute(
      GenerateOpenAPISpec $generator,
      Schema $schema,
      Route $route
    ): void {
        // TODO: Implement describeRoute() method.
    }

    protected function getSummary(): string
    {
        return '';
    }

    protected static function describesRelation(): bool
    {
        return false;
    }

    protected static function describesAction(): string
    {
        return '*';
    }

    public static function canDescribe($entity): bool
    {
        return $entity instanceof Route
          && static::describesRelation() === ($entity->relation !== null);
    }

}
