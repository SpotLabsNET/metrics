<?php

namespace Openclerk\Jobs;

class MetricsReportJob extends \Jobs\JobInstance {

  function run(\Db\Connection $db, \Db\Logger $logger) {
    // "What pages are taking the longest to load?"
    // "What pages spend the most time in PHP as opposed to the database?"

    $report_type = "pages_slow";

    // select the worst URLs
    $q = $db->prepare("SELECT script_name, SUM(time_taken) AS time_taken, COUNT(id) AS page_count,
        SUM(db_prepare_time) + SUM(db_execute_time) + SUM(db_fetch_time) + SUM(db_fetch_all_time) AS database_time FROM performance_metrics_pages
        GROUP BY script_name ORDER BY SUM(time_taken) / COUNT(id) LIMIT " . \Openclerk\Config::get('metrics_report_count', 20));
    $q->execute();
    $data = $q->fetchAll();

    $q = $db->prepare("INSERT INTO performance_reports SET report_type=?");
    $q->execute(array($report_type));
    $report_id = $db->lastInsertId();

    foreach ($data as $row) {
      $q = $db->prepare("INSERT INTO performance_report_slow_pages SET report_id=?, script_name=?, page_time=?, page_count=?, page_database=?");
      $q->execute(array($report_id, $row['script_name'], $row['time_taken'], $row['page_count'], $row['database_time']));
    }

    $logger->log("Created report '$report_type'");

    // we've processed all the data we want; delete old metrics data
    $q = $db->prepare("DELETE FROM performance_metrics_pages");
    $q->execute();

    $logger->log("Deleted old metric data");
  }

}
