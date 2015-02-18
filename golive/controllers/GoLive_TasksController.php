<?php

namespace Craft;

class Golive_TasksController extends BaseController {

  public function actionGetTasks() {
    $tasks = craft()->goLive_task->enumerateTasks();
    $this->returnJson($tasks);
  }

  public function actionDoTask() {
    $tasks = craft()->goLive_task->enumerateTasks();
    $taskKey = (int) craft()->request->getSegment(
      count(craft()->request->getSegments())
    ) - 1;

    if(!array_key_exists($taskKey, $tasks)) {
      throw new Exception('Trying to run actionDoTask with a nonexistent index `' . $taskKey . '`.', 1);
    }

    $taskFunction = $tasks[$taskKey]['function'];
    $taskArgument = ( array_key_exists(1, $taskFunction) ) ? $taskFunction[1] : null;

    $this->returnJson(craft()->goLive_task->$taskFunction[0]($taskArgument));
  }
}
