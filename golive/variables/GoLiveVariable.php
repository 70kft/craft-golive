<?php

namespace Craft;

class GoLiveVariable {

  /**
   * @var bool
   */
  public $enabled;


  /**
   * @var array
   */
  public $settings;


  /**
   * Assigns some plugin properties to properties.
   */
  public function __construct() {
    $this->settings = craft()->plugins->getPlugin('GoLive')->getSettings();
    $this->enabled = craft()->plugins->getPlugin('GoLive')->isGoLiveEnabled();
  }

  /**
   * Returns an instance of the GoLive_SecurityService class.
   *
   * @return GoLive_SecurityService
   */
  public function security() {
    return craft()->goLive_security;
  }

  public function getSettings() {
    return $this->settings->attributes;
  }

  public function getTablePrefix() {
    return craft()->db->tablePrefix;
  }
}
