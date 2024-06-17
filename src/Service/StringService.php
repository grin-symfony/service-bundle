<?php

namespace GS\Service\Service;

use function Symfony\Component\String\{
    u,
    b
};

use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
    File\File,
    Session\Session
};
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use GS\Service\Service\{
    ArrayService,
    BoolService,
    RegexService,
    CarbonService
};

class StringService
{
    //###> YOU CAN OVERRIDE IT ###
    public const DOP_WIDTH_FOR_STR_PAD = 10;
    public const EMOJI_START_RANGE = 0x1F400;
    public const EMOJI_END_RANGE = 0x1F440;
    //###< YOU CAN OVERRIDE IT ###

    public function __construct(
        protected readonly ArrayService $arrayService,
        protected readonly CarbonService $carbonService,
        protected readonly BoolService $boolService,
        protected readonly RegexService $regexService,
		#[Autowire(param: 'gs_service.year_regex')]
        protected readonly string $gsServiceYearRegex,
		#[Autowire(param: 'gs_service.year_regex_full')]
        protected readonly string $gsServiceYearRegexFull,
		#[Autowire(param: 'gs_service.ip_v4_regex')]
        protected readonly string $gsServiceIpV4Regex,
		#[Autowire(param: 'gs_service.slash_of_ip_regex')]
        protected readonly string $gsServiceSlashOfIpRegex,
    ) {
    }


    //###> API ###

    /*
        isser
    */
    public function isPathLikeNetworkIpV4(
        string $path,
    ): bool {
        return \preg_match(
            '~^(?:' . $this->gsServiceSlashOfIpRegex . ')?' . $this->gsServiceIpV4Regex . '.*$~',
            \trim($path),
        ) === 1;
    }

    /*
        isser
    */
    public function isNetworkIpV4HasExactlyDoubleShashInTheBeginning(
        string $path,
    ): bool {
        return \preg_match(
            '~^[/\\\]{2}' . $this->gsServiceIpV4Regex . '.*$~',
            \trim($path),
        ) === 1;
    }

    /*
        Do strings contain the same text?

        The empty $string doesn't contain into any not empty string!
    */
    public function strContains(
        string $haystack,
        string $needle,
    ): bool {
        $clear = static fn($string): string
            => Path::normalize(
                \mb_strtolower(
                    \str_replace(
                        ' ',
                        '',
                        \trim(
                            (string) $string,
                        )
                    )
                )
            );

        $haystack = $clear($haystack);
        $needle = $clear($needle);

        if ($haystack == '' && $needle != '') {
            return false;
        }
        if ($needle == '' && $haystack != '') {
            return false;
        }

        return (\str_contains($haystack, $needle) || \str_contains($needle, $haystack));
    }

    /*
        Changes the \Symfony\Component\Finder\Finder object state
        ROOT_PATH/REL_PATH/NAME

        ROOT_PATH - doesn't consider
        REL_PATH - must contain names
        NAME - must not contain names
    */
    public function finderPathMustContainButNotIntoName(
        Finder $finder,
        string|array $names,
    ) {
        if (!empty($names)) {
            if (\is_string($names)) {
                $names = [$names];
            }

            $names = \array_map(
                fn($partOfPath) => '~.*' . $this->regexService->getEscapedStrings($partOfPath) . '.*~',
                $names,
            );

            $finder
                ->path($names)
                ->notName($names)
            ;
        }
    }

    /**
        For the pad of the \str_pad() function

        \str_pad() considers only two-byte text.

        Usage:
            $lines = [
                'Text1',
                'Text2Text2',
                'Text3Text3Text3',
            ];

            $partOfTheString = \str_pad(
                $lines[0],
                $this->stringService->getOptimalWidthForStrPad($lines[0], $lines)
            );
    */
    public function getOptimalWidthForStrPad($inputString, array $all): int
    {
        // const part
        $maxLen         = $this->arrayService->getMaxLen($all);
        $const          = $maxLen + static::DOP_WIDTH_FOR_STR_PAD;
        // dynamic part
        $getCountLettersWithousRussanOnes = static fn($string) => \strlen(\preg_replace('~[а-я]~ui', '', (string) $string));
        $currentLen     = \mb_strlen((string) $inputString) - $getCountLettersWithousRussanOnes($inputString);

        // for \str_pad
        return $currentLen + $const;
    }

