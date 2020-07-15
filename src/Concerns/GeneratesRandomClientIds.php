<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Concerns;

/**
 * Provides common methods used to generate random client ids.
 *
 * @package PhpMqtt\Client\Concerns
 */
trait GeneratesRandomClientIds
{
    /**
     * Generates a random client id in the form of an md5 hash.
     *
     * @return string
     */
    protected function generateRandomClientId(): string
    {
        return substr(md5(uniqid((string) mt_rand(), true)), 0, 20);
    }
}
