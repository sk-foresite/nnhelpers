<?php

namespace Nng\Nnhelpers\EventListener;

use \TYPO3\CMS\Core\Cache\Event\CacheFlushEvent;

/**
 * Wird aufgerufen, wenn über die Kommandzeile der Cache gelöscht wird.
 * Registriert über `Configuration/Service.yaml` und ausgelöst durch: 
 * `vendor/bin/typo3 cache:flush`
 * 
 * Wichtig, damit Extensions wie `nnrestapi` ihren Cache neu aufbauen.
 * Siehe: https://bit.ly/3vjzkzN
 * 
 */
class ClearCacheEvent
{
	public function __invoke( CacheFlushEvent $event ): void {}

	public function handleEvent() 
	{
		\nn\t3::Cache()->clear();
	}
}