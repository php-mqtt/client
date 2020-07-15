<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Concerns;

/**
 * Provides common methods to work with buffers.
 *
 * @package PhpMqtt\Client\Concerns
 */
trait WorksWithBuffers
{
    /**
     * Pops the first $limit bytes from the given buffer and returns them.
     *
     * @param string $buffer
     * @param int    $limit
     * @return string
     */
    protected function pop(string &$buffer, int $limit): string
    {
        $limit = min(strlen($buffer), $limit);

        $result = substr($buffer, 0, $limit);
        $buffer = substr($buffer, $limit);

        return $result;
    }
}
