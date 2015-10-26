<?php
use \ParagonIE\Halite\File;
use \ParagonIE\Halite\Key;
use \ParagonIE\Halite\Symmetric\SecretKey as SymmetricKey;
use \ParagonIE\Halite\Asymmetric\SecretKey as SecretKey;
use \ParagonIE\Halite\Asymmetric\PublicKey as PublicKey;
use \ParagonIE\Halite\Alerts as CryptoException;

/**
 * @backupGlobals disabled
 * @backupStaticAttributes disabled
 */
class FileTest extends PHPUnit_Framework_TestCase
{
    public function testEncrypt()
    {
        \touch(__DIR__.'/tmp/paragon_avatar.encrypted.png');
        \chmod(__DIR__.'/tmp/paragon_avatar.encrypted.png', 0777);
        \touch(__DIR__.'/tmp/paragon_avatar.decrypted.png');
        \chmod(__DIR__.'/tmp/paragon_avatar.decrypted.png', 0777);
        
        $key = new SymmetricKey(\str_repeat('B', 32));
        File::encryptFile(
            __DIR__.'/tmp/paragon_avatar.png',
            __DIR__.'/tmp/paragon_avatar.encrypted.png',
            $key
        );
        
        File::decryptFile(
            __DIR__.'/tmp/paragon_avatar.encrypted.png',
            __DIR__.'/tmp/paragon_avatar.decrypted.png',
            $key
        );
        
        $this->assertEquals(
            \hash_file('sha256', __DIR__.'/tmp/paragon_avatar.png'),
            \hash_file('sha256', __DIR__.'/tmp/paragon_avatar.decrypted.png')
        );
    }
    
    public function testEncryptFail()
    {
        \touch(__DIR__.'/tmp/paragon_avatar.encrypt_fail.png');
        \chmod(__DIR__.'/tmp/paragon_avatar.encrypt_fail.png', 0777);
        \touch(__DIR__.'/tmp/paragon_avatar.decrypt_fail.png');
        \chmod(__DIR__.'/tmp/paragon_avatar.decrypt_fail.png', 0777);
        
        $key = new SymmetricKey(\str_repeat('B', 32));
        File::encryptFile(
            __DIR__.'/tmp/paragon_avatar.png',
            __DIR__.'/tmp/paragon_avatar.encrypt_fail.png',
            $key
        );
        
        $fp = \fopen(__DIR__.'/tmp/paragon_avatar.encrypt_fail.png', 'ab');
        \fwrite($fp, \Sodium\randombytes_buf(1));
        fclose($fp);
            
        try {
            File::decryptFile(
                __DIR__.'/tmp/paragon_avatar.encrypt_fail.png',
                __DIR__.'/tmp/paragon_avatar.decrypt_fail.png',
                $key
            );
            throw new \Exception('ERROR: THIS SHOULD ALWAYS FAIL');
        } catch (CryptoException\InvalidMessage $e) {
            $this->assertTrue($e instanceof CryptoException\InvalidMessage);
        }
    }
    
    public function testSeal()
    {
        \touch(__DIR__.'/tmp/paragon_avatar.sealed.png');
        \chmod(__DIR__.'/tmp/paragon_avatar.sealed.png', 0777);
        \touch(__DIR__.'/tmp/paragon_avatar.opened.png');
        \chmod(__DIR__.'/tmp/paragon_avatar.opened.png', 0777);
        
        list($secretkey, $publickey) = Key::generate(Key::CRYPTO_BOX);
        
        File::sealFile(
            __DIR__.'/tmp/paragon_avatar.png',
            __DIR__.'/tmp/paragon_avatar.sealed.png',
            $publickey
        );
        
        File::unsealFile(
            __DIR__.'/tmp/paragon_avatar.sealed.png',
            __DIR__.'/tmp/paragon_avatar.opened.png',
            $secretkey
        );
        
        $this->assertEquals(
            \hash_file('sha256', __DIR__.'/tmp/paragon_avatar.png'),
            \hash_file('sha256', __DIR__.'/tmp/paragon_avatar.opened.png')
        );
    }
    
    public function testSealFail()
    {
        \touch(__DIR__.'/tmp/paragon_avatar.seal_fail.png');
        \chmod(__DIR__.'/tmp/paragon_avatar.seal_fail.png', 0777);
        \touch(__DIR__.'/tmp/paragon_avatar.open_fail.png');
        \chmod(__DIR__.'/tmp/paragon_avatar.open_fail.png', 0777);
        
        list($secretkey, $publickey) = Key::generate(Key::CRYPTO_BOX);
        
        File::sealFile(
            __DIR__.'/tmp/paragon_avatar.png',
            __DIR__.'/tmp/paragon_avatar.seal_fail.png',
            $publickey
        );
        
        $fp = \fopen(__DIR__.'/tmp/paragon_avatar.seal_fail.png', 'ab');
        \fwrite($fp, \Sodium\randombytes_buf(1));
        fclose($fp);
        
        try {
            File::unsealFile(
                __DIR__.'/tmp/paragon_avatar.seal_fail.png',
                __DIR__.'/tmp/paragon_avatar.opened.png',
                $secretkey
            );
            throw new \Exception('ERROR: THIS SHOULD ALWAYS FAIL');
        } catch (CryptoException\InvalidMessage $e) {
            $this->assertTrue($e instanceof CryptoException\InvalidMessage);
        }
    }
    
    public function testSign()
    {
        list($secretkey, $publickey) = Key::generate(Key::CRYPTO_SIGN);
        
        $signature = File::signFile(
            __DIR__.'/tmp/paragon_avatar.png',
            $secretkey
        );
        
        $this->assertTrue(
            File::verifyFile(
                __DIR__.'/tmp/paragon_avatar.png',
                $publickey,
                $signature
            )
        );
    }
    
    public function testChecksum()
    {
        $csum = File::checksumFile(__DIR__.'/tmp/paragon_avatar.png');
        $this->assertEquals(
            $csum,
            "09f9f74a0e742d057ca08394db4c2e444be88c0c94fe9a914c3d3758c7eccafb".
            "8dd286e3d6bc37f353e76c0c5aa2036d978ca28ffaccfa59f5dc1f076c5517a0"
        );
        
        $data = \Sodium\randombytes_buf(32);
        \file_put_contents(__DIR__.'/tmp/garbage.dat', $data);
        
        $hash = \Sodium\crypto_generichash($data, null, 64);
        $file = File::checksumFile(__DIR__.'/tmp/garbage.dat', null, true);
        $this->assertEquals(
            $hash,
            $file
        );
    }
    
    public function testStreamOperations()
    {
        $filename = \tempnam('/tmp', 'x');
        
        $BYTES = (\Sodium\randombytes_uniform(63) + 1) * 8;
        $buf = \Sodium\randombytes_buf($BYTES);
        \file_put_contents($filename, $buf);
        $file = \fopen($filename, 'rb');
        
        $read = File::readBytes($file, $BYTES);
        $this->assertEquals($buf, $read);
        
        $other_filename = \tempnam('/tmp', 'x');
        
        $fp = \fopen($other_filename, 'wb');
        $written = File::writeBytes($fp, $buf);
        \fclose($fp);
        
        $this->assertEquals($written, $BYTES);
    }
    
}
