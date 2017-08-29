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

namespace Jose\Component\Encryption\Tests;

use Base64Url\Base64Url;
use Jose\Component\Core\JWAManager;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A128CBCHS256;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A192CBCHS384;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256CBCHS512;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256GCM;
use Jose\Component\Encryption\Algorithm\KeyEncryption\Dir;
use Jose\Component\Encryption\Algorithm\KeyEncryption\ECDHES;
use Jose\Component\Encryption\Algorithm\KeyEncryption\ECDHESA256KW;
use Jose\Component\Encryption\Algorithm\KeyEncryption\RSAOAEP256;
use Jose\Component\Encryption\Compression\CompressionMethodsManager;
use Jose\Component\Encryption\Compression\Deflate;
use Jose\Component\Encryption\Decrypter;
use Jose\Component\Encryption\JWEBuilder;
use Jose\Component\Encryption\JWE;
use Jose\Component\Encryption\JWELoader;
use PHPUnit\Framework\TestCase;

/**
 * final class EncrypterTest.
 *
 * @group Encrypter
 * @group Functional
 */
final class EncrypterTest extends TestCase
{
    public function testEncryptWithJWTInput()
    {
        $keyEncryptionAlgorithmManager = JWAManager::create([new RSAOAEP256()]);
        $contentEncryptionAlgorithmManager = JWAManager::create([new A256CBCHS512()]);
        $compressionManager = CompressionMethodsManager::create([new Deflate()]);
        $jweBuilder = new JWEBuilder($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);
        $decrypter = new Decrypter($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);

        $jwe = $jweBuilder
            ->withPayload('FOO')
            ->withSharedProtectedHeaders([
                'enc' => 'A256CBC-HS512',
                'alg' => 'RSA-OAEP-256',
                'zip' => 'DEF',
            ])
            ->withAAD('foo,bar,baz')
            ->addRecipient($this->getRSARecipientKey())
            ->build();

        $jwe = $jwe->toFlattenedJSON(0);

        $loaded = JWELoader::load($jwe);

        $this->assertInstanceOf(JWE::class, $loaded);
        $this->assertEquals('RSA-OAEP-256', $loaded->getSharedProtectedHeader('alg'));
        $this->assertEquals('A256CBC-HS512', $loaded->getSharedProtectedHeader('enc'));
        $this->assertEquals('DEF', $loaded->getSharedProtectedHeader('zip'));
        $this->assertNull($loaded->getPayload());
        $loaded = $decrypter->decryptUsingKeySet($loaded, $this->getPrivateKeySet(), $index);

        $this->assertEquals(0, $index);
        $this->assertEquals('FOO', $loaded->getPayload());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The header contains duplicated entries: ["zip"].
     */
    public function testDuplicatedHeader()
    {
        $keyEncryptionAlgorithmManager = JWAManager::create([new RSAOAEP256()]);
        $contentEncryptionAlgorithmManager = JWAManager::create([new A256CBCHS512()]);
        $compressionManager = CompressionMethodsManager::create([new Deflate()]);
        $jweBuilder = new JWEBuilder($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);

        $jweBuilder
            ->withPayload('FOO')
            ->withSharedProtectedHeaders([
                'enc' => 'A256CBC-HS512',
                'alg' => 'RSA-OAEP-256',
                'zip' => 'DEF',
            ])
            ->addRecipient(
                $this->getRSARecipientKey(),
                ['zip' => 'DEF']
            );
    }

    public function testCreateCompactJWEUsingFactory()
    {
        $keyEncryptionAlgorithmManager = JWAManager::create([new RSAOAEP256()]);
        $contentEncryptionAlgorithmManager = JWAManager::create([new A256CBCHS512()]);
        $compressionManager = CompressionMethodsManager::create([new Deflate()]);
        $jweBuilder = new JWEBuilder($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);
        $decrypter = new Decrypter($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);

        $jwe = $jweBuilder
            ->withPayload('FOO')
            ->withSharedProtectedHeaders([
                'enc' => 'A256CBC-HS512',
                'alg' => 'RSA-OAEP-256',
                'zip' => 'DEF',
            ])
            ->addRecipient($this->getRSARecipientKey())
            ->build()
            ->toCompactJSON(0);

        $loaded = JWELoader::load($jwe);

        $this->assertInstanceOf(JWE::class, $loaded);
        $this->assertEquals('RSA-OAEP-256', $loaded->getSharedProtectedHeader('alg'));
        $this->assertEquals('A256CBC-HS512', $loaded->getSharedProtectedHeader('enc'));
        $this->assertEquals('DEF', $loaded->getSharedProtectedHeader('zip'));
        $this->assertNull($loaded->getPayload());

        $loaded = $decrypter->decryptUsingKeySet($loaded, $this->getPrivateKeySet(), $index);

        $this->assertEquals(0, $index);
        $this->assertEquals('FOO', $loaded->getPayload());
    }

    public function testCreateFlattenedJWEUsingFactory()
    {
        $keyEncryptionAlgorithmManager = JWAManager::create([new RSAOAEP256()]);
        $contentEncryptionAlgorithmManager = JWAManager::create([new A256CBCHS512()]);
        $compressionManager = CompressionMethodsManager::create([new Deflate()]);
        $jweBuilder = new JWEBuilder($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);
        $decrypter = new Decrypter($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);

        $jwe = $jweBuilder
            ->withPayload('FOO')
            ->withSharedProtectedHeaders([
                'enc' => 'A256CBC-HS512',
                'alg' => 'RSA-OAEP-256',
                'zip' => 'DEF',
            ])
            ->withSharedHeaders([
                    'foo' => 'bar',
            ])
            ->addRecipient(
                $this->getRSARecipientKey(),
                [
                    'plic' => 'ploc',
                ]
            )
            ->withAAD('A,B,C,D')
            ->build()
            ->toFlattenedJSON(0);

        $loaded = JWELoader::load($jwe);

        $this->assertInstanceOf(JWE::class, $loaded);
        $this->assertEquals('RSA-OAEP-256', $loaded->getSharedProtectedHeader('alg'));
        $this->assertEquals('A256CBC-HS512', $loaded->getSharedProtectedHeader('enc'));
        $this->assertEquals('DEF', $loaded->getSharedProtectedHeader('zip'));
        $this->assertEquals('bar', $loaded->getSharedHeader('foo'));
        $this->assertEquals('A,B,C,D', $loaded->getAAD());
        $this->assertEquals('ploc', $loaded->getRecipient(0)->getHeader('plic'));
        $this->assertNull($loaded->getPayload());

        $loaded = $decrypter->decryptUsingKeySet($loaded, $this->getPrivateKeySet(), $index);

        $this->assertEquals(0, $index);
        $this->assertEquals('FOO', $loaded->getPayload());
    }

    public function testEncryptAndLoadFlattenedWithAAD()
    {
        $keyEncryptionAlgorithmManager = JWAManager::create([new RSAOAEP256()]);
        $contentEncryptionAlgorithmManager = JWAManager::create([new A256CBCHS512()]);
        $compressionManager = CompressionMethodsManager::create([new Deflate()]);
        $jweBuilder = new JWEBuilder($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);
        $decrypter = new Decrypter($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);

        $jwe = $jweBuilder
            ->withPayload(json_encode($this->getKeyToEncrypt()))
            ->withSharedProtectedHeaders([
                'enc' => 'A256CBC-HS512',
                'alg' => 'RSA-OAEP-256',
                'zip' => 'DEF',
            ])
            ->addRecipient($this->getRSARecipientKey())
            ->withAAD('foo,bar,baz')
            ->build()
            ->toFlattenedJSON(0);

        $loaded = JWELoader::load($jwe);

        $this->assertInstanceOf(JWE::class, $loaded);
        $this->assertEquals('RSA-OAEP-256', $loaded->getSharedProtectedHeader('alg'));
        $this->assertEquals('A256CBC-HS512', $loaded->getSharedProtectedHeader('enc'));
        $this->assertEquals('DEF', $loaded->getSharedProtectedHeader('zip'));
        $this->assertNull($loaded->getPayload());

        $loaded = $decrypter->decryptUsingKeySet($loaded, $this->getPrivateKeySet(), $index);

        $this->assertEquals(0, $index);
        $this->assertEquals($this->getKeyToEncrypt(), JWK::create(json_decode($loaded->getPayload(), true)));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The compression method "FIP" is not supported.
     */
    public function testCompressionAlgorithmNotSupported()
    {
        $keyEncryptionAlgorithmManager = JWAManager::create([new RSAOAEP256()]);
        $contentEncryptionAlgorithmManager = JWAManager::create([new A256CBCHS512()]);
        $compressionManager = CompressionMethodsManager::create([new Deflate()]);
        $jweBuilder = new JWEBuilder($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);

        $jweBuilder
            ->withPayload(json_encode($this->getKeyToEncrypt()))
            ->withSharedProtectedHeaders([
                'enc' => 'A256CBC-HS512',
                'alg' => 'RSA-OAEP-256',
                'zip' => 'FIP',
            ])
            ->addRecipient($this->getRSARecipientKey())
            ->withAAD('foo,bar,baz')
            ->build()
            ->toFlattenedJSON(0);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Foreign key management mode forbidden.
     */
    public function testForeignKeyManagementModeForbidden()
    {
        $keyEncryptionAlgorithmManager = JWAManager::create([new Dir(), new ECDHESA256KW()]);
        $contentEncryptionAlgorithmManager = JWAManager::create([new A256CBCHS512()]);
        $compressionManager = CompressionMethodsManager::create([new Deflate()]);
        $jweBuilder = new JWEBuilder($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);

        $jweBuilder
            ->withPayload('Live long and Prosper.')
            ->withSharedProtectedHeaders([
                'enc' => 'A256CBC-HS512',
            ])
            ->addRecipient($this->getECDHRecipientPublicKey(), ['kid' => 'e9bc097a-ce51-4036-9562-d2ade882db0d', 'alg' => 'ECDH-ES+A256KW'])
            ->addRecipient($this->getDirectKey(), ['kid' => 'DIR_1', 'alg' => 'dir'])
            ->build();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Key cannot be used to encrypt
     */
    public function testOperationNotAllowedForTheKey()
    {
        $keyEncryptionAlgorithmManager = JWAManager::create([new RSAOAEP256()]);
        $contentEncryptionAlgorithmManager = JWAManager::create([new A256CBCHS512()]);
        $compressionManager = CompressionMethodsManager::create([new Deflate()]);
        $jweBuilder = new JWEBuilder($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);

        $jweBuilder
            ->withPayload('Live long and Prosper.')
            ->withSharedProtectedHeaders([
                'enc' => 'A256CBC-HS512',
                'alg' => 'RSA-OAEP-256',
                'zip' => 'DEF',
            ])
            ->addRecipient($this->getSigningKey())
            ->build();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Key is only allowed for algorithm "RSA-OAEP".
     */
    public function testAlgorithmNotAllowedForTheKey()
    {
        $keyEncryptionAlgorithmManager = JWAManager::create([new RSAOAEP256()]);
        $contentEncryptionAlgorithmManager = JWAManager::create([new A256CBCHS512()]);
        $compressionManager = CompressionMethodsManager::create([new Deflate()]);
        $jweBuilder = new JWEBuilder($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);

        $jweBuilder
            ->withPayload('Live long and Prosper.')
            ->withSharedProtectedHeaders([
                'enc' => 'A256CBC-HS512',
                'alg' => 'RSA-OAEP-256',
                'zip' => 'DEF',
            ])
            ->addRecipient($this->getRSARecipientKeyWithAlgorithm())
            ->build();
    }

    public function testEncryptAndLoadFlattenedWithDeflateCompression()
    {
        $keyEncryptionAlgorithmManager = JWAManager::create([new RSAOAEP256()]);
        $contentEncryptionAlgorithmManager = JWAManager::create([new A128CBCHS256()]);
        $compressionManager = CompressionMethodsManager::create([new Deflate()]);
        $jweBuilder = new JWEBuilder($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);
        $decrypter = new Decrypter($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);

        $jwe = $jweBuilder
            ->withPayload(json_encode($this->getKeySetToEncrypt()))
            ->withSharedProtectedHeaders([
                'kid' => '123456789',
                'enc' => 'A128CBC-HS256',
                'alg' => 'RSA-OAEP-256',
                'zip' => 'DEF',
            ])
            ->addRecipient($this->getRSARecipientKey())
            ->build()
            ->toCompactJSON(0);

        $loaded = JWELoader::load($jwe);

        $this->assertInstanceOf(JWE::class, $loaded);
        $this->assertEquals('RSA-OAEP-256', $loaded->getSharedProtectedHeader('alg'));
        $this->assertEquals('A128CBC-HS256', $loaded->getSharedProtectedHeader('enc'));
        $this->assertEquals('DEF', $loaded->getSharedProtectedHeader('zip'));
        $this->assertNull($loaded->getPayload());

        $loaded = $decrypter->decryptUsingKeySet($loaded, $this->getPrivateKeySet(), $index);

        $this->assertEquals(0, $index);
        $this->assertEquals($this->getKeySetToEncrypt(), JWKSet::createFromKeyData(json_decode($loaded->getPayload(), true)));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Parameter "alg" is missing.
     */
    public function testAlgParameterIsMissing()
    {
        $keyEncryptionAlgorithmManager = JWAManager::create([]);
        $contentEncryptionAlgorithmManager = JWAManager::create([new A256CBCHS512()]);
        $compressionManager = CompressionMethodsManager::create([new Deflate()]);
        $jweBuilder = new JWEBuilder($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);

        $jweBuilder
            ->withPayload(json_encode($this->getKeyToEncrypt()))
            ->withSharedProtectedHeaders([
                'kid' => '123456789',
                'enc' => 'A256CBC-HS512',
                'zip' => 'DEF',
            ])
            ->addRecipient($this->getRSARecipientKey())
            ->build();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Parameter "enc" is missing.
     */
    public function testEncParameterIsMissing()
    {
        $keyEncryptionAlgorithmManager = JWAManager::create([new RSAOAEP256()]);
        $contentEncryptionAlgorithmManager = JWAManager::create([]);
        $compressionManager = CompressionMethodsManager::create([new Deflate()]);
        $jweBuilder = new JWEBuilder($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);

        $jweBuilder
            ->withPayload(json_encode($this->getKeyToEncrypt()))
            ->withSharedProtectedHeaders([
                'kid' => '123456789',
                'alg' => 'RSA-OAEP-256',
                'zip' => 'DEF',
            ])
            ->addRecipient($this->getRSARecipientKey())
            ->build();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The key encryption algorithm "A256CBC-HS512" is not supported or not a key encryption algorithm instance.
     */
    public function testNotAKeyEncryptionAlgorithm()
    {
        $keyEncryptionAlgorithmManager = JWAManager::create([new A256CBCHS512()]);
        $contentEncryptionAlgorithmManager = JWAManager::create([new A256CBCHS512()]);
        $compressionManager = CompressionMethodsManager::create([new Deflate()]);
        $jweBuilder = new JWEBuilder($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);

        $jweBuilder
            ->withPayload(json_encode($this->getKeyToEncrypt()))
            ->withSharedProtectedHeaders([
                'kid' => '123456789',
                'enc' => 'A256CBC-HS512',
                'alg' => 'A256CBC-HS512',
                'zip' => 'DEF',
            ])
            ->addRecipient($this->getRSARecipientKey())
            ->build();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The content encryption algorithm "RSA-OAEP-256" is not supported or not a content encryption algorithm instance.
     */
    public function testNotAContentEncryptionAlgorithm()
    {
        $keyEncryptionAlgorithmManager = JWAManager::create([new RSAOAEP256()]);
        $contentEncryptionAlgorithmManager = JWAManager::create([new RSAOAEP256()]);
        $compressionManager = CompressionMethodsManager::create([new Deflate()]);
        $jweBuilder = new JWEBuilder($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);

        $jweBuilder
            ->withPayload(json_encode($this->getKeyToEncrypt()))
            ->withSharedProtectedHeaders([
                'kid' => '123456789',
                'enc' => 'RSA-OAEP-256',
                'alg' => 'RSA-OAEP-256',
                'zip' => 'DEF',
            ])
            ->addRecipient($this->getRSARecipientKey())
            ->build();
    }

    public function testEncryptAndLoadCompactWithDirectKeyEncryption()
    {
        $keyEncryptionAlgorithmManager = JWAManager::create([new Dir()]);
        $contentEncryptionAlgorithmManager = JWAManager::create([new A192CBCHS384()]);
        $compressionManager = CompressionMethodsManager::create([new Deflate()]);
        $jweBuilder = new JWEBuilder($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);
        $decrypter = new Decrypter($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);

        $jwe = $jweBuilder
            ->withPayload(json_encode($this->getKeyToEncrypt()))
            ->withSharedProtectedHeaders([
                'kid' => 'DIR_1',
                'enc' => 'A192CBC-HS384',
                'alg' => 'dir',
            ])
            ->addRecipient($this->getDirectKey())
            ->build()
            ->toFlattenedJSON(0);

        $loaded = JWELoader::load($jwe);

        $this->assertInstanceOf(JWE::class, $loaded);
        $this->assertEquals('dir', $loaded->getSharedProtectedHeader('alg'));
        $this->assertEquals('A192CBC-HS384', $loaded->getSharedProtectedHeader('enc'));
        $this->assertFalse($loaded->hasSharedHeader('zip'));
        $this->assertNull($loaded->getPayload());

        $loaded = $decrypter->decryptUsingKeySet($loaded, $this->getSymmetricKeySet(), $index);

        $this->assertEquals(0, $index);
        $this->assertEquals($this->getKeyToEncrypt(), JWK::create(json_decode($loaded->getPayload(), true)));
    }

    public function testEncryptAndLoadCompactKeyAgreement()
    {
        $keyEncryptionAlgorithmManager = JWAManager::create([new ECDHES()]);
        $contentEncryptionAlgorithmManager = JWAManager::create([new A192CBCHS384()]);
        $compressionManager = CompressionMethodsManager::create([new Deflate()]);
        $jweBuilder = new JWEBuilder($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);
        $decrypter = new Decrypter($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);

        $payload = json_encode(['user_id' => '1234', 'exp' => time() + 3600]);
        $jwe = $jweBuilder
            ->withPayload($payload)
            ->withSharedProtectedHeaders([
                'kid' => 'e9bc097a-ce51-4036-9562-d2ade882db0d',
                'enc' => 'A192CBC-HS384',
                'alg' => 'ECDH-ES',
            ])
            ->addRecipient($this->getECDHRecipientPublicKey())
            ->build()
            ->toFlattenedJSON(0);

        $loaded = JWELoader::load($jwe);

        $this->assertInstanceOf(JWE::class, $loaded);
        $this->assertEquals('ECDH-ES', $loaded->getSharedProtectedHeader('alg'));
        $this->assertEquals('A192CBC-HS384', $loaded->getSharedProtectedHeader('enc'));
        $this->assertFalse($loaded->hasSharedProtectedHeader('zip'));
        $this->assertNull($loaded->getPayload());

        $loaded = $decrypter->decryptUsingKeySet($loaded, $this->getPrivateKeySet(), $index);

        $this->assertEquals(0, $index);
        $this->assertEquals($payload, $loaded->getPayload());
    }

    public function testEncryptAndLoadCompactKeyAgreementWithWrappingCompact()
    {
        $keyEncryptionAlgorithmManager = JWAManager::create([new ECDHESA256KW()]);
        $contentEncryptionAlgorithmManager = JWAManager::create([new A256CBCHS512()]);
        $compressionManager = CompressionMethodsManager::create([new Deflate()]);
        $jweBuilder = new JWEBuilder($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);
        $decrypter = new Decrypter($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);

        $jwe = $jweBuilder
            ->withPayload('Live long and Prosper.')
            ->withSharedProtectedHeaders([
                'kid' => 'e9bc097a-ce51-4036-9562-d2ade882db0d',
                'enc' => 'A256CBC-HS512',
                'alg' => 'ECDH-ES+A256KW',
            ])
            ->addRecipient($this->getECDHRecipientPublicKey())
            ->build()
            ->toFlattenedJSON(0);

        $loaded = JWELoader::load($jwe);

        $this->assertInstanceOf(JWE::class, $loaded);
        $this->assertEquals('ECDH-ES+A256KW', $loaded->getSharedProtectedHeader('alg'));
        $this->assertEquals('A256CBC-HS512', $loaded->getSharedProtectedHeader('enc'));
        $this->assertFalse($loaded->hasSharedProtectedHeader('zip'));
        $this->assertFalse($loaded->hasSharedHeader('zip'));
        $this->assertNull($loaded->getPayload());

        $loaded = $decrypter->decryptUsingKeySet($loaded, $this->getPrivateKeySet(), $index);

        $this->assertEquals(0, $index);
        $this->assertTrue(is_string($loaded->getPayload()));
        $this->assertEquals('Live long and Prosper.', $loaded->getPayload());
    }

    public function testEncryptAndLoadWithGCMAndAAD()
    {
        $keyEncryptionAlgorithmManager = JWAManager::create([new ECDHESA256KW()]);
        $contentEncryptionAlgorithmManager = JWAManager::create([new A256GCM()]);
        $compressionManager = CompressionMethodsManager::create([new Deflate()]);
        $jweBuilder = new JWEBuilder($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);

        $jwe = $jweBuilder
            ->withPayload('Live long and Prosper.')
            ->withSharedProtectedHeaders([
                'kid' => 'e9bc097a-ce51-4036-9562-d2ade882db0d',
                'enc' => 'A256GCM',
                'alg' => 'ECDH-ES+A256KW',
            ])
            ->withAAD('foo,bar,baz')
            ->addRecipient($this->getECDHRecipientPublicKey())
            ->build()
            ->toFlattenedJSON(0);

        $loaded = JWELoader::load($jwe);

        $keyEncryptionAlgorithmManager = JWAManager::create([new ECDHESA256KW()]);
        $contentEncryptionAlgorithmManager = JWAManager::create([new A256GCM()]);
        $compressionManager = CompressionMethodsManager::create([new Deflate()]);
        $decrypter = new Decrypter($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);

        $this->assertInstanceOf(JWE::class, $loaded);
        $this->assertEquals('ECDH-ES+A256KW', $loaded->getSharedProtectedHeader('alg'));
        $this->assertEquals('A256GCM', $loaded->getSharedProtectedHeader('enc'));
        $this->assertFalse($loaded->hasSharedProtectedHeader('zip'));
        $this->assertFalse($loaded->hasSharedHeader('zip'));
        $this->assertNull($loaded->getPayload());

        $loaded = $decrypter->decryptUsingKeySet($loaded, $this->getPrivateKeySet(), $index);

        $this->assertEquals(0, $index);
        $this->assertTrue(is_string($loaded->getPayload()));
        $this->assertEquals('Live long and Prosper.', $loaded->getPayload());
    }

    public function testEncryptAndLoadCompactKeyAgreementWithWrapping()
    {
        $keyEncryptionAlgorithmManager = JWAManager::create([new RSAOAEP256(), new ECDHESA256KW()]);
        $contentEncryptionAlgorithmManager = JWAManager::create([new A256CBCHS512()]);
        $compressionManager = CompressionMethodsManager::create([new Deflate()]);
        $jweBuilder = new JWEBuilder($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);
        $decrypter = new Decrypter($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionManager);

        $jwe = $jweBuilder
            ->withPayload('Live long and Prosper.')
            ->withSharedProtectedHeaders([
                'enc' => 'A256CBC-HS512',
            ])
            ->withAAD('foo,bar,baz')
            ->addRecipient($this->getECDHRecipientPublicKey(), ['kid' => 'e9bc097a-ce51-4036-9562-d2ade882db0d', 'alg' => 'ECDH-ES+A256KW'])
            ->addRecipient($this->getRSARecipientKey(), ['kid' => '123456789', 'alg' => 'RSA-OAEP-256'])
            ->build()
            ->toJSON();

        $loaded = JWELoader::load($jwe);

        $this->assertEquals(2, $loaded->countRecipients());

        $this->assertInstanceOf(JWE::class, $loaded);
        $this->assertEquals('A256CBC-HS512', $loaded->getSharedProtectedHeader('enc'));
        $this->assertEquals('ECDH-ES+A256KW', $loaded->getRecipient(0)->getHeader('alg'));
        $this->assertEquals('RSA-OAEP-256', $loaded->getRecipient(1)->getHeader('alg'));
        $this->assertFalse($loaded->hasSharedHeader('zip'));
        $this->assertFalse($loaded->hasSharedProtectedHeader('zip'));
        $this->assertNull($loaded->getPayload());

        $loaded = $decrypter->decryptUsingKeySet($loaded, $this->getPrivateKeySet(), $index);

        $this->assertEquals(0, $index);
        $this->assertTrue(is_string($loaded->getPayload()));
        $this->assertEquals('Live long and Prosper.', $loaded->getPayload());
    }

    /**
     * @return JWK
     */
    private function getKeyToEncrypt()
    {
        $key = JWK::create([
            'kty' => 'EC',
            'use' => 'enc',
            'crv' => 'P-256',
            'x' => 'f83OJ3D2xF1Bg8vub9tLe1gHMzV76e8Tus9uPHvRVEU',
            'y' => 'x_FEzRu9m36HLN_tue659LNpXW6pCyStikYjKIWI5a0',
            'd' => 'jpsQnnGQmL-YBIffH1136cspYG6-0iY7X1fCE9-E9LI',
        ]);

        return $key;
    }

    /**
     * @return JWKSet
     */
    private function getKeySetToEncrypt()
    {
        $key = JWK::create([
            'kty' => 'EC',
            'use' => 'enc',
            'crv' => 'P-256',
            'x' => 'f83OJ3D2xF1Bg8vub9tLe1gHMzV76e8Tus9uPHvRVEU',
            'y' => 'x_FEzRu9m36HLN_tue659LNpXW6pCyStikYjKIWI5a0',
            'd' => 'jpsQnnGQmL-YBIffH1136cspYG6-0iY7X1fCE9-E9LI',
        ]);

        $key_set = JWKSet::createFromKeys([$key]);

        return $key_set;
    }

    /**
     * @return JWK
     */
    private function getRSARecipientKey()
    {
        $key = JWK::create([
            'kty' => 'RSA',
            'use' => 'enc',
            'n' => 'tpS1ZmfVKVP5KofIhMBP0tSWc4qlh6fm2lrZSkuKxUjEaWjzZSzs72gEIGxraWusMdoRuV54xsWRyf5KeZT0S-I5Prle3Idi3gICiO4NwvMk6JwSBcJWwmSLFEKyUSnB2CtfiGc0_5rQCpcEt_Dn5iM-BNn7fqpoLIbks8rXKUIj8-qMVqkTXsEKeKinE23t1ykMldsNaaOH-hvGti5Jt2DMnH1JjoXdDXfxvSP_0gjUYb0ektudYFXoA6wekmQyJeImvgx4Myz1I4iHtkY_Cp7J4Mn1ejZ6HNmyvoTE_4OuY1uCeYv4UyXFc1s1uUyYtj4z57qsHGsS4dQ3A2MJsw',
            'e' => 'AQAB',
        ]);

        return $key;
    }

    /**
     * @return JWK
     */
    private function getRSARecipientKeyWithAlgorithm()
    {
        $key = JWK::create([
            'kty' => 'RSA',
            'use' => 'enc',
            'alg' => 'RSA-OAEP',
            'n' => 'tpS1ZmfVKVP5KofIhMBP0tSWc4qlh6fm2lrZSkuKxUjEaWjzZSzs72gEIGxraWusMdoRuV54xsWRyf5KeZT0S-I5Prle3Idi3gICiO4NwvMk6JwSBcJWwmSLFEKyUSnB2CtfiGc0_5rQCpcEt_Dn5iM-BNn7fqpoLIbks8rXKUIj8-qMVqkTXsEKeKinE23t1ykMldsNaaOH-hvGti5Jt2DMnH1JjoXdDXfxvSP_0gjUYb0ektudYFXoA6wekmQyJeImvgx4Myz1I4iHtkY_Cp7J4Mn1ejZ6HNmyvoTE_4OuY1uCeYv4UyXFc1s1uUyYtj4z57qsHGsS4dQ3A2MJsw',
            'e' => 'AQAB',
        ]);

        return $key;
    }

    /**
     * @return JWK
     */
    private function getSigningKey()
    {
        $key = JWK::create([
            'kty' => 'EC',
            'key_ops' => ['sign', 'verify'],
            'crv' => 'P-256',
            'x' => 'f83OJ3D2xF1Bg8vub9tLe1gHMzV76e8Tus9uPHvRVEU',
            'y' => 'x_FEzRu9m36HLN_tue659LNpXW6pCyStikYjKIWI5a0',
            'd' => 'jpsQnnGQmL-YBIffH1136cspYG6-0iY7X1fCE9-E9LI',
        ]);

        return $key;
    }

    /**
     * @return JWK
     */
    private function getECDHRecipientPublicKey()
    {
        $key = JWK::create([
            'kty' => 'EC',
            'key_ops' => ['encrypt', 'decrypt'],
            'crv' => 'P-256',
            'x' => 'f83OJ3D2xF1Bg8vub9tLe1gHMzV76e8Tus9uPHvRVEU',
            'y' => 'x_FEzRu9m36HLN_tue659LNpXW6pCyStikYjKIWI5a0',
        ]);

        return $key;
    }

    /**
     * @return JWK
     */
    private function getDirectKey()
    {
        $key = JWK::create([
            'kid' => 'DIR_1',
            'key_ops' => ['encrypt', 'decrypt'],
            'kty' => 'oct',
            'k' => Base64Url::encode(hex2bin('00112233445566778899AABBCCDDEEFF000102030405060708090A0B0C0D0E0F')),
        ]);

        return $key;
    }

    /**
     * @return JWKSet
     */
    private function getPrivateKeySet(): JWKSet
    {
        $keys = ['keys' => [
            [
                'kty' => 'EC',
                'crv' => 'P-256',
                'x' => 'weNJy2HscCSM6AEDTDg04biOvhFhyyWvOHQfeF_PxMQ',
                'y' => 'e8lnCO-AlStT-NJVX-crhB7QRYhiix03illJOVAOyck',
                'd' => 'VEmDZpDXXK8p8N0Cndsxs924q6nS1RXFASRl6BfUqdw',
            ],
            [
                'kty' => 'EC',
                'crv' => 'P-256',
                'x' => 'gI0GAILBdu7T53akrFmMyGcsF3n5dO7MmwNBHKW5SV0',
                'y' => 'SLW_xSffzlPWrHEVI30DHM_4egVwt3NQqeUD7nMFpps',
                'd' => '0_NxaRPUMQoAJt50Gz8YiTr8gRTwyEaCumd-MToTmIo',
            ],
            [
                'kid' => '2010-12-29',
                'kty' => 'RSA',
                'n' => 'ofgWCuLjybRlzo0tZWJjNiuSfb4p4fAkd_wWJcyQoTbji9k0l8W26mPddxHmfHQp-Vaw-4qPCJrcS2mJPMEzP1Pt0Bm4d4QlL-yRT-SFd2lZS-pCgNMsD1W_YpRPEwOWvG6b32690r2jZ47soMZo9wGzjb_7OMg0LOL-bSf63kpaSHSXndS5z5rexMdbBYUsLA9e-KXBdQOS-UTo7WTBEMa2R2CapHg665xsmtdVMTBQY4uDZlxvb3qCo5ZwKh9kG4LT6_I5IhlJH7aGhyxXFvUK-DWNmoudF8NAco9_h9iaGNj8q2ethFkMLs91kzk2PAcDTW9gb54h4FRWyuXpoQ',
                'e' => 'AQAB',
                'd' => 'Eq5xpGnNCivDflJsRQBXHx1hdR1k6Ulwe2JZD50LpXyWPEAeP88vLNO97IjlA7_GQ5sLKMgvfTeXZx9SE-7YwVol2NXOoAJe46sui395IW_GO-pWJ1O0BkTGoVEn2bKVRUCgu-GjBVaYLU6f3l9kJfFNS3E0QbVdxzubSu3Mkqzjkn439X0M_V51gfpRLI9JYanrC4D4qAdGcopV_0ZHHzQlBjudU2QvXt4ehNYTCBr6XCLQUShb1juUO1ZdiYoFaFQT5Tw8bGUl_x_jTj3ccPDVZFD9pIuhLhBOneufuBiB4cS98l2SR_RQyGWSeWjnczT0QU91p1DhOVRuOopznQ',
            ],
            [
                'kid' => 'e9bc097a-ce51-4036-9562-d2ade882db0d',
                'kty' => 'EC',
                'crv' => 'P-256',
                'x' => 'f83OJ3D2xF1Bg8vub9tLe1gHMzV76e8Tus9uPHvRVEU',
                'y' => 'x_FEzRu9m36HLN_tue659LNpXW6pCyStikYjKIWI5a0',
                'd' => 'jpsQnnGQmL-YBIffH1136cspYG6-0iY7X1fCE9-E9LI',
            ],
            [
                'kid' => '123456789',
                'kty' => 'RSA',
                'n' => 'tpS1ZmfVKVP5KofIhMBP0tSWc4qlh6fm2lrZSkuKxUjEaWjzZSzs72gEIGxraWusMdoRuV54xsWRyf5KeZT0S-I5Prle3Idi3gICiO4NwvMk6JwSBcJWwmSLFEKyUSnB2CtfiGc0_5rQCpcEt_Dn5iM-BNn7fqpoLIbks8rXKUIj8-qMVqkTXsEKeKinE23t1ykMldsNaaOH-hvGti5Jt2DMnH1JjoXdDXfxvSP_0gjUYb0ektudYFXoA6wekmQyJeImvgx4Myz1I4iHtkY_Cp7J4Mn1ejZ6HNmyvoTE_4OuY1uCeYv4UyXFc1s1uUyYtj4z57qsHGsS4dQ3A2MJsw',
                'e' => 'AQAB',
                'p' => '5BGU1c7af_5sFyfsa-onIJgo5BZu8uHvz3Uyb8OA0a-G9UPO1ShLYjX0wUfhZcFB7fwPtgmmYAN6wKGVce9eMAbX4PliPk3r-BcpZuPKkuLk_wFvgWAQ5Hqw2iEuwXLV0_e8c2gaUt_hyMC5-nFc4v0Bmv6NT6Pfry-UrK3BKWc',
                'd' => 'Kp0KuZwCZGL1BLgsVM-N0edMNitl9wN5Hf2WOYDoIqOZNAEKzdJuenIMhITJjRFUX05GVL138uyp2js_pqDdY9ipA7rAKThwGuDdNphZHech9ih3DGEPXs-YpmHqvIbCd3GoGm38MKwxYkddEpFnjo8rKna1_BpJthrFxjDRhw9DxJBycOdH2yWTyp62ZENPvneK40H2a57W4QScTgfecZqD59m2fGUaWaX5uUmIxaEmtGoJnd9RE4oywKhgN7_TK7wXRlqA4UoRPiH2ACrdU-_cLQL9Jc0u0GqZJK31LDbOeN95QgtSCc72k3Vtzy3CrVpp5TAA67s1Gj9Skn-CAQ',
                'q' => 'zPD-B-nrngwF-O99BHvb47XGKR7ON8JCI6JxavzIkusMXCB8rMyYW8zLs68L8JLAzWZ34oMq0FPUnysBxc5nTF8Nb4BZxTZ5-9cHfoKrYTI3YWsmVW2FpCJFEjMs4NXZ28PBkS9b4zjfS2KhNdkmCeOYU0tJpNfwmOTI90qeUdU',
                'dp' => 'aJrzw_kjWK9uDlTeaES2e4muv6bWbopYfrPHVWG7NPGoGdhnBnd70-jhgMEiTZSNU8VXw2u7prAR3kZ-kAp1DdwlqedYOzFsOJcPA0UZhbORyrBy30kbll_7u6CanFm6X4VyJxCpejd7jKNw6cCTFP1sfhWg5NVJ5EUTkPwE66M',
                'dq' => 'Swz1-m_vmTFN_pu1bK7vF7S5nNVrL4A0OFiEsGliCmuJWzOKdL14DiYxctvnw3H6qT2dKZZfV2tbse5N9-JecdldUjfuqAoLIe7dD7dKi42YOlTC9QXmqvTh1ohnJu8pmRFXEZQGUm_BVhoIb2_WPkjav6YSkguCUHt4HRd2YwE',
                'qi' => 'BocuCOEOq-oyLDALwzMXU8gOf3IL1Q1_BWwsdoANoh6i179psxgE4JXToWcpXZQQqub8ngwE6uR9fpd3m6N_PL4T55vbDDyjPKmrL2ttC2gOtx9KrpPh-Z7LQRo4BE48nHJJrystKHfFlaH2G7JxHNgMBYVADyttN09qEoav8Os',
            ],
            [
                'kty' => 'RSA',
                'n' => 'oahUIoWw0K0usKNuOR6H4wkf4oBUXHTxRvgb48E-BVvxkeDNjbC4he8rUWcJoZmds2h7M70imEVhRU5djINXtqllXI4DFqcI1DgjT9LewND8MW2Krf3Spsk_ZkoFnilakGygTwpZ3uesH-PFABNIUYpOiN15dsQRkgr0vEhxN92i2asbOenSZeyaxziK72UwxrrKoExv6kc5twXTq4h-QChLOln0_mtUZwfsRaMStPs6mS6XrgxnxbWhojf663tuEQueGC-FCMfra36C9knDFGzKsNa7LZK2djYgyD3JR_MB_4NUJW_TqOQtwHYbxevoJArm-L5StowjzGy-_bq6Gw',
                'e' => 'AQAB',
                'd' => 'kLdtIj6GbDks_ApCSTYQtelcNttlKiOyPzMrXHeI-yk1F7-kpDxY4-WY5NWV5KntaEeXS1j82E375xxhWMHXyvjYecPT9fpwR_M9gV8n9Hrh2anTpTD93Dt62ypW3yDsJzBnTnrYu1iwWRgBKrEYY46qAZIrA2xAwnm2X7uGR1hghkqDp0Vqj3kbSCz1XyfCs6_LehBwtxHIyh8Ripy40p24moOAbgxVw3rxT_vlt3UVe4WO3JkJOzlpUf-KTVI2Ptgm-dARxTEtE-id-4OJr0h-K-VFs3VSndVTIznSxfyrj8ILL6MG_Uv8YAu7VILSB3lOW085-4qE3DzgrTjgyQ',
                'p' => '1r52Xk46c-LsfB5P442p7atdPUrxQSy4mti_tZI3Mgf2EuFVbUoDBvaRQ-SWxkbkmoEzL7JXroSBjSrK3YIQgYdMgyAEPTPjXv_hI2_1eTSPVZfzL0lffNn03IXqWF5MDFuoUYE0hzb2vhrlN_rKrbfDIwUbTrjjgieRbwC6Cl0',
                'q' => 'wLb35x7hmQWZsWJmB_vle87ihgZ19S8lBEROLIsZG4ayZVe9Hi9gDVCOBmUDdaDYVTSNx_8Fyw1YYa9XGrGnDew00J28cRUoeBB_jKI1oma0Orv1T9aXIWxKwd4gvxFImOWr3QRL9KEBRzk2RatUBnmDZJTIAfwTs0g68UZHvtc',
                'dp' => 'ZK-YwE7diUh0qR1tR7w8WHtolDx3MZ_OTowiFvgfeQ3SiresXjm9gZ5KLhMXvo-uz-KUJWDxS5pFQ_M0evdo1dKiRTjVw_x4NyqyXPM5nULPkcpU827rnpZzAJKpdhWAgqrXGKAECQH0Xt4taznjnd_zVpAmZZq60WPMBMfKcuE',
                'dq' => 'Dq0gfgJ1DdFGXiLvQEZnuKEN0UUmsJBxkjydc3j4ZYdBiMRAy86x0vHCjywcMlYYg4yoC4YZa9hNVcsjqA3FeiL19rk8g6Qn29Tt0cj8qqyFpz9vNDBUfCAiJVeESOjJDZPYHdHY8v1b-o-Z2X5tvLx-TCekf7oxyeKDUqKWjis',
                'qi' => 'VIMpMYbPf47dT1w_zDUXfPimsSegnMOA1zTaX7aGk_8urY6R8-ZW1FxU7AlWAyLWybqq6t16VFd7hQd0y6flUK4SlOydB61gwanOsXGOAOv82cHq0E3eL4HrtZkUuKvnPrMnsUUFlfUdybVzxyjz9JF_XyaY14ardLSjf4L_FNY',
            ],
            [
                'kty' => 'RSA',
                'n' => 'sXchDaQebHnPiGvyDOAT4saGEUetSyo9MKLOoWFsueri23bOdgWp4Dy1WlUzewbgBHod5pcM9H95GQRV3JDXboIRROSBigeC5yjU1hGzHHyXss8UDprecbAYxknTcQkhslANGRUZmdTOQ5qTRsLAt6BTYuyvVRdhS8exSZEy_c4gs_7svlJJQ4H9_NxsiIoLwAEk7-Q3UXERGYw_75IDrGA84-lA_-Ct4eTlXHBIY2EaV7t7LjJaynVJCpkv4LKjTTAumiGUIuQhrNhZLuF_RJLqHpM2kgWFLU7-VTdL1VbC2tejvcI2BlMkEpk1BzBZI0KQB0GaDWFLN-aEAw3vRw',
                'e' => 'AQAB',
                'd' => 'VFCWOqXr8nvZNyaaJLXdnNPXZKRaWCjkU5Q2egQQpTBMwhprMzWzpR8Sxq1OPThh_J6MUD8Z35wky9b8eEO0pwNS8xlh1lOFRRBoNqDIKVOku0aZb-rynq8cxjDTLZQ6Fz7jSjR1Klop-YKaUHc9GsEofQqYruPhzSA-QgajZGPbE_0ZaVDJHfyd7UUBUKunFMScbflYAAOYJqVIVwaYR5zWEEceUjNnTNo_CVSj-VvXLO5VZfCUAVLgW4dpf1SrtZjSt34YLsRarSb127reG_DUwg9Ch-KyvjT1SkHgUWRVGcyly7uvVGRSDwsXypdrNinPA4jlhoNdizK2zF2CWQ',
                'p' => '9gY2w6I6S6L0juEKsbeDAwpd9WMfgqFoeA9vEyEUuk4kLwBKcoe1x4HG68ik918hdDSE9vDQSccA3xXHOAFOPJ8R9EeIAbTi1VwBYnbTp87X-xcPWlEPkrdoUKW60tgs1aNd_Nnc9LEVVPMS390zbFxt8TN_biaBgelNgbC95sM',
                'q' => 'uKlCKvKv_ZJMVcdIs5vVSU_6cPtYI1ljWytExV_skstvRSNi9r66jdd9-yBhVfuG4shsp2j7rGnIio901RBeHo6TPKWVVykPu1iYhQXw1jIABfw-MVsN-3bQ76WLdt2SDxsHs7q7zPyUyHXmps7ycZ5c72wGkUwNOjYelmkiNS0',
                'dp' => 'w0kZbV63cVRvVX6yk3C8cMxo2qCM4Y8nsq1lmMSYhG4EcL6FWbX5h9yuvngs4iLEFk6eALoUS4vIWEwcL4txw9LsWH_zKI-hwoReoP77cOdSL4AVcraHawlkpyd2TWjE5evgbhWtOxnZee3cXJBkAi64Ik6jZxbvk-RR3pEhnCs',
                'dq' => 'o_8V14SezckO6CNLKs_btPdFiO9_kC1DsuUTd2LAfIIVeMZ7jn1Gus_Ff7B7IVx3p5KuBGOVF8L-qifLb6nQnLysgHDh132NDioZkhH7mI7hPG-PYE_odApKdnqECHWw0J-F0JWnUd6D2B_1TvF9mXA2Qx-iGYn8OVV1Bsmp6qU',
                'qi' => 'eNho5yRBEBxhGBtQRww9QirZsB66TrfFReG_CcteI1aCneT0ELGhYlRlCtUkTRclIfuEPmNsNDPbLoLqqCVznFbvdB7x-Tl-m0l_eFTj2KiqwGqE9PZB9nNTwMVvH3VRRSLWACvPnSiwP8N5Usy-WRXS-V7TbpxIhvepTfE0NNo',
            ],
            [
                'kty' => 'RSA',
                'n' => 'ofgWCuLjybRlzo0tZWJjNiuSfb4p4fAkd_wWJcyQoTbji9k0l8W26mPddxHmfHQp-Vaw-4qPCJrcS2mJPMEzP1Pt0Bm4d4QlL-yRT-SFd2lZS-pCgNMsD1W_YpRPEwOWvG6b32690r2jZ47soMZo9wGzjb_7OMg0LOL-bSf63kpaSHSXndS5z5rexMdbBYUsLA9e-KXBdQOS-UTo7WTBEMa2R2CapHg665xsmtdVMTBQY4uDZlxvb3qCo5ZwKh9kG4LT6_I5IhlJH7aGhyxXFvUK-DWNmoudF8NAco9_h9iaGNj8q2ethFkMLs91kzk2PAcDTW9gb54h4FRWyuXpoQ',
                'e' => 'AQAB',
                'd' => 'Eq5xpGnNCivDflJsRQBXHx1hdR1k6Ulwe2JZD50LpXyWPEAeP88vLNO97IjlA7_GQ5sLKMgvfTeXZx9SE-7YwVol2NXOoAJe46sui395IW_GO-pWJ1O0BkTGoVEn2bKVRUCgu-GjBVaYLU6f3l9kJfFNS3E0QbVdxzubSu3Mkqzjkn439X0M_V51gfpRLI9JYanrC4D4qAdGcopV_0ZHHzQlBjudU2QvXt4ehNYTCBr6XCLQUShb1juUO1ZdiYoFaFQT5Tw8bGUl_x_jTj3ccPDVZFD9pIuhLhBOneufuBiB4cS98l2SR_RQyGWSeWjnczT0QU91p1DhOVRuOopznQ',
                'p' => '4BzEEOtIpmVdVEZNCqS7baC4crd0pqnRH_5IB3jw3bcxGn6QLvnEtfdUdiYrqBdss1l58BQ3KhooKeQTa9AB0Hw_Py5PJdTJNPY8cQn7ouZ2KKDcmnPGBY5t7yLc1QlQ5xHdwW1VhvKn-nXqhJTBgIPgtldC-KDV5z-y2XDwGUc',
                'q' => 'uQPEfgmVtjL0Uyyx88GZFF1fOunH3-7cepKmtH4pxhtCoHqpWmT8YAmZxaewHgHAjLYsp1ZSe7zFYHj7C6ul7TjeLQeZD_YwD66t62wDmpe_HlB-TnBA-njbglfIsRLtXlnDzQkv5dTltRJ11BKBBypeeF6689rjcJIDEz9RWdc',
                'dp' => 'BwKfV3Akq5_MFZDFZCnW-wzl-CCo83WoZvnLQwCTeDv8uzluRSnm71I3QCLdhrqE2e9YkxvuxdBfpT_PI7Yz-FOKnu1R6HsJeDCjn12Sk3vmAktV2zb34MCdy7cpdTh_YVr7tss2u6vneTwrA86rZtu5Mbr1C1XsmvkxHQAdYo0',
                'dq' => 'h_96-mK1R_7glhsum81dZxjTnYynPbZpHziZjeeHcXYsXaaMwkOlODsWa7I9xXDoRwbKgB719rrmI2oKr6N3Do9U0ajaHF-NKJnwgjMd2w9cjz3_-kyNlxAr2v4IKhGNpmM5iIgOS1VZnOZ68m6_pbLBSp3nssTdlqvd0tIiTHU',
                'qi' => 'IYd7DHOhrWvxkwPQsRM2tOgrjbcrfvtQJipd-DlcxyVuuM9sQLdgjVk2oy26F0EmpScGLq2MowX7fhd_QJQ3ydy5cY7YIBi87w93IKLEdfnbJtoOPLUW0ITrJReOgo1cq9SbsxYawBgfp_gh6A5603k2-ZQwVK0JKSHuLFkuQ3U',
            ],
            [
                'kty' => 'EC',
                'crv' => 'P-521',
                'x' => 'AekpBQ8ST8a8VcfVOTNl353vSrDCLLJXmPk06wTjxrrjcBpXp5EOnYG_NjFZ6OvLFV1jSfS9tsz4qUxcWceqwQGk',
                'y' => 'ADSmRA43Z1DSNx_RvcLI87cdL07l6jQyyBXMoxVg_l2Th-x3S1WDhjDly79ajL4Kkd0AZMaZmh9ubmf63e3kyMj2',
                'd' => 'AY5pb7A0UFiB3RELSD64fTLOSV_jazdF7fLYyuTw8lOfRhWg6Y6rUrPAxerEzgdRhajnu0ferB0d53vM9mE15j2C',
            ],
        ]];

        return JWKSet::createFromKeyData($keys);
    }

    /**
     * @return JWKSet
     */
    private function getSymmetricKeySet(): JWKSet
    {
        $keys = ['keys' => [
            [
                'kid' => 'DIR_1',
                'kty' => 'oct',
                'k' => Base64Url::encode(hex2bin('00112233445566778899AABBCCDDEEFF000102030405060708090A0B0C0D0E0F')),
            ],
            [
                'kty' => 'oct',
                'k' => 'f5aN5V6iihwQVqP-tPNNtkIJNCwUb9-JukCIKkF0rNfxqxA771RJynYAT2xtzAP0MYaR7U5fMP_wvbRQq5l38Q',
            ],
            [
                'kty' => 'oct',
                'k' => 'GawgguFyGrWKav7AX4VKUg',
            ],
            [
                'kty' => 'oct',
                'k' => 'AyM1SysPpbyDfgZld3umj1qzKObwVMkoqQ-EstJQLr_T-1qS0gZH75aKtMN3Yj0iPS4hcgUuTwjAzZr1Z9CAow',
            ],
        ]];

        return JWKSet::createFromKeyData($keys);
    }
}
