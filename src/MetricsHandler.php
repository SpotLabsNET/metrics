<?php

namespace Openclerk;

use \Openclerk\Config;
use \Openclerk\Events;

/**
 * To initialise, call {@link #init()}.
 */
class MetricsHandler {

  var $db;
  var $state = array();
  var $results = array();

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

  function page_start() {
    $this->state['page_start'] = microtime(true);
  }

  function page_end() {
    if (isset($this->state['page_end'])) {
      // do not log twice
      throw new MetricsException("Unexpectedly measured page_end twice");
    }

    $this->state['page_end'] = microtime(true);
    $this->results['page'] = $this->state['page_end'] - $this->state['page_start'];

    // do inserts
    // "What database queries take the longest?"
    // "What tables take the longest to query?"
    // "What URLs take the longest to request?"
    // "How long does it take for a page to be generated?"
    // "What pages are taking the longest to load?"
    // "What pages have the most database queries?"
    // "What pages spend the most time in PHP as opposed to the database?"
    $query = "INSERT INTO performance_metrics_pages SET script_name=:script_name, time_taken=:time_taken";
    $args = array(
      'script_name' => isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : null,
      'time_taken' => $this->results['page'] * 1000,
    );

    $q = $this->db->prepare($query);
    $q->execute($args);
    $id = $this->db->lastInsertId();

    // TODO database queries
    // TODO curl queries
  }

  function getResults() {
    return $this->results;
  }

  function printResults() {
    $results = array();
    foreach ($this->results as $key => $value) {
      $results[$key] = sprintf("%0.4f ms", $value);
    }
    return $results;
  }

  static function getInstance() {
    return static::$instance;
  }

}
