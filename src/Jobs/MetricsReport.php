<?php

namespace Openclerk\Jobs;

class MetricsReport extends \Jobs\JobType {

  /**
   * Get a list of all job instances that should be run soon.
   * @return a list of job parameters
   */
  function getPending(\Db\Connection $db) {
    $q = $db->prepare("SELECT * FROM performance_reports WHERE created_at < DATE_SUB(NOW(), INTERVAL " . \Openclerk\Config::get("job_metrics_report_interval", 60 * 12) . " MINUTE) LIMIT 1");
    $q->execute();

    if (!$q->fetch()) {
      // there's no recent reports; make another one
      return array(
        array(
          "job_type" => $this->getName(),
          "arg" => null,
        ),
      );
    }

    return array();
  }

  /**
   * Prepare a {@link JobInstance} that can be executed from
   * the given parameters.
   */
  function createInstance($params) {
    return new MetricsReportJob($params);
  }

  /**
   * Do any post-job-queue behaviour e.g. marking the job queue
   * as checked.
   */
  function finishedQueue(\Db\Connection $db, $jobs) {
    // empty
  }

  function getName() {
    return "metrics_report";
  }

}
