<?php


namespace LaravelJsonApi\OpenApiSpec\Descriptors\Actions;


use cebe\openapi\spec\Example;
use cebe\openapi\spec\MediaType;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\RequestBody;
use cebe\openapi\spec\Response;
use cebe\openapi\spec\Responses;
use cebe\openapi\spec\Schema as OASchema;
use LaravelJsonApi\Contracts\Schema\Schema;
use LaravelJsonApi\Contracts\Server\Server;
use LaravelJsonApi\OpenApiSpec\Actions\GenerateOpenAPISpec;
use LaravelJsonApi\OpenApiSpec\Descriptors\Concerns\Descriptor;
use LaravelJsonApi\OpenApiSpec\Descriptors\DescriptorContainer;
use Neomerx\JsonApi\Contracts\Http\Headers\MediaTypeInterface;

abstract class ActionsDescriptor implements Descriptor
{

    protected DescriptorContainer $descriptorContainer;

    protected Server $server;

    protected Schema $schema;

    protected string $operationId;

    protected string $resourceType;

    protected string $action;

    protected array $parameters = [];

    protected Responses $responses;

    /**
     * @var \cebe\openapi\spec\Reference|\cebe\openapi\spec\RequestBody|null
     */
    protected $requestBody = null;

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function __construct()
    {
        $this->descriptorContainer = app()->make(DescriptorContainer::class);
    }


    public static function canDescribe($entity): bool
    {
        return $entity instanceof Route
          && static::describesRelation() === ($entity->relation !== null)
          &&  static::describesAction() === $entity->action;
    }


    /**
     * @param  \LaravelJsonApi\OpenApiSpec\Actions\GenerateOpenAPISpec  $generator
     * @param  \LaravelJsonApi\Contracts\Schema\Schema  $schema
     * @param  Route  $entity
     *
     * @return \cebe\openapi\spec\Operation
     * @throws \cebe\openapi\exceptions\TypeErrorException
     */
    public function describe(
      GenerateOpenAPISpec $generator,
      Schema $schema,
       $entity
    ) {

        $this->server = $generator->getJsonapiServer();

        $this->resourceType = $entity->resource;
        $this->action = $entity->action;

        $this->operationId = collect([$entity->resource, $entity->relation, $entity->action])->join('_');

        $this->schema = $this->server->schemas()->schemaFor($this->resourceType);

        $this->responses = new Responses([]);

        $this->responses->addResponse(401, new Response([
          'description' => "Unauthorized Action",
          "content" => [
            MediaTypeInterface::JSON_API_MEDIA_TYPE => new MediaType([
              "schema" => new OASchema([
                "oneOf" => [
                  new Reference([
                    '$ref' => "#/components/schemas/unauthorized",
                  ]),
                ],
              ]),
            ]),
          ],
        ]));

        $this->responses->addResponse(403, new Response([
          'description' => "Forbidden Action",
          "content" => [
            MediaTypeInterface::JSON_API_MEDIA_TYPE => new MediaType([
              "schema" => new OASchema([
                "oneOf" => [
                  new Reference([
                    '$ref' => "#/components/schemas/forbidden",
                  ]),
                ],
              ]),
            ]),
          ],
        ]));

        $this->describeRoute($generator, $schema, $entity);

        if (isset($entity->route->defaults[\LaravelJsonApi\Laravel\Routing\Route::RESOURCE_ID_NAME])) {
            $this->responses->addResponse(404, new Response([
              'description' => "Content Not Found",
              "content" => [
                MediaTypeInterface::JSON_API_MEDIA_TYPE => new MediaType([
                  "schema" => new OASchema([
                    "oneOf" => [
                      new Reference([
                        '$ref' => "#/components/schemas/not_found",
                      ]),
                    ],
                  ]),
                ]),
              ],
            ]));

            $examples = $generator->getModelResources($schema::model())
              ->mapWithKeys(function ($model) {
                  $id = $model->id();
                  return [
                    $id => new Example([
                      'value' => $id,
                    ]),
                  ];
              })->toArray();
            $this->parameters[] = new Parameter([
              'name' => $entity->route->defaults[\LaravelJsonApi\Laravel\Routing\Route::RESOURCE_ID_NAME],
              'in' => 'path',
              'required' => true,
              'allowEmptyValue' => false,
              "examples" => $examples,
              'schema' => new OASchema([
                'title' => $this->resourceType,
              ]),
            ]);
        }

        $operation = new Operation([
          "summary" => $generator->getSummary(
              $this->operationId) ?? $this->getSummary(),
          "description" => $generator->getDescription(
              $this->operationId) ?? $this->getDescription(),
          "operationId" => $this->operationId,
          "parameters" => $this->parameters,
          "responses" => $this->responses,
          "tags" => [
            ucfirst($this->resourceType),
            ...$generator->getTags($this->operationId),
          ],
        ]);

        if ($this->requestBody) {
            $operation->requestBody = $this->requestBody;
        }

        return $operation;
    }

    protected function getDescription(): string{
        return '';
    }

    abstract protected function describeRoute(
      GenerateOpenAPISpec $generator,
      Schema $schema,
      Route $route
    ): void;

    abstract protected function getSummary(): string;

    abstract protected static function describesRelation(): bool;
    abstract protected static function describesAction(): string;

}
