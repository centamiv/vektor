<?php

namespace Centamiv\Vektor\Tests\Integration;

use Centamiv\Vektor\Core\Config;
use Centamiv\Vektor\Services\Indexer;
use Centamiv\Vektor\Services\Searcher;
use PHPUnit\Framework\TestCase;

class FlowTest extends TestCase
{
    protected function setUp(): void
    {
        foreach ([Config::getVectorFile(), Config::getGraphFile(), Config::getMetaFile(), Config::getLockFile()] as $file) {
            if (file_exists($file)) unlink($file);
        }
    }

    protected function tearDown(): void
    {
        foreach ([Config::getVectorFile(), Config::getGraphFile(), Config::getMetaFile(), Config::getLockFile()] as $file) {
            if (file_exists($file)) unlink($file);
        }
    }

    public function testEndToEndFlow(): void
    {
        $indexer = new Indexer();

        // Orthogonal Vectors
        $vecA = array_fill(0, 1536, 0.0);
        $vecA[0] = 1.0;
        $vecB = array_fill(0, 1536, 0.0);
        $vecB[1] = 1.0;
        $vecC = array_fill(0, 1536, 0.0);
        $vecC[2] = 1.0;

        $indexer->insert("A", $vecA);
        $indexer->insert("B", $vecB);
        $indexer->insert("C", $vecC);

        $searcher = new Searcher();

        // Search for A
        $results = $searcher->search($vecA, 2);

        $this->assertNotEmpty($results);
        $this->assertEquals("A", $results[0]['id']);
        $this->assertEqualsWithDelta(1.0, $results[0]['score'], 0.0001);

        // Verify Persistence (Simulated)
        unset($indexer, $searcher);
        $searcher2 = new Searcher();
        $results2 = $searcher2->search($vecB, 2);

        $this->assertEquals("B", $results2[0]['id']);
    }

    public function testDeleteFlow(): void
    {
        $indexer = new Indexer();

        $vecA = array_fill(0, 1536, 0.0);
        $vecA[0] = 1.0;

        $indexer->insert("del_test", $vecA);

        // 1. Verify existence
        $searcher = new Searcher();
        $results = $searcher->search($vecA, 1);
        $this->assertEquals("del_test", $results[0]['id']);

        // 2. Delete
        $success = $indexer->delete("del_test");
        $this->assertTrue($success, "Deletion failed");

        // 3. Verify gone (Search should return empty or not contain 'del_test')
        $results = $searcher->search($vecA, 1);
        // If it was the only item, results should be empty.
        $this->assertEmpty($results);

        // 4. Verify delete non-existent
        $this->assertFalse($indexer->delete("non_existent"));

        // 5. Re-insert
        $indexer->insert("del_test", $vecA);

        // 6. Verify back
        $results = $searcher->search($vecA, 1);
        $this->assertEquals("del_test", $results[0]['id'], "Re-insertion failed");
    }
}
