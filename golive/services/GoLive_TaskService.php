<?php

namespace Craft;

use stdClass;
use Net_SSH2;
use Net_SFTP;

class GoLive_TaskService extends BaseApplicationComponent {


  //---
  // Public methods
  //---


  /**
   * Provide a list of all tasks that will be run during a deployment.
   *
   * @return array
   */
  public function enumerateTasks() {
    $tasks = array();
    $tasks = $this->_addBeforeBackupTasks($tasks);
    $tasks = $this->_addBackupTasks($tasks);
    $tasks = $this->_addAfterImportTasks($tasks);


    return $tasks;
  }

  /**
   * Create a database dump file, using as much of Craft's built-ins as possible
   *
   * @param BaseModel $settings The task's settings
   *
   * @return bool
   */
  public function backup($settings) {
    $backup = new GoLive_DbBackup();

    // The settings stores excluded tables as a collection, with table as a property.
    $excludeCollection = $this->_getSettings()['backup']['excludeTables'];

    // Map to a simple array of table names
    $exclude = array();
    foreach ($excludeCollection as $item) {
      if($item['table'] !== '') {
        array_push($exclude, $item['table']);
      }
    }

    // If tasks get copied, then GoLive will try to deploy again!
    array_push($exclude, 'tasks');

    $backup->setIgnoreDataTables($exclude);
    $backupFile = $backup->run($settings->backupFileName);

    return ($backupFile !== false);
  }


  /**
   * Copies the database dump to the production server via SFTP
   *
   * @param BaseModel $settings The task's settings
   * @param mixed $arg An optional second argument
   *
   * @return bool
   */
  public function copyBackup($settings, $arg = null) {
    $keepAfterCopy = ($this->_getSettings()['backup']['keepBackup'] === '1') ? true : false;

    $pathToBackup =
      craft()->path->getDbBackupPath() .
      StringHelper::toLowerCase(
        IOHelper::cleanFilename($settings->backupFileName)
      );

    $destination = $this->_getSettings()['copyBackup']['destination'];
    $destination = rtrim($destination, '/') . '/';
    $destination = $destination . basename($pathToBackup);

    $sshHostname = $this->_getSettings()['ssh']['remote']['hostname'];
    $sshUsername = $this->_getSettings()['ssh']['remote']['username'];
    $sshPassword = craft()->goLive_security->decrypt($this->_getSettings()['ssh']['remote']['password']);

    $sftp = new Net_SFTP($sshHostname);
    $sftp->login($sshUsername, $sshPassword);

    $sftpOutput = $sftp->put($destination, $pathToBackup, NET_SFTP_LOCAL_FILE);

    if(!$keepAfterCopy) {
      IOHelper::deleteFile($pathToBackup);
    }

    if($sftpOutput === false) {
      return false;
    }
    else {
      return true;
    }
  }


  /**
   * Import the database dump by running mysql from the command line (assumes Bash?)
   *
   * @param BaseModel $settings The task's settings
   * @param mixed $arg An optional second argument
   *
   * @return bool
   */
  public function importBackup($settings, $arg = null) {
    $keepAfterCopy = ($this->_getSettings()['importBackup']['keepBackup'] === '1') ? true : false;

    $pathToBackup =
      craft()->path->getDbBackupPath() .
      StringHelper::toLowerCase(
        IOHelper::cleanFilename($settings->backupFileName)
      );

    $destination = $this->_getSettings()['copyBackup']['destination'];
    $destination = rtrim($destination, '/') . '/';
    $destination = $destination . basename($pathToBackup);

    $sshHostname = $this->_getSettings()['ssh']['remote']['hostname'];
    $sshUsername = $this->_getSettings()['ssh']['remote']['username'];
    $sshPassword = craft()->goLive_security->decrypt($this->_getSettings()['ssh']['remote']['password']);

    $mysqlHostname = $this->_getSettings()['mysql']['hostname'];
    $mysqlUsername = $this->_getSettings()['mysql']['username'];
    $mysqlPassword = craft()->goLive_security->decrypt($this->_getSettings()['mysql']['password']);
    $mysqlDb = $this->_getSettings()['mysql']['dbname'];

    $ssh = new Net_SSH2($sshHostname);
    $ssh->login($sshUsername, $sshPassword);

    $commands = array(
      array(
        'command' => sprintf(
          'mysql -h %s -u %s  -p%s %s < %s',
          $mysqlHostname,
          $mysqlUsername,
          $mysqlPassword,
          $mysqlDb,
          $destination
        ),
        'output' => ''
      )
    );

    if(!$keepAfterCopy) {
      array_push($commands, array(
        'command' => sprintf(
          'rm %s',
          $destination
        ),
        'output' => ''
      ));
    }

    for ($i = 0; $i < count($commands); $i++) {
      $commands[$i]['output'] = $ssh->exec($commands[$i]['command']);
    }

    return true;
  }

