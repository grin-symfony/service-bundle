<?php

namespace GS\Service\Service;

use Symfony\Component\Finder\{
    Finder
};
use GS\Service\Service\{
    StringService,
    BufferService
};
use GS\Command\Command\{
    AbstractCommand
};
use Symfony\Contracts\Translation\TranslatorInterface;
use GS\Service\Service\ConfigService;

class DumpInfoService
{
    public function __construct(
        protected readonly StringService $stringService,
        protected readonly TranslatorInterface $gsServiceT,
        protected readonly ConfigService $configService,
    ) {
    }


    //###> API ###

    /**/
    public function dumpInfoAboutCurrentIp(
        AbstractCommand $command,
    ): void {
        $command->getIo()->note([
            $this->gsServiceT->trans('gs_service.service.current_ip') . ':'
            . ' "' . $this->stringService->replaceSlashWithSystemDirectorySeparator(
                $this->configService->getCurrentIp()
            ) . '"',
        ]);
    }

    /* Dumps paths */
    public function dumpInfo(
        AbstractCommand $command,
        string|array $from,
        string|array $to = null,
        bool $dirname = true,
        ?bool $onlyFrom = null,
        ?bool $onlyTo = null,
    ): void {

        /* ASK isDumpInfo ONLY IF THIS FUNCTION EXISTS (Trait was used) */
        if (\method_exists($command, 'isDumpInfo') && $command->isDumpInfo() == false) {
            return;
        }

        if (\is_array($from)) {
            $from = \array_filter($from);
            if (empty($from)) {
                return;
            }
        }

        $paths = [];

        $headers = [
            'Откуда',
            'Куда',
        ];

        if (\is_string($from) && \is_string($to)) {
            $this->fillInDumpInfo(
                $paths,
                $from,
                $to,
                $dirname,
            );
        } elseif (\is_string($from) && \is_array($to)) {
            $to             = \array_filter($to);

            foreach ($to as $_to) {
                $this->fillInDumpInfo(
                    $paths,
                    $from,
                    $_to,
                    $dirname,
                );
            }
        } elseif (\is_array($from) && \is_string($to)) {
            $from           = \array_filter($from);

            foreach ($from as $_from) {
                $this->fillInDumpInfo(
                    $paths,
                    $_from,
                    $to,
                    $dirname,
                );
            }
        } elseif (\is_array($from) && \is_array($to)) {
            if (\count($from) != \count($to)) {
                throw new \Exception('\\count(firstArray) != \\count(secondArray) but must be');
            }

            $fromIdxed  = \array_values(\array_filter($from));
            $to         = \array_values(\array_filter($to));
            foreach ($fromIdxed as $i => $_from) {
                $this->fillInDumpInfo(
                    $paths,
                    $_from,
                    $to[$i],
                    $dirname,
                );
            }
            /* FOR STRUCTURE
                paths   => [
                    [
                        <from>,
                        <to>,
                    ],
                    ...
                ]
            */
        } elseif (\is_array($from) && $to === null) {
            $from           = \array_filter($from);

            foreach ($from as [ 'from' => $_from, 'to' => $_to ]) {
                $this->fillInDumpInfo(
                    $paths,
                    $_from,
                    $_to,
                    $dirname,
                );
            }
        }

        //###>
        if ($onlyFrom === true && $onlyTo == null/* == consider false too */) {
            $headers = [
                'Откуда',
            ];
            \array_walk($paths, static fn(&$v, $k) => $v = [$v['from']] ?? null);
            \array_filter($paths);
        }
        if ($onlyTo === true && $onlyFrom == null/* == consider false too */) {
            $headers = [
                'Куда',
            ];
            \array_walk($paths, static fn(&$v, $k) => $v = [$v['to']] ?? null);
            \array_filter($paths);
        }

        //BufferService::clear();
        /* STYLE 1 */
        $command->getCloneTable()
            ->setHeaders(
                $headers,
            )
            ->setRows(
                $paths,
            )
            ->render()
        ;
        /* STYLE 2
        $command->getIo()->table(
            [
                'Откуда',
                'Куда',
            ],
            $paths,
        );
        */
    }

    /* USAGE:
        $this->io->table(
            [
                '...',
            ],
            [
                [
                    ...$this->dumpInfoService->getLineFromTo(
                        from:       $from,
                        to:         $to,
                        toBg:       'yellow',
                        toFg:       'black',
                    ),
                ]
            ]
        );
    */
    public function getLineFromTo(
        string $from,
        string $to,
        int $fromGetDir = 0,
        int $toGetDir = 0,
        bool $fromAsLink = true,
        bool $toAsLink = true,
        string $fromFg = 'blue',
        string $toFg = 'blue',
        string $fromBg = 'white',
        string $toBg = 'white',
    ): array {

        $from               = $this->stringService->replaceSlashWithSystemDirectorySeparator($from);
        $to                 = $this->stringService->replaceSlashWithSystemDirectorySeparator($to);

        for ($i = 0; $fromGetDir > $i; $i++) {
            $from   = $this->stringService->getDirectory($from);
        }
        for ($i = 0; $toGetDir > $i; $i++) {
            $to     = $this->stringService->getDirectory($to);
        }

        if ($fromAsLink) {
            $from = ''
                . '<bg=' . $fromBg . ';fg=' . $fromFg . ';href="' . $from . '">"' . $from . '"</>';
        }
        if ($toAsLink) {
            $to = ''
                . '<bg=' . $toBg . ';fg=' . $toFg . ';href="' . $to . '">"' . $to . '"</>';
        }

        return [
            'from'  => $from,
            'to'    => $to,
        ];
    }

    // DEBUG
    public function ddFinder(
        AbstractCommand $command,
        Finder $finder,
    ): void {
        foreach ($finder as $file) {
            $command->getIo()->info(
                $file->getRealPath(),
            );
        }
        \dd('END');
    }

    //###< API ###


    //###> HELPER ###

    private function fillInDumpInfo(
        array &$fillIn,
        string $from,
        string $to,
        bool $dirname,
    ): void {
        if ($dirname == true) {
            $from   = $this->stringService->getDirectory($from);
            $to     = $this->stringService->getDirectory($to);
        }

        $fillInKey          = $from . $to;

        $from               = $this->stringService->replaceSlashWithSystemDirectorySeparator($from);
        $to                 = $this->stringService->replaceSlashWithSystemDirectorySeparator($to);

        if (isset($fillIn[$fillInKey])) {
            return;
        }

        $fillIn[$fillInKey] = $this->getLineFromTo(
            from:   $from,
            to:     $to,
        );
    }

    //###< HELPER ###
}
