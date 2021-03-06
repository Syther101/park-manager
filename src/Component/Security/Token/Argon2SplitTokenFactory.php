<?php

declare(strict_types=1);

/*
 * This file is part of the Park-Manager project.
 *
 * Copyright (c) the Contributors as noted in the AUTHORS file.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ParkManager\Component\Security\Token;

use ParagonIE\Halite\HiddenString;
use function random_bytes;

/**
 * Uses Libsodium Argon2i(d) for hashing the SplitToken verifier.
 *
 * Configuration accepts the following (all integer):
 *
 * 'memory_cost' amount of memory in bytes that Argon2lib will use while trying to compute a hash.
 * 'time_cost'   amount of time that Argon2lib will spend trying to compute a hash.
 * 'threads'     number of threads that Argon2lib will use.
 */
final class Argon2SplitTokenFactory implements SplitTokenFactory
{
    private $config;
    private $defaultExpirationTimestamp;

    /**
     * @param int[] $config
     */
    public function __construct(array $config = [], ?\DateTimeImmutable $defaultExpirationTimestamp = null)
    {
        $this->config                     = $config;
        $this->defaultExpirationTimestamp = $defaultExpirationTimestamp;
    }

    public function generate(?string $id = null): SplitToken
    {
        $splitToken = Argon2SplitToken::create(
            // DO NOT ENCODE HERE (always provide as raw binary)!
            new HiddenString(random_bytes(SplitToken::TOKEN_CHAR_LENGTH), false, true),
            $id,
            $this->config
        );

        if ($this->defaultExpirationTimestamp !== null) {
            $splitToken->expireAt($this->defaultExpirationTimestamp);
        }

        return $splitToken;
    }

    public function fromString(string $token): SplitToken
    {
        return Argon2SplitToken::fromString($token);
    }
}