    /*
        Usage:

        $path = $this->stringService->getPath(
            '/root/dir1/dir2',
            '//dir3',
            '/dir4/',
            'filename.ext/',
        ); // "/root/dir1/dir2/dir3/dir4/filename.ext"

        $path = $this->stringService->getPath(
            '//root/dir1/dir2',
            '//dir3',
            '/dir4/',
            'filename.ext/',
        ); // "//root/dir1/dir2/dir3/dir4/filename.ext"

        $path = $this->stringService->getPath(
            'root/dir1/dir2',
            '/dir3',
            '/dir4//',
            'filename.ext/',
        ); // "root/dir1/dir2/dir3/dir4/filename.ext"
    */
    public function getPath(
        string ...$parts,
    ): string {
        $NDS = Path::normalize(\DIRECTORY_SEPARATOR);

        //###> start slashes ###
        $zeroEl = null;
        $idx = 0;
        if (isset(\array_values($parts)[$idx])) {
            $zeroEl = \array_values($parts)[$idx];
        }

        //!
        $startSlashes = '';
        if (!\is_null($zeroEl)) {
            // saves start slashes, for abs path and network ip
            $startSlashes = \preg_replace(
                '~^([\\\/]+).*$~',
                '$1',
                $zeroEl,
            );

            $startSlashesWereNotFound = $startSlashes == $zeroEl;
            if ($startSlashesWereNotFound) {
                //!
                $startSlashes = '';
            }
        }
        //###< start slashes ###

        \array_walk(
            $parts,
            static fn(&$path) => $path = \trim($path, " \n\r\t\v\x00/\\"),
        );

        $resultPath = \implode($NDS, $parts);
        $resultPath = Path::normalize(
            (string) u($resultPath)->ensureStart($startSlashes),
        );

        return $resultPath;
    }

    /*
        Gets pathname without extension
    */
    public function getPathnameWithoutExt(
        string $path,
    ): string {
        return \preg_replace('~[.][^.]+$~iu', '', $path);
    }

    /*
        Usage:

        $string = $this->stringService->replaceSlashWithSystemDirectorySeparator(
            '/root/dir1\\dir2.//',
        ); // "\root\dir1\dir2.\\"
    */
    public function replaceSlashWithSystemDirectorySeparator(
        string|array $path,
    ): string|array {
        if (\is_array($path)) {
            \array_walk(
                $path,
                fn(&$el)
                    => $el = $this->getStringWithReplacedSlashesWithSystemDirectorySeparator($el),
            );
        } else {
            $path = $this->getStringWithReplacedSlashesWithSystemDirectorySeparator($path);
        }

        return $path;
    }

    /*
        Gets number of the year by substring
    */
    public function getYearBySubstr(
        int|string $yearSubstr,
        bool $fullYear = false,
        bool $throwIfNull = false,
    ): ?string {
        $year = null;
        $matches = [];

        $yearSubstr = (string) $yearSubstr;

        $currentYear = $this->carbonService->getCurrentYear();
        $firstTwoFiguresFromCurrentYear = \substr($currentYear, 0, 2);

        $gsServiceYearRegex = $this->gsServiceYearRegex;

        if ($fullYear) {
            $gsServiceYearRegex = $this->gsServiceYearRegexFull;
        }

        \preg_match(
            '~(?<year>' . $gsServiceYearRegex . ')~',
            $yearSubstr,
            $matches,
        );

        if (isset($matches['year']) && $matches['year'] != false) {
            $year = $matches['year'];
        }

        if (\strlen($year) == 2) {
            $year = $firstTwoFiguresFromCurrentYear . $year;
        }

        if ($year === null && $throwIfNull) {
            throw new \Exception('Год не распознан по строке: ' . $yearSubstr);
        }

        return $year;
    }

