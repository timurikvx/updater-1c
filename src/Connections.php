<?php

namespace Timurikvx\Update1c;

use RacWorker\Entity\ClusterEntity;
use RacWorker\Entity\InfobaseEntity;
use RacWorker\RacWorker;
use Timurikvx\Update1c\Traits\ErrorHandler;

class Connections
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
    public function clearAll(): void
    {
        $this->clearConnections();
        $this->clearSessions();
    }

    /**
     * @throws \Exception
     */
    public function clearConnections(): void
    {
        $error = '';
        $servers = $this->worker->server->list($this->cluster, $error);
        $this->handleError($error, 116);
        foreach($servers as $server){
            $processes = $this->worker->process->list($this->cluster, $server, $error);
            $this->handleError($error, 117);
            foreach($processes as $process){
                $connections = $this->worker->connection->list($this->cluster, $process, $this->infobase, $error);
                $this->handleError($error, 118);
                foreach($connections as $connection){
                    $this->worker->connection->remove($this->cluster, $process, $connection, $this->infobase->getInfobaseUser());
                }
            }
        }
    }

    public function clearSessions(): void
    {
        $error = '';
        $sessions = $this->worker->session->list($this->cluster, $this->infobase, $error);
        foreach($sessions as $session){
            $this->worker->session->remove($this->cluster, $session);
        }
    }
}