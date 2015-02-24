<?php

namespace Craft;

class GoLive_DeployTask extends BaseTask {

  /**
   * Returns a description of what's happening while this task runs
   * @return string
   */
  public function getDescription() {
    return Craft::t('Running Go Live');
  }

  /**
   * Gets the total number of steps in the task, including all user-defined commands
   *
   * @return int
   */
  public function getTotalSteps() {
    return count(
      craft()->goLive_task->enumerateTasks()
    );
  }

  /**
   * Called by the Craft task runner as many times as there are steps.
   *
   * @param int $step
   *
   * @return bool
   */
  public function runStep($step) {
    $tasks = craft()->goLive_task->enumerateTasks();
    $settings = $this->getSettings();
    $taskKey = $step;

    if(!array_key_exists($taskKey, $tasks)) {
      throw new Exception('Trying to run actionDoTask with a nonexistent index `' . $taskKey . '`.', 1);
    }

    $taskFunction = $tasks[$taskKey]['function'];
    $taskArgument = ( array_key_exists(1, $taskFunction) ) ? $taskFunction[1] : null;

    return craft()->goLive_task->$taskFunction[0]($settings, $taskArgument);
  }

  /**
   * Sets basic types for settings
   *
   * @return array
   */
  protected function defineSettings()
  {
    return array(
      'backupFileName' => AttributeType::String
    );
  }
}
