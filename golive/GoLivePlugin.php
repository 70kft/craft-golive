<?php

namespace Craft;

class GoLivePlugin extends BasePlugin {
  public function init() {
    Craft::import('plugins.golive.tasks.GoLive_DeployTask', true);
    Craft::import('plugins.golive.etc.db.GoLive_DbBackup', true);
    Craft::import('plugins.golive.vendor.autoload', true);

    if(craft()->request->isCpRequest()) {
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
    return $this->isGoLiveEnabled();
  }

  protected function defineSettings() {
    return array(
      'beforeBackup' => array(AttributeType::Mixed, 'default' => array(
        'commands' => array()
      )),
      'backup' => array(AttributeType::Mixed, 'default' => array(
        'keepBackup' => '',
        'excludeTables' => array(
          'table' => ''
        )
      )),
      'copyBackup' => array(AttributeType::Mixed, 'default' => array(
        'destination' => ''
      )),
      'importBackup' => array(AttributeType::Mixed, 'default' => array(
        'keepBackup' => ''
      )),
      'afterImport' => array(AttributeType::Mixed, 'default' => array(
        'commands' => array()
      )),
      'ssh' => array(AttributeType::Mixed, 'default' => array()),
      'mysql' => array(AttributeType::Mixed, 'default' => array())
    );
  }

  public function prepSettings($settings) {
    $settings['ssh']['password'] = craft()->goLive_security->encrypt($settings['ssh']['password']);
    $settings['mysql']['password'] = craft()->goLive_security->encrypt($settings['mysql']['password']);

    return $settings;
  }

  public function getSettingsUrl() {
    if ($this->isGoLiveEnabled()) {
      return UrlHelper::getUrl('/golive/settings');
    }
    else {
      return false;
    }
  }

  public function onAfterInstall() {
    craft()->request->redirect(UrlHelper::getCpUrl('/golive/settings?firstrun=1'));
  }

  public function isGoLiveEnabled() {
    $environmentVars = craft()->config->get('environmentVariables');

    if(! array_key_exists('goLive_enabled', $environmentVars)) {
      return true;
    }

    if ($environmentVars['goLive_enabled'] === false) {
      return false;
    }
    else {
      return true;
    }
  }
}
