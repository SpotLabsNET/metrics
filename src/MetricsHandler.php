<?php

namespace Openclerk;

use \Openclerk\Config;
use \Openclerk\Events;

/**
 * To initialise, call {@link #init()}.
 */
class MetricsHandler {

  var $db;

  private function __construct(\Db\Connection $db) {
    $this->db = $db;
  }

  static $instance = null;

  static function init(\Db\Connection $db) {
    // set up the event handlers
    /**
     * Set up metrics-capturing events.
     */
    if (Config::get('metrics_enabled', true)) {
      self::$instance = new MetricsHandler($db);

      if (Config::get('metrics_db_enabled', true)) {
        Events::on('db_prepare_start', array(self::$instance, 'db_prepare_start'));
        Events::on('db_prepare_end', array(self::$instance, 'db_prepare_end'));
      }

      if (Config::get('metrics_page_enabled', true)) {
        Events::on('page_start', array(self::$instance, 'page_start'));
        Events::on('page_end', array(self::$instance, 'page_end'));
      }

    }

  }

}
