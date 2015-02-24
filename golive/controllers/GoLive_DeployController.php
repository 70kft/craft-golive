<?php

namespace Craft;

use stdClass;

class Golive_DeployController extends BaseController {

  public function actionCreateTask() {
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


    $deployTask = craft()->tasks->createTask(
      'GoLive_Deploy',
      'GoLive_Deploy',
      array(
        'backupFileName' => uniqid('goLive_') . '.sql'
      )
    );

    craft()->tasks->closeAndRun();
  }
}
