<?php

namespace Openclerk\Migrations;

class PerformanceReportsMigration extends \Db\Migration {

  /**
   * Apply only the current migration.
   * @return true on success or false on failure
   */
  function apply(\Db\Connection $db) {
    $q = $db->prepare("CREATE TABLE performance_reports (
      id int not null auto_increment primary key,
      created_at timestamp not null default current_timestamp,
      report_type varchar(32) not null,

      INDEX(report_type)
    );");
    return $q->execute();
  }

}
