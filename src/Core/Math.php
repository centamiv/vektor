<?php

namespace Centamiv\Vektor\Core;

use InvalidArgumentException;

final class Math
{
    /**
     * Calculates Cosine Similarity between two vectors.
     * 
     * @param array $v1
     * @param array $v2
     * @return float Similarity (0.0 to 1.0 mostly, can be negative)
     * @throws InvalidArgumentException
     */
    public static function cosineSimilarity(array $v1, array $v2): float
    {
        $count = count($v1);
        if ($count !== count($v2)) {
            throw new InvalidArgumentException("Vector dimensions do not match");
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $dot += $v1[$i] * $v2[$i];
            $normA += $v1[$i] * $v1[$i];
            $normB += $v2[$i] * $v2[$i];
        }

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
