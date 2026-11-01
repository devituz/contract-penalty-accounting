<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Docker'da ENV $_SERVER ga tushadi va .env dan ustun turadi: testlar haqiqiy bazaga tegmasligi kerak. */
class TestEnvironmentTest extends TestCase
{
    public function test_suite_runs_in_testing_mode_on_an_in_memory_database(): void
    {
        $this->assertTrue($this->app->environment('testing'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('array', config('session.driver'));
    }
}