    /*
        Gets number of the year which is more similar on current one
    */
    public function getMoreSimilarOnCurrentYearBySubstr(
        int|string $yearSubstr,
    ): ?string {
        $year = null;
        $matches = [];

        $yearSubstr = (string) $yearSubstr;

        $currentYear = $this->carbonService->getCurrentYear();
        $firstTwoFiguresFromCurrentYear = \substr($currentYear, 0, 2);

        \preg_match_all(
            '~(?<year>' . $this->gsServiceYearRegex . ')~',
            $yearSubstr,
            $matches,
        );

        if (isset($matches['year'])) {
            $years = $matches['year'];

            foreach ($years as $_year) {
                if (\strlen($_year) == 2) {
                    $_year = (int) ($firstTwoFiguresFromCurrentYear . $_year);
                }
                if (
                    false
                    || $year === null
                    || $year != $this->getNumberThatMoreSimilarCurrentYear($year, $_year)
                ) {
                    $year = $_year;
                }
            }
        }

        return $year;
    }

    /*
        Gets the string without the substring
    */
    public function removeSubstr(
        string $string,
        string $substr,
        int $limit = -1,
    ): string {
        if (!\str_contains($string, $substr)) {
            return $string;
        }
        $substr = $this->regexService->getEscapedStrings($substr);

        return \preg_replace('~' . $substr . '~', '', $string, $limit);
    }

    /*
        Usage:

        $filename = $this->stringService->getFilenameWithExt(
            '/root/rel/filename.ext',
            '....txt',
        ); // "filename.txt"
    */
    public function getFilenameWithExt(
        string $pathname,
        ?string $ext,
    ): string {
        if (\is_null($ext)) {
            return $pathname;
        }

        return ''
            . $this->getPathnameWithoutExt(\basename($pathname))
            . ((string) u(\mb_strtolower($ext))->ensureStart('.'))
        ;
    }

    /*
        Only for DISK NAME, not for IP

        returns $rootDrive, but if it's only a letter it ensures end
    */
    public function getEnsuredRootDrive(
        string $rootDrive,
    ): string {
        $isDrive = static fn($path) => \preg_match('~^[a-zа-я]$~iu', $path) === 1;

        $trimmedRootDrive = \trim(
            \rtrim(
                $rootDrive,
                ':/\\',
            )
        );
        if (!$isDrive($trimmedRootDrive)) {
            return $rootDrive;
        }

        return (string) u($trimmedRootDrive)->ensureEnd(':/');
    }

    /* WARNING: use it instad of Path::makeAbsolute()

        dir1/dir2 + //ipV4 => (save // in the beginning)//ip4/dir1/dir2
        dir1/dir2 + C:/ => C:/dir1/dir2
    */
    public function makeAbsolute(
        string $path,
        string $basePath,
    ): string {
        if (Path::isAbsolute($path)) {
            $absPath = $path;
        } else {
            $absPath = Path::makeAbsolute($path, $basePath);
        }

        //###> CONSIDER NETWORK PATHS
        if ($this->isNetworkPath($absPath)) {
            $absPath = (string) u(
                \ltrim($absPath, '/\\ \n\r\t\v\x00'),
            )->ensureStart('//');
        }

        return Path::normalize($absPath);
    }

    /* WARNING: use it instad of Path::getDirectory()

        //ipV4 => //ipV4
        //ipV4/dir1/dir2 => //ipV4/dir1
        C:/ => C:/
        C:/dir1/dir2 => C:/dir1
    */
    public function getDirectory(
        string $path,
    ): string {
        $isOnlyNetworkPath = $this->isOnlyNetworkPath(
            $path,
        );

        if ($isOnlyNetworkPath) {
            return Path::normalize($path);
        }

        return Path::normalize(\dirname($path));
    }

    /* WARNING: use it instad of Path::getRoot()

        //ipV4/ => //ipV4 (instead of just /)
        //ipV4/dir1/dir2 => //ipV4
        C: => C:/
        C:/dir1/dir2 => C:/
    */
    public function getRoot(
        string $path,
    ): string {
        $isNetworkPath = $this->isNetworkPath(
            $path,
        );

        if ($isNetworkPath) {
            $ipRoot = null;
            $ipRootName = 'ipRoot';

            $matches = [];
            \preg_match(
                '~^(?<' . $ipRootName . '>' . $this->gsServiceSlashOfIpRegex . '' . $this->gsServiceIpV4Regex . ').*~',
                \trim($path),
                $matches,
            );
            if ($v = $this->boolService->isGet($matches, $ipRootName)) {
                $ipRoot = $v;
            }

            if ($ipRoot === null) {
                throw new \Exception($ipRootName . ' не был найден из ' . $path);
            }
            return Path::normalize($ipRoot);
        }
        return Path::normalize(Path::getRoot($path));
    }

