<?php


namespace LaravelJsonApi\OpenApiSpec\Descriptors\Schema\Filters;


use cebe\openapi\spec\Parameter;
use LaravelJsonApi\Contracts\Schema\Filter;
use LaravelJsonApi\Contracts\Schema\Schema;
use LaravelJsonApi\OpenApiSpec\Actions\GenerateOpenAPISpec;
use LaravelJsonApi\OpenApiSpec\Descriptors\Concerns\Descriptor;

abstract class FilterDescriptor implements Descriptor
{
    /**
     * @param  \LaravelJsonApi\OpenApiSpec\Actions\GenerateOpenAPISpec  $generator
     * @param  Filter  $entity
     *
     * @return \cebe\openapi\spec\Parameter
     */
    public function describe(
      GenerateOpenAPISpec $generator,
      Schema $schema,
      $entity
    ) {
        return $this->describeFilter($generator,$schema, $entity);
    }

    abstract protected function describeFilter(GenerateOpenAPISpec $generator,Schema $schema, Filter $filter): ?Parameter;

}
