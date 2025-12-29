<?php

namespace Centamiv\Vektor\Storage\Binary;

use Centamiv\Vektor\Core\Config;
use RuntimeException;

class GraphFile
{
    /** @var resource */
    private $handle;

    public function __construct()
    {
        $exists = file_exists(Config::GRAPH_FILE);
        if (!$exists) {
            touch(Config::GRAPH_FILE);
        }
        $this->handle = fopen(Config::GRAPH_FILE, 'r+b');
        if (!$this->handle) {
            throw new RuntimeException("Could not open graph file");
        }

        clearstatcache(true, Config::GRAPH_FILE);
        // Initialize Header if new
        if (!$exists || filesize(Config::GRAPH_FILE) === 0) {
            $this->writeHeader(-1, 0);
        }
    }

    public function __destruct()
    {
        if ($this->handle) {
            fclose($this->handle);
        }
    }

    public function writeHeader(int $entryPointId, int $totalNodes): void
    {
        fseek($this->handle, 0);
        $bin = pack('ll', $entryPointId, $totalNodes);
        fwrite($this->handle, $bin);
        fflush($this->handle);
    }

    public function readHeader(): array
    {
        fseek($this->handle, 0);
        $data = fread($this->handle, 8);
        return array_values(unpack('lentry/ltotal', $data)); // [0 => entryId, 1 => totalNodes]
    }

    /**
     * Creates a new node in the graph.
     * 
     * @param int $internalId
     * @param int $maxLevel
     */
    public function createNode(int $internalId, int $maxLevel): void
    {
        $offset = Config::GRAPH_HEADER_SIZE + ($internalId * Config::GRAPH_NODE_SIZE);

        fseek($this->handle, 0, SEEK_END);
        $currentSize = ftell($this->handle);

        if ($offset > $currentSize) {
            fseek($this->handle, $offset);
        } else {
            fseek($this->handle, $offset);
        }

        // Max Level
        fwrite($this->handle, pack('C', $maxLevel));

        // Initialize Links for all levels to -1
        $l0 = pack('l*', ...array_fill(0, 32, -1));
        $l1 = pack('l*', ...array_fill(0, 16, -1));
        $l2 = pack('l*', ...array_fill(0, 16, -1));
        $l3 = pack('l*', ...array_fill(0, 16, -1));

        fwrite($this->handle, $l0 . $l1 . $l2 . $l3);

        // Update Total Nodes count
        $header = $this->readHeader();
        $this->writeHeader($header[0], $header[1] + 1);
        fflush($this->handle);
    }

    public function updateLinks(int $internalId, int $level, array $links): void
    {
        $offset = Config::GRAPH_HEADER_SIZE + ($internalId * Config::GRAPH_NODE_SIZE) + 1; // +1 for MaxLevel

        $maxSlots = ($level === 0) ? 32 : 16;

        // Calculate offset for specific level
        if ($level > 0) $offset += 128; // Skip L0 (32 * 4)
        if ($level > 1) $offset += 64;  // Skip L1 (16 * 4)
        if ($level > 2) $offset += 64;  // Skip L2 (16 * 4)

        // Pad with -1
        $padded = array_pad($links, $maxSlots, -1);
        $padded = array_slice($padded, 0, $maxSlots);

        fseek($this->handle, $offset);
        fwrite($this->handle, pack('l*', ...$padded));
        fflush($this->handle);
    }

    /**
     * Reads a node structure.
     * @return array {maxLevel: int, l0: int[], l1: int[], ...}
     */
    public function readNode(int $internalId): array
    {
        $offset = Config::GRAPH_HEADER_SIZE + ($internalId * Config::GRAPH_NODE_SIZE);
        fseek($this->handle, 0, SEEK_END);
        if ($offset >= ftell($this->handle)) {
            throw new RuntimeException("Node $internalId not found in graph");
        }

        fseek($this->handle, $offset);

        $maxLevel = unpack('C', fread($this->handle, 1))[1];

        $l0 = array_values(unpack('l*', fread($this->handle, 128)));
        $l1 = array_values(unpack('l*', fread($this->handle, 64)));
        $l2 = array_values(unpack('l*', fread($this->handle, 64)));
        $l3 = array_values(unpack('l*', fread($this->handle, 64)));

        $clean = function ($arr) {
            return array_values(array_filter($arr, fn($x) => $x !== -1));
        };

        return [
            'maxLevel' => $maxLevel,
            'connections' => [
                0 => $clean($l0),
                1 => $clean($l1),
                2 => $clean($l2),
                3 => $clean($l3),
            ]
        ];
    }
}
