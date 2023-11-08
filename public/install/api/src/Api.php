<?php

namespace Merchant\Installer;

use DirectoryIterator;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Str;
use JetBrains\PhpStorm\NoReturn;
use Merchant\Installer\Exception\SSLValidationException;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use mysqli;
use ReflectionMethod;
use Symfony\Component\Console\Output\BufferedOutput;
use Winter\Packager\Composer;
use ZipArchive;

class Api
{
    // Minimum PHP version required for Merchant
    const MIN_PHP_VERSION = '8.2.0';

    // Minimum PHP version that is unsupported for Merchant (upper limit)
    const MAX_PHP_VERSION = '8.999.999';

    const BACKEND_URL_IN_FRONTEND = 'http://localhost:8080';
    const MERCHANT_ARCHIVE = 'https://github.com/stickpro/dv-backend/archive/refs/tags/dv-pay-1.0.0.zip';

    const ARCHIVE_SUBFOLDER = 'dv-backend-dv-pay-1.0.0/';

    protected $log;

    protected $endpoint;

    protected $method;

    protected $data;

    protected $responseCode = 200;

    /**
     * @throws \JsonException
     */
    public function request()
    {
        ini_set('display_errors', 'Off');

        $this->initialiseLogging();

        $this->setExceptionHandler();

        $this->parseRequest();

        $this->log->info('Installer API request received', [
            'method'   => $this->method,
            'endpoint' => $this->endpoint,
        ]);

        $method = $this->getRequestedMethod();
        if (is_null($method)) {
            $this->error('Invalid Installer API endpoint requested', 404);
        }

        $this->{$method}();

        $this->response(true);
    }

    /**
     * GET /api.php?endpoint=checkPhpVersion
     *
     * Checks that the currently-running version of PHP matches the minimum required
     * @throws \JsonException
     */
    public function getCheckPhpVersion(): void
    {
        $hasVersion = (
            version_compare(trim(strtolower(PHP_VERSION)), self::MIN_PHP_VERSION, '>=')
            && version_compare(trim(strtolower(PHP_VERSION)), self::MAX_PHP_VERSION, '<')
        );

        $this->data = [
            'detected'    => PHP_VERSION,
            'needed'      => self::MIN_PHP_VERSION,
            'installPath' => $this->rootDir(),
        ];

        $this->log->notice('Compared PHP version', [
            'installed' => PHP_VERSION,
            'needed'    => self::MIN_PHP_VERSION
        ]);

        if (!$hasVersion) {
            $this->error('PHP version requirement not met.');
        }

        $this->log->notice('PHP version requirement met.');
    }

    public function getCheckPhpExtensions()
    {
        $this->log->notice('Checking PHP "curl" extension');

        if (!function_exists('curl_init') || !defined('CURLOPT_FOLLOWLOCATION')) {
            $this->data['extension'] = 'curl';
            $this->error('Missing extension');
        }
        $this->log->notice('Checking PHP "json" extension');
        if (!function_exists('json_decode')) {
            $this->data['extension'] = 'json';
            $this->error('Missing extension');
        }
        $this->log->notice('Checking PHP "pdo" extension');
        if (!defined('PDO::ATTR_DRIVER_NAME')) {
            $this->data['extension'] = 'pdo';
            $this->error('Missing extension');
        }

        $this->log->notice('Checking PHP "zip" extension');
        if (!class_exists('ZipArchive')) {
            $this->data['extension'] = 'zip';
            $this->error('Missing extension');
        }

        $extensions = [
            'mbstring',
            'fileinfo',
            'openssl',
            'filter',
            'hash',
            'dom',
            'bcmath',
            'exif',
            'tokenizer',
            'JSON',
//            'sockets',
//            'gmp',
//            'fileinfo',
//            'XML',
//            'ctype'
        ];
        foreach ($extensions as $ext) {
            $this->log->notice('Checking PHP "' . $ext . '" extension');

            if (!extension_loaded($ext)) {
                $this->data['extension'] = $ext;
                $this->error('Missing extension');
            }
        }
        $this->log->notice('Required PHP extensions are installed.');
    }

