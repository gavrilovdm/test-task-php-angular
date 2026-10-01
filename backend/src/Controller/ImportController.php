<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\NotFoundException;
use App\Http\ImportJobPresenter;
use App\Http\JsonResponder;
use App\Http\Request\UploadedImportFileFactory;
use App\Service\Import\ImportService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ImportController
{
    public function __construct(
        private readonly ImportService $imports,
        private readonly ImportJobPresenter $presenter,
        private readonly UploadedImportFileFactory $uploads,
    ) {
    }

    /** POST /api/imports — queues the uploaded file, returns 202 with the job to poll. */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $job = $this->imports->start($this->uploads->fromRequest($request));

        return JsonResponder::json($response, $this->presenter->present($job), 202)
            ->withHeader('Location', '/api/imports/'.$job->getId());
    }

    /** @param array{id: string} $args */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $job = $this->imports->find($args['id']) ?? throw new NotFoundException('Задача импорта не найдена');

        return JsonResponder::json($response, $this->presenter->present($job));
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $limit = (int) ($request->getQueryParams()['limit'] ?? 10);
        $jobs = $this->imports->latest(max(1, min(50, $limit)));

        return JsonResponder::json($response, ['data' => array_map($this->presenter->present(...), $jobs)]);
    }
}
