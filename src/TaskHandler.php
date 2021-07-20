<?php

namespace Stackkit\LaravelGoogleCloudScheduler;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

class TaskHandler
{
    private $command;
    private $request;
    private $openId;
    private $kernel;
    private $schedule;
    private $container;
    private $logger;

    public function __construct(
        Command $command,
        Request $request,
        OpenIdVerificator $openId,
        Kernel $kernel,
        Schedule $schedule,
        Container $container,
        Logger $logger
    ) {
        $this->command = $command;
        $this->request = $request;
        $this->openId = $openId;
        $this->kernel = $kernel;
        $this->schedule = $schedule;
        $this->container = $container;
        $this->logger = $logger;
    }

    /**
     * @throws CloudSchedulerException
     */
    public function handle()
    {
        $this->authorizeRequest();

        set_time_limit(0);

        return $this->runCommand($this->command->captureWithoutArtisan());
    }

    /**
     * @throws CloudSchedulerException
     */
    private function authorizeRequest()
    {
        if (!$this->request->hasHeader('Authorization')) {
            throw new CloudSchedulerException('Unauthorized');
        }

        $openIdToken = $this->request->bearerToken();

        $kid = $this->openId->getKidFromOpenIdToken($openIdToken);

        $decodedToken = $this->openId->decodeOpenIdToken($openIdToken, $kid);

        $this->openId->guardAgainstInvalidOpenIdToken($decodedToken);
    }

    private function runCommand($command)
    {
        $scheduledCommand = $this->isScheduledCommand($command)
            ? $this->getScheduledCommand($command)
            : new NullScheduledCommand();

        if ($scheduledCommand->withoutOverlapping && !$scheduledCommand->mutex->create($scheduledCommand)) {
            return response('', 201);
        }

        try {
            $scheduledCommand->callBeforeCallbacks($this->container);
            Artisan::call($command);
            $this->logger->log(Artisan::output());
            $scheduledCommand->callAfterCallbacks($this->container);
            return response('', 201);
        } catch (\Throwable $e) {
            if ($scheduledCommand->withoutOverlapping) {
                $scheduledCommand->mutex->forget($scheduledCommand);
            }

            $this->logger->log((string) $e, true);

            return response('', 500);
        }
    }

    private function isScheduledCommand($command)
    {
        return !is_null($this->getScheduledCommand($command));
    }

    private function getScheduledCommand($command)
    {
        $events = $this->schedule->events();

        foreach ($events as $event) {
            if (!is_string($event->command)) {
                continue;
            }

            $eventCommand = $this->commandWithoutArtisan($event->command);

            if ($command === $eventCommand) {
                return $event;
            }
        }

        return null;
    }

    private function commandWithoutArtisan($command)
    {
        $parts = explode('artisan', $command);

        return substr($parts[1], 2, strlen($parts[1]));
    }
}
