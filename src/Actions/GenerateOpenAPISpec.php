<?php


namespace LaravelJsonApi\OpenApiSpec\Actions;


use cebe\openapi\spec\Components;
use cebe\openapi\spec\MediaType;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\RequestBody;
use cebe\openapi\spec\Schema as OASchema;
use cebe\openapi\spec\Server as OAServer;
use cebe\openapi\spec\ServerVariable as OAServerVariable;
use cebe\openapi\spec\Type;
use Error;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str as StrStr;
use LaravelJsonApi\Contracts\Schema\Field;
use LaravelJsonApi\Contracts\Schema\Schema;
use LaravelJsonApi\Contracts\Server\Server;
use LaravelJsonApi\Core\Support\Str;
use LaravelJsonApi\Eloquent\Fields\ArrayHash;
use LaravelJsonApi\Eloquent\Fields\ArrayList;
use LaravelJsonApi\Eloquent\Fields\Attribute;
use LaravelJsonApi\Eloquent\Fields\Boolean;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Map;
use LaravelJsonApi\Eloquent\Fields\Number;
use LaravelJsonApi\Eloquent\Fields\Relations\Relation;
use LaravelJsonApi\Eloquent\Fields\Relations\ToOne;
use LaravelJsonApi\OpenApiSpec\Descriptors\DescriptorContainer;
use Throwable;

class GenerateOpenAPISpec
{
    public const OA_VERSION = '3.0.2';

    protected string $serverKey;

    protected OpenApi $openApi;

    protected Collection $routes;

    protected array $paths = [];

    protected Server $jsonapiServer;

    /**
     * @return \LaravelJsonApi\Contracts\Server\Server
     */
    public function getJsonapiServer(): Server
    {
        return $this->jsonapiServer;
    }

    protected array $resources = [];

    protected DescriptorContainer $descriptorContainer;

    public function __construct(string $serverKey)
    {
        $this->serverKey = $serverKey;
        $this->descriptorContainer = app()->make(DescriptorContainer::class);
    }

    /**
     * @throws \cebe\openapi\exceptions\TypeErrorException
     * @throws \Exception
     */
    public function generate(): OpenApi
    {
        $serverKey = $this->serverKey;
        $serverClass = config("jsonapi.servers.$serverKey");

        // Initial OpenAPI object
        $this->openApi = new OpenApi([
          'openapi' => self::OA_VERSION,
          'info' => [
            'title' => config("openapi.servers.$serverKey.info.title"),
            'description' => config("openapi.servers.$serverKey.info.description"),
            'version' => config("openapi.servers.$serverKey.info.version"),
          ],
          'paths' => [],
          "components" => $this->getDefaultComponents(),
          'x-tagGroups' => config("openapi.servers.$serverKey.tag_groups"),
        ]);

        // Load JSON:API Server
        $this->jsonapiServer = new $serverClass(app(), $serverKey);

        // Add server to OpenAPI spec
        $this->openApi->servers = [
          new OAServer([
            'url' => "{serverURL}",
            "description" => "provide your server URL",
            "variables" => [
              "serverURL" => new OAServerVariable([
                "default" => $this->jsonapiServer->url(""),
                "description" => "path for server",
              ]),
            ],
          ]),
        ];

        // Get all Laravel routes associated with this JSON:API Server
        $this->routes =
          collect(Route::getRoutes()->getRoutes())->filter(
            function (\Illuminate\Routing\Route $route) use ($serverKey) {
                return StrStr::contains($route->getName(), $serverKey);
            }
          );

        foreach ($this->routes as $route) {
            foreach ($route->methods() as $method) {
                $this->generateOperationForMethod(
                  $method,
                  $route,
                );
            }
        }
        // Add paths to OpenApi spec
        foreach ($this->paths as $key => $method) {
            $this->openApi->paths[$key] = new PathItem($method);
        }


        if ( ! $this->openApi->validate()) {

            throw new Error("Validation failed.");
        }
        return $this->openApi;
    }

