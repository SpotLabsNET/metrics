<?php

namespace Openclerk\Migrations;

class PerformanceMetricsPagesMigration extends \Db\Migration {

  /**
   * Apply only the current migration.
   * @return true on success or false on failure
   */
  function apply(\Db\Connection $db) {
    $q = $db->prepare("CREATE TABLE performance_metrics_pages (
      id int not null auto_increment primary key,
      time_taken int not null,

      script_name varchar(255) null,      -- might be null if running from CLI; probably not though
      is_logged_in tinyint not null,

      -- timed_sql
      db_prepares int null,
      db_executes int null,
      db_fetches int null,
      db_fetch_alls int null,
      db_prepare_time int null,
      db_execute_time int null,
      db_fetch_time int null,
      db_fetch_all_time int null,

      -- timed_curl
      curl_requests int null,
      curl_request_time int null,

      INDEX(script_name)
    );");
    return $q->execute();
  }

}