    /**
     * POST /api.php[endpoint=checkDatabase]
     * @return void
     * @throws \JsonException
     */
    public function postCheckDatabase()
    {
        set_time_limit(60);

        $dbConfig = $this->data['site']['database'];
        try {
            $this->log->notice('Check database connection');
            $capsule = $this->createCapsule($dbConfig);

            if (is_null($dbConfig['name'])) {
                $capsule->setAsGlobal();
                $capsule->bootEloquent();
                $newDatabaseName = 'merchant';
                $this->createDatabase($capsule, $newDatabaseName);
                $dbConfig['name'] = $newDatabaseName;
            }
            $capsule = $this->createCapsule($dbConfig);
            $connection = $capsule->getConnection();

            $tables = $connection->getDoctrineSchemaManager()->listTableNames();
            $this->log->notice('Found ' . count($tables) . ' table(s)', ['tables' => implode(', ', $tables)]);
        } catch (\Throwable $e) {
            $this->data['exception'] = $e->getMessage();
            $this->error('Database could not be connected to.');
        }

        if (count($tables)) {
            $this->data['dbNotEmpty'] = true;
            $this->error('Database is not empty.');
        }

        $this->log->notice('Database connection established and verified empty');
    }

    private function createDatabase($capsule, $databaseName)
    {
        $this->log->notice('Try create database');
        try {
            $query = "CREATE DATABASE IF NOT EXISTS $databaseName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
            $capsule::connection()->statement($query);
        } catch (\Throwable $e) {
            $this->data['exception'] = $e->getMessage();
            $this->error('Database could not be create');
        }
    }

    /**
     * @return void
     * @throws SSLValidationException
     * @throws \Exception
     */
    public function postDownloadDvPay()
    {
        set_time_limit(360);

        if (is_dir($this->workDir())) {
            $this->rimraf($this->workDir());
        }
        if (!@mkdir($this->workDir(), 0755, true)) {
            $this->error('Unable to create a work directory for installation');
        }

        $dvZip = $this->workDir('dv-backend.zip');

        if (!file_exists($dvZip)) {
            $this->log->notice('Try downloading DV PAY archive');

            try {
                $fp = fopen($dvZip, 'w');
                if (!$fp) {
                    $this->log->error('DV zip file unwritable', ['path' => $dvZip]);
                    $this->error('Unable to write the DV PAY installation file');
                }
                $curl = curl_init();

                curl_setopt_array($curl, [
                    CURLOPT_URL            => self::MERCHANT_ARCHIVE,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 300,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS      => 5,
                    CURLOPT_FILE           => $fp
                ]);

                if (file_exists($this->rootDir('.ignore-ssl'))) {
                    curl_setopt_array($curl, [
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => 0,
                    ]);
                } else {
                    curl_setopt_array($curl, [
                        CURLOPT_SSL_VERIFYPEER => true,
                        CURLOPT_SSL_VERIFYHOST => 2,
                    ]);
                }

                $this->log->notice('Downloading DV ZIP via cURL', ['url' => self::MERCHANT_ARCHIVE]);
                curl_exec($curl);
                $responseCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

                if ($responseCode === 0) {
                    $this->log->error('Unable to verify SSL certificate or connection to GitHub', []);

                    curl_close($curl);

                    throw new SSLValidationException('Unable to verify SSL certificate or connection');
                } elseif ($responseCode < 200 || $responseCode > 299) {
                    throw new \Exception('Invalid HTTP code received - got ' . $responseCode);
                }
                curl_close($curl);
            } catch (\Throwable $e) {
                if (isset($fp)) {
                    fclose($fp);
                }
                if (isset($curl) && is_resource($curl)) {
                    curl_close($curl);
                }
                $this->error('Unable to download DV PAY. ' . $e->getMessage());
            }
            $this->log->notice('DV PAY ZIP file downloaded', ['path' => $dvZip]);
        } else {
            $this->log->notice('DV ZIP file already downloaded', ['path' => $dvZip]);
        }
    }