    /**
     * @param  mixed  $method
     * @param  mixed  $route
     *
     */
    protected function generateOperationForMethod(
      $method,
      \Illuminate\Routing\Route $route
    ): void {

        $routeNameSegments = explode('.', $route->getName());
        $routeNameSegments = array_slice(
          $routeNameSegments,
          array_search($this->jsonapiServer->name(), $routeNameSegments) + 1
        );

        $resource = null;
        $action = null;
        $relation = null;

        if(count($routeNameSegments) === 2){
            [$resource, $action] = $routeNameSegments;
        }
        elseif (count($routeNameSegments) === 3){
            [$resource, $relation, $action] = $routeNameSegments;
        }

        $schema = $this->jsonapiServer->schemas()->schemaFor($resource);

        if($relation === null && $schema->isRelationship($action)){
            $relation = $action;
            $action = 'showRelated';
        }

        if ($method === 'HEAD') {
            return;
        }
        $routeAttributes = new \LaravelJsonApi\OpenApiSpec\Descriptors\Actions\Route($route, $resource, $action, $relation);

        $operation = $this->descriptorContainer->getDescriptor($routeAttributes)->describe($this, $schema, $routeAttributes);


        $uri = '/'.$route->uri();
        $routeUri = str_replace($this->jsonapiServer->baseUri(), '', $uri);

        if ( ! isset($this->paths[$routeUri])) {
            $this->paths[$routeUri] = [];
        }
        $this->paths[$routeUri][strtolower($method)] = $operation;
    }

