<?php

namespace Craft;

use stdClass;

class Golive_DeployController extends BaseController {

  public function actionCreateTask() {
    $deployTask = craft()->tasks->createTask(
      'GoLive_Deploy',
      'GoLive_Deploy',
      array(
        'backupFileName' => uniqid('goLive_') . '.sql'
      )
    );
    $output = craft()->tasks->runPendingTasks();
    craft()->end();
  }
}
