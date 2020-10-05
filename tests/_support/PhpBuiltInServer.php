<?php

use Codeception\Configuration;

/**
 * Start/stop the PHP built-in web server on localhost
 */
class PhpBuiltInServer
{
    /**
     * HTTP port
     * @var int
     */
    protected $port;
    
    /**
     * @var resource
     */
    protected $handle;

    /**
     * @var resource[]
     */
    protected $pipes;

    /**
     * Class constructor
     *
     * @param string   $documentRoot  Path to router file.
     * @param int      $port
     * @param string[] $env           Environment variables
     */
    public function __construct(string $documentRoot, int $port, array $env = [])
    {
        $this->port = $port;
        
        $this->run($documentRoot, $env);
        $this->testConnection();
    }

    /**
     * Start the web server
     *
     * @param string   $documentRoot  Path to router file.
     * @param string[] $env           Environment variables
     */
    protected function run(string $documentRoot, array $env): void
    {
        if ($this->handle) {
            trigger_error("Built-in webserver on port {$this->port} already started", E_USER_NOTICE);
            return;
        }

        $cmd = $this->getCommand($documentRoot);
        $descriptorSpec = [
            ["pipe", "r"],
            ['file', Configuration::logDir() . "phpbuiltinserver.{$this->port}.output.txt", 'w'],
            ['file', Configuration::logDir() . "phpbuiltinserver.{$this->port}.errors.txt", 'a']
        ];
        $pipes = [];

        $this->handle = proc_open($cmd, $descriptorSpec, $this->pipes, ROOT_DIR, $env, ['bypass_shell' => true]);
        fclose($this->pipes[0]); // close stdin

        $this->registerShutdown();

        usleep(10000);
        $status = proc_get_status($this->handle);

        if (!$status['running']) {
            proc_close($this->handle);

            $error = stream_get_contents($pipes[2]) ?: stream_get_contents($pipes[1]);
            throw new \Exception("Failed to start PHP built-in web server. $error");
        }
    }

    /**
     * Get the executable command to start the webserver.
     */
    protected function getCommand(string $documentRoot): string
    {
        // Platform uses POSIX process handling. Use exec to avoid controlling the shell process instead of the PHP
        // interpreter.
        $exec = (PHP_OS !== 'WINNT' && PHP_OS !== 'WIN32') ? 'exec ' : '';

        return $exec . escapeshellcmd(PHP_BINARY)
            . " -S localhost:{$this->port}"
            . " -t " . escapeshellarg($documentRoot)
            . ($this->isRemoteDebug() ? ' -dxdebug.remote_enable=1' : '');
    }

    /**
     * Check if codeception remote debugging is available.
     */
    protected function isRemoteDebug(): bool
    {
        return Configuration::isExtensionEnabled('Codeception\Extension\RemoteDebug');
    }

    /**
     * Make sure we can connect to the webserver
     */
    protected function testConnection()
    {
        for ($i=0; $i < 5; $i++) {
            if ($this->connect()) {
                return;
            }
            sleep(1);
        }
        
        $err = error_get_last();
        throw new \Exception("Failed to connect to built-in web server: {$err['message']}");
    }

    /**
     * Connect to the webserver
     */
    protected function connect(): bool
    {
        $sock = @fsockopen('localhost', $this->port, $errno, $errstr, 1);

        return is_resource($sock) && $errno === 0;
    }
    
    /**
     * Stop the web server
     */
    public function __destruct()
    {
        $this->stop();
    }

    /**
     * Stop the web server
     */
    public function stop(): void
    {
        if ($this->handle === null) {
            return;
        }

        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        proc_terminate($this->handle, 15);
        unset($this->handle);
    }

    /**
     * Register shutdown function to stop webserver on an error.
     */
    protected function registerShutdown(): void
    {
        $handle = $this->handle;

        register_shutdown_function(function () use ($handle) {
            if (is_resource($handle)) {
                proc_terminate($handle);
            }
        });
    }
}
