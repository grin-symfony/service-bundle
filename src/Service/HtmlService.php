<?php

namespace GS\Service\Service;

use function Symfony\Component\String\u;

use Symfony\Component\HttpFoundation\Response;

class HtmlService
{
    public function __construct()
    {
    }

    //###> API ###

    /**/
    public static function getImgHtmlByBinary(
        string $binaryImgContent,
    ): string {
        return (string) u('<img
			class="img-fluid"
			src="data:png;base64,' . \base64_encode($binaryImgContent) . '" alt="img">')->collapseWhitespace();
    }

    //###< API ###
}
