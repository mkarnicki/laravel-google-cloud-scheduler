<?php

namespace Stackkit\LaravelGoogleCloudScheduler;

use Google\Cloud\Logging\Logger as CloudLogger;
use Google\Cloud\Logging\LoggingClient;

class Logger
{
    /**
     * @var LoggingClient
     */
    private $logger;

    public function __construct()
    {
        $this->logger = new LoggingClient([
            'projectId' => config('laravel-google-cloud-scheduler.project'),
        ]);
    }

    public function log($output, $isError = false)
    {
        $logger = $this->logger->logger('my-log', [
            'resource' => [
                'type' => 'cloud_scheduler_job',
                'labels' => [
                    'job_id' => request()->header('X-Cloudscheduler-Jobname'),
                    'location' => config('laravel-google-cloud-scheduler.region'),
                ],
            ],
        ]);

        $entry = $logger->entry($output, [
            'severity' => $isError ? CloudLogger::ERROR : CloudLogger::INFO,
        ]);

        $logger->write($entry);
    }
}