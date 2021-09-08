<?php

namespace Tests;

class SamlUserTest extends TestCase
{
    public function tearDown(): void
    {
        \Mockery::close();
    }
}
