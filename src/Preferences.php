<?php

namespace Timurikvx\Update1c;

class Preferences
{

    private string $path;
    private string $key;

    public function __construct(string $path, string $private_key)
    {
        $this->path = $path;
        $this->key = $private_key;
    }

    public function get(string $key, $default = null): mixed
    {
        return [];
    }

    public function set(string $key, mixed $value): void
    {

    }



}