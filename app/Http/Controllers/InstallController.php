<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;

class InstallController extends Controller
{
    /**
     * Determine if the application has been installed.
     */
    public static function isInstalled(): bool
    {
        $lockFile = storage_path('installed');

        // If explicitly unlocked by user during current session, allow wizard
        if (session('installer_unlocked')) {
            return false;
        }

        if (file_exists($lockFile)) {
            return true;
        }

        // Auto-detect if database is already configured and migrated (e.g. CLI migration, cloned repo, or imported DB)
        try {
            if (config('database.default') && config('database.connections.' . config('database.default') . '.database')) {
                if (DB::connection()->getPdo() && \Illuminate\Support\Facades\Schema::hasTable('users') && \Illuminate\Support\Facades\Schema::hasTable('migrations')) {
                    if (DB::table('users')->exists()) {
                        // Persist the lock file so subsequent checks are instant filesystem checks
                        @file_put_contents($lockFile, json_encode([
                            'installed_at' => now()->toIso8601String(),
                            'app_url' => config('app.url'),
                            'auto_detected' => true,
                            'version' => app()->version(),
                        ], JSON_PRETTY_PRINT));

                        return true;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Cannot connect to DB or tables do not exist
            return false;
        }

        return false;
    }

    /**
     * Show installation wizard
     */
    public function index()
    {
        $isInstalled = self::isInstalled();

        // Check system requirements
        $requirements = [
            'php' => [
                'name' => 'PHP Versi >= 8.2',
                'current' => PHP_VERSION,
                'status' => version_compare(PHP_VERSION, '8.2.0', '>='),
            ],
            'pdo' => [
                'name' => 'PDO & PDO MySQL Extension',
                'current' => extension_loaded('pdo_mysql') ? 'Aktif' : 'Tidak Ada',
                'status' => extension_loaded('pdo_mysql'),
            ],
            'mbstring' => [
                'name' => 'Mbstring Extension',
                'current' => extension_loaded('mbstring') ? 'Aktif' : 'Tidak Ada',
                'status' => extension_loaded('mbstring'),
            ],
            'openssl' => [
                'name' => 'OpenSSL Extension',
                'current' => extension_loaded('openssl') ? 'Aktif' : 'Tidak Ada',
                'status' => extension_loaded('openssl'),
            ],
            'fileinfo' => [
                'name' => 'Fileinfo Extension',
                'current' => extension_loaded('fileinfo') ? 'Aktif' : 'Tidak Ada',
                'status' => extension_loaded('fileinfo'),
            ],
            'tokenizer' => [
                'name' => 'Tokenizer Extension',
                'current' => extension_loaded('tokenizer') ? 'Aktif' : 'Tidak Ada',
                'status' => extension_loaded('tokenizer'),
            ],
            'xml' => [
                'name' => 'XML / DOM Extension',
                'current' => extension_loaded('xml') ? 'Aktif' : 'Tidak Ada',
                'status' => extension_loaded('xml'),
            ],
            'curl' => [
                'name' => 'cURL Extension',
                'current' => extension_loaded('curl') ? 'Aktif' : 'Tidak Ada',
                'status' => extension_loaded('curl'),
            ],
        ];

        // Check folder permissions
        $permissions = [
            'storage' => [
                'name' => 'storage/',
                'path' => storage_path(),
                'status' => is_writable(storage_path()),
            ],
            'storage_framework' => [
                'name' => 'storage/framework/',
                'path' => storage_path('framework'),
                'status' => is_writable(storage_path('framework')),
            ],
            'storage_logs' => [
                'name' => 'storage/logs/',
                'path' => storage_path('logs'),
                'status' => is_writable(storage_path('logs')),
            ],
            'bootstrap_cache' => [
                'name' => 'bootstrap/cache/',
                'path' => base_path('bootstrap/cache'),
                'status' => is_writable(base_path('bootstrap/cache')),
            ],
            'env' => [
                'name' => '.env (atau root aplikasi)',
                'path' => base_path('.env'),
                'status' => file_exists(base_path('.env')) ? is_writable(base_path('.env')) : is_writable(base_path()),
            ],
        ];

        $allRequirementsPassed = collect($requirements)->every(fn($r) => $r['status']);
        $allPermissionsPassed = collect($permissions)->every(fn($p) => $p['status']);

        // Default or current config
        $currentConfig = [
            'app_url' => config('app.url') ?: url('/'),
            'db_host' => config('database.connections.mysql.host') ?: '127.0.0.1',
            'db_port' => config('database.connections.mysql.port') ?: '3306',
            'db_database' => config('database.connections.mysql.database') ?: '',
            'db_username' => config('database.connections.mysql.username') ?: '',
            'db_password' => config('database.connections.mysql.password') ?: '',
        ];

        return view('install.index', compact(
            'isInstalled',
            'requirements',
            'permissions',
            'allRequirementsPassed',
            'allPermissionsPassed',
            'currentConfig'
        ));
    }

    /**
     * Test database connection via PDO
     */
    public function checkDb(Request $request)
    {
        $request->validate([
            'db_host' => 'required|string',
            'db_port' => 'required|numeric',
            'db_database' => 'required|string',
            'db_username' => 'required|string',
            'db_password' => 'nullable|string',
        ]);

        try {
            $host = $request->db_host;
            $port = $request->db_port;
            $database = $request->db_database;
            $username = $request->db_username;
            $password = $request->db_password ?? '';

            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Koneksi ke database berhasil! Basis data ditemukan dan siap digunakan.',
            ]);
        } catch (\PDOException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Koneksi database gagal: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Save .env configuration
     */
    public function saveConfig(Request $request)
    {
        $request->validate([
            'app_url' => 'required|url',
            'db_host' => 'required|string',
            'db_port' => 'required|numeric',
            'db_database' => 'required|string',
            'db_username' => 'required|string',
            'db_password' => 'nullable|string',
        ]);

        try {
            $this->updateEnv([
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'APP_URL' => rtrim($request->app_url, '/'),
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $request->db_host,
                'DB_PORT' => $request->db_port,
                'DB_DATABASE' => $request->db_database,
                'DB_USERNAME' => $request->db_username,
                'DB_PASSWORD' => $request->db_password ?? '',
                'SESSION_DRIVER' => 'file',
                'CACHE_STORE' => 'file',
            ]);

            // Ensure APP_KEY exists
            if (empty(config('app.key')) && empty(env('APP_KEY'))) {
                Artisan::call('key:generate', ['--force' => true]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Konfigurasi environment (.env) berhasil disimpan!',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan .env: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Run individual install step (migrate, seed, storage_link, finish)
     */
    public function runStep(Request $request)
    {
        $request->validate([
            'step' => 'required|in:key,migrate,seed,storage_link,finish',
        ]);

        @set_time_limit(300);
        $step = $request->step;
        $output = '';

        try {
            switch ($step) {
                case 'key':
                    Artisan::call('key:generate', ['--force' => true]);
                    $output = Artisan::output();
                    $message = 'App Encryption Key berhasil di-generate.';
                    break;

                case 'migrate':
                    Artisan::call('migrate', ['--force' => true]);
                    $output = Artisan::output();
                    $message = 'Database migration berhasil dieksekusi.';
                    break;

                case 'seed':
                    Artisan::call('db:seed', ['--force' => true]);
                    $output = Artisan::output();
                    $message = 'Seeder data awal (Admin, Roles, Master Data) berhasil diisi.';
                    break;

                case 'storage_link':
                    try {
                        Artisan::call('storage:link');
                        $output = Artisan::output();
                    } catch (\Throwable $e) {
                        $output = "Symlink notice: " . $e->getMessage();
                    }
                    try {
                        Artisan::call('optimize:clear');
                        $output .= "\n" . Artisan::output();
                    } catch (\Throwable $e) {
                        $output .= "\nCache notice: " . $e->getMessage();
                    }
                    $message = 'Symlink storage dan cache aplikasi berhasil disiapkan.';
                    break;

                case 'finish':
                    @file_put_contents(storage_path('installed'), json_encode([
                        'installed_at' => now()->toIso8601String(),
                        'app_url' => config('app.url'),
                        'version' => app()->version(),
                    ], JSON_PRETTY_PRINT));

                    session()->forget('installer_unlocked');

                    $message = 'Instalasi selesai! Aplikasi siap digunakan.';
                    break;
            }

            return response()->json([
                'success' => true,
                'step' => $step,
                'message' => $message,
                'output' => trim($output) ?: 'Sukses tanpa output tambahan.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'step' => $step,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'output' => $e->getTraceAsString(),
            ], 500);
        }
    }

    /**
     * Unlock installation for re-running setup
     */
    public function unlock(Request $request)
    {
        $lockFile = storage_path('installed');
        if (file_exists($lockFile)) {
            @unlink($lockFile);
        }

        session(['installer_unlocked' => true]);

        return redirect()->route('install.index')
            ->with('success', 'Kunci installer dibuka. Anda dapat mengonfigurasi ulang aplikasi.');
    }

    /**
     * Safely update .env file keys
     */
    protected function updateEnv(array $values)
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            if (file_exists(base_path('.env.example'))) {
                copy(base_path('.env.example'), $envPath);
            } else {
                file_put_contents($envPath, '');
            }
        }

        $content = file_get_contents($envPath);

        foreach ($values as $key => $value) {
            $formattedValue = (str_contains($value, ' ') && !str_starts_with($value, '"')) ? '"' . $value . '"' : $value;
            if (preg_match("/^{$key}=.*/m", $content)) {
                $content = preg_replace("/^{$key}=.*/m", "{$key}={$formattedValue}", $content);
            } else {
                $content .= "\n{$key}={$formattedValue}";
            }
        }

        file_put_contents($envPath, $content);
    }
}