    /**
     * @throws \cebe\openapi\exceptions\TypeErrorException
     */
    public function generateOAGetDataSchema(
      Schema $schema,
      string $schemaName,
      string $schemaNamePlural
    ): Reference {
        $oaSchemaName = $schemaName.'_data';
        if ( ! ($ref = $this->hasSchema($oaSchemaName))) {

            $relationSchemas = [];

            /** @var \LaravelJsonApi\Core\Resources\JsonApiResource $resource */
            $resource = $this->getModelResources($schema::model())->first();
            $model = $resource->resource;
            $fieldValues = collect($resource->attributes(null))->toArray();
            foreach ($schema->fields() as $field) {

                $fieldSchema = new OASchema([
                  'title' => $field->name(),
                  "type" => Type::OBJECT,
                ]);

                if ($field instanceof ID) {
                    continue;
                }

                if ($field instanceof Attribute) {
                    if($field->isHidden(null)){
                        continue;
                    }

                    switch ($field){
                      case $field instanceof Boolean:
                        $fieldSchema->type = Type::BOOLEAN;
                        break;
                      case $field instanceof Number:
                        $fieldSchema->type = Type::NUMBER;
                        break;
                      case $field instanceof ArrayList:
                        $fieldSchema->type = Type::ARRAY;
                        break;
                      case $field instanceof ArrayHash:
                      case $field instanceof Map:
                        $fieldSchema->type = Type::OBJECT;
                        break;
                      default:
                        $fieldSchema->type = Type::STRING;
                    }

                    // Try to get example data
                    try {
                        $fieldSchema->example = $fieldValues[$field->name()];
                    } catch (Throwable $e) {
                    }

                } elseif ($field instanceof Relation) {
                    $relationSchema = new OASchema([
                      'title' => $field->name(),
                    ]);
                    $relationLinkSchema = new OASchema([
                      'title' => $field->name(),
                    ]);
                    $relationDataSchema = new OASchema([
                      'title' => $field->name(),
                    ]);
                    $fieldName = Str::dasherize(
                      Str::plural($field->relationName())
                    );

                    $relationLinkSchema->properties = [
                      'related' => new OASchema([
                        'title' => 'related',
                        "type" => Type::STRING,
                      ]),
                      'self' => new OASchema([
                        'title' => 'self',
                        "type" => Type::STRING,
                      ]),
                    ];
                    $relationDataSchema->properties = [
                      'type' => new OASchema([
                        'title' => 'type',
                        "type" => Type::STRING,
                        "example" => $fieldName,
                      ]),
                      'id' => new OASchema([
                        'title' => 'id',
                        "type" => Type::STRING,
                        "example" => (string) optional($model)->{$schema->id()
                          ->column() ?? optional($model)->getRouteKeyName()},
                      ]),
                    ];
                    $relationSchema->properties = [
                      'links' => new OASchema([
                        'title' => 'links',
                        'type' => Type::OBJECT,
                        "allOf" => [$relationLinkSchema],
                        "example" => $this->jsonapiServer->url([
                          $fieldName,
                          (string) optional($model)->{$schema->id()
                            ->column() ?? optional($model)->getRouteKeyName()},
                        ]),
                      ]),
                      'data' => new OASchema([
                        'title' => 'data',
                        "allOf" => [$relationDataSchema],
                      ]),
                    ];

                    if (
                      $field instanceof ToOne
                      && in_array($fieldName,
                        $this->jsonapiServer->schemas()->types(), true)
                    ) {
                        $fieldSchema->__set('oneOf', [
                          $relationSchema,
                        ]);
                    }
                    $relationSchemas[$field->name()] = $relationSchema;
                    continue;
                }

                $attributes[$field->name()] = $fieldSchema;
                unset($fieldSchema);
            }


            $oaSchema = new OASchema([
              "type" => Type::OBJECT,
              "title" => ucfirst($schemaName)." data",
              "properties" => [
                "type" => new OASchema([
                  'title' => $schemaName,
                  'type' => Type::STRING,
                  'example' => $schemaNamePlural,
                ]),
                "id" => new OASchema([
                  'title' => 'id',
                  'type' => Type::STRING,
                  "example" => isset($resource) ? $resource->id() : NULL,
                ]),
                "attributes" => new OASchema([
                  'title' => 'attributes',
                  'properties' => $attributes,
                ]),
                "relationships" => new OASchema([
                  'title' => 'relationships',
                  'properties' => ! empty($relationSchemas) ? $relationSchemas : [],
                ]),
                "links" => new OASchema([
                  'title' => 'links',
                  "nullable" => true,
                  'properties' => [
                    "self" => new OASchema([
                      "title" => "self",
                      'type' => Type::STRING,
                      "example" => $this->jsonapiServer->url([
                        $schemaNamePlural,
                        (string) optional($model)->{$schema->id()
                          ->column() ?? optional($model)->getRouteKeyName()},
                      ]),
                    ]),
                  ],
                ]),
              ],
            ]);
            $ref = $this->addSchema($oaSchemaName, $oaSchema);
        }
        return $ref;
    }

    /**
     * @throws \cebe\openapi\exceptions\TypeErrorException
     */
    public function generateOAGetResponseSchema(
      Schema $schema,
      string $schemaName,
      string $schemaNamePlural
    ): Reference {
        $oaSchemaName = $schemaName.'_get_single';
        if ( ! ($ref = $this->hasSchema($oaSchemaName))) {
            $dataRef = $this->generateOAGetDataSchema(
              $schema,
              $schemaName,
              $schemaNamePlural
            );

            $ref = $this->addSchema(
              $oaSchemaName,
              new OASchema([
                'title' => ucfirst($schemaName)." response",
                'properties' => [
                  'jsonapi' => $this->getDefaultJsonApiSchema(),
                  'data' => new OASchema([
                    'oneOf' => [
                      $dataRef,
                    ],
                  ]),
                ],
              ])
            );
        }
        return $ref;
    }

