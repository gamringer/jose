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

namespace Jose\Component\Encryption\Algorithm\KeyEncryption;

use Base64Url\Base64Url;
use Jose\Component\Core\JWK;

/**
 * Class AESGCMKW.
 */
abstract class AESGCMKW implements KeyWrappingInterface
{
    /**
     * {@inheritdoc}
     */
    public function keyType(): array
    {
        return ['oct'];
    }

    /**
     * {@inheritdoc}
     */
    public function wrapKey(JWK $key, string $cek, array $complete_headers, array &$additional_headers): string
    {
        $this->checkKey($key);
        $kek = Base64Url::decode($key->get('k'));
        $iv = random_bytes(96 / 8);
        $additional_headers['iv'] = Base64Url::encode($iv);

        $mode = sprintf('aes-%d-gcm', $this->getKeySize());
        $tag = null;
        $encrypted_cek = openssl_encrypt($cek, $mode, $kek, OPENSSL_RAW_DATA, $iv, $tag, '');
        if (false === $encrypted_cek) {
            throw new \RuntimeException('Unable to encrypt the data.');
        }
        $additional_headers['tag'] = Base64Url::encode($tag);

        return $encrypted_cek;
    }

    /**
     * {@inheritdoc}
     */
    public function unwrapKey(JWK $key, string $encrypted_cek, array $complete_headers): string
    {
        $this->checkKey($key);
        $this->checkAdditionalParameters($complete_headers);

        $kek = Base64Url::decode($key->get('k'));
        $tag = Base64Url::decode($complete_headers['tag']);
        $iv = Base64Url::decode($complete_headers['iv']);

        $mode = sprintf('aes-%d-gcm', $this->getKeySize());
        $cek = openssl_decrypt($encrypted_cek, $mode, $kek, OPENSSL_RAW_DATA, $iv, $tag, '');
        if (false === $cek) {
            throw new \RuntimeException('Unable to decrypt or to verify the tag.');
        }

        return $cek;
    }

    /**
     * {@inheritdoc}
     */
    public function getKeyManagementMode(): string
    {
        return self::MODE_WRAP;
    }

    /**
     * @param JWK $key
     */
    protected function checkKey(JWK $key)
    {
        if ('oct' !== $key->get('kty')) {
            throw new \InvalidArgumentException('Wrong key type.');
        }
        if (!$key->has('k')) {
            throw new \InvalidArgumentException('The key parameter "k" is missing.');
        }
    }

    /**
     * @param array $header
     */
    protected function checkAdditionalParameters(array $header)
    {
        foreach (['iv', 'tag'] as $k) {
            if (!array_key_exists($k, $header)) {
                throw new \InvalidArgumentException(sprintf('Parameter "%s" is missing.', $k));
            }
        }
    }

    /**
     * @return int
     */
    abstract protected function getKeySize(): int;
}
