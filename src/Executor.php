<?php

namespace Timurikvx\Update1c;

use RacWorker\Entity\InfobaseEntity;
use Timurikvx\Update1c\Command\Command;
use Timurikvx\Update1c\Traits\Output;
use Timurikvx\Update1c\Traits\PlatformConnection;

class Executor
{
    use PlatformConnection, Output;

    public function __construct(string $version, bool $isLinux, bool $is32bits, InfobaseEntity $infobase)
    {
        $this->version = $version;
        $this->isLinux = $isLinux;
        $this->is32bits = $is32bits;
        $this->infobase = $infobase;
        $this->cluster = $infobase->cluster();
    }

    public function setUser($login, $password): void
    {
        $this->infobase_login = $login;
        $this->infobase_password = $password;
    }

    public function execute(string $command, mixed &$out = null): bool
    {
        $connection = $this->connection();
        $infobase = $this->infobase();
        $messages = $this->disableMessages();

        $filename = __DIR__."/output.txt";
        file_put_contents($filename, '');

        $command = $connection." ENTERPRISE ".$infobase.$this->getAccessCode()." /C ".$command." /L ru /Out \"".$filename."\"".$messages;
        $code = 0;
        Command::run($command, $code);
        //$data = $this->readArray($filename);
        $out = $this->getOutText($filename);
        return ($code === 0);
    }

}