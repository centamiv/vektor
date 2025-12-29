<?php

namespace Centamiv\Vektor\Tests\Unit\Core;

use Centamiv\Vektor\Core\Math;
use PHPUnit\Framework\TestCase;

class MathTest extends TestCase
{
    public function testCosineSimilarityPoints(): void
    {
        $v1 = [1.0, 0.0, 0.0];
        $v2 = [1.0, 0.0, 0.0];
        $this->assertEqualsWithDelta(1.0, Math::cosineSimilarity($v1, $v2), 0.0001);

        $v3 = [0.0, 1.0, 0.0];
        $this->assertEqualsWithDelta(0.0, Math::cosineSimilarity($v1, $v3), 0.0001);

        $v4 = [-1.0, 0.0, 0.0];
        $this->assertEqualsWithDelta(-1.0, Math::cosineSimilarity($v1, $v4), 0.0001);
    }

    public function testCosineSimilarityOrthogonalHighDim(): void
    {
        $dim = 1536;
        $v1 = array_fill(0, $dim, 0.0);
        $v1[0] = 1.0;
        $v2 = array_fill(0, $dim, 0.0);
        $v2[1] = 1.0;

        $this->assertEqualsWithDelta(0.0, Math::cosineSimilarity($v1, $v2), 0.0001);
    }
}
