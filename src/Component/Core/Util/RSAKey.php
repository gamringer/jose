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

namespace Jose\Component\Core\Util;

use Base64Url\Base64Url;
use FG\ASN1\Universal\BitString;
use FG\ASN1\Universal\Integer;
use FG\ASN1\Universal\NullObject;
use FG\ASN1\Universal\ObjectIdentifier;
use FG\ASN1\Universal\OctetString;
use FG\ASN1\Universal\Sequence;
use Jose\Component\Core\JWK;

final class RSAKey
{
    /**
     * @var Sequence
     */
    private $sequence;

    /**
     * @var bool
     */
    private $private = false;

    /**
     * @var array
     */
    private $values = [];

    /**
     * @var BigInteger
     */
    private $modulus;

    /**
     * @var int
     */
    private $modulus_length;

    /**
     * @var BigInteger
     */
    private $public_exponent;

    /**
     * @var BigInteger|null
     */
    private $private_exponent = null;

    /**
     * @var BigInteger[]
     */
    private $primes = [];

    /**
     * @var BigInteger[]
     */
    private $exponents = [];

    /**
     * @var BigInteger|null
     */
    private $coefficient = null;

    /**
     * @param JWK $data
     */
    private function __construct(JWK $data)
    {
        $this->loadJWK($data->all());
        $this->populateBigIntegers();
        $this->private = array_key_exists('d', $this->values);
    }

    /**
     * @param JWK $jwk
     *
     * @return RSAKey
     */
    public static function createFromJWK(JWK $jwk): RSAKey
    {
        return new self($jwk);
    }

    /**
     * @return BigInteger
     */
    public function getModulus(): BigInteger
    {
        return $this->modulus;
    }

    /**
     * @return int
     */
    public function getModulusLength(): int
    {
        return $this->modulus_length;
    }

    /**
     * @return BigInteger
     */
    public function getExponent(): BigInteger
    {
        $d = $this->getPrivateExponent();
        if (null !== $d) {
            return $d;
        }

        return $this->getPublicExponent();
    }

    /**
     * @return BigInteger
     */
    public function getPublicExponent(): BigInteger
    {
        return $this->public_exponent;
    }

    /**
     * @return BigInteger|null
     */
    public function getPrivateExponent(): ?BigInteger
    {
        return $this->private_exponent;
    }

    /**
     * @return BigInteger[]
     */
    public function getPrimes(): array
    {
        return $this->primes;
    }

    /**
     * @return BigInteger[]
     */
    public function getExponents(): array
    {
        return $this->exponents;
    }

    /**
     * @return BigInteger|null
     */
    public function getCoefficient(): ?BigInteger
    {
        return $this->coefficient;
    }

    /**
     * @return bool
     */
    public function isPublic(): bool
    {
        return !array_key_exists('d', $this->values);
    }

