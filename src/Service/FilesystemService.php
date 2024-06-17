<?php

namespace GS\Service\Service;

use function Symfony\Component\String\{
    u,
    b
};

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
use Symfony\Component\Uid\Uuid;
use Carbon\Carbon;
use GS\Service\Service\{
    StringService,
    DumpInfoService
};
use Symfony\Component\String\Slugger\SluggerInterface;

class FilesystemService
{
    //###> YOU CAN OVERRIDE IT ###
    public const TMP_FILENAME_PREFIX = '';
    /*
        Watch out when changing it
        This lenght depends on OS System
    */
    public const MAX_TMP_FILENAME_LEN = 150;
    //###< YOU CAN OVERRIDE IT ###

    protected OptionsResolver $demandsOptionsResolver;
    protected readonly array $demandsKeys;
    protected readonly Filesystem $filesystem;

    public function __construct(
        protected readonly DumpInfoService $dumpInfoService,
        protected readonly StringService $stringService,
        protected readonly SluggerInterface $slugger,
		#[Autowire(param: 'gs_service.local_drive_for_test')]
        protected readonly string $gsServiceLocalDriveForTest,
		#[Autowire(param: 'gs_service.app_env')]
        protected readonly string $gsServiceAppEnv,
		#[Autowire(service: 'gs_service.carbon_factory_immutable')]
        protected $gsServiceCarbonFactoryImmutable,
    ) {
        $this->filesystem = new Filesystem();

        //###> DEMANDS ###
        $demands = [
            'exists',
            'isAbsolutePath',
            'isDir',
            'isFile',
        ];
        //###< DEMANDS ###
        $this->demandsKeys = \array_combine($demands, $demands);

        $this->demandsOptionsResolver = new OptionsResolver();
        $this->configureOptions();
    }


    //###> API ###

    /*
        Throws an exception if the paths haven't passed checks of DEMANDS section
    */
    public function throwIfNot(
        array $demands,
        ?string ...$paths,
    ): void {
        $this->ifNot(true, $demands, ...$paths);
    }

    /*
        Gets errors if the paths haven't passed checks of DEMANDS section
    */
    public function getErrorsIfNot(
        array $demands,
        ?string ...$paths,
    ): array {
        return $this->ifNot(false, $demands, ...$paths);
    }

    /*
        You have to make a file by path $to in your $callback

        If something's wrong just throw \Exception in $callback
    */
    public function executeWithoutChangeMTime(
        \Closure|\callable $callback,
        string $from,
        string $to,
        bool $override,
        bool $move,
        bool $isMakeIt = false,
    ) {
        return $this->make(
            $callback,
            $from,
            $to,
            $override,
            $move,
            isMakeIt:               $isMakeIt,
            withoutChangeMTime:     true,
        );
    }

    /**/
    public function copyWithoutChangeMTime(
        string $from,
        string $to,
        bool $override,
        bool $move,
        bool $isMakeIt = false,
    ) {
        return $this->make(
            'copy',
            $from,
            $to,
            $override,
            $move,
            isMakeIt:               $isMakeIt,
            withoutChangeMTime:     true,
        );
    }

    /**/
    public function copyWithChangeMTime(
        string $from,
        string $to,
        bool $override,
        bool $move,
        bool $isMakeIt = false,
    ) {
        return $this->make(
            'copy',
            $from,
            $to,
            $override,
            $move,
            isMakeIt:               $isMakeIt,
            withoutChangeMTime:     false,
        );
    }

    /*
        Gets ROOT DRIVE where this project situates
    */
    public function getLocalRoot(): string
    {
        if ($this->gsServiceAppEnv === 'test') {
            return $this->gsServiceLocalDriveForTest;
        }

        $NDS = Path::normalize(\DIRECTORY_SEPARATOR);
        return (string) u(\explode($NDS, Path::normalize(__DIR__))[0])->ensureEnd($NDS);
    }

    /**/
    public function exists(
        string $path,
    ): bool {
        return $this->filesystem->exists($path);
    }

    /**/
    public function assignCMTime(
        string $sourceCATimeAbsPath,
        string $toAbsPath,
    ): void {
        $this->throwIfNot(
            [
                'exists',
                'isAbsolutePath',
                'isFile',
            ],
            $sourceCATimeAbsPath,
            $toAbsPath,
        );

        $splFileInfoSource      = new \SplFileInfo($sourceCATimeAbsPath);
        $modifiedTimestamp      = $splFileInfoSource->getMTime();

        // modified
        $this->filesystem->touch($toAbsPath, $modifiedTimestamp);
    }

