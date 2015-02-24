<?php

namespace Craft;

class GoLivePlugin extends BasePlugin {
  public function init() {
    Craft::import('plugins.golive.tasks.GoLive_DeployTask', true);
    Craft::import('plugins.golive.etc.db.GoLive_DbBackup', true);
    Craft::import('plugins.golive.vendor.autoload', true);

    if(craft()->request->isCpRequest() && craft()->goLive_settings->isPluginEnabled()) {
      craft()->templates->includeCssResource('golive/css/golive.css');
    }
  }

  function getName() {
    return Craft::t('Go Live');
  }

  function getVersion() {
    return '1.0';
  }

  function getDeveloper() {
    return '70kft';
  }

  function getDeveloperUrl() {
    return 'http://70kft.com';
  }

  public function hasCpSection() {
    return craft()->goLive_settings->isPluginEnabled();
  }

  protected function defineSettings() {
    return craft()->goLive_settings->defineSettings();
  }

  public function prepSettings($settings) {
    $settings = craft()->goLive_settings->encryptFields($settings);
    $settings = craft()->goLive_settings->cleanTables($settings);

    return $settings;
  }

  public function getSettingsUrl() {
    return craft()->goLive_settings->getSettingsUrl();
  }

  public function onAfterInstall() {
    craft()->request->redirect(UrlHelper::getCpUrl('/golive/settings?firstrun=1'));
  }
}