    /**
     * @param RSAKey $private
     *
     * @return RSAKey
     */
    public static function toPublic(RSAKey $private): RSAKey
    {
        $data = $private->toArray();
        $keys = ['p', 'd', 'q', 'dp', 'dq', 'qi'];
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                unset($data[$key]);
            }
        }

        return new self(JWK::create($data));
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->values;
    }

    /**
     * @param array $jwk
     */
    private function loadJWK(array $jwk)
    {
        if (!array_key_exists('kty', $jwk)) {
            throw new \InvalidArgumentException('The key parameter "kty" is missing.');
        }
        if ('RSA' !== $jwk['kty']) {
            throw new \InvalidArgumentException('The JWK is not a RSA key.');
        }

        $this->values = $jwk;
    }

    private function populateBigIntegers()
    {
        $this->modulus = $this->convertBase64StringToBigInteger($this->values['n']);
        $this->modulus_length = mb_strlen($this->getModulus()->toBytes(), '8bit');
        $this->public_exponent = $this->convertBase64StringToBigInteger($this->values['e']);

        if (!$this->isPublic()) {
            $this->private_exponent = $this->convertBase64StringToBigInteger($this->values['d']);

            if (array_key_exists('p', $this->values) && array_key_exists('q', $this->values)) {
                $this->primes = [
                    $this->convertBase64StringToBigInteger($this->values['p']),
                    $this->convertBase64StringToBigInteger($this->values['q']),
                ];
                if (array_key_exists('dp', $this->values) && array_key_exists('dq', $this->values) && array_key_exists('qi', $this->values)) {
                    $this->exponents = [
                        $this->convertBase64StringToBigInteger($this->values['dp']),
                        $this->convertBase64StringToBigInteger($this->values['dq']),
                    ];
                    $this->coefficient = $this->convertBase64StringToBigInteger($this->values['qi']);
                }
            }
        }
    }

    /**
     * @param string $value
     *
     * @return BigInteger
     */
    private function convertBase64StringToBigInteger(string $value): BigInteger
    {
        return BigInteger::createFromBinaryString(Base64Url::decode($value));
    }

    /**
     * @return string
     */
    public function toPEM(): string
    {
        if (null === $this->sequence) {
            $this->sequence = new Sequence();
            if (array_key_exists('d', $this->values)) {
                $this->initPrivateKey();
            } else {
                $this->initPublicKey();
            }
        }
        $result = '-----BEGIN '.($this->private ? 'RSA PRIVATE' : 'PUBLIC').' KEY-----'.PHP_EOL;
        $result .= chunk_split(base64_encode($this->sequence->getBinary()), 64, PHP_EOL);
        $result .= '-----END '.($this->private ? 'RSA PRIVATE' : 'PUBLIC').' KEY-----'.PHP_EOL;

        return $result;
    }

    /**
     * @throws \Exception
     */
    private function initPublicKey()
    {
        $oid_sequence = new Sequence();
        $oid_sequence->addChild(new ObjectIdentifier('1.2.840.113549.1.1.1'));
        $oid_sequence->addChild(new NullObject());
        $this->sequence->addChild($oid_sequence);
        $n = new Integer($this->fromBase64ToInteger($this->values['n']));
        $e = new Integer($this->fromBase64ToInteger($this->values['e']));
        $key_sequence = new Sequence();
        $key_sequence->addChild($n);
        $key_sequence->addChild($e);
        $key_bit_string = new BitString(bin2hex($key_sequence->getBinary()));
        $this->sequence->addChild($key_bit_string);
    }

    private function initPrivateKey()
    {
        $this->sequence->addChild(new Integer(0));
        $oid_sequence = new Sequence();
        $oid_sequence->addChild(new ObjectIdentifier('1.2.840.113549.1.1.1'));
        $oid_sequence->addChild(new NullObject());
        $this->sequence->addChild($oid_sequence);
        $v = new Integer(0);
        $n = new Integer($this->fromBase64ToInteger($this->values['n']));
        $e = new Integer($this->fromBase64ToInteger($this->values['e']));
        $d = new Integer($this->fromBase64ToInteger($this->values['d']));
        $p = new Integer($this->fromBase64ToInteger($this->values['p']));
        $q = new Integer($this->fromBase64ToInteger($this->values['q']));
        $dp = array_key_exists('dp', $this->values) ? new Integer($this->fromBase64ToInteger($this->values['dp'])) : new Integer(0);
        $dq = array_key_exists('dq', $this->values) ? new Integer($this->fromBase64ToInteger($this->values['dq'])) : new Integer(0);
        $qi = array_key_exists('qi', $this->values) ? new Integer($this->fromBase64ToInteger($this->values['qi'])) : new Integer(0);
        $key_sequence = new Sequence();
        $key_sequence->addChild($v);
        $key_sequence->addChild($n);
        $key_sequence->addChild($e);
        $key_sequence->addChild($d);
        $key_sequence->addChild($p);
        $key_sequence->addChild($q);
        $key_sequence->addChild($dp);
        $key_sequence->addChild($dq);
        $key_sequence->addChild($qi);
        $key_octet_string = new OctetString(bin2hex($key_sequence->getBinary()));
        $this->sequence->addChild($key_octet_string);
    }

    /**
     * @param string $value
     *
     * @return string
     */
    private function fromBase64ToInteger($value)
    {
        return gmp_strval(gmp_init(current(unpack('H*', Base64Url::decode($value))), 16), 10);
    }
}
