<?php

namespace Craft;

class GoLive_DeployTask extends BaseTask {

  public function getDescription() {
    return Craft::t('Running Go Live');
  }

  public function getTotalSteps() {
    return count(
      craft()->goLive_task->enumerateTasks()
    );
  }

  public function runStep($step) {
    craft()->goLive_settings->verifySettings();

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

  protected function defineSettings()
  {
    return array(
      'backupFileName' => AttributeType::String,
      'elementId' => AttributeType::Mixed,
    );
  }
}
