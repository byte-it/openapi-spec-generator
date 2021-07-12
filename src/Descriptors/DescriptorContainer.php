<?php


namespace LaravelJsonApi\OpenApiSpec\Descriptors;


use LaravelJsonApi\Contracts\Schema\Filter;
use LaravelJsonApi\OpenApiSpec\Descriptors\Actions\Route;
use LaravelJsonApi\OpenApiSpec\Descriptors\Concerns\Descriptor;
use LaravelJsonApi\OpenApiSpec\Descriptors\Concerns\ProvidesDescriptor;
use LaravelJsonApi\OpenApiSpec\Descriptors\Concerns\SelfDescribing;
use LaravelJsonApi\OpenApiSpec\Descriptors\Schema\Filters\Scope;
use LaravelJsonApi\OpenApiSpec\Descriptors\Schema\Filters\Where;
use LaravelJsonApi\OpenApiSpec\Descriptors\Schema\Filters\WhereIdIn;
use LaravelJsonApi\OpenApiSpec\Descriptors\Schema\Filters\WhereIn;
use LaravelJsonApi\OpenApiSpec\Descriptors\Schema\Filters\WithTrashed;
use LogicException;
use LaravelJsonApi\OpenApiSpec\Descriptors\Actions;

class DescriptorContainer
{

    /**
     * @var array|string[]
     */
    protected array $filters = [
      WhereIdIn::class,
      WhereIn::class,
      Scope::class,
      WithTrashed::class,
      Where::class,
    ];

    protected array $actions = [
      Actions\Index::class,
      Actions\Show::class,
      Actions\Store::class,
      Actions\Update::class,
      Actions\Destroy::class,
      Actions\Relationship\ShowRelated::class,
      Actions\Relationship\Attach::class,
      Actions\Relationship\Detach::class,
      Actions\Relationship\Show::class,
      Actions\Relationship\Update::class,
        // Fallback
      Actions\CustomAction::class,
    ];

    public function getDescriptor($needsDescription): Descriptor
    {
        if (
          $needsDescription instanceof SelfDescribing
          && $needsDescription->describesItSelf()
        ) {
            return $needsDescription;
        }

        return $this->resolve($needsDescription);
    }

    public function resolve($for): Descriptor
    {
        if ($for instanceof ProvidesDescriptor) {
            return $for->getDescriptor();
        }

        switch ($for) {
          case $for instanceof Filter;
            return $this->getFilterDescriptor($for);
          case $for instanceof Route;
            return $this->getActionDescriptor($for);
          default;
            throw new LogicException("Can't describe ". get_class($for));
        }
    }

    protected function getFilterDescriptor(Filter $for): ?Descriptor
    {
        foreach ($this->filters as $descriptor) {
            if ($descriptor::canDescribe($for)) {
                return new $descriptor();
            }
        }
        throw new LogicException("Can resolve Descriptor for ". get_class($for));
    }

    protected function getActionDescriptor(Route $for): ?Descriptor
    {
        foreach ($this->actions as $descriptor) {
            if ($descriptor::canDescribe($for)) {
                return new $descriptor();
            }
        }
        throw new LogicException("Can resolve Descriptor for ".$for->route->getName()." action");
    }

}
