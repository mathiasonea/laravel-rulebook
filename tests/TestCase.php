<?php

namespace MathiasOnea\Rulebook\Tests;

use MathiasOnea\Rulebook\Providers\RulebookServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    /** @return list<class-string> */
    protected function getPackageProviders($app)
    {
        return [
            RulebookServiceProvider::class,
        ];
    }
}
