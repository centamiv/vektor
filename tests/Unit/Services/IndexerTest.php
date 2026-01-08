<?php

namespace Centamiv\Vektor\Tests\Unit\Services;

use Centamiv\Vektor\Core\Config;
use Centamiv\Vektor\Services\Indexer;
use PHPUnit\Framework\TestCase;

class IndexerTest extends TestCase
{
    protected function setUp(): void
    {
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

    public function testGetStats(): void
    {
        $indexer = new Indexer();
        $stats = $indexer->getStats();

        $this->assertArrayHasKey('storage', $stats);
        $this->assertArrayHasKey('records', $stats);
        $this->assertArrayHasKey('config', $stats);

        $this->assertEquals(0, $stats['storage']['vector_file_bytes']);
        $this->assertEquals(0, $stats['records']['vectors_total']);

        // Insert one item
        $indexer->insert("stats_test", array_fill(0, 1536, 0.0));

        $stats = $indexer->getStats();
        $this->assertGreaterThan(0, $stats['storage']['vector_file_bytes']);
        $this->assertEquals(1, $stats['records']['vectors_total']);
    }
}
