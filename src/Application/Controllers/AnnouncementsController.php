<?php

declare(strict_types=1);

namespace SupermonNg\Application\Controllers;

use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use SupermonNg\Services\AnnouncementsService;
use SupermonNg\Services\ApiResponseHelper;
use SupermonNg\Services\SessionService;
use SupermonNg\Services\UserPermissionService;

class AnnouncementsController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly AnnouncementsService $announcementsService,
        private readonly UserPermissionService $userPermissionService,
        private readonly SessionService $sessionService
    ) {
    }

    public function getStatus(Request $request, Response $response): Response
    {
        return $this->withAnnouncePermission($response, function (string $user) use ($response): Response {
            $data = $this->announcementsService->getStatus($user);

            return ApiResponseHelper::json($response, [
                'success' => true,
                'data' => $data,
            ]);
        });
    }

    public function play(Request $request, Response $response): Response
    {
        return $this->withAnnouncePermission($response, function (string $user) use ($request, $response): Response {
            $body = $this->parseJson($request);
            $scope = strtolower((string) ($body['scope'] ?? 'local'));
            if ($scope === 'global' && !$this->userPermissionService->hasPermission($user, 'ANNOUNCEGLOBALUSER')) {
                return ApiResponseHelper::error($response, 'You are not authorized for global playback.', 403);
            }

            $result = $this->announcementsService->play($body);

            return ApiResponseHelper::json($response, $result);
        });
    }

    public function upload(Request $request, Response $response): Response
    {
        return $this->withAnnouncePermission($response, function (string $user) use ($request, $response): Response {
            unset($user);
            $uploaded = $request->getUploadedFiles()['file'] ?? null;
            if ($uploaded === null || $uploaded->getError() !== UPLOAD_ERR_OK) {
                return ApiResponseHelper::error($response, 'No file uploaded.', 400);
            }

            $tmp = tempnam(sys_get_temp_dir(), 'smng-ann-');
            if ($tmp === false) {
                return ApiResponseHelper::error($response, 'Could not create temporary file.', 500);
            }

            $uploaded->moveTo($tmp);
            $parsed = $request->getParsedBody();
            $name = is_array($parsed) ? ($parsed['name'] ?? null) : null;

            try {
                $result = $this->announcementsService->upload(
                    $tmp,
                    $uploaded->getClientFilename() ?? 'upload',
                    is_string($name) ? $name : null
                );
            } catch (Exception $e) {
                @unlink($tmp);

                return ApiResponseHelper::error($response, $e->getMessage(), 400);
            }

            return ApiResponseHelper::json($response, $result);
        });
    }

    public function tts(Request $request, Response $response): Response
    {
        return $this->withAnnouncePermission($response, function (string $user) use ($request, $response): Response {
            unset($user);
            $body = $this->parseJson($request);
            try {
                $result = $this->announcementsService->generateTts(
                    (string) ($body['text'] ?? ''),
                    (string) ($body['name'] ?? ''),
                    (string) ($body['node'] ?? ''),
                    isset($body['voice']) ? (string) $body['voice'] : null
                );
            } catch (Exception $e) {
                return ApiResponseHelper::error($response, $e->getMessage(), 400);
            }

            return ApiResponseHelper::json($response, $result);
        });
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        return $this->withAnnouncePermission($response, function (string $user) use ($response, $args): Response {
            unset($user);
            $name = (string) ($args['name'] ?? '');
            try {
                $result = $this->announcementsService->deleteFile($name);
            } catch (Exception $e) {
                return ApiResponseHelper::error($response, $e->getMessage(), 400);
            }

            return ApiResponseHelper::json($response, $result);
        });
    }

    public function listVoices(Request $request, Response $response): Response
    {
        return $this->withAnnouncePermission($response, function (string $user) use ($response): Response {
            unset($user);
            $data = $this->announcementsService->getVoices();

            return ApiResponseHelper::json($response, [
                'success' => true,
                'data' => $data,
            ]);
        });
    }

    public function installVoice(Request $request, Response $response): Response
    {
        return $this->withAnnouncePermission($response, function (string $user) use ($request, $response): Response {
            unset($user);
            $body = $this->parseJson($request);
            $voiceId = (string) ($body['voice_id'] ?? $body['voice'] ?? '');

            try {
                $result = $this->announcementsService->installVoice($voiceId);
            } catch (Exception $e) {
                return ApiResponseHelper::error($response, $e->getMessage(), 400);
            }

            return ApiResponseHelper::json($response, $result);
        });
    }

    public function listSchedules(Request $request, Response $response): Response
    {
        return $this->withSchedPermission($response, function (string $user) use ($response): Response {
            unset($user);
            try {
                $schedules = $this->announcementsService->listSchedules();
            } catch (Exception $e) {
                return ApiResponseHelper::error($response, $e->getMessage(), 500);
            }

            return ApiResponseHelper::json($response, [
                'success' => true,
                'data' => $schedules,
            ]);
        });
    }

    public function addSchedule(Request $request, Response $response): Response
    {
        return $this->withSchedPermission($response, function (string $user) use ($request, $response): Response {
            $body = $this->parseJson($request);
            $scope = strtolower((string) ($body['scope'] ?? 'local'));
            if ($scope === 'global' && !$this->userPermissionService->hasPermission($user, 'ANNOUNCEGLOBALUSER')) {
                return ApiResponseHelper::error($response, 'You are not authorized for global schedules.', 403);
            }

            try {
                $result = $this->announcementsService->addSchedule($body);
            } catch (Exception $e) {
                return ApiResponseHelper::error($response, $e->getMessage(), 400);
            }

            return ApiResponseHelper::json($response, $result);
        });
    }

    public function toggleSchedule(Request $request, Response $response, array $args): Response
    {
        return $this->withSchedPermission($response, function (string $user) use ($request, $response, $args): Response {
            unset($user);
            $body = $this->parseJson($request);
            $enabled = !empty($body['enabled']);
            $id = (string) ($args['id'] ?? '');

            try {
                $result = $this->announcementsService->toggleSchedule($id, $enabled);
            } catch (Exception $e) {
                return ApiResponseHelper::error($response, $e->getMessage(), 400);
            }

            return ApiResponseHelper::json($response, $result);
        });
    }

    public function deleteSchedule(Request $request, Response $response, array $args): Response
    {
        return $this->withSchedPermission($response, function (string $user) use ($response, $args): Response {
            unset($user);
            $id = (string) ($args['id'] ?? '');

            try {
                $result = $this->announcementsService->deleteSchedule($id);
            } catch (Exception $e) {
                return ApiResponseHelper::error($response, $e->getMessage(), 400);
            }

            return ApiResponseHelper::json($response, $result);
        });
    }

    /**
     * @param callable(string): Response $handler
     */
    private function withAnnouncePermission(Response $response, callable $handler): Response
    {
        try {
            $user = $this->sessionService->getCurrentUser();
            if ($user === null) {
                return ApiResponseHelper::error($response, 'Authentication required.', 401);
            }
            if (!$this->userPermissionService->hasPermission($user, 'ANNOUNCEUSER')) {
                return ApiResponseHelper::error($response, 'You are not authorized to use announcements.', 403);
            }

            return $handler($user);
        } catch (Exception $e) {
            $this->logger->error('Announcements request failed', ['error' => $e->getMessage()]);

            return ApiResponseHelper::error(
                $response,
                ApiResponseHelper::safeExceptionMessage($e),
                500
            );
        }
    }

    /**
     * @param callable(string): Response $handler
     */
    private function withSchedPermission(Response $response, callable $handler): Response
    {
        try {
            $user = $this->sessionService->getCurrentUser();
            if ($user === null) {
                return ApiResponseHelper::error($response, 'Authentication required.', 401);
            }
            if (!$this->userPermissionService->hasPermission($user, 'ANNOUNCESCHEDUSER')) {
                return ApiResponseHelper::error($response, 'You are not authorized to manage schedules.', 403);
            }

            return $handler($user);
        } catch (Exception $e) {
            $this->logger->error('Announcements schedule request failed', ['error' => $e->getMessage()]);

            return ApiResponseHelper::error(
                $response,
                ApiResponseHelper::safeExceptionMessage($e),
                500
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJson(Request $request): array
    {
        $body = $request->getParsedBody();
        if (is_array($body)) {
            return $body;
        }

        $raw = (string) $request->getBody();
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
