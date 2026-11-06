<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\ApiException;
use App\Http\JobPresenter;
use App\Http\JsonResponse;
use App\Import\ImportService;
use App\Repository\ImportJobRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Routing\RouteContext;

final class ImportController
{
    public function __construct(
        private readonly ImportService $imports,
        private readonly ImportJobRepository $jobs,
        private readonly JobPresenter $presenter,
        private readonly JsonResponse $json,
    ) {
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('userId');
        if (!is_int($userId)) {
            throw new ApiException('Требуется аутентификация', 401);
        }
        $uploaded = $request->getUploadedFiles()['file'] ?? null;
        if (!$uploaded instanceof UploadedFileInterface) {
            throw new ApiException('Выберите файл .xlsx', 422);
        }
        $job = $this->imports->enqueue($uploaded, $userId);

        return $this->json->write($response, ['data' => $this->presenter->present($job)], 202);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $id = RouteContext::fromRequest($request)->getRoute()?->getArgument('id');
        if (!is_string($id) || preg_match('/^[0-9a-fA-F-]{36}$/', $id) !== 1) {
            throw new ApiException('Задача импорта не найдена', 404);
        }
        $userId = $request->getAttribute('userId');
        if (!is_int($userId)) {
            throw new ApiException('Требуется аутентификация', 401);
        }
        $job = $this->jobs->find($id);
        if ($job === null) {
            throw new ApiException('Задача импорта не найдена', 404);
        }
        if ($job->userId() !== null && $job->userId() !== $userId) {
            throw new ApiException('Задача импорта не найдена', 404);
        }

        return $this->json->write($response, ['data' => $this->presenter->present($job)]);
    }
}
