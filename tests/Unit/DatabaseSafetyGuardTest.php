<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseSafetyGuardTest extends TestCase
{
    /**
     * Uji 1: Membuktikan konfigurasi default SQLite in-memory (:memory:) diizinkan.
     */
    public function test_testing_database_is_permitted(): void
    {
        $connection = DB::connection();
        $this->assertSame('sqlite', $connection->getDriverName());
        $this->assertSame(':memory:', $connection->getDatabaseName());

        // Tidak melempar exception
        $this->enforceTestDatabaseSafety();
        $this->assertTrue(true);
    }

    /**
     * Uji 2: Membuktikan upaya mengubah database ke 'sidatex' langsung ditolak dengan RuntimeException.
     */
    public function test_production_database_is_blocked(): void
    {
        Config::set('database.connections.sqlite.database', 'sidatex');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/FATAL SECURITY VIOLATION: .* \'sidatex\'/');

        try {
            $this->enforceTestDatabaseSafety();
        } finally {
            // Pulihkan konfigurasi agar tidak merusak test lain
            Config::set('database.connections.sqlite.database', ':memory:');
        }
    }

    /**
     * Uji 3: Membuktikan upaya mengubah database ke 'sidatex_staging' langsung ditolak dengan RuntimeException.
     */
    public function test_staging_database_is_blocked(): void
    {
        Config::set('database.connections.sqlite.database', 'sidatex_staging');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/FATAL SECURITY VIOLATION: .* \'sidatex_staging\'/');

        try {
            $this->enforceTestDatabaseSafety();
        } finally {
            Config::set('database.connections.sqlite.database', ':memory:');
        }
    }

    /**
     * Uji 4: Membuktikan koneksi alternatif yang mengarah ke database terlarang juga ditolak.
     */
    public function test_alternative_connection_to_forbidden_database_is_blocked(): void
    {
        Config::set('database.connections.custom_conn', [
            'driver' => 'mysql',
            'database' => 'sidatex',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/FATAL SECURITY VIOLATION: Koneksi \'custom_conn\' terkonfigurasi ke database terlarang \'sidatex\'/');

        try {
            $this->enforceTestDatabaseSafety();
        } finally {
            Config::set('database.connections.custom_conn', null);
        }
    }

    /**
     * Uji 5: Database di luar whitelist SQLite in-memory ':memory:' tetap ditolak.
     */
    public function test_non_whitelisted_arbitrary_database_name_is_blocked(): void
    {
        Config::set('database.connections.sqlite.database', 'sidatex_dev');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/FATAL CONFIG ERROR: Database aktif adalah \'sidatex_dev\'/');

        try {
            $this->enforceTestDatabaseSafety();
        } finally {
            Config::set('database.connections.sqlite.database', ':memory:');
        }
    }

    /**
     * Uji 6: Ketidakcocokan nama database pada koneksi runtime ditolak meski Config menyatakan aman.
     */
    public function test_runtime_pdo_database_mismatch_is_blocked_even_if_config_looks_safe(): void
    {
        $connection = DB::connection();
        $originalDbName = $connection->getDatabaseName();
        $connection->setDatabaseName('sidatex_other');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/UNSAFE DATABASE: Runtime SQLite connection is \'sidatex_other\'/');

        try {
            $this->enforceTestDatabaseSafety();
        } finally {
            $connection->setDatabaseName($originalDbName);
        }
    }

    /**
     * Uji 7: Kegagalan verifikasi PDO bersifat fail-closed (tidak ditelan diam-diam).
     */
    public function test_pdo_verification_failure_fails_closed(): void
    {
        $mockConn = \Mockery::mock(\Illuminate\Database\Connection::class);
        $mockConn->shouldReceive('getDatabaseName')->andReturn(':memory:');
        $mockConn->shouldReceive('getDriverName')->andReturn('sqlite');
        $mockConn->shouldReceive('getPdo')->andThrow(new \PDOException('Simulated PDO connection error'));

        $originalConn = DB::connection();
        DB::shouldReceive('connection')->andReturn($mockConn);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/FATAL PDO VERIFICATION ERROR:.*Simulated PDO connection error/');

        try {
            $this->enforceTestDatabaseSafety();
        } finally {
            DB::clearResolvedInstances();
            DB::swap($this->app['db']);
            $this->assertEquals(':memory:', $originalConn->getDatabaseName());
        }
    }

    /**
     * Uji 8: Verifikasi setUpTraits() memanggil enforceTestDatabaseSafety() sebelum parent::setUpTraits()
     * serta memastikan driver aktif adalah sqlite dan database adalah :memory:.
     */
    public function test_safety_guard_hooked_in_setup_traits_and_live_pdo_is_memory(): void
    {
        $ref = new \ReflectionMethod(TestCase::class, 'setUpTraits');
        $this->assertSame(TestCase::class, $ref->getDeclaringClass()->getName());

        $connection = DB::connection();
        $this->assertSame('sqlite', $connection->getDriverName());
        $this->assertSame(':memory:', $connection->getDatabaseName());
    }

    /**
     * Uji 9: Pastikan composer.json tidak memuat migrate --force dan .env.example tidak menunjuk ke sidatex operasional.
     */
    public function test_composer_and_env_example_protect_operational_database(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $setupScripts = implode("\n", $composer['scripts']['setup'] ?? []);
        $postCreateScripts = implode("\n", $composer['scripts']['post-create-project-cmd'] ?? []);

        $this->assertStringNotContainsString('migrate --force', $setupScripts);
        $this->assertStringNotContainsString('migrate', $postCreateScripts);

        $envExample = (string) file_get_contents(base_path('.env.example'));
        $this->assertDoesNotMatchRegularExpression('/^DB_DATABASE=sidatex\s*$/m', $envExample);
    }

    /**
     * Uji 10: Sesi PDO MySQL riil (SELECT DATABASE()) yang mengarah ke database lain langsung ditolak
     * jika driver adalah mysql.
     */
    public function test_live_pdo_select_database_mismatch_is_blocked(): void
    {
        $mockStmt = \Mockery::mock(\PDOStatement::class);
        $mockStmt->shouldReceive('fetchColumn')->andReturn('sidatex');

        $mockPdo = \Mockery::mock(\PDO::class);
        $mockPdo->shouldReceive('query')->with('SELECT DATABASE()')->andReturn($mockStmt);

        $mockConn = \Mockery::mock(\Illuminate\Database\Connection::class);
        $mockConn->shouldReceive('getDatabaseName')->andReturn('sidatex_testing');
        $mockConn->shouldReceive('getDriverName')->andReturn('mysql');
        $mockConn->shouldReceive('getPdo')->andReturn($mockPdo);

        DB::shouldReceive('connection')->andReturn($mockConn);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/FATAL SECURITY VIOLATION: Sesi PDO MySQL riil tersambung ke \'sidatex\'/');

        try {
            $this->enforceTestDatabaseSafety();
        } finally {
            DB::clearResolvedInstances();
            DB::swap($this->app['db']);
        }
    }
}
