<?php

namespace Craft;

use stdClass;

class Golive_DeployController extends BaseController {

  /**
   * Verifies task settings and creates/runs a task if everything is OK
   *
   * @return null
   */
  public function actionCreateTask() {
    // Check settings, show errors if there are any
    try {
      craft()->goLive_settings->verifySettings();
    }
    catch (Exception $e) {
      header('HTTP/1.1 ' . 500);

      $errorMessage = $e->getMessage();
      $this->returnErrorJson($errorMessage);
    }
    catch (HttpException $e) {
      header('HTTP/1.1 ' . $e->statusCode);

      $errorMessage = $e->getMessage();
      $this->returnErrorJson($errorMessage);
    }

    // Settings look good, create and run the task.
    // Also, set a random-ish backup file name to be referenced within the task steps
    $deployTask = craft()->tasks->createTask(
      'GoLive_Deploy',
      'GoLive_Deploy',
      array(
        'backupFileName' => uniqid('goLive_') . '.sql'
      )
    );

    // Tries to end the HTTP session, and definitely starts running the pending tasks.
    // Our task may or may not be first in that queue.
    craft()->tasks->closeAndRun();
  }
}
