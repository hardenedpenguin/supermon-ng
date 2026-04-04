<?php

declare(strict_types=1);

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use SupermonNg\Services\LocalAllmonGeneratorService;

class AdminController
{
    private LoggerInterface $logger;
    private LocalAllmonGeneratorService $localAllmonGenerator;

    public function __construct(LoggerInterface $logger, LocalAllmonGeneratorService $localAllmonGenerator)
    {
        $this->logger = $logger;
        $this->localAllmonGenerator = $localAllmonGenerator;
    }

    public function listUsers(Request $request, Response $response): Response
    {
        $this->logger->info('Admin list users request');
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Admin list users endpoint - to be implemented',
            'timestamp' => date('c')
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function createUser(Request $request, Response $response): Response
    {
        $this->logger->info('Admin create user request');
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Admin create user endpoint - to be implemented',
            'timestamp' => date('c')
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function updateUser(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'] ?? null;
        $this->logger->info("Admin update user request for ID: $id");
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => "Admin update user endpoint for ID '$id' - to be implemented",
            'timestamp' => date('c')
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function deleteUser(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'] ?? null;
        $this->logger->info("Admin delete user request for ID: $id");
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => "Admin delete user endpoint for ID '$id' - to be implemented",
            'timestamp' => date('c')
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function backup(Request $request, Response $response): Response
    {
        $this->logger->info('Admin backup request');
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Admin backup endpoint - to be implemented',
            'timestamp' => date('c')
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function restore(Request $request, Response $response): Response
    {
        $this->logger->info('Admin restore request');
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Admin restore endpoint - to be implemented',
            'timestamp' => date('c')
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function clearCache(Request $request, Response $response): Response
    {
        $this->logger->info('Admin clear cache request');
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Admin clear cache endpoint - to be implemented',
            'timestamp' => date('c')
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Regenerate user_files/allmon.ini from rpt.conf + manager.conf.
     * JSON body: { "force": true } overwrites existing (with .bak backup); default false returns 409 if file exists.
     */
    public function generateLocalAllmon(Request $request, Response $response): Response
    {
        $this->logger->info('Admin generate local allmon request');

        $parsed = [];
        $ct = $request->getHeaderLine('Content-Type');
        if (str_contains($ct, 'application/json')) {
            $raw = (string) $request->getBody();
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                $parsed = is_array($decoded) ? $decoded : [];
            }
        }
        $force = !empty($parsed['force']);

        $result = $this->localAllmonGenerator->writeAllmonIni('allmon.ini', false, $force);

        if (!$result['success']) {
            $msg = $result['message'];
            $status = str_contains($msg, 'exists') ? 409 : 400;
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $msg,
                'path' => $result['path'] ?? null,
                'timestamp' => date('c'),
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => $result['message'],
            'path' => $result['path'] ?? null,
            'nodes' => $result['nodes'] ?? [],
            'timestamp' => date('c'),
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}


