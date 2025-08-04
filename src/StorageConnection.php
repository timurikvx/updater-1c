<?php

namespace Timurikvx\Update1c;

final class StorageConnection
{

    private string $path;

    private string $user;

    private string $password;

    private string $version = '';

    private bool $force = true;

    private string $extension = '';

    public function __construct(string $path, string $user, string $password = '')
    {
        $this->path = $path;
        $this->user = $user;
        $this->password = $password;
    }

    public function setForce(bool $value): void
    {
        $this->force = $value;
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    public function setExtension(string $extension): void
    {
        $this->extension = $extension;
    }

    public function getConnection(): string
    {
        $extension = ($this->extension != '')? " -Extension ".$this->extension : '';
        $force = $this->force? " --force": "";
        $version = ($this->version != '')? " --v ".$this->version: '';
        return $this->getAuth()." /ConfigurationRepositoryUpdateCfg".$extension.$version.$force;
    }

    public function getAuth(): string
    {
        return " /ConfigurationRepositoryF \"".$this->path."\" /ConfigurationRepositoryN \"".$this->user."\" /ConfigurationRepositoryP \"".$this->password."\"";
    }

}