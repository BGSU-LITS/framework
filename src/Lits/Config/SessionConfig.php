<?php

declare(strict_types=1);

namespace Lits\Config;

use Lits\Config;
use Lits\Exception\InvalidConfigException;
use Throwable;

use function Safe\base64_decode;

final class SessionConfig extends Config
{
    public const MINIMUM_BITS = 32;

    public int $expires = 3600;
    public string $key = '';

    /** @throws InvalidConfigException */
    public function testKey(): void
    {
        if ($this->key === '') {
            throw new InvalidConfigException(
                'The session key must be specified'
            );
        }

        try {
            $decoded = base64_decode($this->key, true);
        } catch (Throwable $exception) {
            throw new InvalidConfigException(
                'The session key must be base64 encoded',
                0,
                $exception
            );
        }

        if (\base64_encode($decoded) !== $this->key) {
            throw new InvalidConfigException(
                'The session key must be base64 encoded'
            );
        }

        if (\strlen($decoded) < self::MINIMUM_BITS) {
            throw new InvalidConfigException(
                'The session key must have ' . self::MINIMUM_BITS .
                ' bits of entropy',
            );
        }
    }
}
