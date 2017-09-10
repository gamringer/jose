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

namespace Jose\Bundle\KeyManagement\Service;

use GuzzleHttp\Psr7\Request;
use Http\Message\RequestFactory as Psr7RequestFactory;

final class RequestFactory implements Psr7RequestFactory
{
    /**
     * {@inheritdoc}
     */
    public function createRequest($method, $uri, array $headers = [], $body = null, $protocolVersion = '1.1')
    {
        return new Request($method,$uri,$headers, $body, $protocolVersion);
    }

}