    /**
     * @throws \cebe\openapi\exceptions\TypeErrorException
     */
    public function generateOAGetMultipleResponseSchema(
      Schema $schema,
      string $schemaName,
      string $schemaNamePlural
    ): Reference {
        $oaSchemaName = $schemaName.'_get_multiple';
        if ( ! ($ref = $this->hasSchema($oaSchemaName))) {
            $dataRef = $this->generateOAGetDataSchema($schema, $schemaName,
              $schemaNamePlural);
            $ref = $this->addSchema(
              $oaSchemaName,
              new OASchema([
                'title' => ucfirst($schemaNamePlural)." response",
                'properties' => [
                  'jsonapi' => $this->getDefaultJsonApiSchema(),
                  'data' => new OASchema([
                    'type' => Type::ARRAY,
                    'items' => $dataRef,
                  ]),
                ],
              ]));
        }
        return $ref;
    }

    /**
     * @throws \cebe\openapi\exceptions\TypeErrorException
     */
    public function generateOAStoreRequestBody(
      Schema $schema,
      string $schemaName,
      string $schemaNamePlural
    ): Reference {

        $requestName = $schemaName."_store";
        if ( ! isset($this->requests[$requestName])) {

            $relationSchemas = [];
            $attributes = [];

            $modelClass = $schema::model();
            $model = $modelClass::first();

            foreach ($schema->fields() as $field) {

                $fieldSchema = new OASchema([
                  'title' => $field->name(),
                  "type" => Type::OBJECT,
                ]);

                if ($field instanceof ID) {
                    continue;
                }


                if ($field instanceof Attribute) {
                    if($field->isReadOnly(null)){
                        continue;
                    }

                    switch ($field){
                      case $field instanceof Boolean:
                        $fieldSchema->type = Type::BOOLEAN;
                        break;
                      case $field instanceof Number:
                        $fieldSchema->type = Type::NUMBER;
                        break;
                      case $field instanceof ArrayList:
                        $fieldSchema->type = Type::ARRAY;
                        break;
                      case $field instanceof ArrayHash:
                      case $field instanceof Map:
                        $fieldSchema->type = Type::OBJECT;
                        break;
                      default:
                        $fieldSchema->type = Type::STRING;
                    }

                    // Try to get example data
                    try {
                        $fieldSchema->example = isset($model) ? $model->{$field->column()} : NULL;
                    } catch (Throwable $e) {
                    }

                } elseif ($field instanceof Relation) {
                    $relationSchema = new OASchema([
                      'title' => $field->name(),
                    ]);
                    $relationDataSchema = new OASchema([
                      'title' => $field->name(),
                    ]);
                    $fieldName = Str::dasherize(
                      Str::plural($field->relationName())
                    );

                    $relationDataSchema->properties = [
                      'type' => new OASchema([
                        'title' => 'type',
                        "type" => Type::STRING,
                        "example" => $fieldName,
                      ]),
                      'id' => new OASchema([
                        'title' => 'id',
                        "type" => Type::STRING,
                        "example" => (string) optional($model)->{$schema->id()
                          ->column() ?? optional($model)->getRouteKeyName()},
                      ]),
                    ];
                    $relationSchema->properties = [
                      'data' => new OASchema([
                        'title' => 'data',
                        "allOf" => [$relationDataSchema],
                      ]),
                    ];

                    if (
                      $field instanceof ToOne
                      && in_array($fieldName,
                        $this->jsonapiServer->schemas()->types(), true)
                    ) {
                        $fieldSchema->__set('oneOf', [
                          $relationSchema,
                        ]);
                    }
                    $relationSchemas[$field->name()] = $relationSchema;
                    continue;
                }

                $attributes[$field->name()] = $fieldSchema;
                unset($fieldSchema);
            }


            $oaSchema = new OASchema([
              "type" => Type::OBJECT,
              "title" => ucfirst($schemaName)." store data",
              "properties" => [
                "type" => new OASchema([
                  'title' => $schemaName,
                  'type' => Type::STRING,
                  'example' => $schemaNamePlural,
                ]),
                "attributes" => new OASchema([
                  'title' => 'attributes',
                  'properties' => $attributes,
                ]),
                "relationships" => new OASchema([
                  'title' => 'relationships',
                  'properties' => ! empty($relationSchemas) ? $relationSchemas : [],
                ]),
              ],
            ]);
            $oaSchemaName = $schemaName.'_store_data';
            $dataRef = $this->addSchema($oaSchemaName, $oaSchema);


            $this->addRequestBody($requestName, new RequestBody([
                'description' => ucfirst($schemaName)." store",
                'content' => [
                  'application/vnd.api+json' => new MediaType([
                    "schema" => new OASchema([
                      "properties" => [
                        "data" => new OASchema([
                          "title" => 'data',
                          "type" => Type::OBJECT,
                          "oneOf" => [
                            $dataRef,
                          ],
                        ]),
                      ],
                    ]),
                  ]),
                ],
              ])
            );
        }
        return new Reference(['$ref' => "#/components/requestBodies/$requestName"]);

    }

