<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected static ?\PDO $sharedSqlitePdo = null;

    /**
     * Safety Guard Mutlak:
     * Berjalan tepat sebelum parent::setUpTraits() agar trait seperti RefreshDatabase
     * atau DatabaseTransactions tidak pernah mengeksekusi query pada database selain sidatex_testing atau SQLite :memory:.
     */
    protected function setUpTraits(): array
    {
        $this->enforceTestDatabaseSafety();
        $this->initializeSqliteInMemory();

        return parent::setUpTraits();
    }

    protected function initializeSqliteInMemory(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite' && Config::get('database.connections.sqlite.database') === ':memory:') {
            if (self::$sharedSqlitePdo === null) {
                \Illuminate\Support\Facades\Artisan::call('migrate');

                // Muat Master Data referensi lengkap ke SQLite in-memory jika tersedia
                $masterDbPath = base_path('database/seeders/data/testing_master.sqlite');
                if (!file_exists($masterDbPath)) {
                    $masterDbPath = storage_path('framework/cache/testing_master.sqlite');
                }
                if (file_exists($masterDbPath)) {
                    $rawPdo = DB::connection()->getPdo();
                    $normPath = str_replace('\\', '/', $masterDbPath);
                    $rawPdo->exec("PRAGMA foreign_keys = OFF");
                    $rawPdo->exec("ATTACH DATABASE 'file:{$normPath}?mode=ro' AS disk_ref");

                    $refTables = [
                        'roles',
                        'users',
                        'master_provinsi',
                        'master_kabkota',
                        'master_negara',
                        'master_pelabuhan',
                        'master_hs',
                        'master_hs_mappings',
                        'master_wilayah_bps',
                        'data_batches',
                        'trash',
                        'comments',
                        'brs_indicator_summaries',
                        'monthly_origin_trade_summaries',
                        'monthly_trade_summaries',
                        'distribusi_transactions',
                        'neraca_transactions',
                    ];
                    foreach ($refTables as $tbl) {
                        try {
                            $targetCols = $rawPdo->query("PRAGMA table_info(`{$tbl}`)")->fetchAll(\PDO::FETCH_COLUMN, 1);
                            $diskCols = $rawPdo->query("PRAGMA disk_ref.table_info(`{$tbl}`)")->fetchAll(\PDO::FETCH_COLUMN, 1);
                            $commonCols = array_intersect($targetCols, $diskCols);

                            $colList = implode(', ', array_map(fn($c) => "`{$c}`", $commonCols));
                            $rawPdo->exec("INSERT OR REPLACE INTO `{$tbl}` ({$colList}) SELECT {$colList} FROM disk_ref.`{$tbl}`");
                        } catch (\Throwable $e) {
                            // Abaikan jika tabel opsional
                        }
                    }
                    try {
                        $rawPdo->exec("DETACH DATABASE disk_ref");
                    } catch (\Throwable $e) {
                    }
                    $rawPdo->exec("PRAGMA foreign_keys = ON");
                }

                // Seed baseline testing accounts untuk RBAC
                \App\Models\User::firstOrCreate(
                    ['username' => 'pegawai_sidatex'],
                    [
                        'role_id' => 2,
                        'nip' => null,
                        'name' => 'Pegawai SIDATEX',
                        'nama' => 'Pegawai SIDATEX',
                        'email' => 'pegawai@sidatex.local',
                        'password' => \Illuminate\Support\Facades\Hash::make('password'),
                        'is_active' => true,
                    ]
                );

                \App\Models\User::firstOrCreate(
                    ['username' => 'viewer_sidatex'],
                    [
                        'role_id' => 3,
                        'nip' => null,
                        'name' => 'Viewer Eksekutif SIDATEX',
                        'nama' => 'Viewer Eksekutif SIDATEX',
                        'email' => 'viewer@sidatex.local',
                        'password' => \Illuminate\Support\Facades\Hash::make('password'),
                        'is_active' => true,
                    ]
                );

                // Baseline master data pelabuhan & wilayah
                $kabkotaId = \Illuminate\Support\Facades\DB::table('master_kabkota')->value('kabkota_id') ?? 1;
                \Illuminate\Support\Facades\DB::table('master_pelabuhan')->insertOrIgnore([
                    'pelabuhan_id' => 1,
                    'kabkota_id' => $kabkotaId,
                    'kode_pelabuhan' => 'IDDUM',
                    'nama_pelabuhan' => 'Pelabuhan Dumai',
                ]);

                \Illuminate\Support\Facades\DB::table('master_hs')->insertOrIgnore([
                    ['hs_code' => '27090010', 'hs2' => '27', 'hs2_desc' => 'Minyak Bumi Mentah'],
                    ['hs_code' => '15111000', 'hs2' => '15', 'hs2_desc' => 'Minyak Kelapa Sawit'],
                ]);

                // Baseline sample summary data
                $hsId = \Illuminate\Support\Facades\DB::table('master_hs')->value('hs_id') ?? 1;
                $negaraId = \Illuminate\Support\Facades\DB::table('master_negara')->value('negara_id') ?? 1;
                $provinsiId = \Illuminate\Support\Facades\DB::table('master_provinsi')->where('kode_provinsi', '14')->value('provinsi_id') ?? 14;

                \Illuminate\Support\Facades\DB::table('monthly_trade_summaries')->insertOrIgnore([
                    'hs_id' => $hsId,
                    'negara_id' => $negaraId,
                    'provinsi_id' => $provinsiId,
                    'pelabuhan_id' => null,
                    'pelabuhan_key' => 0,
                    'jenis_transaksi' => 'Ekspor',
                    'tipe_sumber' => 'distribusi',
                    'bulan' => 9,
                    'tahun' => 2026,
                    'total_berat' => 1000.000000,
                    'total_nilai' => 5000.000000,
                    'jumlah_transaksi' => 10,
                    'status_visibilitas' => 'RILIS',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                self::$sharedSqlitePdo = DB::connection()->getPdo();
            } else {
                DB::connection()->setPdo(self::$sharedSqlitePdo);
            }
        }
    }

    /**
     * Verifikasi integritas nama database pada konfigurasi dan koneksi PDO runtime.
     */
    public function enforceTestDatabaseSafety(): void
    {
        $forbiddenDbs = [
            'sidatex',
            'sidatex_staging',
            'sidatex_prod',
            'sidatex_production',
        ];

        // 1. Periksa database default pada konfigurasi Laravel
        $defaultConn = Config::get('database.default', 'sqlite');
        $activeDbName = Config::get("database.connections.{$defaultConn}.database");

        foreach ($forbiddenDbs as $forbidden) {
            if (strcasecmp((string) $activeDbName, $forbidden) === 0) {
                throw new \RuntimeException(
                    "FATAL SECURITY VIOLATION: Test runner dilarang berjalan pada database '{$activeDbName}'! " .
                    "Test suite SIDATEX hanya diizinkan berjalan pada SQLite ':memory:' atau database terisolasi 'sidatex_testing'."
                );
            }
        }

        $isSqliteMemory = ($defaultConn === 'sqlite' && ($activeDbName === ':memory:' || empty($activeDbName)));

        if (!$isSqliteMemory && strcasecmp((string) $activeDbName, 'sidatex_testing') !== 0) {
            throw new \RuntimeException(
                "FATAL CONFIG ERROR: Database aktif adalah '{$activeDbName}', bukan 'sidatex_testing' atau SQLite ':memory:'. " .
                "Pastikan konfigurasi .env.testing dan phpunit.xml mengarah ke SQLite ':memory:' atau 'sidatex_testing'."
            );
        }

        // 2. Periksa SELURUH koneksi yang terdaftar di config('database.connections')
        $allConnections = Config::get('database.connections', []);
        foreach ($allConnections as $connName => $connConfig) {
            if (is_array($connConfig) && isset($connConfig['database'])) {
                $dbVal = (string) $connConfig['database'];
                foreach ($forbiddenDbs as $forbidden) {
                    if (strcasecmp($dbVal, $forbidden) === 0) {
                        throw new \RuntimeException(
                            "FATAL SECURITY VIOLATION: Koneksi '{$connName}' terkonfigurasi ke database terlarang '{$dbVal}'! Eksekusi dihentikan!"
                        );
                    }
                }
            }
        }

        // 3. Periksa koneksi PDO riil (strict whitelist 'sidatex_testing' / ':memory:' + fail-closed)
        try {
            $connection = DB::connection();
            $pdo = $connection->getPdo();
            $realDb = (string) $connection->getDatabaseName();

            if (in_array(strtolower($realDb), $forbiddenDbs, true)) {
                throw new \RuntimeException(
                    "FATAL SECURITY VIOLATION: Koneksi PDO riil tersambung ke '{$realDb}'. Eksekusi dihentikan!"
                );
            }

            if ($connection->getDriverName() === 'sqlite') {
                if ($realDb !== ':memory:' && !empty($realDb)) {
                    throw new \RuntimeException(
                        "UNSAFE DATABASE: Runtime SQLite connection is '{$realDb}', expected ':memory:'!"
                    );
                }
            } elseif (strcasecmp($realDb, 'sidatex_testing') !== 0) {
                throw new \RuntimeException(
                    "UNSAFE DATABASE: Runtime Laravel connection is '{$realDb}', expected 'sidatex_testing' or SQLite ':memory:'!"
                );
            }

            if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
                $pdoDb = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

                if (in_array(strtolower($pdoDb), $forbiddenDbs, true)) {
                    throw new \RuntimeException(
                        "FATAL SECURITY VIOLATION: Sesi PDO MySQL riil tersambung ke '{$pdoDb}'. Eksekusi dihentikan!"
                    );
                }

                if (strcasecmp($pdoDb, 'sidatex_testing') !== 0) {
                    throw new \RuntimeException(
                        "UNSAFE PDO DATABASE: Live MySQL PDO session is '{$pdoDb}', expected 'sidatex_testing'!"
                    );
                }
            }
        } catch (\PDOException $e) {
            throw new \RuntimeException(
                "FATAL PDO VERIFICATION ERROR: Gagal memverifikasi koneksi PDO runtime: " . $e->getMessage(),
                0,
                $e
            );
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "FATAL PDO VERIFICATION ERROR: Gagal memverifikasi koneksi PDO runtime: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
