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

class OSService
{
    protected array $store;
    protected readonly string $currentOs;

    public function __construct()
    {
        $this->store = [];
        $this->currentOs = \php_uname(mode: "s");
    }

    //###> API ###

    public function getCurrentOs(): string
    {
        return $this->currentOs;
    }

    /*
        Invoke it to execute the callback
        for the connected Operation System
    */
    public function __invoke(
        string|int $callbackKey,
        bool $removeCallbackAfterExecution,
        ...$args,
    ): mixed {
        foreach ($this->store as $requiredOs => $callbacks) {
            if (\preg_match('~' . $requiredOs . '~i', $this->currentOs)) {
                foreach ($callbacks as $storeCallbackKey => $callback) {
                    if ($storeCallbackKey === $callbackKey) {
                        if ($removeCallbackAfterExecution) {
                            unset($this->store[$requiredOs][$storeCallbackKey]);
                        }
                        return $callback(...$args);
                        break;
                    }
                }
                break;
            }
        }
        return null;
    }

    /*
        IF $getOsName IS NULL IT MEANS ANY OS
        Return value of the callback $getOsName must be ?string that will be compared with $this->currentOs
        for different Operation Systems
            Windows
            Darwin
            Linux
            FreeBSD
            ...

        __invoke returns What returns callback
    */
    public function setCallback(
        callable|\Closure|null|string $getOsName,
        string|int $callbackKey,
        callable|\Closure $callback,
    ): static {
        if (\is_callable($getOsName) || $getOsName instanceof \Closure) {
            $requiredOs = $getOsName();
        } else {
            $requiredOs = $getOsName;
        }

        if (\is_null($requiredOs)) {
            //###> ANY OS
            $requiredOs = $this->currentOs;
        }

        if (!\is_string($requiredOs)) {
            if (\is_null($requiredOs)) {
                return $this;
            }
            throw new \Exception(
                'Return value of the callback $getOsName must be ?string that will be compared with $this->currentOs'
            );
        }

        $this->store[$requiredOs][$callbackKey] = $callback;

        return $this;
    }

    //###< API ###
}
