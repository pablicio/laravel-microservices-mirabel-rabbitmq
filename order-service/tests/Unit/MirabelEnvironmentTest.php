<?php

namespace Tests\Unit;

use App\Support\MirabelEnvironment;
use PHPUnit\Framework\TestCase;

final class MirabelEnvironmentTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('MB_TEST_HOST');
        putenv('MB_TEST_CONFIRMS');
        putenv('MB_TEST_MISSING');
    }

    public function testExportsConfigurationToTheProcessEnvironment(): void
    {
        MirabelEnvironment::export(['MB_TEST_HOST' => 'rabbitmq', 'MB_TEST_CONFIRMS' => true, 'MB_TEST_MISSING' => null]);

        $this->assertSame('rabbitmq', getenv('MB_TEST_HOST'));
        $this->assertSame('true', getenv('MB_TEST_CONFIRMS'));
        $this->assertFalse(getenv('MB_TEST_MISSING'));
    }

    public function testRealEnvironmentWins(): void
    {
        putenv('MB_TEST_HOST=from-docker');

        MirabelEnvironment::export(['MB_TEST_HOST' => 'from-config']);

        $this->assertSame('from-docker', getenv('MB_TEST_HOST'));
    }
}
