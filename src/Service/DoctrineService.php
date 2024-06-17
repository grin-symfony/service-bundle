<?php

namespace GS\Service\Service;

use Doctrine\ORM\UnitOfWork;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use GS\Service\GSServiceExtension;

class DoctrineService
{
	private static ?\Closure $getStateNotFoundErrorMessage = null;
	
    public function __construct(
        protected readonly TranslatorInterface $t,
	) {
		static::initGetStateNotFoundErrorMessage($t);
    }


    //###> STATIC API ###

    /*
		Entity state name
	*/
    public static function getStateName(
       TranslatorInterface $t,
       EntityManagerInterface $em,
	   object $entity,
    ): string {
		static::initGetStateNotFoundErrorMessage($t);
		
        return match($state = $em->getUnitOfWork()->getEntityState($entity)) {
			UnitOfWork::STATE_MANAGED => 'MANAGED',
			UnitOfWork::STATE_REMOVED => 'REMOVED',
			UnitOfWork::STATE_DETACHED => 'DETACHED',
			UnitOfWork::STATE_NEW => 'NEW',
			default => (static::$getStateNotFoundErrorMessage)($state),
		};
    }

    //###< STATIC API ###
	
	
    //###> OVERRIDE ###
	
	protected static function getStateNotFoundErrorMessage(): string {
		return GSServiceExtension::PREFIX . '.error.doctrine.not_found.entity_state';
	}
	
	protected static function addStateNotFoundErrorMessageParameters(): array {
		return [];
	}
	
    //###< OVERRIDE ###
	
	
	//###> HELPER ###
	
	private static function initGetStateNotFoundErrorMessage(
		TranslatorInterface $t,
	): void {
		if (static::$getStateNotFoundErrorMessage !== null) return;
		
		$message ??= static fn(int $state) => $t->trans(
			static::getStateNotFoundErrorMessage(),
			[
				'%state%' => $state,
				...static::addStateNotFoundErrorMessageParameters(),
			],
		);
		static::$getStateNotFoundErrorMessage = $message;
	}
	
	//###< HELPER ###
}
