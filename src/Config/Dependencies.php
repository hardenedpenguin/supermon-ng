<?php

use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Processor\UidProcessor;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\CacheInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use SupermonNg\Services\DatabaseGenerationService;
use SupermonNg\Services\AllStarConfigService;

return [
    // Logger
    LoggerInterface::class => function () {
        $logger = new Logger('supermon-ng');
        
        // Add rotating file handler
        $logger->pushHandler(new RotatingFileHandler(
            $_ENV['LOG_PATH'] ?? 'logs/app.log',
            $_ENV['LOG_MAX_FILES'] ?? 30,
            $_ENV['LOG_LEVEL'] ?? Logger::INFO
        ));
        
        // Add UID processor for request tracking
        $logger->pushProcessor(new UidProcessor());
        
        return $logger;
    },
    
    // Database connection
    Connection::class => function () {
        $dbType = $_ENV['DB_TYPE'] ?? 'sqlite';
        $dbPath = $_ENV['DB_PATH'] ?? 'database/supermon-ng.db';
        
        if ($dbType === 'sqlite') {
            $connectionParams = [
                'driver' => 'pdo_sqlite',
                'path' => $dbPath,
            ];
        } else {
            $connectionParams = [
                'driver' => 'pdo_mysql',
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'port' => $_ENV['DB_PORT'] ?? 3306,
                'dbname' => $_ENV['DB_NAME'] ?? 'supermon_ng',
                'user' => $_ENV['DB_USER'] ?? 'root',
                'password' => $_ENV['DB_PASSWORD'] ?? '',
                'charset' => 'utf8mb4',
            ];
        }
        
        return DriverManager::getConnection($connectionParams);
    },
    
    // Cache
    CacheInterface::class => function () {
        return new FilesystemAdapter(
            'supermon-ng',
            $_ENV['CACHE_TTL'] ?? 3600,
            'cache/'
        );
    },
    
    // JWT Service
    'jwt.service' => function () {
        return new class($_ENV['JWT_SECRET'] ?? 'your-secret-key') {
            private string $secret;
            
            public function __construct(string $secret) {
                $this->secret = $secret;
            }
            
            public function encode(array $payload): string {
                return JWT::encode($payload, $this->secret, 'HS256');
            }
            
            public function decode(string $token): array {
                return (array) JWT::decode($token, new Key($this->secret, 'HS256'));
            }
        };
    },
    
    // AllStar Configuration Service
    AllStarConfigService::class => function ($container) {
        return new AllStarConfigService(
            $container->get(LoggerInterface::class),
            $_ENV['USER_FILES_PATH'] ?? 'user_files/'
        );
    },
    
    // AMI Client - now uses AllStarConfigService
    'ami.client' => function ($container) {
        return new class($container->get(AllStarConfigService::class)) {
            private AllStarConfigService $configService;
            private array $connections = [];
            
            public function __construct(AllStarConfigService $configService) {
                $this->configService = $configService;
            }
            
            public function connect(string $nodeId, ?string $username = null): bool {
                try {
                    $config = $this->configService->getAmiConfig($nodeId, $username);
                    
                    // Include AMI functions
                    require_once __DIR__ . '/../includes/amifunctions.inc';
                    
                    // Create AMI connection
                    $ami = new \SimpleAmiClient();
                    $connected = $ami->connect($config['host'], $config['port']);
                    
                    if ($connected) {
                        $loggedIn = $ami->login($config['username'], $config['password']);
                        if ($loggedIn) {
                            $this->connections[$nodeId] = $ami;
                            return true;
                        }
                    }
                    
                    return false;
                } catch (Exception $e) {
                    return false;
                }
            }
            
            public function sendCommand(string $nodeId, string $command, ?string $username = null): string {
                try {
                    if (!isset($this->connections[$nodeId])) {
                        if (!$this->connect($nodeId, $username)) {
                            return "Error: Failed to connect to AMI";
                        }
                    }
                    
                    $ami = $this->connections[$nodeId];
                    $response = \SimpleAmiClient::command($ami, "Command", ["Command" => $command]);
                    
                    return $response ?: "No response";
                } catch (Exception $e) {
                    return "Error: " . $e->getMessage();
                }
            }
            
            public function disconnect(string $nodeId): void {
                if (isset($this->connections[$nodeId])) {
                    unset($this->connections[$nodeId]);
                }
            }
        };
    },
    
    // Database Generation Service
    DatabaseGenerationService::class => function ($container) {
        return new DatabaseGenerationService(
            $container->get(LoggerInterface::class),
            $container->get(CacheInterface::class)
        );
    },
];
