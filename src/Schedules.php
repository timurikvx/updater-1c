<?php

namespace Timurikvx\Update1c;

use RacWorker\Entity\ClusterEntity;
use RacWorker\Entity\InfobaseEntity;
use RacWorker\RacWorker;
use Timurikvx\Update1c\Traits\ErrorHandler;


class Schedules
{

    use ErrorHandler;

    private InfobaseEntity $infobase;

    private ClusterEntity $cluster;

    private RacWorker $worker;

    public function __construct(RacWorker $worker, ClusterEntity $cluster, InfobaseEntity $infobase)
    {
        $this->cluster = $cluster;
        $this->infobase = $infobase;
        $this->worker = $worker;
    }

    /**
     * @throws \Exception
     */
    public function on(): bool
    {
        return $this->changeScheduledJobs(false);
    }

    /**
     * @throws \Exception
     */
    public function off(): bool
    {
        return $this->changeScheduledJobs(true);
    }

    /**
     * @throws \Exception
     */
    private function changeScheduledJobs(bool $value): bool
    {
        $error = '';
        $this->infobase->setScheduledJobsDeny($value);
        $result = $this->worker->infobase->update($this->cluster, $this->infobase, $error);
        $this->handleError($error, 110);
        return $result;
    }
}