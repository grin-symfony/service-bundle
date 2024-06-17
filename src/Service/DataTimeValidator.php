<?php

namespace GS\Service\Service;

class DataTimeValidator
{
    //###> API ###

    /**
        default php name of timezone
    */
    public static function isTimezone(
        $tz,
    ): bool {
        return
            \in_array(
                \strtolower($tz),
                \array_map(
                    static fn($v) => \strtolower($v),
                    \DateTimeZone::listIdentifiers(\DateTimeZone::ALL)
                )
            )
        ;
    }

    /**
        default php name of timezone
        or
        pattern is available for Carbon\Carbon

        +12:00
        +1230
        0100
    */
    public static function isCarbonTimezone(
        $tz,
    ): bool {
        return
            self::isTimezone($tz)
            || \preg_match('~^(?![^+\-0-9])[+\-][0-2][0-4][:]?[0-5][0-9]\b~', $tz)
        ;
    }

    //###< API ###
}
