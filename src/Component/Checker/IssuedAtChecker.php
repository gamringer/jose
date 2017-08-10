<?php

declare(strict_types=1);

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2014-2017 Spomky-Labs
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
 */

namespace Jose\Component\Checker;

final class IssuedAtChecker implements ClaimCheckerInterface
{
    /**
     * {@inheritdoc}
     */
    public function checkClaim(array $claims): array
    {
        if (!array_key_exists('iat', $claims)) {
            return [];
        }

        $iat = $claims['iat'];
        if (!is_int($iat)) {
            throw new \InvalidArgumentException('The claim "iat" must be an integer.');
        }
        if (time() < $iat) {
            throw new \InvalidArgumentException('The JWT is issued in the future.');
        }

        return ['iat'];
    }
}
