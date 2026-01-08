<?php

namespace Centamiv\Vektor\Storage\Binary;

use Centamiv\Vektor\Core\Config;
use RuntimeException;

class PayloadFile
{
    /** @var resource */
    private $handle;

    public function __construct(?string $filePath = null)
    {
        $path = $filePath ?? Config::getPayloadFile();
        if (!file_exists($path)) {
            touch($path);
        }
        $this->handle = fopen($path, 'r+b');
        if (!$this->handle) {
            throw new RuntimeException("Could not open payload file: " . $path);
        }
    }

    public function __destruct()
    {
        if ($this->handle) {
            fclose($this->handle);
        }
    }

    /**
     * Appends metadata to the payload file.
     *
     * @param mixed $metadata
     * @return array{offset: int, length: int}
     */
    public function append(mixed $metadata): array
    {
        $json = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException("Failed to encode metadata to JSON.");
        }

        fseek($this->handle, 0, SEEK_END);
        $offset = ftell($this->handle);
        $length = strlen($json);

        fwrite($this->handle, $json);
        fflush($this->handle);

        return ['offset' => $offset, 'length' => $length];
    }

    /**
     * Reads metadata from the payload file.
     *
     * @param int $offset
     * @param int $length
     * @return mixed
     */
    public function read(int $offset, int $length): mixed
    {
        if ($offset < 0 || $length <= 0) {
            return null;
        }

        fseek($this->handle, 0, SEEK_END);
        if ($offset + $length > ftell($this->handle)) {
            return null;
        }

        fseek($this->handle, $offset);
        $data = fread($this->handle, $length);
        if ($data === false || strlen($data) < $length) {
            return null;
        }

        $decoded = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid metadata JSON payload.");
        }

        return $decoded;
    }
}
