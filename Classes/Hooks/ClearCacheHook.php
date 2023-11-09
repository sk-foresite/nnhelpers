<?php

namespace Nng\Nnhelpers\Hooks;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Form\AbstractNode;

/**
 * Hook, der beim Löschen des Cache aufgerufen wird.
 * 
 */
class ClearCacheHook {

	/**
	 * Löscht alle `nnhelpers`-Caches.
	 * 
	 * Wird vom Core beim Klick auf den Blitz im Backend aufgerufen.
	 * Registriert in der `ext_localconf.php` von nnhelpers.
	 * 
	 * Abhängig von den Einstellungen im Extension-Manager werden:
	 * - nur die nnhelpers-Caches gelöscht
	 * - oder ALLE Caches aller Extensions gelöscht
	 * 
	 * @return void
	 */
	public function postProcessClearCache() {
		$extConf = \nn\t3::Settings()->getExtConf('nnhelpers');
		$clearAllCaches = $extConf['clearAllCaches'] ?? false;
		\nn\t3::Cache()->clear( $clearAllCaches ? null : 'nnhelpers' );
	}

}