    /**/
    public function getEmoji(): string
    {
        [$max, $min] = [
            static::EMOJI_START_RANGE,
            static::EMOJI_END_RANGE,
        ];
        if ($min > $max) {
            [$max, $min] = [$min, $max];
        }
        return \IntlChar::chr(\random_int($min, $max));
    }

    /*
        Can return NOT EXISTING $ext
            IF $onlyExistingPath == false

        Always prefers EXISTING $path $ext
    */
    public function getExtFromPath(
        string $path,
        bool $onlyExistingPath,
        bool $withDotAtTheBeginning = true,
        ?array $amongExtensions = null,
    ): ?string {
        $ext = null;

        //###> $substrExt for trying to get a $resultExt
        $substrExt = \preg_replace('~^.*([.].+)$~', '$1', $path);
        if ($substrExt == $path) {
            $substrExt = null;
        }
        //###<

        //###> $resultExt
        $resultExt = null;
        $amongExtensions ??= [];
        if (!empty($amongExtensions) && $substrExt !== null) {
            \array_unshift($amongExtensions, $substrExt);
        }
        foreach ($amongExtensions as $k => $cycleEmongExt) {
            $cycleEmongExt = (string) $cycleEmongExt;
            $file = $this->makeAbsolute(
                (string) u($this->getFilenameWithExt($path, $cycleEmongExt)),
                $this->getDirectory($path),
            );

            if (\is_file($file)) {
                $resultExt = $cycleEmongExt;
                unset($cycleEmongExt);
                break;
            }
        }
        //###<

        //###> PREFERENCES /* != */
        if (!$onlyExistingPath && $substrExt != null) {
            $ext = $substrExt;
        }
        if ($resultExt != null) {
            $ext = $resultExt;
        }
        //###< PREFERENCES (MORE IMPORTANT)


        //###> DOT
        if ($ext !== null) {
            if ($withDotAtTheBeginning) {
                if (!\is_null($ext)) {
                    $ext = (string) u($ext)->ensureStart('.');
                }
            } else {
                $ext = \ltrim($ext, '.');
            }
        }
        //###< DOT

        return $ext;
    }

    /*
        returns null when $string doesn't contain the pattern
    */
    public function getFromCallbackIfStringLikeRegex(
        string $string,
        array|string $regexs,
        callable|\Closure $callback,
    ): mixed {
        if (\is_string($regexs)) {
            $regexs = [$regexs];
        }

        foreach ($regexs as $regex) {
            if (\preg_match($regex, $string) === 1) {
                return $callback(
                    $regex,
                );
            }
        }
        return null;
    }

    //###< API ###


    //###> HELPER ###

    private function isOnlyNetworkPath(
        string $path,
    ): bool {
        return \preg_match('~^' . $this->gsServiceSlashOfIpRegex . $this->gsServiceIpV4Regex . '$~', \trim($path)) === 1;
    }

    private function isNetworkPath(
        string $path,
    ): bool {
        return \preg_match('~^' . $this->gsServiceSlashOfIpRegex . '.*$~', \trim($path)) === 1;
    }

    private function getNumberThatMoreSimilarCurrentYear(
        $firstYear,
        $secontYear,
    ) {
        $currentYear    = (int) $this->carbonService->getCurrentYear();
        $firstYear      = \abs((int) $firstYear);
        $secontYear     = \abs((int) $secontYear);

        if (\abs($currentYear - $firstYear) < \abs($currentYear - $secontYear)) {
            return $firstYear;
        }

        return $secontYear;
    }

    private function getStringWithReplacedSlashesWithSystemDirectorySeparator(
        string $path,
    ): string {
        return \str_replace(Path::normalize(\DIRECTORY_SEPARATOR), \DIRECTORY_SEPARATOR, $path);
    }

    //###< HELPER ###
}
