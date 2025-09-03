<?php

namespace Timurikvx\Update1c;

use RacWorker\Entity\ClusterEntity;
use RacWorker\Entity\ConnectionEntity;
use RacWorker\Entity\InfobaseEntity;
use RacWorker\Entity\ProcessEntity;
use RacWorker\RacConnectionProvider;

class Connection
{

    private ConnectionEntity $connection;

    private RacConnectionProvider $provider;

    private ClusterEntity $cluster;

    private InfobaseEntity $infobase;

    private ProcessEntity $process;

    public function __construct(RacConnectionProvider $provider, ClusterEntity $cluster, ProcessEntity $process, InfobaseEntity $infobase, ConnectionEntity $connection)
    {
        $this->connection = $connection;
        $this->provider = $provider;
        $this->cluster = $cluster;
        $this->infobase = $infobase;
        $this->process = $process;
    }

    public function remove(): void
    {
        $this->provider->remove($this->cluster, $this->process, $this->connection, $this->infobase->getInfobaseUser());
    }

    public function getAppID(): string
    {
        return $this->connection->getAppId();
    }

    public function getID(): string
    {
        return $this->connection->getID();
    }

    public function getHost(): string
    {
        return $this->connection->getHost();
    }

    public function getUser(): string
    {
        return $this->connection->getUser();
    }

}