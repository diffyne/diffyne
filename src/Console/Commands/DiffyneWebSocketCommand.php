<?php

namespace Diffyne\Console\Commands;

use Diffyne\WebSocket\DiffyneController;
use Exception;
use Illuminate\Console\Command;
use Sockeon\Sockeon\Config\ServerConfig;
use Sockeon\Sockeon\Connection\Server;
use Sockeon\Sockeon\Logging\Logger;
use Sockeon\Sockeon\Logging\LogLevel;

class DiffyneWebSocketCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'diffyne:websocket';

    /**
     * The console command description.
     */
    protected $description = 'Start the Diffyne WebSocket server using Sockeon';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $host = config('diffyne.websocket.host', '127.0.0.1');
        $port = config('diffyne.websocket.port', 6001);
        $debug = config('diffyne.debug', false);
        $key = config('diffyne.websocket.key', null);

        $logger = new Logger(LogLevel::INFO, false, true, storage_path('logs/diffyne-websocket'), false);
        $config = new ServerConfig([
            'host' => $host,
            'port' => (int) $port,
            'debug' => $debug,
            'auth_key' => $key,
            'cors' => [
                'allowed_origins' => config('diffyne.websocket.cors.allowed_origins', ['*']),
                'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
                'allowed_headers' => ['Content-Type', 'Authorization', 'X-CSRF-TOKEN']
            ],
            'logger' => $logger,
        ]);

        $server = new Server($config);

        $server->registerController(new DiffyneController());

        try {
            $server->run();
        } catch (Exception $e) {
            $this->error("Failed to start WebSocket server: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
