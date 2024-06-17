<?php

namespace GS\Service\IsoFormat;

use GS\Service\Contracts\GSIsoFormat;

class GSLLLIsoFormat implements GSIsoFormat
{
    public static function get(): string
    {
        return 'dddd, MMMM D, YYYY h:mm:ss A';
    }
}
