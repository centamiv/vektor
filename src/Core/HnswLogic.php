<?php

namespace Centamiv\Vektor\Core;

use Centamiv\Vektor\Storage\Binary\GraphFile;
use Centamiv\Vektor\Storage\Binary\VectorFile;
use SplPriorityQueue;

class HnswLogic
{
    private VectorFile $vectorFile;
    private GraphFile $graphFile;
    private array $cache = []; // Request-level vector cache

    public function __construct(VectorFile $vectorFile, GraphFile $graphFile)
    {
        $this->vectorFile = $vectorFile;
        $this->graphFile = $graphFile;
    }

    private function getVector(int $id): array
    {
        if (!isset($this->cache[$id])) {
            $this->cache[$id] = $this->vectorFile->readVectorOnly($id);
        }
        return $this->cache[$id];
    }

    /**
     * Search for K nearest neighbors.
     * 
     * @param array $queryVector The query vector (1536 floats)
     * @param int $k Number of neighbors to return
     * @param int $ef Size of the dynamic candidate list
     * @return array Array of ['id' => int, 'distance' => float]
     */
    public function search(array $queryVector, int $k, int $ef): array
    {
        $header = $this->graphFile->readHeader();
        $entryPoint = $header[0];

        if ($entryPoint === -1) {
            return [];
        }

        // Zoom in from Top Level to Level 1
        $nodeData = $this->graphFile->readNode($entryPoint);
        $maxLevel = $nodeData['maxLevel'];

        $currObj = $entryPoint;
        $currDist = Math::cosineSimilarity($queryVector, $this->getVector($currObj));

        for ($lc = $maxLevel; $lc >= 1; $lc--) {
            while (true) {
                $changed = false;
                $node = $this->graphFile->readNode($currObj);
                $neighbors = $node['connections'][$lc] ?? [];

                foreach ($neighbors as $neighborId) {
                    $dist = Math::cosineSimilarity($queryVector, $this->getVector($neighborId));
                    if ($dist > $currDist) {
                        $currDist = $dist;
                        $currObj = $neighborId;
                        $changed = true;
                    }
                }
                if (!$changed) break;
            }
        }

        // Search Level 0 with ef
        return $this->searchLayer($currObj, $queryVector, $ef, 0, $k);
    }

    /**
     * Layer Search for insertion or final level search.
     * 
     * @param int $entryPoint
     * @param array $queryVector
     * @param int $ef Candidate list size
     * @param int $level Current graph level
     * @param int|null $k Final result count limit (optional)
     * @return array
     */
    public function searchLayer(int $entryPoint, array $queryVector, int $ef, int $level, ?int $k = null): array
    {
        $visited = [$entryPoint => true];
        $candidates = new SplPriorityQueue(); // Max Heap (priority = similarity)

        $entrySim = Math::cosineSimilarity($queryVector, $this->getVector($entryPoint));
        $candidates->insert($entryPoint, $entrySim);

        $W = [$entryPoint => $entrySim]; // Found nearest candidates

        while (!$candidates->isEmpty()) {
            $c = $candidates->extract(); // Best candidate

            // Find worst in W
            asort($W);
            $worstId = array_key_first($W);
            $worstSim = $W[$worstId];

            $cSim = Math::cosineSimilarity($queryVector, $this->getVector($c));

            if ($cSim < $worstSim && count($W) >= $ef) {
                break;
            }

            $node = $this->graphFile->readNode($c);
            $neighbors = $node['connections'][$level] ?? [];

            foreach ($neighbors as $neighborId) {
                if (!isset($visited[$neighborId])) {
                    $visited[$neighborId] = true;
                    $sim = Math::cosineSimilarity($queryVector, $this->getVector($neighborId));

                    if ($sim > $worstSim || count($W) < $ef) {
                        $candidates->insert($neighborId, $sim);
                        $W[$neighborId] = $sim;

                        if (count($W) > $ef) {
                            asort($W);
                            $idToRemove = array_key_first($W);
                            unset($W[$idToRemove]);

                            asort($W);
                            $worstSim = $W[array_key_first($W)];
                        }
                    }
                }
            }
        }

        // Format results
        arsort($W); // Best first
        $finalParams = [];
        $count = 0;
        foreach ($W as $id => $sim) {
            $finalParams[] = ['id' => $id, 'distance' => $sim];
            $count++;
            if ($k !== null && $count >= $k) break;
        }
        return $finalParams;
    }
}
