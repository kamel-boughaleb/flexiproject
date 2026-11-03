<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Navigateur francophone par défaut (Symfony envoie « en-us » sinon) : l'interface est alors en français.
        $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9');
    }
}
