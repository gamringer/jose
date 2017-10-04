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

namespace Jose\Component\Signature\Serializer;

use Base64Url\Base64Url;
use Jose\Component\Core\Converter\JsonConverterInterface;
use Jose\Component\Signature\JWS;

/**
 * Class JSONFlattenedSerializer.
 */
final class JSONFlattenedSerializer extends AbstractSerializer
{
    public const NAME = 'jws_json_flattened';

    /**
     * @var JsonConverterInterface
     */
    private $jsonConverter;

    /**
     * JSONFlattenedSerializer constructor.
     *
     * @param JsonConverterInterface $jsonConverter
     */
    public function __construct(JsonConverterInterface $jsonConverter)
    {
        $this->jsonConverter = $jsonConverter;
    }

    /**
     * {@inheritdoc}
     */
    public function displayName(): string
    {
        return 'JWS JSON Flattened';
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return self::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(JWS $jws, ?int $signatureIndex = null): string
    {
        if (null === $signatureIndex) {
            $signatureIndex = 0;
        }
        $signature = $jws->getSignature($signatureIndex);

        $data = [];
        $values = [
            'payload' => $jws->getEncodedPayload(),
            'protected' => $signature->getEncodedProtectedHeaders(),
            'header' => $signature->getHeaders(),
        ];

        foreach ($values as $key => $value) {
            if (!empty($value)) {
                $data[$key] = $value;
            }
        }
        $data['signature'] = Base64Url::encode($signature->getSignature());

        return $this->jsonConverter->encode($data);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize(string $input): JWS
    {
        $data = $this->jsonConverter->decode($input);
        if (!is_array($data) || !array_key_exists('signature', $data)) {
            throw new \InvalidArgumentException('Unsupported input.');
        }

        $signature = Base64Url::decode($data['signature']);

        if (array_key_exists('protected', $data)) {
            $encodedProtectedHeaders = $data['protected'];
            $protectedHeaders = $this->jsonConverter->decode(Base64Url::decode($data['protected']));
        } else {
            $encodedProtectedHeaders = null;
            $protectedHeaders = [];
        }
        if (array_key_exists('header', $data)) {
            if (!is_array($data['header'])) {
                throw new \InvalidArgumentException('Bad header.');
            }
            $headers = $data['header'];
        } else {
            $headers = [];
        }

        if (array_key_exists('payload', $data)) {
            $encodedPayload = $data['payload'];
            $payload = $this->isPayloadEncoded($protectedHeaders) ? Base64Url::decode($encodedPayload) : $encodedPayload;
        } else {
            $payload = null;
            $encodedPayload = null;
        }

        $jws = JWS::create($payload, $encodedPayload, null === $encodedPayload);
        $jws = $jws->addSignature($signature, $protectedHeaders, $encodedProtectedHeaders, $headers);

        return $jws;
    }
}