    /**/
    public function getSmallestDrive(): string
    {
        $drives         = \explode(
            ' ',
            ((string) u(\shell_exec('fsutil fsinfo drives'))->collapseWhitespace()),
        );
        $smallestDrive  = null;

        foreach ($drives as $drive) {
            $errors = $this->getErrorsIfNot(
                [
                    'exists',
                ],
                $drive,
            );
            if (empty($errors)) {
                if ($smallestDrive === null) {
                    $smallestDrive = $drive;
                }
                if (\disk_total_space($drive) < \disk_total_space($smallestDrive)) {
                    $smallestDrive = $drive;
                }
            }
        }

        return Path::normalize($smallestDrive);
    }

    /*
        Makes directories recursively
    */
    public function mkdir(
        string|iterable $dirs,
        int $mode = 0777,
    ): void {
        $this->filesystem->mkdir($dirs, $mode);
    }

    /**/
    public function getDesktopPath(): string
    {
        $desktopPath = $this->stringService->getPath(
            \getenv("HOMEDRIVE"),
            \getenv("HOMEPATH"),
            "Desktop",
        );

        $this->throwIfNot(
            [
                'exists',
                'isAbsolutePath',
                'isDir',
            ],
            $desktopPath,
        );

        return $desktopPath;
    }

    /*
        Is first file newer?
    */
    public function firstFileNewer(
        \SplFileInfo|string $first,
        \SplFileInfo|string $second,
    ): bool {

        if (\is_string($first)) {
            $first      = Path::normalize($first);

            $errors = $this->getErrorsIfNot(
                [
                    'exists',
                ],
                $first,
            );
            if (!empty($errors)) {
                return false;
            }
        }

        if (\is_string($second)) {
            $second             = Path::normalize($second);

            $errors = $this->getErrorsIfNot(
                [
                    'exists',
                ],
                $second,
            );

            if (!empty($errors)) {
                return true;
            }
        }

        $carbonFirst = $this->getCarbonByFile($first);
        $carbonSecond = $this->getCarbonByFile($second);

        return $carbonFirst > $carbonSecond;
    }

    /*
        Creates file into temporary OS directory
    */
    public function tempnam(
        ?string $path = null,
        string $ext = 'txt',
    ): string {
        $fileExists = empty($this->getErrorsIfNot(
            [
                'exists',
                'isFile',
            ],
            $path,
        ));
        if (!\is_null($path) && $fileExists) {
            $ext = (new \SplFileInfo($path))->getExtension();
        }

        $ext = (string) u($ext)->ensureStart('.');

        return $this->filesystem->tempnam(
            Path::normalize(\sys_get_temp_dir()),
            \substr(
                $this->slugger->slug(
                    static::TMP_FILENAME_PREFIX . Uuid::v1()
                ),
                0,
                static::MAX_TMP_FILENAME_LEN,
            ),
            $ext,
        );
    }

    /*
        Appends the content into the file
        DOESN'T PUT ANY \PHP_EOL, you should do it on your onw
    */
    public function appendToFile(
        string $absPath,
        $content,
    ): void {
        $exists = $this->isAbsPathExists($absPath);
        if ($exists) {
            $this->filesystem->appendToFile($absPath, $content, true/* LOCK */);
        }
    }

    /*
        It's a RECURSIVELY method.
        Removes file or directories.
    */
    public function deleteByAbsPathIfExists(
        string $absPath,
    ): void {
        $exists = $this->isAbsPathExists($absPath);
        if (!$exists) {
            return;
        }
        $this->filesystem->remove($absPath);
    }

    //###< API ###


    //###> HELPER ###

    private function getCarbonByFile(
        \SplFileInfo|string $file,
    ): Carbon {
        if (\is_string($file)) {
            $this->throwIfNot(
                [
                    'isFile',
                ],
                $file,
            );
            $carbon     = Carbon::createFromTimestamp((new \SplFileInfo($file))->getMTime());
        } else {
            $carbon     = Carbon::createFromTimestamp($file->getMTime());
        }

        return $carbon;
    }

