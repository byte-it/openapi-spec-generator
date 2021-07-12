<?php


namespace LaravelJsonApi\OpenApiSpec\Descriptors\Concerns;


use LaravelJsonApi\Contracts\Schema\Schema;
use LaravelJsonApi\OpenApiSpec\Actions\GenerateOpenAPISpec;

interface Descriptor
{
    public function describe(GenerateOpenAPISpec $generator, Schema $schema, $entity);

    public static function canDescribe($entity): bool;
}
