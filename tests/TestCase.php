<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $application = parent::createApplication();

        if (! $application->environment('testing')) {
            throw new RuntimeException('Pengujian wajib berjalan dengan APP_ENV=testing. Bersihkan config cache sebelum mencoba lagi.');
        }

        $connection = $application->make('db')->connection();

        if (env('REQUIRE_MYSQL_CONNECTION', false) && $connection->getDriverName() !== 'mysql') {
            throw new RuntimeException('Konfigurasi pengujian MySQL wajib memakai driver mysql.');
        }

        if ($connection->getDriverName() !== 'sqlite' && ! str_ends_with($connection->getDatabaseName(), '_testing')) {
            throw new RuntimeException('Pengujian database harus memakai database terpisah dengan akhiran _testing.');
        }

        return $application;
    }
}
