<?php


namespace LaravelJsonApi\OpenApiSpec\Descriptors\Schema\Filters;


use cebe\openapi\spec\Example;
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\Schema as OASchema;
use cebe\openapi\spec\Type;
use Exception;
use LaravelJsonApi\Contracts\Schema\Filter;
use LaravelJsonApi\Contracts\Schema\Schema;
use LaravelJsonApi\Eloquent\Filters\WhereIn as WhereInFilter;
use LaravelJsonApi\Eloquent\Filters\WhereNotIn;
use LaravelJsonApi\OpenApiSpec\Actions\GenerateOpenAPISpec;

class WhereIn extends FilterDescriptor
{

    public static function canDescribe($entity): bool
    {
        return $entity instanceof WhereInFilter;
    }

    /**
     *
     * @param  \LaravelJsonApi\OpenApiSpec\Actions\GenerateOpenAPISpec  $generator
     * @param  \LaravelJsonApi\Contracts\Schema\Schema  $schema
     * @param  \LaravelJsonApi\Eloquent\Filters\WhereIn  $filter
     *
     * @return \cebe\openapi\spec\Parameter
     * @throws \cebe\openapi\exceptions\TypeErrorException
     *
     * @todo Pay attention to delimiter
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
                      'value' => [$f],
                    ]),
                  ];
              })
              ->toArray();
        } catch (Exception $e) {
            $examples = [];
        }

        return new Parameter([
          'name' => "filter[{$filter->key()}]",
          'description' =>  $filter instanceof WhereNotIn ? "A list of {$filter->key()}s to exclude by." : "A list of {$filter->key()}s to filter by.",
          'in' => 'query',
          'style' => 'form',
          'explode' => true,
          'required' => false,
          'allowEmptyValue' => false,
          'examples' => $examples,
          'schema' => new OASchema([
            'type' => Type::ARRAY,
            'items' => new OASchema([
              'type' => Type::STRING,
            ]),
          ]),
        ]);
    }

}
