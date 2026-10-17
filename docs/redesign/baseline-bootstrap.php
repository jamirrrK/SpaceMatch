<?php

// Evaluate the archived, unmodified Blade source with the unchanged backend.
// PHPUnit still enforces in-memory SQLite through the project phpunit.xml.
namespace {
    require __DIR__.'/../../vendor/autoload.php';
}

namespace Tests {
    abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
    {
        public function createApplication()
        {
            $app = parent::createApplication();
            $app['config']->set('view.paths', [__DIR__.'/baseline/resources/views']);
            $app['view']->getFinder()->setPaths([__DIR__.'/baseline/resources/views']);
            $app['view']->getFinder()->flush();

            return $app;
        }
    }
}