  /**
   * Run a user-defined task on the command line
   *
   * @todo Combine this function and afterImportTask (which are practically identical)
   *       into a single function.
   *
   * @param BaseModel $settings The task's settings
   * @param mixed $arg The index of the command to be run
   *
   * @return bool
   */
  public function beforeBackupTask($settings, $arg = null) {
    $commands = $this->_getSettings()['beforeBackup']['commands'];
    $command = $commands[$arg]['command'];

    $sshHostname = $this->_getSettings()['ssh']['local']['hostname'];
    $sshUsername = $this->_getSettings()['ssh']['local']['username'];
    $sshPassword = craft()->goLive_security->decrypt($this->_getSettings()['ssh']['local']['password']);

    $ssh = new Net_SSH2($sshHostname);
    $ssh->login($sshUsername, $sshPassword);

    // Prepend to any command, as Net_SSH2 doesn't persist state changes
    // across multiple calls to exec()
    $cdCommand = 'cd ' . $this->_getSettings()['beforeBackup']['cwd'] .'; ';

    $ssh->exec($cdCommand . $command);

    return true;
  }


  /**
   * Run a user-defined task on the command line
   *
   * @param BaseModel $settings The task's settings
   * @param mixed $arg The index of the command to be run
   *
   * @return bool
   */
  public function afterImportTask($settings, $arg = null) {
    $commands = $this->_getSettings()['afterImport']['commands'];
    $command = $commands[$arg]['command'];

    $sshHostname = $this->_getSettings()['ssh']['remote']['hostname'];
    $sshUsername = $this->_getSettings()['ssh']['remote']['username'];
    $sshPassword = craft()->goLive_security->decrypt($this->_getSettings()['ssh']['remote']['password']);

    $ssh = new Net_SSH2($sshHostname);
    $ssh->login($sshUsername, $sshPassword);

    // Prepend to any command, as Net_SSH2 doesn't persist state changes
    // across multiple calls to exec()
    $cdCommand = 'cd ' . $this->_getSettings()['afterImport']['cwd'] .'; ';

    $ssh->exec($cdCommand . $command);

    return true;
  }

  //---
  // Private methods
  //---

  /**
   * Merges an array of before-backup tasks with the provided list of existing tasks
   *
   * @param array $taskList The existing list of tasks
   *
   * @return array
   */
  private function _addBeforeBackupTasks($taskList = array()) {
    $beforeBackupCommands =
      craft()->plugins->getPlugin('goLive')->getSettings()->beforeBackup['commands'];

    $beforeBackupTasks = array();

    foreach ($beforeBackupCommands as $key => $command) {

      $task = array(
        'function' => array(
          'beforeBackupTask',
          $key
        ),
        'message' => sprintf('Running local task <code>%s</code>...', $command['command'])
      );
      array_push($beforeBackupTasks, $task);
    }

    $taskList = array_merge($taskList, $beforeBackupTasks);

    return $taskList;
  }

  /**
   * Merges an array of backup tasks with the provided list of existing tasks
   *
   * @param array $taskList The existing list of tasks
   *
   * @return array
   */
  private function _addBackupTasks($taskList = array()) {
    $tasks = $this->_getBackupTasks();

    $taskList = array_merge($taskList, $tasks);

    return $taskList;
  }

  /**
   * Merges an array of after-import tasks with the provided list of existing tasks
   *
   * @param array $taskList The existing list of tasks
   *
   * @return array
   */
  private function _addAfterImportTasks($taskList = array()) {
    $afterImportCommands =
      craft()->plugins->getPlugin('goLive')->getSettings()->afterImport['commands'];

    $afterImportTasks = array();

    foreach ($afterImportCommands as $key => $command) {

      $task = array(
        'function' => array(
          'afterImportTask',
          $key
        ),
        'message' => sprintf('Running remote task <code>%s</code>...', $command['command'])
      );
      array_push($afterImportTasks, $task);
    }

    $taskList = array_merge($taskList, $afterImportTasks);

    return $taskList;
  }

  /**
   * Returns a list of statically defined tasks that are not user-editable
   *
   * @return array
   */
  private function _getBackupTasks() {
    $tasksPath = CRAFT_PLUGINS_PATH . 'golive/config/tasks.php';
    $tasks = require $tasksPath;

    return $tasks['backup'];
  }

  /**
   * Returns the plugin's settings.
   *
   * @return BaseModel
   */
  private function _getSettings() {
    return craft()->plugins->getPlugin('GoLive')->getSettings();
  }
}
