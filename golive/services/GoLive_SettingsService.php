<?php

namespace Craft;

class GoLive_SettingsService extends BaseApplicationComponent {

  /**
   * @var string A string prepended to Exception messages
   */
  public static $exceptionPrefix = 'GoLive Settings Exception: ';

  /**
   * Defines settings fields, types, and some defaults.
   *
   * @return array
   */
  public function defineSettings() {
    return array(
      'beforeBackup' => array(AttributeType::Mixed, 'default' => array(
        'commands' => array()
      )),
      'backup' => array(AttributeType::Mixed, 'default' => array(
        'keepBackup' => '',
        'excludeTables' => array()
      )),
      'copyBackup' => array(AttributeType::Mixed, 'default' => array(
        'destination' => ''
      )),
      'importBackup' => array(AttributeType::Mixed, 'default' => array(
        'keepBackup' => ''
      )),
      'afterImport' => array(AttributeType::Mixed, 'default' => array(
        'commands' => array()
      )),
      'ssh' => array(AttributeType::Mixed, 'default' => array(
        'local' => array(),
        'remote' => array()
      )),
      'mysql' => array(AttributeType::Mixed, 'default' => array())
    );
  }

  /**
   * Encrypts sensitive fields and returns the whole set
   *
   * @param $fields array
   *
   * @return array
   */
  public function encryptFields($fields) {
    // Encrypt local SSH password
    if (isset($fields['ssh']['local']['password'])) {
      $fields['ssh']['local']['password'] =
        craft()->goLive_security->encrypt($fields['ssh']['local']['password']);
    }

    // Encrypt remote SSH password
    if (isset($fields['ssh']['remote']['password'])) {
      $fields['ssh']['remote']['password'] =
        craft()->goLive_security->encrypt($fields['ssh']['remote']['password']);
    }

    // Encrypt remote MySQL password
    if (isset($fields['mysql']['password'])) {
      $fields['mysql']['password'] =
        craft()->goLive_security->encrypt($fields['mysql']['password']);
    }

    return $fields;
  }

  public function getSettingsUrl() {
    if ($this->isPluginEnabled()) {
      return UrlHelper::getUrl('/golive/settings');
    }
    else {
      return false;
    }
  }

  /**
   * Determines whether the admin features of the plugin are available to the user.
   *
   * @return bool
   */
  public function isPluginEnabled() {
    $environmentVars = craft()->config->get('environmentVariables');

    if(! array_key_exists('goLive_enabled', $environmentVars)) {
      return true;
    }

    if ($environmentVars['goLive_enabled'] === false) {
      return false;
    }
    else {
      return true;
    }
  }

  /**
   * Cleans up table fields by removing any rows that are empty.
   *
   * @param array $fields
   *
   * @return array
   */
  public function cleanTables($fields) {

    // Remove empty commands from the beforeBackup set
    if(isset($fields['beforeBackup']['commands'])) {
      $fields['beforeBackup']['commands'] =
        array_filter($fields['beforeBackup']['commands'], function ($command) {
          if( trim($command['command']) === '') {
            return false;
          }

          return true;
        });
    }

    // Remove empty table name from the backup exclusion set
    if(isset($fields['backup']['excludeTables'])) {
      $fields['backup']['excludeTables'] =
        array_filter($fields['backup']['excludeTables'], function ($table) {
          if( trim($table['table']) === '') {
            return false;
          }

          return true;
        });

      // remove the DB prefix from table names, in case they didn't RTFM.
      $fields['backup']['excludeTables'] =
        array_map(function ($table) {
          if (strpos($table['table'], craft()->db->tablePrefix) === 0) {
            $table['table'] = str_replace(craft()->db->tablePrefix, '', $table['table']);
          }

          return $table;
        }, $fields['backup']['excludeTables']);
    }

    // Remove empty commands from the afterImport set
    if(isset($fields['afterImport']['commands'])) {
      $fields['afterImport']['commands'] =
        array_filter($fields['afterImport']['commands'], function ($command) {
          if( trim($command['command']) === '') {
            return false;
          }

          return true;
        });
    }

    return $fields;
  }

  /**
   * Verifies overall sanity of all settings and throws like crazy if there are
   * any problems.
   *
   * @throws Exception
   */
  public function verifySettings() {
    $settings = craft()->plugins->getPlugin('GoLive')->getSettings();

    // If there is one or more beforeBackup command, we need a working directory
    if(
      isset($settings['beforeBackup']['commands']) &&
      count($settings['beforeBackup']['commands']) !== 0 ) {
      if( trim($settings['beforeBackup']['cwd']) === '') {
        throw new Exception(self::$exceptionPrefix . 'The working directory for before-backup commands must not be empty.');
      }
    }

    // A destination for copying the backup must be set, and we don't want to
    // try to define a default for them.
    if(
      !isset($settings['copyBackup']['destination']) ||
      trim($settings['copyBackup']['destination']) === '' ) {
      throw new Exception(self::$exceptionPrefix . 'The destination for copying the backup must not be empty.');
    }

    // If there is one or more afterImport command, we need a working directory
    if(
      isset($settings['afterImport']['commands']) &&
      count($settings['afterImport']['commands']) !== 0 ) {
      if( trim($settings['afterImport']['cwd']) === '') {
        throw new Exception(self::$exceptionPrefix . 'The working directory for after-import commands must not be empty.');
      }
    }

    // If there is one or more beforeBackup command, the credentials must be sane.
    if(
      isset($settings['beforeBackup']['commands']) &&
      count($settings['beforeBackup']['commands']) !== 0
    ) {

      if(
        trim($settings['ssh']['local']['hostname']) === '' ||
        trim($settings['ssh']['local']['username']) === '' ||
        trim($settings['ssh']['local']['password']) === ''
      ) {
        throw new Exception(self::$exceptionPrefix . 'None of the local SSH credentials may be empty.');
      }
    }

    // The remote SSH credentials must be sane
    if(
      trim($settings['ssh']['remote']['hostname']) === '' ||
      trim($settings['ssh']['remote']['username']) === '' ||
      trim($settings['ssh']['remote']['password']) === ''
    ) {
      throw new Exception(self::$exceptionPrefix . 'None of the remote SSH credentials may be empty.');
    }

    // The remote MySQL credentials must be sane
    if(
      trim($settings['mysql']['hostname']) === '' ||
      trim($settings['mysql']['username']) === '' ||
      trim($settings['mysql']['password']) === '' ||
      trim($settings['mysql']['dbname']) === ''
    ) {
      throw new Exception(self::$exceptionPrefix . 'None of the remote MySQL credentials may be empty.');
    }
  }
}
