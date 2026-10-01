<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\ImportJob;
use PHPUnit\Framework\TestCase;

final class ImportJobTest extends TestCase
{
    public function testTracksProgressAndCounters(): void
    {
        $job = new ImportJob('file.xlsx', '/tmp/file.xlsx');
        self::assertSame(ImportJob::STATUS_PENDING, $job->getStatus());
        self::assertSame(0, $job->getProgress());

        $job->start(4);
        $job->markRowCreated();
        $job->markRowUpdated();
        $job->markRowFailed();
        $job->addError('bad price', 4, 'X', 'price');

        self::assertSame(ImportJob::STATUS_PROCESSING, $job->getStatus());
        self::assertSame(75, $job->getProgress());
        self::assertSame([1, 1, 1], [$job->getCreatedCount(), $job->getUpdatedCount(), $job->getFailedCount()]);
        self::assertSame('bad price', $job->getErrors()[0]['message']);

        $job->complete();
        self::assertTrue($job->isFinished());
        self::assertSame(100, $job->getProgress());
    }

    public function testFailAddsReason(): void
    {
        $job = new ImportJob('file.xlsx', '/tmp/file.xlsx');
        $job->fail('Broken file');

        self::assertSame(ImportJob::STATUS_FAILED, $job->getStatus());
        self::assertSame('Broken file', $job->getErrors()[0]['message']);
    }
}
