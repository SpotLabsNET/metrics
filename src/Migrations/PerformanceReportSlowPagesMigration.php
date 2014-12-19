<?php

namespace Openclerk\Migrations;

class PerformanceReportSlowPagesMigration extends \Db\Migration {

  /**
   * Apply only the current migration.
   * @return true on success or false on failure
   */
  function apply(\Db\Connection $db) {
    $q = $db->prepare("CREATE TABLE performance_report_slow_pages (
      id int not null auto_increment primary key,
      report_id int not null,
      created_at timestamp not null default current_timestamp,

      script_name varchar(255) null,      -- might be null if running from CLI; probably not though

      page_count int not null,
      page_time int not null,
      page_database int null,

      INDEX(report_id)
    );");
    return $q->execute();
  }

}
