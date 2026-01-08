<?php

namespace Centamiv\Vektor\Tests\Unit\Storage;

use Centamiv\Vektor\Core\Config;
use Centamiv\Vektor\Storage\Binary\PayloadFile;
use PHPUnit\Framework\TestCase;

class PayloadFileTest extends TestCase
{
    protected function setUp(): void
    {
        if (file_exists(Config::getPayloadFile())) unlink(Config::getPayloadFile());
    }

    protected function tearDown(): void
    {
        if (file_exists(Config::getPayloadFile())) unlink(Config::getPayloadFile());
    }

    public function testAppendAndRead(): void
    {
        $file = new PayloadFile();
        $metadata = ['source' => 'unit-test', 'chunk' => 1];

        $info = $file->append($metadata);
        $this->assertGreaterThanOrEqual(0, $info['offset']);
        $this->assertGreaterThan(0, $info['length']);

        $read = $file->read($info['offset'], $info['length']);
        $this->assertEquals($metadata, $read);

        unset($file);
    }
}
