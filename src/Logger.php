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

    private const CLOUD_LOGGING_PAYLOAD_MAX_BYTES = 256000;

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

        $this->chunk($output, function ($chunk) use ($logger, $isError) {
            $entry = $logger->entry($chunk, [
                'severity' => $isError ? CloudLogger::ERROR : CloudLogger::INFO,
            ]);

            $logger->write($entry);
        });
    }

    private function chunk($string, $closure)
    {
        foreach (str_split($string, self::CLOUD_LOGGING_PAYLOAD_MAX_BYTES) as $chunk) {
            $closure($chunk);
        }
    }
}