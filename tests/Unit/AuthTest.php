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
        $this->assertNull(Config::getApiToken('/non/existent/path/.env'));
    }

    public function testGetApiTokenReturnsValue()
    {
        file_put_contents($this->tempEnvFile, 'VEKTOR_API_TOKEN=test-token');
        $this->assertEquals('test-token', Config::getApiToken($this->tempEnvFile));
    }

    public function testGetApiTokenIgnoresComments()
    {
        $content = "# Comment\nVEKTOR_API_TOKEN=valid-token\n# Another";
        file_put_contents($this->tempEnvFile, $content);
        $this->assertEquals('valid-token', Config::getApiToken($this->tempEnvFile));
    }

    public function testGetApiTokenUnquotes()
    {
        file_put_contents($this->tempEnvFile, 'VEKTOR_API_TOKEN="quoted-token"');
        $this->assertEquals('quoted-token', Config::getApiToken($this->tempEnvFile));
    }
}
