<?php
namespace ParagonIE\Halite;

use \ParagonIE\Halite\Asymmetric\EncryptionSecretKey;
use \ParagonIE\Halite\Asymmetric\EncryptionPublicKey;
use \ParagonIE\Halite\Asymmetric\SignatureSecretKey;
use \ParagonIE\Halite\Asymmetric\SignaturePublicKey;
use \ParagonIE\Halite\Alerts as CryptoException;

/**
 * Describes a pair of secret and public keys
 */
class KeyPair
{
    protected  $secret_key;
    protected  $public_key;
    
    /**
     * 
     * Pass it a secret key, it will automatically generate a public key
     * 
     * @param ...Key $keys
     */
    public function __construct(Key ...$keys)
    {
        switch (\count($keys)) {
            /**
             * If we received two keys, it must be an asymmetric secret key and
             * an asymmetric public key, in either order.
             */
            case 2:
                if (!$keys[0]->isAsymmetricKey()) {
                    throw new CryptoException\InvalidKey(
                        'Only keys intended for asymmetric cryptography can be used in a KeyPair object'
                    );
                } elseif (!$keys[1]->isAsymmetricKey()) {
                    throw new CryptoException\InvalidKey(
                        'Only keys intended for asymmetric cryptography can be used in a KeyPair object'
                    );
                }
                if ($keys[0]->isPublicKey()) {
                    if ($keys[1]->isPublicKey()) {
                        throw new CryptoException\InvalidKey(
                            'Both keys cannot be public keys'
                        );
                    }
                    $sign = $keys[1]->isSigningKey();
                    // $keys[0] is public, $keys[1] is secret
                    if ($sign) {
                        $this->secret_key = $keys[1] instanceof SignatureSecretKey
                            ? $keys[1]
                            : new SignatureSecretKey(
                                $keys[1]->get()
                            );
                        $pub = \Sodium\crypto_sign_publickey_from_secretkey(
                            $keys[1]->get()
                        );
                        $this->public_key = new SignaturePublicKey($pub, true);
                        \Sodium\memzero($pub);
                    } else {
                        $this->secret_key = $keys[1] instanceof EncryptionSecretKey
                            ? $keys[1]
                            : new EncryptionSecretKey(
                                $keys[1]->get()
                            );
                        // crypto_box - Curve25519
                        $pub = \Sodium\crypto_box_publickey_from_secretkey(
                            $keys[1]->get()
                        );
                        $this->public_key = new EncryptionPublicKey($pub, false);
                        \Sodium\memzero($pub);
                    }
                } elseif ($keys[1]->isPublicKey()) {
                    $sign = $keys[0]->isSigningKey();
                    // We can deduce that $keys[0] is a secret key
                    if ($sign) {
                        $this->secret_key = $keys[0] instanceof SignatureSecretKey
                            ? $keys[0]
                            : new SignatureSecretKey(
                                $keys[0]->get()
                            );
                        // crypto_sign - Ed25519
                        $pub = \Sodium\crypto_sign_publickey_from_secretkey(
                            $keys[0]->get()
                        );
                        $this->public_key = new SignaturePublicKey($pub);
                        \Sodium\memzero($pub);
                    } else {
                        $this->secret_key = $keys[0] instanceof EncryptionSecretKey
                            ? $keys[0]
                            : new EncryptionSecretKey(
                                $keys[0]->get()
                            );
                        // crypto_box - Curve25519
                        $pub = \Sodium\crypto_box_publickey_from_secretkey(
                            $keys[0]->get()
                        );
                        $this->public_key = new EncryptionPublicKey($pub);
                        \Sodium\memzero($pub);
                    }
                } else {
                    throw new CryptoException\InvalidKey(
                        'Both keys cannot be secret keys'
                    );
                }
                break;
            /**
             * If we only received one key, it must be an asymmetric secret key!
             */
            case 1:
                if (!$keys[0]->isAsymmetricKey()) {
                    throw new CryptoException\InvalidKey(
                        'Only keys intended for asymmetric cryptography can be used in a KeyPair object'
                    );
                }
                if ($keys[0]->isPublicKey()) {
                    throw new CryptoException\InvalidKey(
                        'We cannot generate a valid keypair given only a public key; we can given only a secret key, however.'
                    );
                }
                $sign = $keys[0]->isSigningKey();
                // We can deduce that $keys[0] is a secret key
                if ($sign) {
                    $this->secret_key = $keys[0] instanceof SignatureSecretKey
                        ? $keys[0]
                        : new SignatureSecretKey(
                            $keys[0]->get()
                        );
                    // crypto_sign - Ed25519
                    $pub = \Sodium\crypto_sign_publickey_from_secretkey(
                        $keys[0]->get()
                    );
                    $this->public_key = new SignaturePublicKey($pub);
                    \Sodium\memzero($pub);
                } else {
                    $this->secret_key = $keys[0] instanceof EncryptionSecretKey
                        ? $keys[0]
                        : new EncryptionSecretKey(
                            $keys[0]->get()
                        );
                    // crypto_box - Curve25519
                    $pub = \Sodium\crypto_box_publickey_from_secretkey(
                        $keys[0]->get()
                    );
                    $this->public_key = new EncryptionPublicKey($pub);
                    \Sodium\memzero($pub);
                }
                break;
            default:
                throw new \InvalidArgumentException(
                    'Halite\\KeyPair expects 1 or 2 keys'
                );
        }
    }
    
