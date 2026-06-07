<?php

declare(strict_types=1);

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use SupermonNg\Services\ApiResponseHelper;
use SupermonNg\Services\ConfigBackupService;
use SupermonNg\Services\ConfigImportService;
use SupermonNg\Services\LocalAllmonGeneratorService;

class AdminController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly LocalAllmonGeneratorService $localAllmonGenerator,
        private readonly ConfigBackupService $configBackupService,
        private readonly ConfigImportService $configImportService
    ) {
    }

    /**
     * Regenerate user_files/allmon.ini from rpt.conf + manager.conf.
     * JSON body: { "force": true } overwrites existing (with .bak backup); default false returns 409 if file exists.
     */
    public function generateLocalAllmon(Request $request, Response $response): Response
    {
        $this->logger->info('Admin generate local allmon request');

        $parsed = $this->parseJson($request);
        $force = !empty($parsed['force']);

        $result = $this->localAllmonGenerator->writeAllmonIni('allmon.ini', false, $force);

        if (!$result['success']) {
            $msg = $result['message'];
            $status = str_contains($msg, 'exists') ? 409 : 400;

            return ApiResponseHelper::json($response, [
                'success' => false,
                'message' => $msg,
                'path' => $result['path'] ?? null,
                'timestamp' => date('c'),
            ], $status);
        }

        return ApiResponseHelper::json($response, [
            'success' => true,
            'message' => $result['message'],
            'path' => $result['path'] ?? null,
            'nodes' => $result['nodes'] ?? [],
            'timestamp' => date('c'),
        ]);
    }

    public function exportConfig(Request $request, Response $response): Response
    {
        $result = $this->configBackupService->createExportArchive();
        if (!$result['success'] || empty($result['path'])) {
            return ApiResponseHelper::error($response, $result['message'] ?? 'Export failed', 500);
        }

        $zipPath = $result['path'];
        $filename = $result['filename'] ?? 'supermon-ng-config.zip';
        $contents = file_get_contents($zipPath);
        @unlink($zipPath);

        if ($contents === false) {
            return ApiResponseHelper::error($response, 'Could not read export archive', 500);
        }

        $response->getBody()->write($contents);

        return $response
            ->withHeader('Content-Type', 'application/zip')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string) strlen($contents));
    }

    public function importConfig(Request $request, Response $response): Response
    {
        $uploaded = $request->getUploadedFiles()['archive'] ?? null;
        if ($uploaded === null || $uploaded->getError() !== UPLOAD_ERR_OK) {
            return ApiResponseHelper::error($response, 'No archive file uploaded', 400);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'smng-import-');
        if ($tmp === false) {
            return ApiResponseHelper::error($response, 'Could not create temporary file', 500);
        }

        $uploaded->moveTo($tmp);
        $result = $this->configBackupService->importArchive($tmp);
        @unlink($tmp);

        if (!$result['success']) {
            return ApiResponseHelper::error($response, $result['message'], 400);
        }

        return ApiResponseHelper::json($response, $result);
    }

    public function importAllScanFavorites(Request $request, Response $response): Response
    {
        $body = $this->parseJson($request);
        $content = (string) ($body['content'] ?? '');
        if ($content === '') {
            return ApiResponseHelper::error($response, 'Import content is required', 400);
        }

        $result = $this->configImportService->importAllScanFavorites($content);
        if (!$result['success']) {
            return ApiResponseHelper::error($response, $result['message'], 400);
        }

        return ApiResponseHelper::json($response, $result);
    }

    public function importAllmon3Nodes(Request $request, Response $response): Response
    {
        $body = $this->parseJson($request);
        $content = (string) ($body['content'] ?? '');
        if ($content === '') {
            return ApiResponseHelper::error($response, 'Import content is required', 400);
        }

        $result = $this->configImportService->importAllmon3Nodes($content);
        if (!$result['success']) {
            return ApiResponseHelper::error($response, $result['message'], 400);
        }

        return ApiResponseHelper::json($response, $result);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJson(Request $request): array
    {
        $ct = $request->getHeaderLine('Content-Type');
        if (!str_contains($ct, 'application/json')) {
            return [];
        }
        $raw = (string) $request->getBody();
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
