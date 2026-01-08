<?php

namespace Centamiv\Vektor\Tests\Unit\Storage;

use Centamiv\Vektor\Core\Config;
use Centamiv\Vektor\Storage\Binary\MetaFile;
use PHPUnit\Framework\TestCase;

class MetaFileTest extends TestCase
{
    protected function setUp(): void
    {
        if (file_exists(Config::getMetaFile())) unlink(Config::getMetaFile());
    }

    protected function tearDown(): void
    {
        if (file_exists(Config::getMetaFile())) unlink(Config::getMetaFile());
    }

    public function testInsertAndFind(): void
    {
        $file = new MetaFile();
        $file->insert("alpha", 100);
        $file->insert("beta", 101);
        $file->insert("gamma", 102);

        $this->assertEquals(100, $file->find("alpha"));
        $this->assertEquals(101, $file->find("beta"));
        $this->assertEquals(102, $file->find("gamma"));
        $this->assertNull($file->find("omega"));

        unset($file);
    }

    public function testBSTStructure(): void
    {
        // Insert in order that creates a tree: M, A, Z
        $file = new MetaFile();
        $file->insert("M", 10);
        $file->insert("A", 1); // Left of M
        $file->insert("Z", 26); // Right of M

        $this->assertEquals(10, $file->find("M"));
        $this->assertEquals(1, $file->find("A"));
        $this->assertEquals(26, $file->find("Z"));

        unset($file);
    }

    public function testUpdate(): void
    {
        $file = new MetaFile();
        $file->insert("key1", 100);

        $this->assertEquals(100, $file->find("key1"));

        $updated = $file->update("key1", 999);
        $this->assertTrue($updated);
        $this->assertEquals(999, $file->find("key1"));

        $notUpdated = $file->update("non_existent", 999);
        $this->assertFalse($notUpdated);

        unset($file);
    }

    public function testFindEntryIncludesPayloadInfo(): void
    {
        $file = new MetaFile();
        $file->insert("payload", 5, 123, 456);

        $entry = $file->findEntry("payload");
        $this->assertEquals(5, $entry['id']);
        $this->assertEquals(123, $entry['payload_offset']);
        $this->assertEquals(456, $entry['payload_length']);

        unset($file);
    }
}
