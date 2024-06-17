<?php

namespace GS\Service\Service;

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

class ClipService
{
    public function __construct(
        protected readonly OSService $OSService,
    ) {
        $OSService
            ->setCallback(
                'windows',
                'copy',
                static fn($contents) => \exec('echo | set /p="' . $contents . '" | clip'),
            )
            ->setCallback(
                'darwin',
                'copy',
                static fn($contents) => \exec('echo ' . $contents . ' | pbcopy'),
            )
            ->setCallback(
                'linux',
                'copy',
                static fn($contents) => \exec('echo ' . $contents . ' | xclip -sel clip'),
            )
        ;
    }

    //###> API ###

    /*
        Copy into OS buffer
    */
    public function copy(
        int|float|string|null $contents,
    ): void {
        if ($contents === null) {
            return;
        }

        $contents = \trim((string) $contents);

        ($this->OSService)(
            'copy',
            true,
            $contents,
        );
    }

    //###< API ###
}
