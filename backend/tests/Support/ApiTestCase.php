<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Kernel;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\UploadedFile;

/** Runs requests through the full Slim application (routing, middleware, controllers, DB). */
abstract class ApiTestCase extends DatabaseTestCase
{
    /** @var App<ContainerInterface> */
    protected App $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = Kernel::createApp($this->container);
    }

    /**
     * @param array<string, mixed>                 $query
     * @param array<string, mixed>|null            $json
     * @param array<string, UploadedFileInterface> $files
     * @param array<string, string>                $headers
     */
    protected function request(string $method, string $path, array $query = [], ?array $json = null, array $files = [], array $headers = []): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $path, ['REMOTE_ADDR' => '127.0.0.1'])
            ->withQueryParams($query);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if (null !== $json) {
            $request = $request->withHeader('Content-Type', 'application/json')
                ->withBody((new StreamFactory())->createStream((string) json_encode($json)));
        }
        if ([] !== $files) {
            $request = $request->withUploadedFiles($files);
        }

        return $this->app->handle($request);
    }

    /**
     * @param array<string, mixed>                 $query
     * @param array<string, UploadedFileInterface> $files
     */
    protected function authorized(string $method, string $path, array $query = [], array $files = []): ResponseInterface
    {
        return $this->request($method, $path, $query, null, $files, ['Authorization' => 'Bearer '.$this->token()]);
    }

    protected function token(): string
    {
        $response = $this->request('POST', '/api/auth/login', json: ['email' => 'admin@example.com', 'password' => 'admin123']);
        self::assertSame(200, $response->getStatusCode());

        return (string) self::json($response)['token'];
    }

    /** @return array<string, mixed> */
    protected static function json(ResponseInterface $response): array
    {
        $data = json_decode((string) $response->getBody(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    protected static function uploadedFile(string $source, string $clientName): UploadedFile
    {
        $tmp = (string) tempnam(sys_get_temp_dir(), 'upl');
        copy($source, $tmp);

        return new UploadedFile((new StreamFactory())->createStreamFromFile($tmp), $clientName, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', (int) filesize($tmp), \UPLOAD_ERR_OK);
    }
}