    /**
     * POST /api.php[endpoint=extractDvPay]
     *
     * Extracts the downloaded ZIP file.
     *
     * @return void
     * @throws \JsonException
     */
    public function postExtractDvPay(): void
    {
        set_time_limit(120);
        $dvZip = $this->workDir('dv-backend.zip');

        if (!file_exists($dvZip)) {
            $this->error('DV PAy Zip file not found.');
            return;
        }

        try {
            $this->log->notice('Begin extracting DV PAY archive');
            $zip = new ZipArchive();
            $zip->open($dvZip);
        } catch (\Throwable $e) {
            $this->error('Unable to extract DV PAY. ' . $e->getMessage());
        }

        $zip->extractTo($this->workDir());

        if (!empty(self::ARCHIVE_SUBFOLDER)) {
            $this->log->notice('Move subfoldered files into position', ['subfolder' => self::ARCHIVE_SUBFOLDER]);

            $dir = new DirectoryIterator($this->workDir(self::ARCHIVE_SUBFOLDER));

            foreach ($dir as $item) {
                if ($item->isDot()) {
                    continue;
                }

                $relativePath = str_replace($this->workDir(self::ARCHIVE_SUBFOLDER), '', $item->getPathname());

                rename($item->getPathname(), $this->workDir($relativePath));
            }
        }

        $zip->close();

        if (!empty(self::ARCHIVE_SUBFOLDER)) {
            $this->log->notice('Remove ZIP subfolder', ['subfolder' => self::ARCHIVE_SUBFOLDER]);
            rmdir($this->workDir(self::ARCHIVE_SUBFOLDER));
        }

        $this->log->notice('Make artisan command-line tool executable', ['path' => $this->workDir('artisan')]);
        chmod($this->workDir('artisan'), 0755);
    }

    public function postUpdateFrontendPath(): void
    {
        set_time_limit(120);

        $this->log->notice('Begin replace backend url DV PAY archive');
        $replaceString = $this->data['site']['url'] . '/' . $this->data['site']['backendUrl'];
        $this->replaceInFiles($this->workDir('frontend'), self::BACKEND_URL_IN_FRONTEND, $replaceString);
        $this->log->notice('Success update url');
    }

