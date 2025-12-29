<?php

namespace Centamiv\Vektor\Storage\Binary;

use Centamiv\Vektor\Core\Config;
use RuntimeException;

class MetaFile
{
    /** @var resource */
    private $handle;

    public function __construct(?string $filePath = null)
    {
        $path = $filePath ?? Config::META_FILE;
        if (!file_exists($path)) {
            touch($path);
        }
        $this->handle = fopen($path, 'r+b');
    }

    public function __destruct()
    {
        if ($this->handle) {
            fclose($this->handle);
        }
    }

    /**
     * Finds the Internal ID for an External ID.
     * @return int|null Internal ID or null if not found.
     */
    public function find(string $externalId): ?int
    {
        fseek($this->handle, 0, SEEK_END);
        if (ftell($this->handle) === 0) {
            return null;
        }

        $currentNodeIdx = 0;

        while ($currentNodeIdx !== -1) {
            fseek($this->handle, $currentNodeIdx * Config::META_ROW_SIZE);
            $data = fread($this->handle, Config::META_ROW_SIZE);

            // Unpack: Key(36), Val(4), Left(4), Right(4)
            // Use 'a36' for string, 'i' for ints
            $node = unpack('a36key/ival/ileft/iright', $data);
            $key = rtrim($node['key'], "\0");

            $cmp = strcmp($externalId, $key);

            if ($cmp === 0) {
                return $node['val'];
            } elseif ($cmp < 0) {
                $currentNodeIdx = $node['left'];
            } else {
                $currentNodeIdx = $node['right'];
            }
        }

        return null;
    }

    /**
     * Inserts a new mapping into the BST.
     * 
     * @param string $externalId
     * @param int $internalId
     */
    public function insert(string $externalId, int $internalId): void
    {
        // Pad Key
        $paddedKey = str_pad($externalId, 36, "\0");
        $newNodeBin = pack('a36iii', $paddedKey, $internalId, -1, -1);

        fseek($this->handle, 0, SEEK_END);
        $fileSize = ftell($this->handle);
        $newNodeIdx = $fileSize / Config::META_ROW_SIZE;

        // If file is empty, just write root
        if ($fileSize === 0) {
            fwrite($this->handle, $newNodeBin);
            return;
        }

        // Append new node
        fwrite($this->handle, $newNodeBin);

        // Traverse to find parent to link
        $currentNodeIdx = 0;

        while (true) {
            fseek($this->handle, $currentNodeIdx * Config::META_ROW_SIZE);
            $data = fread($this->handle, Config::META_ROW_SIZE);
            $node = unpack('a36key/ival/ileft/iright', $data);
            $key = rtrim($node['key'], "\0");

            $cmp = strcmp($externalId, $key);

            if ($cmp === 0) {
                return;
            } elseif ($cmp < 0) {
                if ($node['left'] === -1) {
                    // Link here
                    $this->updateLink($currentNodeIdx, 'left', $newNodeIdx);
                    return;
                }
                $currentNodeIdx = $node['left'];
            } else {
                if ($node['right'] === -1) {
                    // Link here
                    $this->updateLink($currentNodeIdx, 'right', $newNodeIdx);
                    return;
                }
                $currentNodeIdx = $node['right'];
            }
        }
    }

    /**
     * Updates the Internal ID for an existing External ID.
     * 
     * @param string $externalId
     * @param int $newInternalId
     * @return bool True if found and updated, False otherwise.
     */
    public function update(string $externalId, int $newInternalId): bool
    {
        fseek($this->handle, 0, SEEK_END);
        if (ftell($this->handle) === 0) {
            return false;
        }

        $currentNodeIdx = 0; // Root

        while ($currentNodeIdx !== -1) {
            fseek($this->handle, $currentNodeIdx * Config::META_ROW_SIZE);
            $data = fread($this->handle, Config::META_ROW_SIZE);
            $node = unpack('a36key/ival/ileft/iright', $data);
            $key = rtrim($node['key'], "\0");

            $cmp = strcmp($externalId, $key);

            if ($cmp === 0) {
                // Found, update Value (Offset 36)
                fseek($this->handle, ($currentNodeIdx * Config::META_ROW_SIZE) + 36);
                fwrite($this->handle, pack('i', $newInternalId));
                return true;
            } elseif ($cmp < 0) {
                $currentNodeIdx = $node['left'];
            } else {
                $currentNodeIdx = $node['right'];
            }
        }

        return false;
    }

    private function updateLink(int $nodeIdx, string $which, int $childIdx): void
    {
        // Key(36) + Val(4) + Left(4) + Right(4)
        // Left offset = 40, Right offset = 44
        $offset = ($nodeIdx * Config::META_ROW_SIZE) + ($which === 'left' ? 40 : 44);
        fseek($this->handle, $offset);
        fwrite($this->handle, pack('i', $childIdx));
    }
}
