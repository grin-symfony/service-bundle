<?php

namespace GS\Service;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\DependencyInjection\Definition;
use GS\Service\Configuration;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use GS\Service\Service\ServiceContainer;
use GS\Service\Service\ConfigService;
use GS\Service\Service\ArrayService;
use GS\Service\Service\BoolService;
use GS\Service\Service\DoctrineService;
use GS\Service\Service\BufferService;
use GS\Service\Service\CarbonService;
use GS\Service\Service\ClipService;
use GS\Service\Service\DumpInfoService;
use GS\Service\Service\FilesystemService;
use GS\Service\Service\HtmlService;
use GS\Service\Service\ParserService;
use GS\Service\Service\RandomPasswordService;
use GS\Service\Service\RegexService;
use GS\Service\Service\StringService;
use GS\Service\Service\OSService;

class GSServiceExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    public const PREFIX = 'gs_service';

    public const LOCALE = 'locale';
    public Parameter $localeParameter;
    public const TIMEZONE = 'timezone';
    public Parameter $timezoneParameter;

    public const APP_ENV = 'app_env';
    public const LOCAL_DRIVE_FOR_TEST = 'local_drive_for_test';
    public const FAKER_SERVICE_KEY = 'faker';
    public const CARBON_FACTORY_SERVICE_KEY = 'carbon_factory_immutable';

    public const YEAR_REGEX_KEY = 'year_regex';
    public const YEAR_REGEX_FULL_KEY = 'year_regex_full';
    public const IP_V_4_REGEX_KEY = 'ip_v4_regex';
    public const SLASH_OF_IP_REGEX_KEY = 'slash_of_ip_regex';
    public const START_OF_WIN_SYS_FILE_REGEX = 'start_of_win_sys_file_regex';

    public function __construct(
        //private readonly BoolService $boolService,
    ) {
    }

    public function getAlias(): string
    {
        return self::PREFIX;
    }

    public function prepend(ContainerBuilder $container)
    {
        ServiceContainer::loadYaml(
            $container,
			__DIR__ . '/..',
            [
                ['config', 'services.yaml'],
                ['config/packages', 'translation.yaml'],
                ['config/packages', 'gs_service.yaml'],
            ],
        );
    }

    public function getConfiguration(
        array $config,
        ContainerBuilder $container,
    ) {
        return new Configuration(
            locale:     $container->getParameter(
                ServiceContainer::getParameterName(self::PREFIX, self::LOCALE),
            ),
            timezone:   $container->getParameter(
                ServiceContainer::getParameterName(self::PREFIX, self::TIMEZONE),
            ),
            appEnv: $container->getParameter(
                ServiceContainer::getParameterName(self::PREFIX, self::APP_ENV),
            ),
            localDriveForTest: $container->getParameter(
                ServiceContainer::getParameterName(self::PREFIX, self::LOCAL_DRIVE_FOR_TEST),
            ),
            loadPacksConfigs: $container->getParameter(
                ServiceContainer::getParameterName(self::PREFIX, ConfigService::CONFIG_SERVICE_KEY),
            ),
            gsServiceYearRegex: $container->getParameter(
                ServiceContainer::getParameterName(self::PREFIX, self::YEAR_REGEX_KEY),
            ),
            gsServiceYearRegexFull: $container->getParameter(
                ServiceContainer::getParameterName(self::PREFIX, self::YEAR_REGEX_FULL_KEY),
            ),
            gsServiceIpV4Regex: $container->getParameter(
                ServiceContainer::getParameterName(self::PREFIX, self::IP_V_4_REGEX_KEY),
            ),
            gsServiceSlashOfIpRegex:    $container->getParameter(
                ServiceContainer::getParameterName(self::PREFIX, self::SLASH_OF_IP_REGEX_KEY),
            ),
            gsServiceStartOfWinSysFileRegex:    $container->getParameter(
                ServiceContainer::getParameterName(self::PREFIX, self::START_OF_WIN_SYS_FILE_REGEX),
            ),
        );
    }

    public function loadInternal(array $config, ContainerBuilder $container): void
    {
		$this->setContainerParameters(
            $config,
            $container,
        );
		$this->setContainerDefinitions(
            $config,
            $container,
        );
        $this->setContainerTags(
            $container,
        );
    }

    private function setContainerParameters(
        array $config,
        ContainerBuilder $container,
    ) {
		$pa = PropertyAccess::createPropertyAccessor();

		ServiceContainer::setParametersForce(
            $container,
            callbackGetValue: static function ($key) use (&$config, $pa) {
                return $pa->getValue($config, '[' . $key . ']');
            },
            parameterPrefix: self::PREFIX,
            keys: [
            self::LOCALE,
            self::TIMEZONE,
            self::APP_ENV,
            self::LOCAL_DRIVE_FOR_TEST,
            self::YEAR_REGEX_KEY,
            self::YEAR_REGEX_FULL_KEY,
            self::IP_V_4_REGEX_KEY,
            self::SLASH_OF_IP_REGEX_KEY,
            ],
        );

        ServiceContainer::setParametersForce(
            $container,
            callbackGetValue: static function ($key) use (&$config, $pa) {
                $loadPacksConfigs = [];
                $configsService = $pa->getValue($config, '[' . $key . ']');
				foreach ($configsService as $configService) {
                    //###>
                    $packName = null;
                    if (isset($configService[ConfigService::PACK_NAME])) {
                        $packName = $configService[ConfigService::PACK_NAME];
                    }
                    $packRelPath = null;
                    if (isset($configService[ConfigService::PACK_REL_PATH])) {
                        $packRelPath = $configService[ConfigService::PACK_REL_PATH];
                    }
                    if ($packName == false) {
                        continue;
                    }
                    if ($packRelPath == false) {
                        $packRelPath = null;
                    }

                    $lazyLoad = $configService[ConfigService::LAZY_LOAD]
                    ?? ConfigService::DEFAULT_LAZY_LOAD
                    ;

                    $doesNotExistMess = $configService[ConfigService::DOES_NOT_EXIST_MESS]
                    ?? ConfigService::DEFAULT_DOES_NOT_EXIST_MESS
                    ;

                    $loadPacksConfigs [] = [
                    ConfigService::PACK_NAME            => $packName,
                    ConfigService::PACK_REL_PATH        => $packRelPath,
                    ConfigService::LAZY_LOAD            => $lazyLoad,
                    ConfigService::DOES_NOT_EXIST_MESS  => $doesNotExistMess,
                    ];
                }
                return $loadPacksConfigs;
            },
            parameterPrefix: self::PREFIX,
            keys: [
				ConfigService::CONFIG_SERVICE_KEY,
            ],
        );
		
		//\dd($container->getParameter('gs_service.load_packs_configs'));
    }


    //###> HELPERS ###


    private function setRestContainerDefinitions(
        ContainerBuilder $container,
    ): void {
        foreach (
            [
            [
                ArrayService::class,
                ArrayService::class,
				false,
            ],
            [
                BoolService::class,
                BoolService::class,
				false,
            ],
            [
                BufferService::class,
                BufferService::class,
				false,
            ],
            [
                CarbonService::class,
                CarbonService::class,
				false,
            ],
            [
                ClipService::class,
                ClipService::class,
				false,
            ],
            [
                DumpInfoService::class,
                DumpInfoService::class,
				false,
            ],
            [
                FilesystemService::class,
                FilesystemService::class,
				false,
            ],
            [
                HtmlService::class,
                HtmlService::class,
				false,
            ],
            [
                ParserService::class,
                ParserService::class,
				false,
            ],
            [
                RandomPasswordService::class,
                RandomPasswordService::class,
				false,
            ],
            [
                RegexService::class,
                RegexService::class,
				false,
            ],
            [
                StringService::class,
                StringService::class,
				false,
            ],
            [
                OSService::class,
                OSService::class,
				false,
            ],
            [
                ConfigService::class,
                ConfigService::class,
				false,
            ],
            [
                DoctrineService::class,
                DoctrineService::class,
				false,
            ],
            ] as [ $id, $class, $isAbstract ]
        ) {
            $container
                ->setDefinition(
                    $id,
                    (new Definition($class))
                        ->setAutowired(true)
                        ->setAbstract($isAbstract)
					,
                )
            ;
        }

        foreach (
            [
            [
                StringService::class,
                [
                    '$gsServiceYearRegex' => $container->getParameter(
                        ServiceContainer::getParameterName(
                            self::PREFIX,
                            self::YEAR_REGEX_KEY,
                        ),
                    ),
                    '$gsServiceYearRegexFull' => $container->getParameter(
                        ServiceContainer::getParameterName(
                            self::PREFIX,
                            self::YEAR_REGEX_FULL_KEY,
                        ),
                    ),
                    '$gsServiceIpV4Regex' => $container->getParameter(
                        ServiceContainer::getParameterName(
                            self::PREFIX,
                            self::IP_V_4_REGEX_KEY,
                        ),
                    ),
                    '$gsServiceSlashOfIpRegex' => $container->getParameter(
                        ServiceContainer::getParameterName(
                            self::PREFIX,
                            self::SLASH_OF_IP_REGEX_KEY,
                        ),
                    ),
                ],
            ],
            [
                ConfigService::class,
                [
                    '$gsServiceProjectDir' => $container->getParameter('kernel.project_dir'),
                    '$gsServicePackageFilenames' => $container->getParameter(
                        ServiceContainer::getParameterName(self::PREFIX, ConfigService::CONFIG_SERVICE_KEY),
                    ),
                ],
            ],
            [
                FilesystemService::class,
                [
                    '$gsServiceLocalDriveForTest' => $container->getParameter(
                        ServiceContainer::getParameterName(self::PREFIX, self::LOCAL_DRIVE_FOR_TEST),
                    ),
                    '$gsServiceAppEnv' => $container->getParameter(
                        ServiceContainer::getParameterName(self::PREFIX, self::APP_ENV),
                    ),
                    '$gsServiceCarbonFactoryImmutable' => $container->getDefinition(
                        ServiceContainer::getParameterName(self::PREFIX, self::CARBON_FACTORY_SERVICE_KEY),
                    ),
                ],
            ],
            [
                CarbonService::class,
                [
                    '$gsServiceCarbonFactoryImmutable' => $container->getDefinition(
                        ServiceContainer::getParameterName(self::PREFIX, self::CARBON_FACTORY_SERVICE_KEY),
                    ),
                ],
            ],
            ] as [ $id, $args ]
        ) {
            if ($container->hasDefinition($id)) {
				$container
                    ->getDefinition($id)
                    ->setArguments($args)
                ;
            }
        }

        //###>
		foreach([
			[
				OSService::class,
			],
		] as [ $id ]) {
			if ($container->hasDefinition($id)) {
				$container
					->getDefinition($id)
					->setShared(false)
				;
			}			
		}
    }
    
    private function carbonDefinition(
        array $config,
        ContainerBuilder $container,
    ): void {
        $carbon = new Definition(
            class: \Carbon\FactoryImmutable::class,
            arguments: [
                '$settings'         => [
                    'locale'                    => $this->localeParameter,
                    'strictMode'                => true,
                    'timezone'                  => $this->timezoneParameter,
                    'toStringFormat'            => 'd.m.Y H:i:s P',
                    'monthOverflow'             => true,
                    'yearOverflow'              => true,
                ],
            ],
        );
        $container->setDefinition(
            id: ServiceContainer::getParameterName(self::PREFIX, self::CARBON_FACTORY_SERVICE_KEY),
            definition: $carbon,
        );
    }

    private function fakerDefinition(
        array $config,
        ContainerBuilder $container,
    ): void {
        $faker = (new Definition(\Faker\Factory::class, []))
            ->setFactory([\Faker\Factory::class, 'create'])
            ->setArgument(0, $this->localeParameter)
        ;
        //\dd($config['locale']);
        $faker = $container->setDefinition(
            id: ServiceContainer::getParameterName(self::PREFIX, self::FAKER_SERVICE_KEY),
            definition: $faker,
        );
    }

    private function setContainerDefinitions(
        array $config,
        ContainerBuilder $container,
    ) {
        $this->setParameterObjects();
        $this->carbonDefinition(
            $config,
            $container,
        );
        $this->fakerDefinition(
            $config,
            $container,
        );
        $this->setRestContainerDefinitions(
            $container,
        );
    }

    private function setContainerTags(ContainerBuilder $container)
    {
        /*
        $container
            ->registerForAutoconfiguration(\GS\Service\<>Interface::class)
            ->addTag(GSTag::<>)
        ;
        */
    }

    private function setParameterObjects(): void
    {
        /* to use in this object */

        $this->localeParameter = new Parameter(ServiceContainer::getParameterName(
            self::PREFIX,
            self::LOCALE,
        ));
        $this->timezoneParameter = new Parameter(ServiceContainer::getParameterName(
            self::PREFIX,
            self::TIMEZONE,
        ));
    }
}
