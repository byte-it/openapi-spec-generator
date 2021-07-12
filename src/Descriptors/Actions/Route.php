<?php


namespace LaravelJsonApi\OpenApiSpec\Descriptors\Actions;


class Route
{

  public \Illuminate\Routing\Route $route;

  public string $resource;

  public string $action;

  public ?string $relation = NULL;

  public function __construct(
      \Illuminate\Routing\Route $route,
      string $resource,
      string $action,
      ?string $relation = null
    )
    {
      $this->relation = $relation;
      $this->action = $action;
      $this->resource = $resource;
      $this->route = $route;
    }
}
