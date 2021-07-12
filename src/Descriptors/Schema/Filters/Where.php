<?php


namespace LaravelJsonApi\OpenApiSpec\Descriptors\Schema\Filters;


use cebe\openapi\spec\Example;
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\Schema as OASchema;
use cebe\openapi\spec\Type;
use Exception;
use LaravelJsonApi\Contracts\Schema\Filter;
use LaravelJsonApi\Contracts\Schema\Schema;
use LaravelJsonApi\Eloquent\Filters\Where as WhereFilter;
use LaravelJsonApi\OpenApiSpec\Actions\GenerateOpenAPISpec;


class Where extends FilterDescriptor
{

    public static function canDescribe($entity): bool
    {
        return $entity instanceof WhereFilter;
    }

    /**
     * @throws \cebe\openapi\exceptions\TypeErrorException
     *
     * @todo Pay attention to isSingular
     */
    protected function describeFilter(
      GenerateOpenAPISpec $generator,
      Schema $schema,
      Filter $filter
    ): Parameter {
        $key = $filter->key();
        try {
            $examples = $schema::model()::all()
              ->pluck($key)
              ->mapWithKeys(function ($f) {
                  return [
                    $f => new Example([
                      'value' => $f,
                    ]),
                  ];
              })
              ->toArray();
        } catch (Exception $e) {
            $examples = [];
        }


        return new Parameter([
          'name' => "filter[{$filter->key()}]",
          'description' => 'Filters the records',
          'in' => 'query',
          'required' => false,
          'allowEmptyValue' => false,
          'examples' => $examples,
          'schema' => new OASchema([
            "type" => Type::STRING,
          ]),
        ]);
    }

}
