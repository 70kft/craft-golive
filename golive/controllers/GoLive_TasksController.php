<?php

namespace Craft;

class Golive_TasksController extends BaseController {

  /**
   * Displays details and descriptions for each step in the task
   *
   * @return null
   */
  public function actionGetTasks() {
    $tasks = craft()->goLive_task->enumerateTasks();
    $this->returnJson($tasks);
  }
}