    /**
     * @throws \cebe\openapi\exceptions\TypeErrorException
     */
    public function generateOAUpdateRequestBody(
      Schema $schema,
      string $schemaName,
      string $schemaNamePlural
    ): Reference {

        $requestName = $schemaName."_update";
        if ( ! ($ref = $this->hasRequestBody($requestName))) {

            $relationSchemas = [];
            $attributes = [];

            $modelClass = $schema::model();
            $model = $modelClass::first();

            foreach ($schema->fields() as $field) {

                $fieldSchema = new OASchema([
                  'title' => $field->name(),
                  "type" => Type::OBJECT,
                ]);

                if ($field instanceof ID) {
                    continue;
                }

                if ($field instanceof Attribute) {
                    if($field->isReadOnly(null)){
                        continue;
                    }

                  switch ($field){
                    case $field instanceof Boolean:
                      $fieldSchema->type = Type::BOOLEAN;
                      break;
                    case $field instanceof Number:
                      $fieldSchema->type = Type::NUMBER;
                      break;
                    case $field instanceof ArrayList:
                      $fieldSchema->type = Type::ARRAY;
                      break;
                    case $field instanceof ArrayHash:
                    case $field instanceof Map:
                      $fieldSchema->type = Type::OBJECT;
                      break;
                    default:
                      $fieldSchema->type = Type::STRING;
                  }

                    // Try to get example data
                    try {
                        $fieldSchema->example = isset($model) ? $model->{$field->column()} : NULL;
                    } catch (Throwable $e) {
                    }

                } elseif ($field instanceof Relation) {
                    $relationSchema = new OASchema([
                      'title' => $field->name(),
                    ]);
                    $relationDataSchema = new OASchema([
                      'title' => $field->name(),
                    ]);
                    $fieldName = Str::dasherize(
                      Str::plural($field->relationName())
                    );

                    $relationDataSchema->properties = [
                      'type' => new OASchema([
                        'title' => 'type',
                        "type" => Type::STRING,
                        "example" => $fieldName,
                      ]),
                      'id' => new OASchema([
                        'title' => 'id',
                        "type" => Type::STRING,
                        "example" => (string) optional($model)->{$schema->id()
                          ->column() ?? optional($model)->getRouteKeyName()},
                      ]),
                    ];
                    $relationSchema->properties = [
                      'data' => new OASchema([
                        'title' => 'data',
                        "allOf" => [$relationDataSchema],
                      ]),
                    ];

                    if (
                      $field instanceof ToOne
                      && in_array($fieldName,
                        $this->jsonapiServer->schemas()->types(), true)
                    ) {
                        $fieldSchema->__set('oneOf', [
                          $relationSchema,
                        ]);
                    }
                    $relationSchemas[$field->name()] = $relationSchema;
                    continue;
                }

                $attributes[$field->name()] = $fieldSchema;
                unset($fieldSchema);
            }


            $oaSchema = new OASchema([
              "type" => Type::OBJECT,
              "title" => ucfirst($schemaName)." update data",
              "properties" => [
                "type" => new OASchema([
                  'title' => $schemaName,
                  'type' => Type::STRING,
                  'example' => $schemaNamePlural,
                ]),
                "attributes" => new OASchema([
                  'title' => 'attributes',
                  'properties' => $attributes,
                ]),
                "relationships" => new OASchema([
                  'title' => 'relationships',
                  'properties' => ! empty($relationSchemas) ? $relationSchemas : [],
                ]),
              ],
            ]);
            $oaSchemaName = $schemaName.'_store_data';
            $dataRef = $this->addSchema($oaSchemaName, $oaSchema);


            $ref = $this->addRequestBody($requestName, new RequestBody([
                'description' => $schemaName."_update",
                'content' => [
                  'application/vnd.api+json' => new MediaType([
                    "schema" => new OASchema([
                      "properties" => [
                        "data" => new OASchema([
                          "title" => 'data',
                          "type" => Type::OBJECT,
                          "oneOf" => [
                            $dataRef,
                          ],
                        ]),
                      ],
                    ]),
                  ]),
                ],
              ])
            );
        }
        return $ref;

    }

