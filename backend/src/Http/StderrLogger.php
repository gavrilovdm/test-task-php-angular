<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Log\AbstractLogger;

/** Minimal PSR-3 logger writing one JSON line per record to stderr (collected by `docker logs`). */
final class StderrLogger extends AbstractLogger
{
    /** @var resource */
    private $stream;

    public function __construct(string $target = 'php://stderr')
    {
        $stream = fopen($target, 'a');
        if (false === $stream) {
            throw new \RuntimeException('Cannot open log stream '.$target);
        }
        $this->stream = $stream;
    }

    /**
     * @param mixed[] $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $e = $context['exception'];
            $context['exception'] = $e::class.': '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine();
        }

        fwrite($this->stream, json_encode([
            'time' => date(\DATE_ATOM),
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PARTIAL_OUTPUT_ON_ERROR)."\n");
    }
}
