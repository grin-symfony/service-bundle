<?php

namespace GS\Service\Service;

class BufferService
{
    public function __construct()
    {
    }

    //###> API ###

    /**
        Clears all the levels of the output OS buffer
        Works with php output buffer
    */
    public static function clear(): void
    {
        while (\ob_get_level()) {
            \ob_end_clean();
        }
    }

    //###< API ###
}
