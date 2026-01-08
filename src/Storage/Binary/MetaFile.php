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
        $path = $filePath ?? Config::getMetaFile();
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
        $entry = $this->findEntry($externalId);
        return $entry['id'] ?? null;
    }

    /**
     * Finds the Internal ID and payload info for an External ID.
     *
     * @return array{id: int, payload_offset: int, payload_length: int}|null
     */
    public function findEntry(string $externalId): ?array
    {
        fseek($this->handle, 0, SEEK_END);
        if (ftell($this->handle) === 0) {
            return null;
        }

        $currentNodeIdx = 0;

        while ($currentNodeIdx !== -1) {
            $node = $this->readNode($currentNodeIdx);
            $key = $node['key'];

            $cmp = strcmp($externalId, $key);

            if ($cmp === 0) {
                return [
                    'id' => $node['val'],
                    'payload_offset' => $node['payload_offset'],
                    'payload_length' => $node['payload_length'],
                ];
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
     * @param int $payloadOffset
     * @param int $payloadLength
     */
    public function insert(string $externalId, int $internalId, int $payloadOffset = -1, int $payloadLength = 0): void
    {
        // Pad Key
        $paddedKey = str_pad($externalId, 36, "\0");
        $newNodeBin = pack('a36lqlll', $paddedKey, $internalId, $payloadOffset, $payloadLength, -1, -1);

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
            $node = $this->readNode($currentNodeIdx);
            $key = $node['key'];

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
     * @param int|null $payloadOffset
     * @param int|null $payloadLength
     * @return bool True if found and updated, False otherwise.
     */
    public function update(
        string $externalId,
        int $newInternalId,
        ?int $payloadOffset = null,
        ?int $payloadLength = null
    ): bool
    {
        fseek($this->handle, 0, SEEK_END);
        if (ftell($this->handle) === 0) {
            return false;
        }

        $currentNodeIdx = 0; // Root

        while ($currentNodeIdx !== -1) {
            $node = $this->readNode($currentNodeIdx);
            $key = $node['key'];

            $cmp = strcmp($externalId, $key);

            if ($cmp === 0) {
                // Found, update Value (Offset 36)
                $baseOffset = $currentNodeIdx * Config::META_ROW_SIZE;
                fseek($this->handle, $baseOffset + 36);
                fwrite($this->handle, pack('l', $newInternalId));

                if ($payloadOffset !== null) {
                    fseek($this->handle, $baseOffset + 40);
                    fwrite($this->handle, pack('q', $payloadOffset));
                }
                if ($payloadLength !== null) {
                    fseek($this->handle, $baseOffset + 48);
                    fwrite($this->handle, pack('l', $payloadLength));
                }
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
        // Key(36) + Val(4) + PayloadOffset(8) + PayloadLength(4) + Left(4) + Right(4)
        // Left offset = 52, Right offset = 56
        $offset = ($nodeIdx * Config::META_ROW_SIZE) + ($which === 'left' ? 52 : 56);
        fseek($this->handle, $offset);
        fwrite($this->handle, pack('l', $childIdx));
    }

    private function readNode(int $nodeIdx): array
    {
        fseek($this->handle, $nodeIdx * Config::META_ROW_SIZE);
        $data = fread($this->handle, Config::META_ROW_SIZE);
        $node = unpack('a36key/lval/qoffset/llength/lleft/lright', $data);

        return [
            'key' => rtrim($node['key'], "\0"),
            'val' => $node['val'],
            'payload_offset' => $node['offset'],
            'payload_length' => $node['length'],
            'left' => $node['left'],
            'right' => $node['right'],
        ];
    }
}
