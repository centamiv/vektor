<?php

namespace Centamiv\Vektor\Tests\Integration;

use Centamiv\Vektor\Core\Config;
use Centamiv\Vektor\Services\Indexer;
use Centamiv\Vektor\Services\Optimizer;
use Centamiv\Vektor\Services\Searcher;
use PHPUnit\Framework\TestCase;

class OptimizerTest extends TestCase
{
    protected function setUp(): void
    {
        foreach ([Config::getVectorFile(), Config::getGraphFile(), Config::getMetaFile(), Config::getPayloadFile(), Config::getLockFile()] as $file) {
            if (file_exists($file)) unlink($file);
        }

        // Also cleanup backups and temps if any
        foreach (glob(Config::getDataDir() . '/*.bak') as $f) unlink($f);
        foreach (glob(Config::getDataDir() . '/*.tmp') as $f) unlink($f);
    }

    protected function tearDown(): void
    {
        foreach ([Config::getVectorFile(), Config::getGraphFile(), Config::getMetaFile(), Config::getPayloadFile(), Config::getLockFile()] as $file) {
            if (file_exists($file)) unlink($file);
        }
        foreach (glob(Config::getDataDir() . '/*.bak') as $f) unlink($f);
        foreach (glob(Config::getDataDir() . '/*.tmp') as $f) unlink($f);
    }

    public function testOptimizerVacuumsAndBalances()
    {
        $indexer = new Indexer();

        // Insert 3 vectors (using small dimension subset, filled with 0)
        // Create Orthogonal vectors
        $v1 = array_fill(0, Config::getDimensions(), 0.0);
        $v1[0] = 1.0;
        $v2 = array_fill(0, Config::getDimensions(), 0.0);
        $v2[1] = 1.0;
        $v3 = array_fill(0, Config::getDimensions(), 0.0);
        $v3[2] = 1.0;

        $indexer->insert('doc-1', $v1, ['source' => 'optimizer-test']);
        $indexer->insert('doc-2', $v2);
        $indexer->insert('doc-3', $v3);

        // Delete doc-2
        $indexer->delete('doc-2');

        // Check size before optimization
        $sizeBefore = filesize(Config::getVectorFile());
        // 3 rows.
        $this->assertEquals(Config::getVectorRowSize() * 3, $sizeBefore, "Initial size incorrect");

        // Release lock/handles by destroying indexer (implicit)
        unset($indexer);

        // Run Optimizer
        $optimizer = new Optimizer();
        $optimizer->run();

        // Check size after optimization
        clearstatcache();
        $sizeAfter = filesize(Config::getVectorFile());

        // Should be 2 rows
        $this->assertEquals(Config::getVectorRowSize() * 2, $sizeAfter, "Vector file size did not decrease");

        // Verify Search Logic
        $searcher = new Searcher();

        // Search for doc-1
        $results = $searcher->search($v1, 1, false, true);
        $this->assertCount(1, $results);
        $this->assertEquals('doc-1', $results[0]['id']);
        $this->assertEquals(['source' => 'optimizer-test'], $results[0]['metadata']);

        // Search for doc-3
        $results3 = $searcher->search($v3, 1);
        $this->assertCount(1, $results3);
        $this->assertEquals('doc-3', $results3[0]['id']);

        // Search for deleted doc-2 should NOT find 'doc-2'
        // It might find doc-3 or doc-1 as nearest neighbour
        $results2 = $searcher->search($v2, 1);
        if (count($results2) > 0) {
            $this->assertNotEquals('doc-2', $results2[0]['id']);
        } else {
            $this->assertEmpty($results2);
        }
    }
}
