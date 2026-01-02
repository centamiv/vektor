<?php

namespace Centamiv\Vektor\Storage\Binary;

use Centamiv\Vektor\Core\Config;
use RuntimeException;
use InvalidArgumentException;

class VectorFile
{
    /** @var resource */
    private $handle;

    public function __construct(?string $filePath = null)
    {
        $path = $filePath ?? Config::getVectorFile();

        if (!file_exists($path)) {
            touch($path);
        }
        $this->handle = fopen($path, 'r+b');
        if (!$this->handle) {
            throw new RuntimeException("Could not open vector file: " . $path);
        }
    }

    public function __destruct()
    {
        if ($this->handle) {
            fclose($this->handle);
        }
    }

    /**
     * Appends a vector to the file.
     * 
     * @param string $externalId 36-char string (UUID or padded)
     * @param array $vector 1536 floats
     * @return int Internal ID (index of the vector)
     */
    public function append(string $externalId, array $vector): int
    {
        if (strlen($externalId) > 36) {
            throw new InvalidArgumentException("External ID must be <= 36 chars");
        }
        // Pad ID
        $paddedId = str_pad($externalId, 36, "\0");

        if (count($vector) !== Config::DIMENSION) {
            throw new InvalidArgumentException("Vector must have " . Config::DIMENSION . " dimensions");
        }

        // Seek to end to get the new index
        fseek($this->handle, 0, SEEK_END);
        $fileSize = ftell($this->handle);
        $internalId = $fileSize / Config::VECTOR_ROW_SIZE;

        // Verify alignment
        if ($fileSize % Config::VECTOR_ROW_SIZE !== 0) {
            throw new RuntimeException("Vector file corrupted. Size is not multiple of row size.");
        }

        // Write Flag (Active = 0x00)
        fwrite($this->handle, pack('C', 0));

        // Write External ID
        fwrite($this->handle, pack('a36', $paddedId));

        // Write Vector
        $binaryVector = pack('f*', ...$vector);
        fwrite($this->handle, $binaryVector);
        fflush($this->handle);

        return $internalId;
    }

    /**
     * Reads a vector by Internal ID.
     * 
     * @param int $internalId
     * @return array|null Returns ['id' => string, 'vector' => float[]] or null if deleted/invalid
     */
    public function read(int $internalId): ?array
    {
        $offset = $internalId * Config::VECTOR_ROW_SIZE;
        fseek($this->handle, 0, SEEK_END);
        if ($offset >= ftell($this->handle)) {
            return null;
        }

        fseek($this->handle, $offset);

        // Read Flag
        $flag = unpack('C', fread($this->handle, 1))[1];
        if ($flag !== 0) {
            return null; // Deleted
        }

        // Read External ID
        $idData = fread($this->handle, 36);
        $externalId = rtrim($idData, "\0"); // Remove padding

        // Read Vector
        $vectorData = fread($this->handle, Config::VECTOR_DATA_SIZE);
        $vector = array_values(unpack('f*', $vectorData));

        return [
            'id' => $externalId,
            'vector' => $vector
        ];
    }

    /**
     * Read ONLY the vector floats.
     */
    public function readVectorOnly(int $internalId): ?array
    {
        $offset = $internalId * Config::VECTOR_ROW_SIZE + 37; // Skip Flag(1) + ID(36)

        fseek($this->handle, 0, SEEK_END);
        if ($offset >= ftell($this->handle)) {
            return null;
        }

        fseek($this->handle, $offset);
        $vectorData = fread($this->handle, Config::VECTOR_DATA_SIZE);
        return array_values(unpack('f*', $vectorData));
    }
    /**
     * Marks a vector as deleted.
     * 
     * @param int $internalId
     */
    public function delete(int $internalId): void
    {
        $offset = $internalId * Config::VECTOR_ROW_SIZE;
        fseek($this->handle, 0, SEEK_END);
        if ($offset >= ftell($this->handle)) {
            return;
        }

        fseek($this->handle, $offset);
        // Write Flag = 1 (Deleted)
        fwrite($this->handle, pack('C', 1));
        fflush($this->handle);
    }

    /**
     * Yields all valid vectors in the file.
     * @return \Generator
     */
    public function scan(): \Generator
    {
        rewind($this->handle);
        $fileSize = fstat($this->handle)['size'];

        while (ftell($this->handle) < $fileSize) {
            $flagData = fread($this->handle, 1);
            if ($flagData === false || strlen($flagData) < 1) break;

            $flag = unpack('C', $flagData)[1];

            // Read External ID
            $idData = fread($this->handle, 36);
            $externalId = rtrim($idData, "\0");

            // Read Vector
            $vectorData = fread($this->handle, Config::VECTOR_DATA_SIZE);

            if ($flag === 0) {
                $vector = array_values(unpack('f*', $vectorData));
                yield ['id' => $externalId, 'vector' => $vector];
            }
        }
    }
}