    /**
     * Hide this from var_dump(), etc.
     * 
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'privateKey' => '**protected**',
            'publicKey' => '**protected**'
        ];
    }
    
    /**
     * Derive an encryption key from a password and a salt
     * 
     * @param string $password
     * @param string $salt
     * @param int $type
     * @return array|\ParagonIE\Halite\Key
     * @throws CryptoException\InvalidFlags
     */
    public static function deriveFromPassword(
        $password,
        $salt,
        $type = self::CRYPTO_BOX
    ) { 
        if (Key::doesNotHaveFlag($type, Key::ASYMMETRIC)) {
            throw new CryptoException\InvalidKey(
                'An asymmetric key type must be passed to KeyPair::generate()'
            );
        }
        if (Key::hasFlag($type, Key::ENCRYPTION)) {
            $key = Key::deriveFromPassword($password, $salt, Key::CRYPTO_BOX);
            $keypair = new KeyPair(...$key);
            return $keypair;
        } elseif (Key::hasFlag($type, Key::SIGNATURE)) {
            $key = Key::deriveFromPassword($password, $salt, Key::CRYPTO_SIGN);
            $keypair = new KeyPair(...$key);
            return $keypair;
        }
        throw new CryptoException\InvalidKey(
            'You must specify encryption or authentication flags.'
        );
    }
    
    /**
     * Generate a new keypair
     * 
     * @param int $type Key flags
     * @param &string $secret_key - Reference to optional variable to store secret key in
     * @return KeyPair
     * @throws CryptoException\InvalidKey
     */
    public static function generate($type = Key::CRYPTO_BOX, &$secret_key = null)
    {
        if (Key::doesNotHaveFlag($type, Key::ASYMMETRIC)) {
            throw new CryptoException\InvalidKey(
                'An asymmetric key type must be passed to KeyPair::generate()'
            );
        }
        if (Key::hasFlag($type, Key::ENCRYPTION)) {
            $key = Key::generate(Key::CRYPTO_BOX, $secret_key);
            $keypair = new KeyPair(...$key);
            return $keypair;
        } elseif (Key::hasFlag($type, Key::SIGNATURE)) {
            $key = Key::generate(Key::CRYPTO_SIGN, $secret_key);
            $keypair = new KeyPair(...$key);
            return $keypair;
        }
        throw new CryptoException\InvalidKey(
            'You must specify encryption or authentication flags.'
        );
    }
    
    /**
     * Get a Key object for the public key
     * 
     * @return Key
     */
    public function getPublicKey()
    {
       return $this->public_key;
    }
    
    /**
     * Get a Key object for the secret key
     * 
     * @return Key
     */
    public function getSecretKey()
    {
       return $this->secret_key;
    }
    
    /**
     * Load a keypair from a file
     * 
     * @param string $filePath
     * @param int $type
     * @return KeyPair
     * 
     * @throws CryptoException\InvalidFlags
     */
    public static function fromFile(
        $filePath,
        $type = Key::CRYPTO_BOX
    ) {
        $keys = Key::fromFile(
            $filePath,
            ($type | Key::ASYMMETRIC)
        );
        return new KeyPair(...$keys);
    }
    
    /**
     * Save a copy of the secret key to a file
     *
     * @param string $filePath
     * @return bool|int
     */
    public function saveToFile($filePath)
    {
        return $this->secret_key->saveToFile($filePath);
    }
}
