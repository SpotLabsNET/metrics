<?php

namespace Openclerk\Migrations;

class PerformanceMetricsUrls extends \Db\Migration {

  /**
   * Apply only the current migration.
   * @return true on success or false on failure
   */
  function apply(\Db\Connection $db) {
    $q = $db->prepare("CREATE TABLE performance_metrics_urls (
      id int not null auto_increment primary key,
      url varchar(255) not null,
      created_at timestamp not null default current_timestamp,

      INDEX(url)
    );");
    return $q->execute();
  }

}
