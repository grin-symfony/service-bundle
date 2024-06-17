<?php

namespace GS\Service\Service;

use Symfony\Component\Finder\{
    SplFileInfo,
    Finder
};
use Symfony\Component\Filesystem\{
    Path,
    Filesystem
};
use Symfony\Component\OptionsResolver\{
    Options,
    OptionsResolver
};
use Symfony\Component\Yaml\{
    Tag\TaggedValue,
    Yaml
};
use Symfony\Component\HttpFoundation\{
    Request,
    RequestStack,
    Session\Session
};
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class RegexService
{
    public function __construct(
        #[Autowire(value: '%gs_service.start_of_win_sys_file_regex%')]
        protected readonly string $gsServiceStartOfWinSysFileRegex,
    ) {
    }

    //###> API ###

    /*
        Gets regex of win system file
    */
    public function getDocxSysFileRegex(): string
    {
        return '~^' . $this->gsServiceStartOfWinSysFileRegex . '.*[.]docx?$~ui';
    }

    /*
        Gets something transformed into regex able one
    */
    public function getEscapedStrings(
        string|array $strings,
    ): string|array {

        $getEscapedString = $this->getEscapedString(...);

        if (\is_array($strings)) {
            \array_walk(
                $strings,
                static fn($partOfPath) => '~.*' . $getEscapedString($partOfPath) . '.*~',
            );
        }

        if (\is_string($strings)) {
            $strings = $getEscapedString($strings);
        }

        return $strings;
    }

    //###< API ###


    //###> HELPER ###

    private function getEscapedString(
        string $string,
    ): string {
        $string = \strtr(
            $string,
            [
                '$'     => '\$',
                '^'     => '\^',
                '|'     => '[|]',
                '+'     => '[+]',
                '*'     => '[*]',
                '?'     => '[?]',
                '['     => '[[]',
                ']'     => '[]]',
                '\\'    => '(?:\\\\|\/)',
                '/'     => '(?:\\|\/)',
                '.'     => '[.]',
                '-'     => '[-]',
                ')'     => '[)]',
                '('     => '[(]',
                '{'     => '[{]',
                '}'     => '[}]',
            ]
        );

        return $string;
    }

    //###< HELPER ###
}
