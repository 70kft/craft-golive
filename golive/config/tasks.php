<?php

return array(
  'backup' => array(
    array(
      'function' => array('backup'),
      'message' => 'Dumping this database...',
    ),
    array(
      'function' => array('copyBackup'),
      'message' => 'Copying database dump...',
    ),
    array(
      'function' => array('importBackup'),
      'message' => 'Importing database dump...',
    )
  )
);
