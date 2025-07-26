<?php

namespace Tests;
use Tests\CreatesApplication;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    // use CreatesApplication;

    // protected function setUp(): void
    // {
    //     parent::setUp();

    //     // Refresh the database for each test
    //     $this->artisan('migrate:fresh');

    //     // Enable foreign key constraints if using SQLite
    //     if (DB::connection()->getDriverName() === 'sqlite') {
    //         DB::statement('PRAGMA foreign_keys = ON;');
    //     }

    //     // If using PostgreSQL in tests (optional setup)
    //     if (DB::connection()->getDriverName() === 'pgsql') {
    //         DB::statement('SET session_replication_role = DEFAULT;');
    //     }
    // }
}
