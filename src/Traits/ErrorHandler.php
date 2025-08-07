<?php

namespace Timurikvx\Update1c\Traits;

trait ErrorHandler
{
    protected function handleError(string $error, int $code = 100): void
    {
        if(!empty($error)){
            throw new \Exception($error, $code);
        }
    }

}