<?php

namespace Centamiv\Vektor\Services;

use Centamiv\Vektor\Core\Config;
use Centamiv\Vektor\Core\HnswLogic;
use Centamiv\Vektor\Storage\Binary\GraphFile;
use Centamiv\Vektor\Storage\Binary\VectorFile;

class Searcher
{
    private VectorFile $vectorFile;
    private GraphFile $graphFile;
    private HnswLogic $hnswLogic;
    /** @var resource|null */
    private $lockHandle = null;

    public function __construct()
    {
        $this->vectorFile = new VectorFile();
        $this->graphFile = new GraphFile();
        $this->hnswLogic = new HnswLogic($this->vectorFile, $this->graphFile);
    }

    /**
     * Executes a search query.
     * 
     * @param array $queryVector
     * @param int $k
     * @return array
     */
    public function search(array $queryVector, int $k = 10): array
    {
        $this->acquireLock();
        try {
            $results = $this->hnswLogic->search($queryVector, $k, 50);

            // Hydrate IDs
            $hydrated = [];
            foreach ($results as $res) {
                $data = $this->vectorFile->read($res['id']);
                if ($data) {
                    $hydrated[] = [
                        'id' => $data['id'],
                        'score' => $res['distance']
                    ];
                }
            }
            return $hydrated;
        } finally {
            $this->releaseLock();
        }
    }

    private function acquireLock()
    {
        $this->lockHandle = fopen(Config::LOCK_FILE, 'c');
        flock($this->lockHandle, LOCK_SH); // Shared for reading
    }

    private function releaseLock()
    {
        flock($this->lockHandle, LOCK_UN);
        fclose($this->lockHandle);
    }
}
