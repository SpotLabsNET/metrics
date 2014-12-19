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
    $this->results = array(
      'db_prepare_count' => 0,
      'db_execute_count' => 0,
      'db_fetch_count' => 0,
      'db_fetch_all_count' => 0,

      'db_prepare_time' => 0,
      'db_execute_time' => 0,
      'db_fetch_time' => 0,
      'db_fetch_all_time' => 0,
    );
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
        Events::on('db_execute_start', array(self::$instance, 'db_execute_start'));
        Events::on('db_execute_end', array(self::$instance, 'db_execute_end'));
        Events::on('db_fetch_start', array(self::$instance, 'db_fetch_start'));
        Events::on('db_fetch_end', array(self::$instance, 'db_fetch_end'));
        Events::on('db_fetch_all_start', array(self::$instance, 'db_fetch_all_start'));
        Events::on('db_fetch_all_end', array(self::$instance, 'db_fetch_all_end'));
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
    $this->results['page_time'] = $this->state['page_end'] - $this->state['page_start'];

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
      'time_taken' => $this->results['page_time'] * 1000,
    );

    $q = $this->db->prepare($query);
    $q->execute($args);
    $id = $this->db->lastInsertId();

    // TODO database query stats

    // TODO curl queries
  }

  function db_prepare_start() {
    $this->state['db_prepare_start'] = microtime(true);
  }

  function db_prepare_end() {
    $time = microtime(true) - $this->state['db_prepare_start'];

    $this->results['db_prepare_count']++;
    $this->results['db_prepare_time'] += $time;
  }

  function db_execute_start() {
    $this->state['db_execute_start'] = microtime(true);
  }

  function db_execute_end() {
    $time = microtime(true) - $this->state['db_execute_start'];

    $this->results['db_execute_count']++;
    $this->results['db_execute_time'] += $time;
  }

  function db_fetch_start() {
    $this->state['db_fetch_start'] = microtime(true);
  }

  function db_fetch_end() {
    $time = microtime(true) - $this->state['db_fetch_start'];

    $this->results['db_fetch_count']++;
    $this->results['db_fetch_time'] += $time;
  }

  function db_fetch_all_start() {
    $this->state['db_fetch_all_start'] = microtime(true);
  }

  function db_fetch_all_end() {
    $time = microtime(true) - $this->state['db_fetch_all_start'];

    $this->results['db_fetch_all_count']++;
    $this->results['db_fetch_all_time'] += $time;
  }

  function getResults() {
    return $this->results;
  }

  function printResults() {
    $results = array();
    foreach ($this->results as $key => $value) {
      if (substr($key, -4) == "time") {
        $results[$key] = sprintf("%0.4f ms", $value);
      } else {
        $results[$key] = sprintf("%0d", $value);
      }
    }
    return $results;
  }

  static function getInstance() {
    return static::$instance;
  }

}
