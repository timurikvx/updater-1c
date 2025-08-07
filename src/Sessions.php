<?php

namespace Timurikvx\Update1c;

use RacWorker\Entity\ClusterEntity;
use RacWorker\Entity\InfobaseEntity;
use RacWorker\RacWorker;
use Timurikvx\Update1c\Traits\ErrorHandler;

class Sessions
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
        return $this->changeSessions(false);
    }

    /**
     * @throws \Exception
     */
    public function off(): bool
    {
        return $this->changeSessions(true);
    }

    /**
     * @throws \Exception
     */
    private function changeSessions(bool $value): bool
    {
        $error = '';
        $this->infobase->setSessionsDeny($value);
        $result = $this->worker->infobase->update($this->cluster, $this->infobase, $error);
        $this->handleError($error, 145);
        return $result;
    }

}