    private function ifNot(
        bool $throw,
        array $demands,
        ?string ...$absPaths,
    ): array {
        $demands = \array_combine($demands, $demands);

        $this->demandsOptionsResolver->resolve($demands);

        $errors = [];
        foreach ($absPaths as $absPath) {
            if ($absPath === null) {
                $absPath = '';
            }

            if (isset($demands[$this->demandsKeys['exists']])               && !$this->filesystem->exists($absPath)) {
                $errors[$absPath][]     = 'существующим';
            }
            if (isset($demands[$this->demandsKeys['isAbsolutePath']])       && !Path::isAbsolute($absPath)) {
                $errors[$absPath][]     = 'абсолютным';
            }
            if (isset($demands[$this->demandsKeys['isDir']])                && !\is_dir($absPath)) {
                $errors[$absPath][]     = 'папкой';
            }
            if (isset($demands[$this->demandsKeys['isFile']])               && !\is_file($absPath)) {
                $errors[$absPath][]     = 'файлом';
            }
        }

        if ($throw && !empty($errors)) {
            $errMessage = '';
            foreach ($errors as $path => $error) {
                $path = $this->stringService->replaceSlashWithSystemDirectorySeparator($path);
                $errMessage .= 'Переданный "' . $path  . '" должен быть: (' . \implode(', ', $error) . ')' . \PHP_EOL;
            }
            throw new \Exception($errMessage);
            return $errors;
        }

        return $errors;
    }

    private function configureOptions()
    {
        $this->demandsOptionsResolver
            ->setDefaults($this->demandsKeys)
        ;
    }

    /*
        If there is an Exception during excuting callback type it removes created tmp file
    */
    private function make(
        string|\Closure|\callable $type,
        string $from,
        string $to,
        bool $override,
        bool $move,
        bool $isMakeIt,
        bool $withoutChangeMTime = true,
    ): array {
        $madeResults = [];

        $defaultPredicatForMakeIt = $override || $this->firstFileNewer(first: $from, second: $to);
        $exactlyMakeIt = ($isMakeIt === true) || $defaultPredicatForMakeIt;

        if ($exactlyMakeIt) {
            $this->throwIfNot(
                [
                    'exists',
                    'isAbsolutePath',
                    'isFile',
                ],
                $from,
            );
            $this->throwIfNot(
                [
                    'isAbsolutePath',
                ],
                $to,
            );

            // from -> tmp
            try {
                $toTmp = $this->tempnam($to);
            } catch (\Exception $e) {
                return $madeResults;
            }

            try {
                $this->detectMakeTypeAndExecute(
                    $type,
                    $from,
                    $toTmp,
                    $exactlyMakeIt,
                );
            } catch (\Exception $e) {
                if (\is_file($toTmp)) {
                    $this->filesystem->remove($toTmp);
                }
                throw $e;
            }

            // tmp -> realTo
            $this->mkdir(
                $this->stringService->getDirectory($to),
            );

            // before rename need to remove $to
            if (\is_file($to)) {
                $this->filesystem->remove($to);
            }
            $this->filesystem->rename(
                $toTmp,
                $to,
                overwrite: $exactlyMakeIt,
            );

            try {
                if ($withoutChangeMTime) {
                    $this->assignCMTime($from, $to);
                }
            } catch (\Exception $e) {
                echo 'ERROR: не получилось установить время файла "' . $to . '"' . \PHP_EOL . \PHP_EOL;
            }

            if (\is_file($toTmp)) {
                $this->filesystem->remove($toTmp);
            }

            try {
                if ($move && \is_file($from)) {
                    $this->filesystem->remove($from);
                }
            } catch (\Exception $e) {
                echo 'ERROR: Не удалось удалить ' . $this->stringService->replaceSlashWithSystemDirectorySeparator($from) . \PHP_EOL . \PHP_EOL;
            }

            $madeResults     = [
                'from'      => $from,
                'to'        => $to,
            ];
        }

        return $madeResults;
    }

    private function isAbsPathExists(string $absPath): bool
    {
        return empty($this->getErrorsIfNot(
            [
                'isAbsolutePath',
                'exists',
            ],
            $absPath,
        ));
    }

    private function detectMakeTypeAndExecute(
        $type,
        $from,
        $to,
        $exactlyMakeIt,
    ) {
        if (\is_string($type)) {
            if ($type == 'copy') {
                $this->filesystem->copy(
                    $from,
                    $to,
                    overwriteNewerFiles:    $exactlyMakeIt,
                );
            }
            return;
        }

        if ($type instanceof \callable || $type instanceof \Closure) {
            $type(
                $from,
                $to,
                $exactlyMakeIt,
            );
            return;
        }
    }

    //###< HELPER ###
}
