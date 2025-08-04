<?php

namespace Timurikvx\Update1c\Command;

class Command
{
    public static function run($command, &$code = -1): array
    {
        $output = [];
        exec($command, $output, $code);
        return $output;
    }
}