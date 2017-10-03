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

namespace Jose\Component\Encryption\Serializer;

use Base64Url\Base64Url;
use Jose\Component\Encryption\JWE;
use Jose\Component\Encryption\Recipient;

/**
 * Class JSONGeneralSerializer.
 */
final class JSONGeneralSerializer implements JWESerializerInterface
{
    public const NAME = 'jwe_json_general';

    /**
     * {@inheritdoc}
     */
    public function displayName(): string
    {
        return 'JWE JSON General';
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
    public function serialize(JWE $jwe, ?int $recipientIndex = null): string
    {
        if (0 === $jwe->countRecipients()) {
            throw new \LogicException('No recipient.');
        }

        $data = [
            'ciphertext' => Base64Url::encode($jwe->getCiphertext()),
            'iv' => Base64Url::encode($jwe->getIV()),
            'tag' => Base64Url::encode($jwe->getTag()),
        ];
        if (null !== $jwe->getAAD()) {
            $data['aad'] = Base64Url::encode($jwe->getAAD());
        }
        if (!empty($jwe->getSharedProtectedHeaders())) {
            $data['protected'] = $jwe->getEncodedSharedProtectedHeaders();
        }
        if (!empty($jwe->getSharedHeaders())) {
            $data['unprotected'] = $jwe->getSharedHeaders();
        }
        $data['recipients'] = [];
        foreach ($jwe->getRecipients() as $recipient) {
            $temp = [];
            if (!empty($recipient->getHeaders())) {
                $temp['header'] = $recipient->getHeaders();
            }
            if (null !== $recipient->getEncryptedKey()) {
                $temp['encrypted_key'] = Base64Url::encode($recipient->getEncryptedKey());
            }
            $data['recipients'][] = $temp;
        }

        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize(string $input): JWE
    {
        $data = json_decode($input, true);
        if (!is_array($data) || !array_key_exists('ciphertext', $data) || !array_key_exists('recipients', $data)) {
            throw new \InvalidArgumentException('Unsupported input.');
        }

        $ciphertext = Base64Url::decode($data['ciphertext']);
        $iv = Base64Url::decode($data['iv']);
        $tag = Base64Url::decode($data['tag']);
        $aad = array_key_exists('aad', $data) ? Base64Url::decode($data['aad']) : null;
        $encodedSharedProtectedHeader = array_key_exists('protected', $data) ? $data['protected'] : null;
        $sharedProtectedHeader = $encodedSharedProtectedHeader ? json_decode(Base64Url::decode($encodedSharedProtectedHeader), true) : [];
        $sharedHeader = array_key_exists('unprotected', $data) ? $data['unprotected'] : [];
        $recipients = [];
        foreach ($data['recipients'] as $recipient) {
            $encryptedKey = array_key_exists('encrypted_key', $recipient) ? Base64Url::decode($recipient['encrypted_key']) : null;
            $header = array_key_exists('header', $recipient) ? $recipient['header'] : [];
            $recipients[] = Recipient::create($header, $encryptedKey);
        }

        return JWE::create(
            $ciphertext,
            $iv,
            $tag,
            $aad,
            $sharedHeader,
            $sharedProtectedHeader,
            $encodedSharedProtectedHeader,
            $recipients);
    }
}
