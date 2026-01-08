<?php

namespace Centamiv\Vektor\Tests\Unit\Api;

use Centamiv\Vektor\Core\Config;
use PHPUnit\Framework\TestCase;

class ControllerTest extends TestCase
{
    protected function setUp(): void
    {
        // Clean DB
        foreach ([Config::getVectorFile(), Config::getGraphFile(), Config::getMetaFile(), Config::getPayloadFile(), Config::getLockFile()] as $file) {
            if (file_exists($file)) unlink($file);
        }
    }

    protected function tearDown(): void
    {
        foreach ([Config::getVectorFile(), Config::getGraphFile(), Config::getMetaFile(), Config::getPayloadFile(), Config::getLockFile()] as $file) {
            if (file_exists($file)) unlink($file);
        }
    }

    public function testInsertSuccess(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/insert';

        TestableController::$mockInput = [
            'id' => 'api_insert',
            'vector' => array_fill(0, 1536, 0.1)
        ];

        $controller = new TestableController();

        ob_start();
        $controller->handleRequest();
        $output = ob_get_clean();

        $json = json_decode($output, true);
        $this->assertEquals('success', $json['status']);
        $this->assertEquals('api_insert', $json['id']);
    }

    public function testSearchSuccess(): void
    {
        // 1. Insert Data first
        $indexer = new \Centamiv\Vektor\Services\Indexer();
        $indexer->insert("find_me", array_fill(0, 1536, 0.9), ['source' => 'unit-test']);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/search';

        TestableController::$mockInput = [
            'vector' => array_fill(0, 1536, 0.9),
            'k' => 1,
            'include_metadata' => true
        ];

        $controller = new TestableController();

        ob_start();
        $controller->handleRequest();
        $output = ob_get_clean();

        $json = json_decode($output, true);
        $this->assertNotEmpty($json['results']);
        $this->assertEquals('find_me', $json['results'][0]['id']);
        $this->assertEquals(['source' => 'unit-test'], $json['results'][0]['metadata']);
    }

    public function testDeleteSuccess(): void
    {
        $indexer = new \Centamiv\Vektor\Services\Indexer();
        $indexer->insert("kill_me", array_fill(0, 1536, 0.9));

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/delete';

        TestableController::$mockInput = [
            'id' => 'kill_me'
        ];

        $controller = new TestableController();

        ob_start();
        $controller->handleRequest();
        $output = ob_get_clean();

        $json = json_decode($output, true);
        $this->assertEquals('success', $json['status']);
    }

    public function testInfoSuccess(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/info';

        TestableController::$mockInput = []; // Not used for GET but safe to init

        $controller = new TestableController();

        ob_start();
        $controller->handleRequest();
        $output = ob_get_clean();

        $json = json_decode($output, true);
        $this->assertArrayHasKey('storage', $json);
        $this->assertArrayHasKey('records', $json);
    }

    public function testValidationErrors(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/insert';

        TestableController::$mockInput = [
            'id' => 'bad_vector',
            'vector' => [1, 2, 3] // Wrong dim
        ];

        $controller = new TestableController();

        ob_start();
        $controller->handleRequest();
        $output = ob_get_clean();

        $this->assertEquals(500, http_response_code());
        $this->assertStringContainsString('exactly 1536 dimensions', $output);
    }
}
