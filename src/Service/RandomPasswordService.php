<?php

namespace GS\Service\Service;

class RandomPasswordService
{
    public function __construct()
    {
    }

    //###> API ###

    /*
        Gets random password
    */
    public static function get(
        int $len = 10,
        string $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890',
    ): string {
        $pass = [];
        $alphaLength = \strlen($alphabet) - 1;
        for ($i = 0; $i < $len; $i++) {
            $n = \rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return \implode($pass);
    }

    //###< API ###
}
