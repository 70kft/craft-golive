<?php

namespace Craft;

use \CSecurityManager;

class GoLive_SecurityService extends SecurityService {

  /**
   * A local instance of CSecurityManager
   *
   * @var CSecurityManager
   */
  private $securityManager;


  /**
   * The name of the environment variable where an encryption key can be found
   *
   * @var string
   */

  private static $ENCRYPTION_CONFIG_KEY = 'goLive_encryptionKey';

  /**
   * 32 null characters to provide minimal security if an encryption key has not
   * been provided.
   *
   * @var string
   */
  private static $DEFAULT_ENCRYPTION_KEY = "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0";

  /**
   * Automatically run when this component is included.
   *
   * @return null
   */
  public function init() {
    $this->_initializeSecurityManager();
  }

  /**
   * Encrypts and Base64-encodes some plaintext.
   *
   * @param $plainText string
   *
   * @return string
   */
  public function encrypt($plainText) {
    $cipherText = $this->securityManager->encrypt($plainText);
    $cipherText = base64_encode($cipherText);

    return $cipherText;
  }

  /**
   * Decrypts some Base64-encoded ciphertext.
   *
   * @param $cipherText string
   *
   * @return string
   */
  public function decrypt($cipherText) {
    $plainText = base64_decode($cipherText);
    $plainText = $this->securityManager->decrypt($plainText);

    return $plainText;
  }

  /**
   * Generates 32 random bytes and returns them as a Base64-encoded string.
   *
   * @return string
   */
  public function generateRandomKey() {
    return base64_encode(
      $this->securityManager->generateRandomBytes(32)
    );
  }

  /**
   * Returns a 32-bit encryption key, either using the insecure default key or
   * one provided as the environment variable "goLive_encryptionKey".
   *
   * @return string
   */
  private function _getEncryptionKey() {
    $environmentVars = craft()->config->get('environmentVariables');
    $key = null;

    if(! array_key_exists(self::$ENCRYPTION_CONFIG_KEY, $environmentVars)) {
      $key = self::$DEFAULT_ENCRYPTION_KEY;
    }
    else {
      $key = base64_decode($environmentVars[self::$ENCRYPTION_CONFIG_KEY], true);
    }

    if ($key === false) {
      throw new Exception('goLive_encryptionKey must be a valid Base64-encoded string.');
    }

    if (strlen($key) !== 32) {
      throw new Exception('goLive_encryptionKey must be exactly 32 bytes, Base64-encoded. Remove the invalid key and use the key generator at /admin/golive/key.');
    }

    return $key;
  }

  /**
   * Initializes the local instance of CSecurityManager, then sets the crypt
   * algorithm and encryption key.
   *
   * @return null
   */
  private function _initializeSecurityManager() {
    $this->securityManager = new CSecurityManager();
    $this->securityManager->init();

    $this->securityManager->cryptAlgorithm = 'rijndael-256';

    $this->securityManager->setEncryptionKey($this->_getEncryptionKey());
  }
}
