<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Context;

/**
 * Explicit context stack. Long-running workers must call flush() between
 * requests/jobs to avoid tenant or transaction leaks.
 *
 * @api public
 */
final class ContextScope
{
    /** @var list<Context> */
    private array $stack = [];

    private ?Context $current = null;

    public function current(): Context
    {
        return $this->current ?? Context::empty();
    }

    public function in(Context $context): void
    {
        if ($this->current !== null) {
            $this->stack[] = $this->current;
        }

        $this->current = $context;
    }

    public function out(): ?Context
    {
        $previous = $this->current;
        $this->current = array_pop($this->stack);

        return $previous;
    }

    /**
     * Run a callback with a context and always restore the previous one.
     *
     * @template T
     *
     * @param  callable(Context): T  $callback
     * @return T
     */
    public function run(Context $context, callable $callback): mixed
    {
        $this->in($context);

        try {
            return $callback($context);
        } finally {
            $this->out();
        }
    }

    public function flush(): void
    {
        $this->stack = [];
        $this->current = null;
    }

    public function depth(): int
    {
        return count($this->stack) + ($this->current === null ? 0 : 1);
    }
}
