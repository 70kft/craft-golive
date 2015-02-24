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
    // If tasks get copied, then GoLive will try to deploy again!
    $exclude = array('tasks');

    // If the user wants to exclude some tables, add them to the list
    if (
      isset($this->getSettings()['backup']['excludeTables']) &&
      count($this->getSettings()['backup']['excludeTables']) > 0
    ) {
      $excludeCollection = $this->getSettings()['backup']['excludeTables'];
      // Map to a simple array of table names
      foreach ($excludeCollection as $item) {
        if($item['table'] !== '') {
          array_push($exclude, $item['table']);
        }
      }
    }

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
    $keepAfterCopy = ($this->getSettings()['backup']['keepBackup'] === '1') ? true : false;

    $pathToBackup =
      craft()->path->getDbBackupPath() .
      StringHelper::toLowerCase(
        IOHelper::cleanFilename($settings->backupFileName)
      );

    $destination = $this->getSettings()['copyBackup']['destination'];
    $destination = rtrim($destination, '/') . '/';
    $destination = $destination . basename($pathToBackup);

    $sshHostname = $this->getSettings()['ssh']['remote']['hostname'];
    $sshUsername = $this->getSettings()['ssh']['remote']['username'];
    $sshPassword = craft()->goLive_security->decrypt($this->getSettings()['ssh']['remote']['password']);

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
    $keepAfterCopy = ($this->getSettings()['importBackup']['keepBackup'] === '1') ? true : false;

    $pathToBackup =
      craft()->path->getDbBackupPath() .
      StringHelper::toLowerCase(
        IOHelper::cleanFilename($settings->backupFileName)
      );

    $destination = $this->getSettings()['copyBackup']['destination'];
    $destination = rtrim($destination, '/') . '/';
    $destination = $destination . basename($pathToBackup);

    $sshHostname = $this->getSettings()['ssh']['remote']['hostname'];
    $sshUsername = $this->getSettings()['ssh']['remote']['username'];
    $sshPassword = craft()->goLive_security->decrypt($this->getSettings()['ssh']['remote']['password']);

    $mysqlHostname = $this->getSettings()['mysql']['hostname'];
    $mysqlUsername = $this->getSettings()['mysql']['username'];
    $mysqlPassword = craft()->goLive_security->decrypt($this->getSettings()['mysql']['password']);
    $mysqlDb = $this->getSettings()['mysql']['dbname'];

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
   * @param BaseModel $settings The task's settings
   * @param mixed $arg The index of the command to be run
   *
   * @return bool
   */
  public function doSSHTask($settings, $args = null)
  {
    $commandSet = $args['commandSet'];
    $commandEnv = $args['commandEnvironment'];
    $cwd = $this->getSettings()[$commandSet]['cwd'];
    $commands = $this->getSettings()[$commandSet]['commands'];
    $command = $commands[$args['commandIndex']]['command'];

    $sshHostname = $this->getSettings()['ssh'][$commandEnv]['hostname'];
    $sshUsername = $this->getSettings()['ssh'][$commandEnv]['username'];
    $sshPassword = craft()->goLive_security->decrypt($this->getSettings()['ssh'][$commandEnv]['password']);

    $ssh = new Net_SSH2($sshHostname);
    $ssh->login($sshUsername, $sshPassword);

    // Prepend to any command, as Net_SSH2 doesn't persist state changes
    // across multiple calls to exec()
    $ssh->exec(sprintf('cd %s; %s', $cwd, $command));

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
    // return w/o changes if there are no tasks to add
    if(
      !isset($this->getSettings()->beforeBackup['commands']) ||
      count($this->getSettings()->beforeBackup['commands']) === 0
    ) {
      return $taskList;
    }

    $beforeBackupCommands = $this->getSettings()->beforeBackup['commands'];
    $beforeBackupTasks = array();

    foreach ($beforeBackupCommands as $key => $command) {

      $task = array(
        'function' => array(
          'doSSHTask',
          array(
            'commandSet' => 'beforeBackup',
            'commandEnvironment' => 'local',
            'commandIndex' => $key
          )
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
    // return w/o changes if there are no tasks to add
    if(
      !isset($this->getSettings()->afterImport['commands']) ||
      count($this->getSettings()->afterImport['commands']) === 0
    ) {
      return $taskList;
    }

    $afterImportCommands =
      craft()->plugins->getPlugin('goLive')->getSettings()->afterImport['commands'];

    $afterImportTasks = array();

    foreach ($afterImportCommands as $key => $command) {

      $task = array(
        'function' => array(
          'doSSHTask',
          array(
            'commandSet' => 'afterImport',
            'commandEnvironment' => 'remote',
            'commandIndex' => $key
          )
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
  private function getSettings() {
    return craft()->plugins->getPlugin('GoLive')->getSettings();
  }
}
