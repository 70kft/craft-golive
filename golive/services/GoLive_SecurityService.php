<?php

namespace Craft;

use \CSecurityManager;

class GoLive_SecurityService extends SecurityService {
  private $securityManager;

  private $pluginHandle;

  private static $ENCRYPTION_CONFIG_KEY = 'goLive_encryptionKey';

  private static $DEFAULT_ENCRYPTION_KEY = "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0";

  public function init() {
    $this->pluginHandle = 'GoLive';
    $this->_initializeSecurityManager();
  }

  /**
   * Encrypt and then Base64-encode
   */
  public function encrypt($plainText) {
    $cipherText = $this->securityManager->encrypt($plainText);
    $cipherText = base64_encode($cipherText);

    return $cipherText;
  }

  public function decrypt($cipherText) {
    $plainText = base64_decode($cipherText);
    $plainText = $this->securityManager->decrypt($plainText);

    return $plainText;
  }

  public function generateRandomKey() {
    return $this->securityManager->generateRandomString(32);
  }

  private function _getEncryptionKey() {
    $environmentVars = craft()->config->get('environmentVariables');

    if(! array_key_exists(self::$ENCRYPTION_CONFIG_KEY, $environmentVars)) {
      return self::$DEFAULT_ENCRYPTION_KEY;
    }

    return $environmentVars[self::$ENCRYPTION_CONFIG_KEY];
  }

  private function _initializeSecurityManager() {
    $this->securityManager = new CSecurityManager();
    $this->securityManager->init();

    $this->securityManager->cryptAlgorithm = array(
      'rijndael-256',
      '',
      'cbc',
      ''
    );

    $pluginSettings = craft()->plugins->getPlugin($this->pluginHandle)->getSettings();

    $this->securityManager->setEncryptionKey($this->_getEncryptionKey());
  }
}