    protected function replaceInFiles($directory, $searchString, $replaceString)
    {
        $files = scandir($directory);

        foreach ($files as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }

            $path = $directory . '/' . $file;

            if (is_dir($path)) {
                $this->replaceInFiles($path, $searchString, $replaceString);
            } elseif (is_file($path) && pathinfo($path, PATHINFO_EXTENSION) == 'js') {
                $content = file_get_contents($path);
                $content = str_replace($searchString, $replaceString, $content);
                file_put_contents($path, $content);
            }
        }
    }

    public function postCleanUp()
    {
        set_time_limit(120);
        $this->log->notice('Removing installation files');
        // Remove install files
        @unlink($this->workDir('dv-backend.zip'));
        @unlink($this->rootDir('install.html'));
        @unlink($this->rootDir('install.zip'));
        @unlink($this->rootDir('.ignore-ssl'));

        // Remove install folders
        $this->log->notice('Removing temporary installation folders');
        $this->rimraf($this->rootDir('install'));
        $this->rimraf($this->workDir('.composer'));

        $this->log->notice('Moving files from temporary work directory to final installation path', [
            'workDir' => $this->workDir(),
            'installDir' => $this->rootDir(),
        ]);

        $dir = new DirectoryIterator($this->workDir());

        foreach ($dir as $item) {
            if ($item->isDot()) {
                continue;
            }

            $relativePath = str_replace($this->workDir(), '', $item->getPathname());

            rename($item->getPathname(), $this->rootDir($relativePath));
        }
        // Remove work directory
        $this->log->notice('Removing work directory');
        rmdir($this->workDir());

        $this->log->notice('Installation complete!');
    }

    /**
     * POST /api.php[endpoint=lockDependencies]
     *
     * Locks the Composer dependencies for DV PAY in composer.lock
     *
     * @return void
     * @throws \JsonException
     */
    public function postLockDependencies()
    {
        set_time_limit(360);

        try {
            $this->log->notice('Create Composer instance');
            $composer = new Composer();
            $this->log->notice('Set memory limit to 1.5GB');
            $composer->setMemoryLimit(1536);
            $this->log->notice('Set work directory for Composer', ['path' => $this->workDir()]);
            $composer->setWorkDir($this->workDir());

            $tmpHomeDir = $this->workDir('.composer');

            if (!is_dir($tmpHomeDir)) {
                $this->log->notice('Create home/cache directory for Composer', ['path' => $tmpHomeDir]);
                mkdir($tmpHomeDir, 0755);
            }

            $this->log->notice('Set home/cache directory for Composer', ['path' => $tmpHomeDir]);
            $composer->setHomeDir($tmpHomeDir);

            $this->log->notice('Run Composer "update" command - generate only a lockfile');
            $update = $composer->update(true, true, false, 'dist', true);
        } catch (\Throwable $e) {
            if (!empty($e->getPrevious())) {
                $this->log->error('Composer exception', ['exception' => $e->getPrevious()]);
            }
            $this->error('Unable to determine dependencies for DV PAY. ' . $e->getMessage());
        }

        $this->log->notice('Locked Composer packages', [
            'numPackages' => $update->getLockInstalledCount(),
            'lockFile'    => $this->workDir('composer.lock'),
        ]);

        $this->data['packagesInstalled'] = $update->getLockInstalledCount();

    }

    /**
     * POST /api.php[endpoint=installDependencies]
     *
     * Installs the locked depencies from the `lockDependencies` call.
     *
     * @return void
     */
    public function postInstallDependencies()
    {
        set_time_limit(180);
        try {
            $this->log->notice('Create Composer instance');
            $composer = new Composer();
            $this->log->notice('Set memory limit to 1.5GB');
            $composer->setMemoryLimit(1536);
            $this->log->notice('Set work directory for Composer', ['path' => $this->workDir()]);
            $composer->setWorkDir($this->workDir());

            $tmpHomeDir = $this->workDir('.composer');

            if (!is_dir($tmpHomeDir)) {
                $this->log->notice('Create home/cache directory for Composer', ['path' => $tmpHomeDir]);
                mkdir($tmpHomeDir, 0755);
            }

            $this->log->notice('Set home/cache directory for Composer', ['path' => $tmpHomeDir]);
            $composer->setHomeDir($tmpHomeDir);

            $this->log->notice('Run Composer "install" command - install from lockfile');
            $install = $composer->install(true, false, false, 'dist', true);
        } catch (\Throwable $e) {
            $this->error('Unable to determine dependencies for DV PAY. ' . $e->getMessage());
        }

        $this->log->notice('Installed Composer packages', [
            'numPackages' => $install->getInstalledCount(),
        ]);

        $this->data['packagesInstalled'] = $install->getInstalledCount();
    }

    /**
     * POST /api.php[endpoint=setupConfig]
     *
     * Rewrites the default configuration files with the values provided in the installer.
     *
     * @return void
     * @throws \JsonException
     */
    public function postSetupConfig(): void
    {
        try {

            $url = str_replace(array("http://", "https://"), "", $this->data['site']['url']);
            $envFileData =
                /*App*/
                'APP_NAME=\'' . 'DV PAY' . "'\n" .
                'APP_ENV=' . 'local' . "\n" .
                'APP_KEY=' . 'base64:' . base64_encode(Str::random(32)) . "\n" .
                'APP_DEBUG=' . true . "\n" .
                'APP_URL=' . $this->data['site']['url'] . '/' . $this->data['site']['backendUrl'] . '/' . "\n\n" .
                'APP_DOMAIN=' . $url . "\n\n" .
                /*Database*/
                'DB_CONNECTION=' . $this->data['site']['database']['type'] . "\n" .
                'DB_HOST=' . $this->data['site']['database']['host'] . "\n" .
                'DB_PORT=' . $this->data['site']['database']['port'] . "\n" .
                'DB_DATABASE=' . $this->data['site']['database']['name'] . "\n" .
                'DB_USERNAME=' . $this->data['site']['database']['user'] . "\n" .
                'DB_PASSWORD=' . $this->data['site']['database']['pass'] . "\n\n" .

                'BROADCAST_DRIVER=' . 'log' . "\n" .
                'CACHE_DRIVER=' . 'file' . "\n" .
                'FILESYSTEM_DRIVER=' . 'local' . "\n" .
                'QUEUE_CONNECTION=' . 'redis' . "\n" .
                'SESSION_DRIVER=' . 'file' . "\n" .
                'SESSION_LIFETIME=' . '120' . "\n\n" .
                /* Redis */
                'REDIS_HOST=' . 'localhost' . "\n" .
                'REDIS_PASSWORD=' . null . "\n" .
                'REDIS_PORT=' . 6379 . "\n\n" .
                /* Processing */
                'PROCESSING_URL=' . $this->data['site']['processingUrl'] . "\n" .
                'PROCESSING_CLIENT_ID=' . "\n" .
                'PROCESSING_CLIENT_KEY=' . "\n" .
                'PROCESSING_WEBHOOK_KEY=' . "\n\n" .
                /* Something default value*/
                'WEBHOOK_TIMEOUT=50' . "\n" .
                'MIN_TRANSACTION_CONFIRMATIONS=1' . "\n" .
                'RATE_SCALE=1' . "\n\n";

            if ($this->copyEnv()) {
                file_put_contents($this->workDir('.env'), $envFileData);
            }

        } catch (\Throwable $e) {
            $this->error('Unable to write .env. ' . $e->getMessage());
        }

        // Force cache flush
        $opcacheEnabled = ini_get('opcache.enable');
        $opcachePath = trim(ini_get('opcache.restrict_api'));

        if (!empty($opcachePath) && !starts_with(__FILE__, $opcachePath)) {
            $opcacheEnabled = false;
        }

        if (function_exists('opcache_reset') && $opcacheEnabled) {
            $this->log->notice('Flushing OPCache');
            opcache_reset();
        }
        if (function_exists('apc_clear_cache')) {
            $this->log->notice('Flushing APC Cache');
            apc_clear_cache();
        }
    }

    /**
     * POST /api.php[endpoint=runMigrations]
     *
     * Runs the migrations.
     *
     * @return void
     * @throws \JsonException
     */
    public function postRunMigrations()
    {
        set_time_limit(120);

        try {
            $this->bootFramework();
            $this->log->notice('Running artisan "config:clear" command');
            $output = new BufferedOutput();
            \Illuminate\Support\Facades\Artisan::call('config:clear', [], $output);
            $this->log->notice('Command finished.', ['output' => $output->fetch()]);

            $this->log->notice('Running database migrations');
            $output = new BufferedOutput();
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--no-interaction' => true], $output);
            $this->log->notice('Command finished.', ['output' => $output->fetch()]);

            $this->log->notice('Running database seed');
            $output = new BufferedOutput();
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--no-interaction' => true], $output);
            $this->log->notice('Command finished.', ['output' => $output->fetch()]);

            $this->log->notice('Get Currency Rate');
            $output = new BufferedOutput();
            \Illuminate\Support\Facades\Artisan::call('cache:currency:rate', ['--no-interaction' => true], $output);
            $this->log->notice('Command finished.', ['output' => $output->fetch()]);

            $this->log->notice('Processing init');
            $output = new BufferedOutput();
            \Illuminate\Support\Facades\Artisan::call('processing:init');
            $this->log->notice('Command finished.', ['output' => $output->fetch()]);
        } catch (\Throwable $e) {
            $this->error('Unable to run migrations. ' . $e->getMessage());
        }
    }

    public function postCreateAdmin()
    {
        try {
            $this->bootFramework();

            $this->log->notice('Finding initial admin account');
            $admin = \App\Models\User::find(1);
        } catch (\Throwable $e) {
            $this->error('Unable to find administrator account. ' . $e->getMessage());
        }


        $this->log->notice('Processing register owner');
        $output = new BufferedOutput();
        \Illuminate\Support\Facades\Artisan::call('register:processing:owner');

        $admin->email = $this->data['site']['admin']['email'];
        $admin->password = \Illuminate\Support\Facades\Hash::make($this->data['site']['admin']['password']);

        try {
            $this->log->notice('Changing admin account to details provided in installation');
            $admin->save();
        } catch (\Throwable $e) {
            $this->error('Unable to save administrator account. ' . $e->getMessage());
        }

        $this->log->notice('Command finished.', ['output' => $output->fetch()]);
    }

    /**
     * POST /api.php[endpoint=loadDatabase]
     *
     * Runs the migrations.
     *
     * @return void
     * @throws \JsonException
     */
    public function postLoadDatabase(): void
    {
        $dbConfig = $this->data['site']['database'];

        try {
            $this->log->notice('Check database connection');
            $capsule = $this->createCapsule($dbConfig);

            $capsule->setAsGlobal();
            $capsule->bootEloquent();

            $databases = Capsule::select("SHOW DATABASES WHERE `Database` NOT IN ('mysql', 'performance_schema', 'sys', 'information_schema')");
            $databaseNames = array_column($databases, 'Database');
            $this->log->notice('Found ' . count($databaseNames) . ' databse(s)', ['databases' => implode(', ', $databaseNames)]);
        } catch (\Throwable $e) {
            $this->data['exception'] = $e->getMessage();
            $this->error('Database could not be connected to.');
        }

        $this->data['databaseList'] =  $databaseNames;

    }

    /**
     * GET /api.php?endpoint=checkWriteAccess
     * @return void
     * @throws \JsonException
     */
    public function getCheckWriteAccess(): void
    {
        if (!is_writable($this->rootDir())) {
            $this->data['writable'] = false;
            $this->error('Current working directory is not writable.');
        }

        $this->data['writable'] = true;
        $this->log->notice('Current working directory is writable.');
    }

    protected function workDir(string $suffix = ''): string
    {
        $suffix = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $suffix), '/\\');

        return $this->rootDir('.dvpay' . (!empty($suffix) ? DIRECTORY_SEPARATOR . $suffix : ''));
    }

    /**
     * @param array $dbConfig
     * @return Capsule
     * @throws \Exception
     */
    protected function createCapsule(array $dbConfig)
    {
        $capsule = new Capsule();

        $capsule->addConnection([
            'driver'    => $dbConfig['type'],
            'host'      => $dbConfig['host'] ?? null,
            'port'      => $dbConfig['port'] ?? $this->getDefaultDbPort($dbConfig['type']),
            'database'  => $dbConfig['name'],
            'username'  => $dbConfig['user'] ?? '',
            'password'  => $dbConfig['pass'] ?? '',
            'charset'   => ($dbConfig['type'] === 'mysql') ? 'utf8mb4' : 'utf8',
            'collation' => ($dbConfig['type'] === 'mysql') ? 'utf8mb4_unicode_ci' : null,
            'prefix'    => '',
        ]);

        return $capsule;
    }

    protected function copyEnv(): bool
    {
        if (!file_exists($this->workDir('.env'))) {
            return copy($this->workDir('.env.example'), $this->workDir('.env'));
        }
        return true;
    }


    /**
     * Boots the Laravel framework for use in some installation steps.
     * @return void
     * @throws \JsonException
     */
    protected function bootFramework()
    {
        $this->log->notice('Booting Laravel framework');

        $autoloadFile = $this->workDir('bootstrap/autoload.php');
        if (!file_exists($autoloadFile)) {
            $this->error('Unable to load bootstrap file for framework from "' . $autoloadFile . '".');
            return;
        }

        $this->log->notice('Loading autoloader');
        require $autoloadFile;

        $appFile = $this->workDir('bootstrap/app.php');
        if (!file_exists($appFile)) {
            $this->error('Unable to load application initialization file for framework from "' . $appFile . '".');
            return;
        }

        $this->log->notice('Bootstrapping kernel');
        $app = require_once $appFile;
        $kernel = $app->make('Illuminate\Contracts\Console\Kernel');
        $kernel->bootstrap();
    }

    /**
     * @param string $type
     * @return int
     * @throws \Exception
     */
    protected function getDefaultDbPort(string $type): int
    {
        switch ($type) {
            case 'mysql':
                return 3306;
            case 'pgsql':
                return 5432;
            case 'sqlsrv':
                return 1433;
            default:
                throw new \Exception('Invalid database type provided');
        }
    }

    /**
     * Initialise the logging for the API / install.
     *
     * @return void
     */
    protected function initialiseLogging(): void
    {
        $dateFormat = 'Y-m-d H:i:sP';
        $logFormat = "[%datetime%] %level_name%: %message% %context% %extra%\n";
        $formatter = new LineFormatter($logFormat, $dateFormat, false, true);

        $this->log = new Logger('install');

        $stream = new StreamHandler($this->rootDir('install.log'));
        $stream->setFormatter($formatter);

        $this->log->pushHandler($stream, Logger::INFO);
    }

    /**
     * Generates and echoes a JSON response to the browser.
     *
     * @param boolean $success Is this is a successful response?
     * @return void
     * @throws \JsonException
     */
    #[NoReturn] protected function response(bool $success = true): void
    {
        $response = [
            'success'  => $success,
            'endpoint' => $this->endpoint,
            'method'   => $this->method,
            'code'     => $this->responseCode,
        ];

        if (!$success) {
            $response['error'] = $this->data['error'];
        }
        if (count($this->data)) {
            $response['data'] = $this->data;
        }

        // Set headers (including CORS)
        http_response_code($this->responseCode);
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 3600');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

        echo json_encode($response, JSON_THROW_ON_ERROR);
        exit(0);
    }

    protected function parseRequest()
    {
        $this->method = $_SERVER['REQUEST_METHOD'];

        if (!in_array($this->method, ['GET', 'POST'])) {
            $this->error('Invalid request method. Must be one of: GET, POST', 405);
            return;
        }

        if ($this->method === 'GET') {
            $this->data = $_GET;
        } else {
            $json = file_get_contents('php://input');

            if (empty($json)) {
                $this->error('No JSON input detected', 400);
                return;
            }

            $data = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('Malformed JSON request: ' . json_last_error_msg());
                return;
            }

            $this->data = $data;
        }

        $this->endpoint = $this->data['endpoint'] ?? null;
        unset($this->data['endpoint']);

        if (is_null($this->endpoint)) {
            $this->error('Missing requested endpoint', 400);
        }
    }

    protected function getRequestedMethod(): ?string
    {
        $method = strtolower($this->method) . ucfirst($this->endpoint);

        if (!method_exists($this, $method)) {
            return null;
        }

        $reflection = new ReflectionMethod($this, $method);
        if (!$reflection->isPublic()) {
            return null;
        }

        return $method;
    }

    /**
     * @param int $code
     * @return void
     */
    protected function setResponseCode(int $code): void
    {
        $this->responseCode = $code;
    }

    /**
     * @param string $message
     * @param int $code
     * @throws \JsonException
     */
    #[NoReturn] protected function error(string $message, int $code = 500): void
    {
        $this->setResponseCode($code);
        $this->data['error'] = $message;
        $this->log->error($message, [
            'code'      => $code,
            'exception' => $this->data['exception'] ?? null
        ]);
        $this->response(false);
    }

    /**
     * Gets the root directory of the install path.
     *
     * @param string $suffix
     * @return string
     */
    protected function rootDir(string $suffix = ''): string
    {
        $suffix = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $suffix), '/\\');

        return (str_replace(['/', '\\'], DIRECTORY_SEPARATOR, dirname(dirname(dirname(__DIR__)))))
            . (!empty($suffix) ? DIRECTORY_SEPARATOR . $suffix : '');
    }


    /**
     * PHP-based "rm -rf" command.
     *
     * Recursively removes a directory and all files and subdirectories within.
     *
     * @return void
     */
    protected function rimraf(string $path)
    {
        $dir = new DirectoryIterator($path);

        foreach ($dir as $item) {
            if ($item->isDot()) {
                continue;
            }

            if ($item->isDir()) {
                $this->rimraf($item->getPathname());
            }

            @unlink($item->getPathname());
        }

        @rmdir($path);
    }

    /**
     * Register a custom exception handler for the API.
     *
     * @return void
     */
    protected function setExceptionHandler(): void
    {
        set_exception_handler([$this, 'handleException']);
    }

    /**
     * Handle an uncaught PHP exception.
     *
     * @param \Exception $exception
     * @return void
     */
    public function handleException($exception)
    {
        $this->data['code'] = $exception->getCode();
        $this->data['file'] = $exception->getFile();
        $this->data['line'] = $exception->getLine();
        $this->log->error($exception->getMessage(), ['exception' => $exception]);
        $this->error($exception->getMessage());
    }
}
