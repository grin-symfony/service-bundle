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
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
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
use Symfony\Component\PropertyAccess\PropertyAccess;
use GS\Service\Service\{
	BoolService,
	StringService
};

/**
	This class allows to get some value from package configuration
	
	USAGE:	
		$valueFromConfig = $this->configService->getPackageValue(
			packName:					'workflow',
			propertyAccessString:		'[framework][workflows][order][initial_marking]',
			packRelPath:				'config/packages',
			...
		);
		
		1) SAVES GOT RESULTS
		
		Return type
			With the help of propertyAccessString it can be a value
*/
class ConfigService
{
	//###> !CHANGE ME! ###
	public const DEFAULT_PACK_EXT				= 'yaml';
	public const DEFAULT_PACK_REL_PATH			= 'config/packages';
	public const DEFAULT_DOES_NOT_EXIST_MESS	= 'gs_service.exception.file_does_not_exist';
	public const DEFAULT_LAZY_LOAD				= false;
	//###> !CHANGE ME! ###
	
	public const CONFIG_SERVICE_KEY		= 'load_packs_configs';
	public const PACK_NAME				= 'pack_name';
	public const PACK_REL_PATH			= 'pack_rel_path';
	public const DOES_NOT_EXIST_MESS	= 'does_not_exist_mess';
	public const LAZY_LOAD				= 'lazy_load';
	
	/*
		[
			$this->getUniqPackId(<$packName>, <$packRelPath>) => [<PACK_CONFIG>],
			...
		]
	*/
	protected array $loadedPackageFilenameData = [];
	
	public function __construct(
		protected readonly BoolService $boolService,
		protected readonly StringService $stringService,
		#[Autowire(param: 'kernel.project_dir')]
		protected readonly string $gsServiceProjectDir,
		/* INTO GSServiceExtension::setParametersFromBundleConfiguration()
			[
				[
					ConfigService::PACK_NAME		=> <>,
					ConfigService::PACK_REL_PATH	=> ?<>,
				],
			]
		*/
		#[Autowire(param: 'gs_service.load_packs_configs')]
		protected array $gsServicePackageFilenames,
		#[Autowire(service: 'Symfony\Contracts\Translation\TranslatorInterface')]
		protected $gsServiceT,
	) {
		$defaultPackageFilenames = [
			static::PACK_REL_PATH => static::DEFAULT_PACK_REL_PATH,
			static::LAZY_LOAD => static::DEFAULT_LAZY_LOAD,
			static::DOES_NOT_EXIST_MESS => static::DEFAULT_DOES_NOT_EXIST_MESS,
		];
		$this->gsServicePackageFilenames = \array_map(
			static fn($el) => [...$defaultPackageFilenames, ...$el],
			$gsServicePackageFilenames,
		);
		
		$this->initPackageFilenameDataByPackageFilenames();
	}
	
	//###> VALIDATE YOUR DATA SET ###
	
	protected function configureConfigOptions(
		string $uniqPackId,
		OptionsResolver $resolver,
		array $inputData,
	): void {
		$resolver
			->setDefaults($inputData)
		;
	}
	
	//###< VALIDATE YOUR DATA SET ###
	
	
	// ###> API ###
	
	public function getPackageValue(
		?string $packName = null,
		?string $propertyAccessString = null,
		?string $packRelPath = null,
	): mixed {
		
		if (\is_null($packName)) {
			return $this->loadedPackageFilenameData;
		}
		
		[
			$filename,
			$packRelPath,
		] = $this->getPackFilenameAndRelPath($packName, $packRelPath);
		
		$this->setPackageFilenameData(
			$filename,
			$packRelPath,
		);
		
		$fromLoaded = $this->getConfigFromLoadedPackageFilenameData(
			$filename,
			$packRelPath,
		);
		
		if (!\is_null($fromLoaded)) {
			return
				\is_null($propertyAccessString)
					? $fromLoaded
					: (PropertyAccess::createPropertyAccessor())->getValue(
						$fromLoaded,
						$propertyAccessString,
					)
				;
		}
		
		return $fromLoaded;
	}

    public function getCurrentIp(): string
    {
        return (string) u(\gethostbyname(\gethostname()))->ensureStart('//');
    }
	
	// ###< API ###
	
	
	// ###> PROTECTED API ###
	
	protected function getUniqPackId(
		string $filename,
		string $packRelPath,
	): string {
		return $this->stringService->getPath(
			$packRelPath,
			$filename,
		);
	}
	
	// ###< PROTECTED API ###
	
	
	// ###> HELPER ###
	
	private function getConfigFromLoadedPackageFilenameData(
		string $filename,
		string $packRelPath,
	): ?array {
		$configFromLoadedConfig = null;
		
		if (empty($this->loadedPackageFilenameData)) return null;
		
		$config = $this->boolService->isGet(
			$this->loadedPackageFilenameData,
			$this->getUniqPackId($filename, $packRelPath),
		);
		if ($config !== null) {
			if (!\is_array($config)) $config = [$config];
			$configFromLoadedConfig = $config;
		}
		
		return $configFromLoadedConfig;
	}
	
