<?php

namespace BiffBangPow\SSMonitor\Server\Helper;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;

class EncryptionHelper
{

    use Configurable;

    /**
     * @config
     * @var int $hashlength
     */
    private static int $hashlength = 50;

    /**
     * @var string $secret
     */
    private string $secret;

    /**
     * @var string $salt
     */
    private string $salt;


    public function __construct($encSecret, $encSalt)
    {
        if (!$encSalt || !$encSecret) {
            throw new \Exception("Missing encryption keys");
        }

        $this->setSecret($encSecret);
        $this->setSalt($encSalt);
    }


    public function encrypt($plaintext)
    {

        $secret = $this->getSecret();

        // Create a 32bit password
        $key = $this->create_32bit_password($secret);

        // Create a nonce: a piece of non-secret unique data that is used to randomize the cipher (safety against replay attack).
        // The nonce should be stored or shared along with the ciphertext, because the nonce needs to be reused with the same key.
        // In this class the nonce is shared with the ciphertext.
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        // Encrypted
        $ciphertext = bin2hex(
            sodium_crypto_secretbox($plaintext, $nonce, $key)
        );

        // Hex nonce (in order to send together with ciphertext)
        $nonce_hex = bin2hex($nonce);

        // Create hash from ciphertext+nonce
        // It is not necessary, but just an extra layer of defense:
        // - more difficult to manipulate the string
        // - a nonce is always 48 characters. Because of a trailing hash (of unkown length), the nonce cannot be identified easily.
        //   (a nonce does not have to be secret, this is just an extra precaution)
        $hash = $this->create_hash($ciphertext . $nonce_hex);

        // Return ciphertext + nonce + hash
        return $ciphertext . $nonce_hex . $hash;
    }


    public function decrypt($ciphertext)
    {

        $secret = $this->getSecret();

        // Create a 32bit password
        $key = $this->create_32bit_password($secret);

        //Get hash
        $hash = substr($ciphertext, -self::$hashlength);

        //Get ciphertext + nonce (remove trailing hash)
        $ciphertext = substr($ciphertext, 0, -self::$hashlength);

        //Re-create hash
        $hash_on_the_fly = $this->create_hash($ciphertext);

        //Check if hash is correct
        if ($hash !== $hash_on_the_fly) {
            //Do proper error handling
            return "error";
        } else {
            // Get nonce (last 48 chars of string)
            $nonce_hex = substr($ciphertext, -48);

            // Get ciphertext (remove nonce)
            $ciphertext = substr($ciphertext, 0, -48);

            // Bin nonce
            $nonce = hex2bin($nonce_hex);

            // Decrypted
            $plaintext = sodium_crypto_secretbox_open(
                hex2bin($ciphertext), $nonce, $key
            );

            return $plaintext;
        }
    }


    private function create_32bit_password($secret)
    {
        $salt = $this->getSalt();

        //Openlib needs a 32bit key for encryption
        return substr(bin2hex(sodium_crypto_generichash($secret . $salt)), 0, 32);
    }


    private function create_hash($ciphertext_and_nonce)
    {
        $hashlength = $this->config()->get('hashlength');
        return substr(bin2hex(sodium_crypto_generichash($ciphertext_and_nonce)), 0, $hashlength);
    }

    /**
     * @return mixed
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * @param mixed $secret
     */
    public function setSecret($secret): void
    {
        $this->secret = $secret;
    }

    /**
     * @return mixed
     */
    public function getSalt()
    {
        return $this->salt;
    }

    /**
     * @param mixed $salt
     */
    public function setSalt($salt): void
    {
        $this->salt = $salt;
    }
}
