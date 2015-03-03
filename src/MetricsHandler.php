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
      'db_prepare_master_count' => 0,
      'db_prepare_slave_count' => 0,
      'db_execute_count' => 0,
      'db_fetch_count' => 0,
      'db_fetch_all_count' => 0,

      'db_prepare_time' => 0,
      'db_execute_time' => 0,
      'db_fetch_time' => 0,
      'db_fetch_all_time' => 0,

      'page_template_count' => 0,
      'page_template_time' => 0,

      'curl_count' => 0,
      'curl_time' => 0,
      'curl_urls' => array(),
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
        Events::on('db_prepare_master', array(self::$instance, 'db_prepare_master'));
        Events::on('db_prepare_slave', array(self::$instance, 'db_prepare_slave'));
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

      if (Config::get('metrics_templates_enabled', true)) {
        Events::on('pages_template_start', array(self::$instance, 'template_start'));
        Events::on('pages_template_end', array(self::$instance, 'template_end'));
      }

      if (Config::get('metrics_curl_enabled', true)) {
        Events::on('curl_start', array(self::$instance, 'curl_start'));
        Events::on('curl_end', array(self::$instance, 'curl_end'));
      }

    }

  }

  function page_start() {
    $this->state['page_start'] = microtime(true);
  }

  function page_end() {
    if (!isset($this->state['page_start'])) {
      throw new MetricsException("page_end event occured without a page_start event");
    }
    if (isset($this->state['page_end'])) {
      // do not log twice
      throw new MetricsException("Unexpectedly measured page_end twice");
    }

    $this->state['page_end'] = microtime(true);
    $this->results['page_time'] = $this->state['page_end'] - $this->state['page_start'];

    if (Config::get('metrics_store', false)) {
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

      // database query stats
      $add = $this->addDatabaseStats();
      $query .= $add['query'];
      $args += $add['args'];

      $q = $this->db->prepare($query);
      $q->execute($args);
      $page_id = $this->db->lastInsertId();

      // curl metrics
      if (Config::get('metrics_curl_enabled', true)) {
        foreach ($this->results['curl_urls'] as $url => $data) {

          // only if it's over a specified limit, so we don't spam the database with super fast URLs
          $slow_url = ($data['time'] / $data['count']) > (Config::get('metrics_curl_slow', 1000) / 1000);
          $repeated_url = $data['count'] > Config::get('metrics_curl_repeated', 2);

          if ($slow_url || $repeated_url) {

            // find the URL reference
            $url_substr = substr($url, 0, 255);
            $q = $this->db->prepare("SELECT id FROM performance_metrics_urls WHERE url=? LIMIT 1");
            $q->execute(array($url_substr));
            $pq = $q->fetch();
            if (!$pq) {
              $q = $this->db->prepare("INSERT INTO performance_metrics_urls SET url=?");
              $q->execute(array($url_substr));
              $pq = array('id' => db()->lastInsertId());
            }

            if ($slow_url) {
              $q = $this->db->prepare("INSERT INTO performance_metrics_slow_urls SET url_id=?, url_count=?, url_time=?, page_id=?");
              $q->execute(array($pq['id'], $data['count'], $data['time'] * 1000, $page_id));
            }
            if ($repeated_url) {
              $q = $this->db->prepare("INSERT INTO performance_metrics_repeated_urls SET url_id=?, url_count=?, url_time=?, page_id=?");
              $q->execute(array($pq['id'], $data['count'], $data['time'] * 1000, $page_id));
            }

          }

        }

      }
    }

  }

  function addDatabaseStats() {
    if (Config::get('metrics_db_enabled', true)) {
      return array(
        'query' => ", db_prepares=:db_prepares, db_executes=:db_executes, db_fetches=:db_fetches, db_fetch_alls=:db_fetch_alls,
          db_prepare_time=:db_prepare_time, db_execute_time=:db_execute_time, db_fetch_time=:db_fetch_time, db_fetch_all_time=:db_fetch_all_time",
        'args' => array(
          'db_prepares' => $this->results['db_prepare_count'] * 1000,
          'db_executes' => $this->results['db_execute_count'] * 1000,
          'db_fetches' => $this->results['db_fetch_count'] * 1000,
          'db_fetch_alls' => $this->results['db_fetch_all_count'] * 1000,
          'db_prepare_time' => $this->results['db_prepare_time'] * 1000,
          'db_execute_time' => $this->results['db_execute_time'] * 1000,
          'db_fetch_time' => $this->results['db_fetch_time'] * 1000,
          'db_fetch_all_time' => $this->results['db_fetch_all_time'] * 1000,
        ),
      );
    } else {
      return array('query' => '', 'args' => array());
    }
  }

  function db_prepare_start() {
    $this->state['db_prepare_start'] = microtime(true);
  }

  function db_prepare_end() {
    $time = microtime(true) - $this->state['db_prepare_start'];

    $this->results['db_prepare_count']++;
    $this->results['db_prepare_time'] += $time;
  }

  function db_prepare_master() {
    $this->results['db_prepare_master_count']++;
  }

  function db_prepare_slave() {
    $this->results['db_prepare_slave_count']++;
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

  function template_start($arguments) {
    if (!isset($this->state['template_start'])) {
      $this->state['template_start'] = array();
    }
    $this->state['template_start'][$arguments['template']] = microtime(true);
  }

  function template_end($arguments) {
    $time = microtime(true) - $this->state['template_start'][$arguments['template']];

    $this->results['page_template_count']++;
    $this->results['page_template_time'] += $time;
  }

  function curl_start($url) {
    $this->state['curl_start'] = microtime(true);
  }

  function curl_end($url) {
    $time = microtime(true) - $this->state['curl_start'];

    $this->results['curl_count']++;
    $this->results['curl_time'] += $time;

    if (!isset($this->results['curl_urls'][$url])) {
      $this->results['curl_urls'][$url] = array('count' => 0, 'time' => 0);
    }
    $this->results['curl_urls'][$url]['count']++;
    $this->results['curl_urls'][$url]['time'] += $time;
  }

  function getResults() {
    return $this->results;
  }

  function printResults() {
    $results = array();
    foreach ($this->results as $key => $value) {
      if (is_array($value)) {
        $results[$key] = sprintf("%0d elements", count($value));
      } else if (substr($key, -4) == "time") {
        $results[$key] = sprintf("%0.2f ms", $value * 1000);
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
