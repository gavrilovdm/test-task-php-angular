<?php

declare(strict_types=1);

namespace App\Persistence;

/** Persistence session abstraction: transactions and identity-map lifecycle. */
interface UnitOfWork
{
    /**
     * Runs $operation in a transaction and flushes pending changes; rolls back on any exception.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function transactional(callable $operation): mixed;

    /** Detaches all loaded entities (keeps memory flat in long-running processes). */
    public function clear(): void;

    /** False after a failed flush — the session cannot be used any more. */
    public function isOpen(): bool;
}