    /**
     * @throws \cebe\openapi\exceptions\TypeErrorException
     */
    protected function getDefaultSchemas(): array
    {
        return [
          'unauthorized' => new OASchema([
            'title' => "401 Unauthorized",
            "type" => Type::OBJECT,
            "properties" => [
              "errors" => new OASchema([
                'title' => "errors",
                "type" => Type::OBJECT,
                "properties" => [
                  "detail" => new OASchema([
                    'title' => "detail",
                    "type" => Type::STRING,
                    "example" => 'Unauthenticated.',
                  ]),
                  "status" => new OASchema([
                    'title' => "status",
                    "type" => Type::STRING,
                    "example" => '401',
                  ]),
                  "title" => new OASchema([
                    'title' => "title",
                    "type" => Type::STRING,
                    "example" => 'Unauthorized',
                  ]),
                ],
              ]),
            ],
          ]),
          'forbidden' => new OASchema([
            'title' => "403 Forbidden",
            "type" => Type::OBJECT,
            "properties" => [
              "errors" => new OASchema([
                'title' => "errors",
                "type" => Type::OBJECT,
                "properties" => [
                  "detail" => new OASchema([
                    'title' => "detail",
                    "type" => Type::STRING,
                    "example" => 'Forbidden.',
                  ]),
                  "status" => new OASchema([
                    'title' => "status",
                    "type" => Type::STRING,
                    "example" => '403',
                  ]),
                  "title" => new OASchema([
                    'title' => "title",
                    "type" => Type::STRING,
                    "example" => 'Forbidden',
                  ]),
                ],
              ]),
            ],
          ]),
          'not_found' => new OASchema([
            'title' => "404 Not Found",
            "type" => Type::OBJECT,
            "properties" => [
              "errors" => new OASchema([
                'title' => "errors",
                "type" => Type::OBJECT,
                "properties" => [
                  "status" => new OASchema([
                    'title' => "status",
                    "type" => Type::STRING,
                    "example" => '404',
                  ]),
                  "title" => new OASchema([
                    'title' => "title",
                    "type" => Type::STRING,
                    "example" => 'Not Found',
                  ]),
                ],
              ]),
            ],
          ]),
        ];
    }

