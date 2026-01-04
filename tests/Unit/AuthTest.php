<?php

namespace Centamiv\Vektor\Tests\Unit;

use Centamiv\Vektor\Core\Config;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    private $tempEnvFile;

    protected function setUp(): void
    {
        // Create a temp file in the system temp directory
        $this->tempEnvFile = tempnam(sys_get_temp_dir(), 'vektor_test_env');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempEnvFile)) {
            unlink($this->tempEnvFile);
        }
    }

    public function testGetApiTokenReturnsNullWhenNoEnvFile()
    {
        // Pass a non-existent file path
        Config::setEnvFile('/non/existent/path/.env');
        $this->assertNull(Config::getApiToken());
    }

    public function testGetApiTokenReturnsValue()
    {
        file_put_contents($this->tempEnvFile, 'VEKTOR_API_TOKEN=test-token');
        Config::setEnvFile($this->tempEnvFile);
        $this->assertEquals('test-token', Config::getApiToken());
    }

    public function testGetApiTokenIgnoresComments()
    {
        $content = "# Comment\nVEKTOR_API_TOKEN=valid-token\n# Another";
        file_put_contents($this->tempEnvFile, $content);
        Config::setEnvFile($this->tempEnvFile);
        $this->assertEquals('valid-token', Config::getApiToken());
    }

    public function testGetApiTokenUnquotes()
    {
        file_put_contents($this->tempEnvFile, 'VEKTOR_API_TOKEN="quoted-token"');
        Config::setEnvFile($this->tempEnvFile);
        $this->assertEquals('quoted-token', Config::getApiToken());
    }
}
