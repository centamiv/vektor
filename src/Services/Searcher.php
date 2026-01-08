<?php

namespace Centamiv\Vektor\Services;

use Centamiv\Vektor\Core\Config;
use Centamiv\Vektor\Core\HnswLogic;
use Centamiv\Vektor\Storage\Binary\GraphFile;
use Centamiv\Vektor\Storage\Binary\MetaFile;
use Centamiv\Vektor\Storage\Binary\PayloadFile;
use Centamiv\Vektor\Storage\Binary\VectorFile;

class Searcher
{
    private VectorFile $vectorFile;
    private GraphFile $graphFile;
    private MetaFile $metaFile;
    private PayloadFile $payloadFile;
    private HnswLogic $hnswLogic;
    /** @var resource|null */
    private $lockHandle = null;

    public function __construct()
    {
        $this->vectorFile = new VectorFile();
        $this->graphFile = new GraphFile();
        $this->metaFile = new MetaFile();
        $this->payloadFile = new PayloadFile();
        $this->hnswLogic = new HnswLogic($this->vectorFile, $this->graphFile);
    }

    /**
     * Executes a search query.
     * 
     * @param list<float> $queryVector
     * @param int $k
     * @param bool $includeVector
     * @param bool $includeMetadata
     * @return list<array{id: string, vector?: list<float>, metadata?: mixed, score: float}>
     */
    public function search(
        array $queryVector,
        int $k = 10,
        bool $includeVector = false,
        bool $includeMetadata = false
    ): array
    {
        $this->acquireLock();
        try {
            // Oversample to handle soft deletes
            $searchK = $k + 20; // Heuristic buffer
            $ef = max($searchK, 50);

            $results = $this->hnswLogic->search($queryVector, $searchK, $ef);

            // Hydrate IDs
            $hydrated = [];
            foreach ($results as $res) {
                $data = $this->vectorFile->read($res['id']);
                if ($data) {
                    $metadata = null;
                    if ($includeMetadata) {
                        $metadata = $this->getMetadata($data['id']);
                    }

                    $hydrated[] = [
                        'id' => $data['id'],
                        'score' => $res['distance']
                    ] + (
                        $includeVector ? ['vector' => $data['vector']] : []
                    ) + (
                        $includeMetadata ? ['metadata' => $metadata] : []
                    );
                }
                if (count($hydrated) >= $k) {
                    break;
                }
            }
            return $hydrated;
        } finally {
            $this->releaseLock();
        }
    }

    private function acquireLock()
    {
        $this->lockHandle = fopen(Config::getLockFile(), 'c');
        flock($this->lockHandle, LOCK_SH); // Shared for reading
    }

    private function releaseLock()
    {
        flock($this->lockHandle, LOCK_UN);
        fclose($this->lockHandle);
    }

    private function getMetadata(string $externalId): mixed
    {
        $entry = $this->metaFile->findEntry($externalId);
        if (!$entry || $entry['payload_length'] <= 0) {
            return null;
        }

        return $this->payloadFile->read($entry['payload_offset'], $entry['payload_length']);
    }
}
