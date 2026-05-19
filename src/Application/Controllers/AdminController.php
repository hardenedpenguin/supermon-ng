<?php

declare(strict_types=1);

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use SupermonNg\Services\LocalAllmonGeneratorService;

class AdminController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly LocalAllmonGeneratorService $localAllmonGenerator
    ) {
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
