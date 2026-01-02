<?php

namespace Centamiv\Vektor\Tests\Unit\Storage;

use Centamiv\Vektor\Core\Config;
use Centamiv\Vektor\Storage\Binary\VectorFile;
use PHPUnit\Framework\TestCase;

class VectorFileTest extends TestCase
{
    protected function setUp(): void
    {
        if (file_exists(Config::getVectorFile())) unlink(Config::getVectorFile());
    }

    protected function tearDown(): void
    {
        if (file_exists(Config::getVectorFile())) unlink(Config::getVectorFile());
    }

    public function testAppendAndRead(): void
    {
        $file = new VectorFile();
        $vec = array_fill(0, 1536, 0.5);
        $id = "test-uuid-1";

        $internalId = $file->append($id, $vec);
        $this->assertEquals(0, $internalId);

        // Verify Data
        $data = $file->read($internalId);
        $this->assertEquals($id, $data['id']);
        $this->assertEqualsWithDelta(0.5, $data['vector'][0], 0.0001);

        unset($file);
    }

    public function testFileIntegrity(): void
    {
        $file = new VectorFile();
        $vec = array_fill(0, 1536, 0.0);
        $file->append("A", $vec);

        $this->assertEquals(6181, filesize(Config::getVectorFile()));

        $file->append("B", $vec);
        $this->assertEquals(12362, filesize(Config::getVectorFile()));

        unset($file);
    }

    public function testDelete(): void
    {
        $file = new VectorFile();
        $vec = array_fill(0, 1536, 0.1);
        $id = $file->append("del", $vec);

        $this->assertNotNull($file->read($id));

        $file->delete($id);

        $this->assertNull($file->read($id));

        unset($file);
    }
}