	private function getConfigFromPackageFilenames(
		string $filename,
		string $packRelPath,
	): ?array {
		$configFromPackageFilenames = null;
		
		if (empty($this->gsServicePackageFilenames)) return null;
		
		$id = $this->getUniqPackId($filename, $packRelPath);
		
		foreach($this->gsServicePackageFilenames as $packageConfig) {
			[
				self::PACK_NAME		=> $packName,
				self::PACK_REL_PATH	=> $packRelPath,
			] = $packageConfig;
			[
				$filenameFromConfig,
				$packRelPathFromConfig,
			] = $this->getPackFilenameAndRelPath($packName, $packRelPath);
			$idFromConfig = $this->getUniqPackId($filenameFromConfig, $packRelPathFromConfig);
			
			if ($id === $idFromConfig) {
				$configFromPackageFilenames = $packageConfig;
				break;
			}
		}
		unset($packageConfig);
		
		if ($configFromPackageFilenames !== null) {
			if (!\is_array($configFromPackageFilenames)) {
				$configFromPackageFilenames = [$configFromPackageFilenames];
			}
		}
		
		return $configFromPackageFilenames;
	}
	
	private function getCalculatedConfig(
		string $filename,
		string $packRelPath,
	): array {
		
		$this->checkFileExisting(
			$packRelPath,
			$filename,
		);
		
		// Abs path for locator
		$absPath = $this->getConfigurationFilePath(
			$packRelPath,
		);
		
		// abs paths
		$fileLocator = new FileLocator(
			[
				$absPath, // depth: == 0
			]
		);
		
		$resolver = new OptionsResolver();
		
		// Locate and parse Ymal
		$configs = \array_map(
			static fn($path) => Yaml::parseFile(
				Path::canonicalize($path),
				flags: Yaml::PARSE_CUSTOM_TAGS,
			),
			$fileLocator->locate($filename, first: false),
		);

		$config = \array_values(\array_replace_recursive($configs))[0];
		$config ??= [];
		
		$uniqPackId = $this->getUniqPackId($filename, $packRelPath);
		$this->configureConfigOptions(
			$uniqPackId,
			$resolver,
			$config,
		);
		return $resolver->resolve($config);
	}
	
	private function initPackageFilenameDataByPackageFilenames(): void {
		//###> init only
		if (!empty($this->loadedPackageFilenameData)) return;
		
		//###> without it, it's useless
		if (empty($this->gsServicePackageFilenames)) return;
		
		foreach($this->gsServicePackageFilenames as $packageConfig) {
			[
				self::PACK_NAME		=> $packName,
				self::PACK_REL_PATH	=> $packRelPath,
				self::LAZY_LOAD		=> $lazyLoad,
			] = $packageConfig;
			
			if ($lazyLoad == true) continue;
			
			[
				$filename,
				$packRelPath,
			] = $this->getPackFilenameAndRelPath($packName, $packRelPath);
			
			//###> check
			$this->checkFileExisting(
				$packRelPath,
				$filename,
			);
			
			$this->setPackageFilenameData(
				$filename,
				$packRelPath,
			);
		}
	}
	
	private function getPackFilenameAndRelPath(
		string $packName,
		?string $packRelPath = null,
	): array {
		return [
			$this->getFilenameByPackname($packName),
			$this->getPackRelPath($packRelPath),
		];
	}
	
	/* LOADS IT ONCE */
	private function setPackageFilenameData(
		string $filename,
		string $packRelPath,
	): self {
		$a =& $this->loadedPackageFilenameData;
		$k = $this->getUniqPackId($filename, $packRelPath);
		if ($this->boolService->isGet($a, $k) !== null) return $this;
		
		$a[$k] = $this->getCalculatedConfig(
			filename:		$filename,
			packRelPath:	$packRelPath,
		);
		return $this;
	}
	
	private function getExt(
		string $filename,
	): string {
		$defExt = (string) u(static::DEFAULT_PACK_EXT)->ensureStart('.');
		return $this->stringService->getExtFromPath($filename, withDotAtTheBeginning: true, onlyExistingPath: false) ?? $defExt;
	}
	
	private function checkFileExisting(
		string $packRelPath,
		string $filename,
	): void {
		$absPathToFile = $this->getConfigurationFilePath(
			$packRelPath,
			$filename,
		);
		
		$config = $this->getConfigFromPackageFilenames(
			$filename,
			$packRelPath,
		);
		
		$mess = $config[self::DOES_NOT_EXIST_MESS] ?? self::DEFAULT_DOES_NOT_EXIST_MESS;
		
		if (!\is_file($absPathToFile)) {
			throw new \Exception($this->gsServiceT->trans(
				$mess,
				[
					'%path%' => $absPathToFile,
				],
			));
		}
	}
	
	private function getConfigurationFilePath(
		string...$partsAfterProjectDir,
	): string {
		return $this->stringService->getPath(
			$this->gsServiceProjectDir,
			...$partsAfterProjectDir,
		);
	}
	
	private function getFilenameByPackname(
		string $packName,
	): string {
		return (string) u($packName)->ensureEnd($this->getExt($packName));
	}
	
	private function getPackRelPath(
		?string $packRelPath = null,
	): string {
		if (\is_null($packRelPath)) return static::DEFAULT_PACK_REL_PATH;
		
		return $packRelPath;
	}
	
	// ###< HELPER ###
}