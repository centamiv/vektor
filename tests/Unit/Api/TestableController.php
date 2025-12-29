<?php

namespace Centamiv\Vektor\Tests\Unit\Api;

use Centamiv\Vektor\Api\Controller;

class TestableController extends Controller
{
    public static $mockInput = null;

    protected function getJsonInput(): array
    {
        if (self::$mockInput !== null) {
            return self::$mockInput;
        }
        return parent::getJsonInput();
    }
}
