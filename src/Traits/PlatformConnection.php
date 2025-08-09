<?php

namespace Timurikvx\Update1c\Traits;

use RacWorker\Entity\ClusterEntity;
use RacWorker\Entity\InfobaseEntity;

trait PlatformConnection
{

    protected bool $isLinux;

    protected ClusterEntity|null $cluster = null;

    protected InfobaseEntity|null $infobase = null;

    protected string $infobase_login;

    protected string $infobase_password;

    protected bool $is32bits;

    protected string $version;

    /////////////////////////////////////////////////////

    protected function getAccessCode(): string
    {
        if(empty($this->code)){
            return '';
        }
        return ' /UC '.$this->code;
    }

    protected function connection(): string
    {
        $filler = $this->isLinux? '': '"';
        $display = $this->isLinux? 'xvfb-run ': '';
        $path = $this->getPath();
        return $display.$filler.$path.'/1cv8'.$filler;
    }

    protected function infobase(): string
    {
        $server = $this->cluster->getHost();
        $base = $this->infobase->getName();
        $user = $this->infobase_login;
        $pass = $this->infobase_password;
        return "/S \"{$server}\\{$base}\" /N \"{$user}\" /P \"{$pass}\"";
    }

    protected function disableMessages(): string
    {
        return " /DisableStartupMessages /DisableSplash /DisableStartupDialogs /DisableUnrecoverableErrorMessage";
    }

    protected function getPath(): string
    {
        if($this->isLinux){
            $start = '/opt/1cv8/';
            $bits = ($this->is32bits)? 'x86_64': 'x64';
            $path = $start.$bits.'/'.$this->version;
        }else{
            $start = 'C:\\';
            $bits = ($this->is32bits)? 'Program Files (x86)': 'Program Files';
            $path = $start.$bits.'\\1cv8\\'.$this->version.'\\bin';
        }
        return $path;
    }

    public function getInfobase(): InfobaseEntity|null
    {
        return $this->infobase;
    }

}