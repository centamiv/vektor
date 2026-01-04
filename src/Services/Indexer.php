<?php

namespace Centamiv\Vektor\Services;

use Centamiv\Vektor\Core\Config;
use Centamiv\Vektor\Core\HnswLogic;
use Centamiv\Vektor\Core\Math;
use Centamiv\Vektor\Storage\Binary\GraphFile;
use Centamiv\Vektor\Storage\Binary\MetaFile;
use Centamiv\Vektor\Storage\Binary\VectorFile;
use Random\Randomizer;
use RuntimeException;

class Indexer
{
    private VectorFile $vectorFile;
    private GraphFile $graphFile;
    private MetaFile $metaFile;
    private HnswLogic $hnswLogic;
    /** @var resource|null */
    private $lockHandle = null;

    public function __construct(
        ?VectorFile $vectorFile = null,
        ?GraphFile $graphFile = null,
        ?MetaFile $metaFile = null
    ) {
        $this->vectorFile = $vectorFile ?? new VectorFile();
        $this->graphFile = $graphFile ?? new GraphFile();
        $this->metaFile = $metaFile ?? new MetaFile();
        $this->hnswLogic = new HnswLogic($this->vectorFile, $this->graphFile);
    }

    /**
     * Inserts a new vector into the database.
     * 
     * @param string $externalId
     * @param list<float> $vector
     * @throws RuntimeException
     */
    public function insert(string $externalId, array $vector): void
    {
        $this->acquireLock();
        try {
            // Check existence
            $existingId = $this->metaFile->find($externalId);
            $isUpdate = false;

            if ($existingId !== null) {
                if ($existingId === -1) {
                    // Previously deleted, overwrite mapping
                    $isUpdate = true;
                } else {
                    throw new RuntimeException("Duplicate ID: $externalId");
                }
            }

            // Append Vector
            $internalId = $this->vectorFile->append($externalId, $vector);

            if ($isUpdate) {
                $this->metaFile->update($externalId, $internalId);
            } else {
                $this->metaFile->insert($externalId, $internalId);
            }

            // Determine Level
            $level = $this->getRandomLevel();

            // Read Graph Header
            $header = $this->graphFile->readHeader();
            $entryPoint = $header[0];
            $maxLevel = -1;

            if ($entryPoint !== -1) {
                $node = $this->graphFile->readNode($entryPoint);
                $maxLevel = $node['maxLevel'];
            }

            // Create Node
            $this->graphFile->createNode($internalId, $level);

            // First node
            if ($entryPoint === -1) {
                $this->graphFile->writeHeader($internalId, 1);
                return;
            }

            // Insert logic
            $currObj = $entryPoint;
            $currDist = Math::cosineSimilarity($vector, $this->vectorFile->readVectorOnly($currObj));

            for ($lc = $maxLevel; $lc > $level; $lc--) {
                while (true) {
                    $changed = false;
                    $node = $this->graphFile->readNode($currObj);
                    $neighbors = $node['connections'][$lc] ?? [];
                    foreach ($neighbors as $neighborId) {
                        $dist = Math::cosineSimilarity($vector, $this->vectorFile->readVectorOnly($neighborId));
                        if ($dist > $currDist) {
                            $currDist = $dist;
                            $currObj = $neighborId;
                            $changed = true;
                        }
                    }
                    if (!$changed) break;
                }
            }

            // Connect on all levels <= $level
            for ($lc = min($level, $maxLevel); $lc >= 0; $lc--) {
                // Determine candidates
                $candidates = $this->hnswLogic->searchLayer($currObj, $vector, 100, $lc, Config::M);

                $neighborIds = array_column($candidates, 'id');

                // Limit neighbors
                $maxM = ($lc === 0) ? Config::M0 : Config::M;
                $selectedNeighbors = array_slice($neighborIds, 0, $maxM);

                // Write links for Current Node
                $this->graphFile->updateLinks($internalId, $lc, $selectedNeighbors);

                // Bi-directional connections
                foreach ($selectedNeighbors as $neighbor) {
                    $nNode = $this->graphFile->readNode($neighbor);
                    $nLinks = $nNode['connections'][$lc];
                    $nLinks[] = $internalId;

                    // Shrink connections if overflow
                    if (count($nLinks) > $maxM) {
                        $nVec = $this->vectorFile->readVectorOnly($neighbor);

                        $sorted = [];
                        foreach ($nLinks as $linkId) {
                            $lVec = $this->vectorFile->readVectorOnly($linkId);
                            $sorted[$linkId] = Math::cosineSimilarity($nVec, $lVec);
                        }
                        arsort($sorted); // Best first
                        $nLinks = array_slice(array_keys($sorted), 0, $maxM);
                    }

                    $this->graphFile->updateLinks($neighbor, $lc, $nLinks);
                }

                $currObj = $selectedNeighbors[0] ?? $currObj;
            }

            // Update Entry Point
            if ($level > $maxLevel) {
                $this->graphFile->writeHeader($internalId, $header[1] + 1);
            }
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Deletes a document by External ID.
     * 
     * @param string $externalId
     * @return bool True if deleted, False if not found.
     */
    public function delete(string $externalId): bool
    {
        $this->acquireLock();
        try {
            $internalId = $this->metaFile->find($externalId);

            if ($internalId === null || $internalId === -1) {
                return false;
            }

            // Soft delete in Vector File
            $this->vectorFile->delete($internalId);

            // Update Meta mapping to -1
            $this->metaFile->update($externalId, -1);

            return true;
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Retrieves database statistics.
     * 
     * @return array{storage: array{vector_file_bytes: int, graph_file_bytes: int, meta_file_bytes: int}, records: array{vectors_total: int, meta_entries: int, graph_nodes: int}, config: array{dimension: int, hnsw_m: int, hnsw_ef_construction: int, max_levels: int}}
     */
    public function getStats(): array
    {
        // Clear cache to get fresh stats
        clearstatcache(true, Config::getVectorFile());
        clearstatcache(true, Config::getGraphFile());
        clearstatcache(true, Config::getMetaFile());

        $vSize = file_exists(Config::getVectorFile()) ? filesize(Config::getVectorFile()) : 0;
        $mSize = file_exists(Config::getMetaFile()) ? filesize(Config::getMetaFile()) : 0;
        $gSize = file_exists(Config::getGraphFile()) ? filesize(Config::getGraphFile()) : 0;

        // Read Graph Header
        $this->acquireLock();
        try {
            $gHeader = $this->graphFile->readHeader(); // [entry, totalNodes]
        } catch (\Exception $e) {
            $gHeader = [-1, 0];
        } finally {
            $this->releaseLock();
        }

        return [
            'storage' => [
                'vector_file_bytes' => $vSize,
                'graph_file_bytes' => $gSize,
                'meta_file_bytes' => $mSize,
            ],
            'records' => [
                'vectors_total' => $vSize > 0 ? floor($vSize / Config::getVectorRowSize()) : 0,
                'meta_entries' => $mSize > 0 ? floor($mSize / Config::META_ROW_SIZE) : 0,
                'graph_nodes' => $gHeader[1] ?? 0,
            ],
            'config' => [
                'dimension' => Config::getDimensions(),
                'hnsw_m' => Config::M,
                'hnsw_ef_construction' => Config::M,
                'max_levels' => Config::L,
            ]
        ];
    }

    private function getRandomLevel(): int
    {
        $randomizer = new Randomizer();
        $level = 0;
        // -ln(uniform) * mL can also be used for geometric distribution, but keeping original logic with Randomizer
        while ($randomizer->getFloat(0, 1) < 0.5 && $level < Config::L - 1) {
            $level++;
        }
        return $level;
    }

    protected function acquireLock()
    {
        $this->lockHandle = fopen(Config::getLockFile(), 'c');
        flock($this->lockHandle, LOCK_EX); // Exclusive for writing
    }

    protected function releaseLock()
    {
        flock($this->lockHandle, LOCK_UN);
        fclose($this->lockHandle);
    }
}
