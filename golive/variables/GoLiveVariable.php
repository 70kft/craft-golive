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
    $this->enabled = craft()->goLive_settings->isPluginEnabled();
  }

  /**
   * Returns an instance of the GoLive_SecurityService class.
   *
   * @return GoLive_SecurityService
   */
  public function security() {
    return craft()->goLive_security;
  }

  /**
   * Returns the attributes of the plugin's settings model
   *
   * @return array
   */
  public function getSettings() {
    return $this->settings->attributes;
  }

  /**
   * Returns the prefix Craft uses for table names
   *
   * @return string
   */
  public function getTablePrefix() {
    return craft()->db->tablePrefix;
  }
}
