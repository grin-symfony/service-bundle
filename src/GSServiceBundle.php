<?php

namespace GS\Service;

use Symfony\Component\EventDispatcher\DependencyInjection\AddEventAliasesPass;
use GS\Service\GSServiceExtension;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Compiler\ResolveEnvPlaceholdersPass;
use GS\Service\Service\{
    ServiceContainer,
    BoolService,
    StringNormalizer,
    ConfigService
};

class GSServiceBundle extends Bundle
{
    public function __construct(
        //private readonly BoolService $boolService,
    ) {
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if ($this->extension === null) {
            $this->extension = new GSServiceExtension(
                //boolService:      $this->boolService,
            );
        }

        return $this->extension;
    }
}
