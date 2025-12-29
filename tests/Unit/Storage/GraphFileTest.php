<?php

namespace Centamiv\Vektor\Tests\Unit\Storage;

use Centamiv\Vektor\Core\Config;
use Centamiv\Vektor\Storage\Binary\GraphFile;
use PHPUnit\Framework\TestCase;

class GraphFileTest extends TestCase
{
    protected function setUp(): void
    {
        if (file_exists(Config::GRAPH_FILE)) unlink(Config::GRAPH_FILE);
    }

    protected function tearDown(): void
    {
        if (file_exists(Config::GRAPH_FILE)) unlink(Config::GRAPH_FILE);
    }

    public function testHeaderInitialization(): void
    {
        $file = new GraphFile();
        $header = $file->readHeader();
        $this->assertEquals([-1, 0], $header);

        unset($file);
    }

    public function testNodeCreation(): void
    {
        $file = new GraphFile();
        $file->createNode(0, 3); // InternalID 0, MaxLevel 3

        $node = $file->readNode(0);
        $this->assertEquals(3, $node['maxLevel']);
        $this->assertEmpty($node['connections'][0]);

        unset($file);
    }

    public function testUpdateLinks(): void
    {
        $file = new GraphFile();
        $file->createNode(0, 1);

        $links = [1, 2, 3];
        $file->updateLinks(0, 0, $links);

        $node = $file->readNode(0);
        $this->assertEquals($links, $node['connections'][0]);

        unset($file);
    }
}