    /**
     * @throws \cebe\openapi\exceptions\TypeErrorException
     */
    protected function getDefaultComponents(): Components
    {
        return new Components([
          'parameters' => [
            'sort' => new Parameter([
              "name" => "sort",
              "in" => "query",
              "description" => '[fields to sort by](https://jsonapi.org/format/#fetching-sorting)',
              "required" => false,
              "allowEmptyValue" => true,
              "style" => "form",
              "schema" => ["type" => "string"],
            ]),
            'pageSize' => new Parameter([
              "name" => "page[size]",
              "in" => "query",
              "description" => 'size of page for paginated results',
              "required" => false,
              "allowEmptyValue" => true,
              "schema" => ["type" => "integer"],
            ]),
            'pageNumber' => new Parameter([
              "name" => "page[number]",
              "in" => "query",
              "description" => 'size of page for paginated results',
              "required" => false,
              "allowEmptyValue" => true,
              "schema" => ["type" => "integer"],
            ]),
            'pageLimit' => new Parameter([
              "name" => "page[limit]",
              "in" => "query",
              "description" => 'size of page for paginated results',
              "required" => false,
              "allowEmptyValue" => true,
              "schema" => ["type" => "integer"],
            ]),
            'pageOffset' => new Parameter([
              "name" => "page[offset]",
              "in" => "query",
              "description" => 'size of page for paginated results',
              "required" => false,
              "allowEmptyValue" => true,
              "schema" => ["type" => "integer"],
            ]),
          ],
          'schemas' => $this->getDefaultSchemas(),
        ]);
    }

    /**
     * @param $operationId
     *
     * @return string|null
     */
    public function getSummary($operationId): ?string
    {
        return config("openapi.servers.{$this->jsonapiServer->name()}.operations.$operationId.summary");
    }

    /**
     * @param $operationId
     *
     * @return string|null
     */
    public function getDescription($operationId): ?string
    {
        return config("openapi.servers.{$this->jsonapiServer->name()}.operations.$operationId.description");
    }

    /**
     * @param $operationId
     *
     * @return string[]
     */
    public function getTags($operationId): array
    {
        return config("openapi.servers.{$this->jsonapiServer->name()}.operations.$operationId.extra_tags",
          []);
    }

    /**
     * @throws \cebe\openapi\exceptions\TypeErrorException
     */
    public function getDefaultJsonApiSchema(): OASchema
    {
        return new OASchema([
          'title' => 'jsonapi',
          'properties' => [
            "version" => new OASchema([
              "title" => "version",
              'type' => Type::STRING,
              "example" => "1.0",
            ]),
          ],
        ]);
    }

    /**
     * @return Reference|bool
     * @throws \cebe\openapi\exceptions\TypeErrorException
     */
    protected function hasSchema(string $name)
    {
      return isset($this->openApi->components->schemas[$name]) ?
        new Reference(['$ref' => "#/components/schemas/$name"]) :
        false;
    }

    /**
     * @throws \cebe\openapi\exceptions\TypeErrorException
     */
    protected function addSchema(string $name, OASchema $schema): Reference
    {
        $this->openApi->components->__set(
          'schemas',
          array_merge($this->openApi->components->schemas, [$name => $schema])
        );
        return new Reference(['$ref' => "#/components/schemas/$name"]);
    }

    /**
     * @return Reference|bool
     * @throws \cebe\openapi\exceptions\TypeErrorException
     */
    protected function hasRequestBody(string $name)
    {
        return isset($this->openApi->components->requestBodies[$name]) ?
          new Reference(['$ref' => "#/components/requestBodies/$name"]) :
          false;
    }

    /**
     * @throws \cebe\openapi\exceptions\TypeErrorException
     */
    protected function addRequestBody(
      string $name,
      RequestBody $requestBody
    ): Reference {
        $this->openApi->components->__set(
          'requestBodies',
          array_merge($this->openApi->components->requestBodies,
            [$name => $requestBody])
        );
        return new Reference(['$ref' => "#/components/requestBodies/$name"]);
    }

    public function getModelResources(string $model): Collection
    {
        if ( ! isset($this->resources[$model])) {
            $resources = $model::all()->map(function ($model) {
                return $this->jsonapiServer->resources()->create($model);
            });

            $this->resources[$model] = $resources;
        }

        return $this->resources[$model];
    }

    /**
     * @return \cebe\openapi\spec\OpenApi
     */
    public function getOpenApi(): OpenApi
    {
        return $this->openApi;
    }

}
