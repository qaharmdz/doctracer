<?php

declare(strict_types=1);

namespace Example;

/**
 * Comment summary
 *
 * @author You Know Who <no-reply@example.com>
 */
abstract class ParentClass
{
    private int $count = 0;

    /**
     * Increament
     *
     * @return int
     */
    public function count(): int
    {
        return ++$this->count;
    }
}
