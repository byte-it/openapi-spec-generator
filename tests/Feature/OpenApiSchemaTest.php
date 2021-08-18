<?php

namespace LaravelJsonApi\OpenApiSpec\Tests\Feature;

use GoldSpecDigital\ObjectOrientedOAS\Exceptions\ValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelJsonApi\OpenApiSpec\Facades\GeneratorFacade;
use LaravelJsonApi\OpenApiSpec\Tests\Support\Database\Seeders\DatabaseSeeder;
use LaravelJsonApi\OpenApiSpec\Tests\TestCase;
use Symfony\Component\Yaml\Yaml;

class OpenApiSchemaTest extends TestCase
{
    use RefreshDatabase;

  private array $spec;

  protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $output = GeneratorFacade::generate('v1', 'json');
        $this->spec = json_decode($output, true);


    }

    public function test_url_is_properly_parsed()
    {
      $this->assertArrayHasKey('/posts', $this->spec['paths'], 'Path to resource is not replaced correctly.');
      
      $this->assertArrayHasKey('/posts/{post}/relationships/author', $this->spec['paths'], 'Path to resource is not replaced correctly.');

      $this->assertEquals('http://localhost/api/v1', $this->spec['servers'][0]['variables']['serverUrl']['default']);
    }

    public function test_has_many_should_be_array_of_reg()
    {
      $this->assertEquals(
        $this->spec['paths']['/posts/{post}/relationships/tags']['patch']['requestBody']['content']['application/vnd.api+json']['schema']['properties']['data'],
        [
        'type' => 'array',
        'items' => [
          '$ref' => '#/components/schemas/resources.posts.relationship.tags.update',
        ]
      ]);
      $this->assertEquals(
        $this->spec['paths']['/posts/{post}/relationships/tags']['post']['requestBody']['content']['application/vnd.api+json']['schema']['properties']['data'],
        [
        'type' => 'array',
        'items' => [
          '$ref' => '#/components/schemas/resources.posts.relationship.tags.update',
        ]
      ]);
    }
}
