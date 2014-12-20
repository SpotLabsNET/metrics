<?php

namespace Openclerk\Migrations;

class PerformanceMetricsSlowUrls extends \Db\Migration {

  /**
   * Apply only the current migration.
   * @return true on success or false on failure
   */
  function apply(\Db\Connection $db) {
    $q = $db->prepare("CREATE TABLE performance_metrics_slow_urls (
      id int not null auto_increment primary key,
      url_id int not null,
      url_count int not null,
      url_time int not null,
      page_id int not null,

      INDEX(url_id),
      INDEX(page_id)
    );");
    return $q->execute();
  }

}
