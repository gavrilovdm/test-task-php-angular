<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Messenger\ImportProductsMessage;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * End-to-end backend scenario: upload → job queued → worker processes the message → client polls status → products available.
 */
final class ImportFlowTest extends ApiTestCase
{
    private const SAMPLE = __DIR__.'/../fixtures/products.xlsx';

    /** Simulates the queue worker: handles every message waiting in the in-memory transport. */
    private function runWorker(): void
    {
        /** @var InMemoryTransport $transport */
        $transport = $this->container->get('messenger.transport.async');
        $bus = $this->container->get(MessageBusInterface::class);
        foreach ($transport->get() as $envelope) {
            $bus->dispatch($envelope->with(new ReceivedStamp('async')));
            $transport->ack($envelope);
        }
    }

    public function testFullImportFlow(): void
    {
        $response = $this->authorized('POST', '/api/imports', files: ['file' => self::uploadedFile(self::SAMPLE, 'import.xlsx')]);

        self::assertSame(202, $response->getStatusCode());
        $job = self::json($response);
        self::assertSame('pending', $job['status']);
        self::assertSame('/api/imports/'.$job['id'], $response->getHeaderLine('Location'));

        /** @var InMemoryTransport $transport */
        $transport = $this->container->get('messenger.transport.async');
        $sent = $transport->getSent();
        self::assertCount(1, $sent, 'import is queued, not processed synchronously');
        self::assertInstanceOf(ImportProductsMessage::class, $sent[0]->getMessage());
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM products'));

        $this->runWorker();

        $status = self::json($this->authorized('GET', '/api/imports/'.$job['id']));
        self::assertSame('completed', $status['status']);
        self::assertSame(100, $status['progress']);
        self::assertSame(40, $status['createdCount']);
        self::assertNotEmpty(array_filter($status['errors'], static fn (array $e) => 34 === $e['row']));

        $products = self::json($this->authorized('GET', '/api/products', ['limit' => '100']));
        self::assertSame(40, $products['meta']['total']);

        $latest = self::json($this->authorized('GET', '/api/imports'));
        self::assertSame($job['id'], $latest['data'][0]['id']);
    }

    public function testRejectsInvalidUpload(): void
    {
        $txt = (string) tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($txt, 'hello');

        $response = $this->authorized('POST', '/api/imports', files: ['file' => self::uploadedFile($txt, 'notes.txt')]);

        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('file', self::json($response)['errors']);
    }

    public function testRequiresFile(): void
    {
        self::assertSame(422, $this->authorized('POST', '/api/imports')->getStatusCode());
    }

    public function testRateLimitOnImportEndpoint(): void
    {
        $token = $this->token();
        $headers = ['Authorization' => 'Bearer '.$token];

        for ($i = 0; $i < 5; ++$i) {
            $response = $this->request('POST', '/api/imports', headers: $headers);
            self::assertSame(422, $response->getStatusCode());
            self::assertSame((string) (4 - $i), $response->getHeaderLine('X-RateLimit-Remaining'));
        }

        $limited = $this->request('POST', '/api/imports', headers: $headers);
        self::assertSame(429, $limited->getStatusCode());
        self::assertGreaterThan(0, (int) $limited->getHeaderLine('Retry-After'));
    }

    public function testUnknownJobReturns404(): void
    {
        self::assertSame(404, $this->authorized('GET', '/api/imports/00000000-0000-4000-8000-000000000000')->getStatusCode());
        self::assertSame(404, $this->authorized('GET', '/api/imports/not-a-uuid')->getStatusCode());
    }
}
