<?php

declare(strict_types=1);

namespace Lits\Config;

use Lits\Config;
use Lits\Exception\InvalidConfigException;

use function Safe\base64_decode;
use function Safe\sprintf;

final class SessionConfig extends Config
{
    public const MINIMUM_BITS = 32;

    public int $expires = 3600;
    public string $key = '';

    public function testKey(): void
    {
        if ($this->key === '') {
            throw new InvalidConfigException(
                'The session key must be specified'
            );
        }

        $decoded = base64_decode($this->key, true);

        if (\base64_encode($decoded) !== $this->key) {
            throw new InvalidConfigException(
                'The session key must be base64 encoded'
            );
        }

        if (\strlen($decoded) < self::MINIMUM_BITS) {
            throw new InvalidConfigException(sprintf(
                'The session key must have %s bits of entropy',
                self::MINIMUM_BITS
            ));
        }
    }
}
