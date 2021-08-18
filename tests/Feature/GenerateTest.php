<?php

namespace LaravelJsonApi\OpenApiSpec\Tests\Feature;

use GoldSpecDigital\ObjectOrientedOAS\Exceptions\ValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelJsonApi\OpenApiSpec\Facades\GeneratorFacade;
use LaravelJsonApi\OpenApiSpec\Tests\Support\Database\Seeders\DatabaseSeeder;
use LaravelJsonApi\OpenApiSpec\Tests\TestCase;
use Symfony\Component\Yaml\Yaml;

class GenerateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_spec_is_yaml()
    {
        $openapiYaml = GeneratorFacade::generate('v1', 'yaml');

        $spec = Yaml::parse($openapiYaml);

        $this->assertEquals('My JSON:API', $spec['info']['title']);
    }

    public function test_spec_is_json()
    {
        $output = GeneratorFacade::generate('v1', 'json');

        $spec = json_decode($output, true);

        file_put_contents('output.json', $output);

        $this->assertEquals('My JSON:API', $spec['info']['title']);
    }

    public function test_spec_file_generated()
    {
        GeneratorFacade::generate('v1');

        $openapiYaml = \Storage::get('v1_openapi.yaml');

        $spec = Yaml::parse($openapiYaml);

        $this->assertEquals('My JSON:API', $spec['info']['title']);
    }

}
