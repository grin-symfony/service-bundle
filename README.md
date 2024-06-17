green-symfony/service-bundle
========

# Description


This bundle provides ready to use services:
| Service id |
| ------------- |
| gs_service.faker |
| gs_service.carbon_factory_immutable |
| [GS\Service\Service\ArrayService](https://github.com/green-symfony/service-bundle/blob/main/src/Service/ArrayService.php) |
| [GS\Service\Service\BoolService](https://github.com/green-symfony/service-bundle/blob/main/src/Service/BoolService.php) |
| [GS\Service\Service\BufferService](https://github.com/green-symfony/service-bundle/blob/main/src/Service/BufferService.php) |
| [GS\Service\Service\CarbonService](https://github.com/green-symfony/service-bundle/blob/main/src/Service/CarbonService.php) |
| [GS\Service\Service\ClipService](https://github.com/green-symfony/service-bundle/blob/main/src/Service/ClipService.php) |
| [GS\Service\Service\ConfigService](https://github.com/green-symfony/service-bundle/blob/main/src/Service/ConfigService.php) |
| [GS\Service\Service\DumpInfoService](https://github.com/green-symfony/service-bundle/blob/main/src/Service/DumpInfoService.php) |
| [GS\Service\Service\FilesystemService](https://github.com/green-symfony/service-bundle/blob/main/src/Service/FilesystemService.php) |
| [GS\Service\Service\HtmlService](https://github.com/green-symfony/service-bundle/blob/main/src/Service/HtmlService.php) |
| [GS\Service\Service\OSService](https://github.com/green-symfony/service-bundle/blob/main/src/Service/OSService.php) |
| [GS\Service\Service\ParserService](https://github.com/green-symfony/service-bundle/blob/main/src/Service/ParserService.php) |
| [GS\Service\Service\RandomPasswordService](https://github.com/green-symfony/service-bundle/blob/main/src/Service/RandomPasswordService.php) |
| [GS\Service\Service\RegexService](https://github.com/green-symfony/service-bundle/blob/main/src/Service/RegexService.php) |
| [GS\Service\Service\StringService](https://github.com/green-symfony/service-bundle/blob/main/src/Service/StringService.php) |
| [GS\Service\Service\DoctrineService](https://github.com/green-symfony/service-bundle/blob/main/src/Service/DoctrineService.php) |

# Installation

### Step 1: Require the bundle

In your `%kernel.project_dir%/composer.json`

```json
"require": {
	"green-symfony/service-bundle": "VERSION"
},
"repositories": [
	{
		"type": "path",
		"url": "./bundles/green-symfony/service-bundle"
	}
]
```

### Step 2: Download the bundle

### [Before git clone](https://github.com/green-symfony/docs/blob/main/docs/bundles_green_symfony%20mkdir.md)

```console
git clone "https://github.com/green-symfony/service-bundle.git"
```

```console
cd "../../"
```

```console
composer require "green-symfony/service-bundle"
```

### [Binds](https://github.com/green-symfony/docs/blob/main/docs/borrow-services.yaml-section.md)

### Step 3: Usage

**Symfony Autowiring**

These services are already available for using:

```php
namespace YourNamespace;

use GS\Service\Service\StringService;

class YourClass {
	public function __construct(
		private readonly StringService $stringService,
	) {}

	public function yourMethod() {
		return $this->stringService->SOME_METHOD();
	}
}
```

**php extending + Symfony Autowiring**

```php
//###> YOUR FILE #1 ###

namespace App\Service;

use GS\Service\Service\StringService as GSStringService;

class StringService extend GSStringService {}


//###> YOUR FILE #2 ###

use App\Service\StringService;

class YourClass {
	public function __construct(
		private readonly StringService $stringService,
	) {}

	public function yourMethod() {
		return $this->stringService->SOME_METHOD();
	}
}
```

**Or bind gs_service services**

```yaml
parameters:

services:
    _defaults:
        autowire: true
        autoconfigure: true # Automatically registers your services as commands, event subscribers, etc.
    
        bind:
            ###> SERVICES ###
            
            $t:                 '@Symfony\Contracts\Translation\TranslatorInterface'
            
            ###>gs_service ###
            
            # ___FOUND THEM BY EXECUTING___: php.exe ./bin/console debug:container | grep gs_service
            # ___OR FOR LINUX CLI___: bin/console debug:container | grep gs_service
            
            $carbonFactoryImmutable: '@gs_service.carbon_factory_immutable'
            $faker: '@gs_service.faker'
            ###< gs_service ###
            
            ###< SERVICES ###
```

```php
//###> YOUR FILE ###

use Symfony\Component\Routing\Attribute\Route;

class YourController {

	#[Route(path: '/')]
	public function home(
		$faker, // BIND AUTOWIRING for @gs_service.faker!
	) {
		return $this->render('home/home.html.twig', [
			'random_number' => $faker->numberBetween(0, 1000),
		]);
	}
}
```

### Step 4: Override bundle parameters and configure the bundle

Open terminal in your project `%kernel.project_dir%` and execute:

```console
cp "./bundles/green-symfony/service-bundle/config/packages/gs_service.yaml" "./config/packages/gs_service.yaml"
```

Here `%kernel.project_dir%/config/packages/gs_service.yaml`, you can override any parameter.

Also you can override parameters in your `%kernel.project_dir%/config/services.yaml`

```yaml
parameters:

    # ___FOUND THEM BY EXECUTING___: php.exe ./bin/console debug:container --parameters | grep gs_service
    # ___OR FOR LINUX CLI___: bin/console debug:container --parameters | grep gs_service

    ###> GS\Service ###
    gs_service.locale:                         '%gs_service.locale%'
    gs_service.timezone:                       '%gs_service.timezone%'
    gs_service.app_env:                        '%gs_service.app_env%'
    gs_service.local_drive_for_test:           '%gs_service.local_drive_for_test%'
    gs_service.year_regex:                     '%gs_service.year_regex%'
    gs_service.year_regex_full:                '%gs_service.year_regex_full%'
    gs_service.ip_v4_regex:                    '%gs_service.ip_v4_regex%'
    gs_service.slash_of_ip_regex:              '%gs_service.slash_of_ip_regex%'
    gs_service.start_of_win_sys_file_regex:    '%gs_service.start_of_win_sys_file_regex%'
    
    
    # To get with \GS\Service\Service\ConfigService::getPackageValue(without arguments)
    # the following result:
    #    array:2 [
    #       "config/packages/framework.yaml" => array:2 []
    #       "config/packages/gs_service.yaml" => []
    #   ]
    gs_service.load_packs_configs:
        -   pack_name:      'framework.yaml'
            pack_rel_path:  'config/packages'
            lazy_load:      false
        -   pack_name:      'gs_service.yaml'
            pack_rel_path:  'config/packages'
            does_not_exist_mess: "This package does't exist!"
        -   pack_name:      'cache.yaml'
            pack_rel_path:  'config/packages'
            lazy_load:      true
    ###< GS\Service ###
```

But remember, `services.yaml` parameters wins `gs_service.yaml` ones.