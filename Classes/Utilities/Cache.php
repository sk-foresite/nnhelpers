<?php

namespace Nng\Nnhelpers\Utilities;

use TYPO3\CMS\Core\SingletonInterface;

/**
 * 	Methoden, zum Lesen und Schreiben in den Typo3 Cache.
 * 	Nutzt das Caching-Framework von Typo3, siehe `EXT:nnhelpers/ext_localconf.php` für Details
 */
class Cache implements SingletonInterface {
	
	/**
	 * @var \TYPO3\CMS\Core\Cache\CacheManager
	 */
	protected $cacheManager;

	/**
	 * 	Injections
	 * 
	 */
	public function __construct( \TYPO3\CMS\Core\Cache\CacheManager $cacheManager ) {
		$this->cacheManager = $cacheManager;
	}

	/**
	 * 	Lädt Inhalt des Typo3-Caches anhand eines Identifiers.
	 * 	Der Identifier ist ein beliebiger String oder ein Array, der den Cache eindeutif Identifiziert.
	 * 	```
	 *	\nn\t3::Cache()->get('myid');
	 *	\nn\t3::Cache()->get(['pid'=>1, 'uid'=>'7']);
	 *	\nn\t3::Cache()->get(['func'=>__METHOD__, 'uid'=>'17']);
	 *	\nn\t3::Cache()->get([__METHOD__=>$this->request->getArguments()]);
	 * 	```
	 * 
	 *  @param mixed $identifier	String oder Array zum Identifizieren des Cache
	 *  @param mixed $useRamCache	temporärer Cache in $GLOBALS statt Caching-Framework
	 * 
	 *  @return mixed
	 */
	public function get( $identifier = '', $useRamCache = false ) {
		$identifier = self::getIdentifier( $identifier );

		// Ram-Cache verwenden? Einfache globale.
		if ($useRamCache && ($cache = $GLOBALS['nnhelpers_cache_'.$identifier] ?? false)) {
			return $cache;
		}
		
		$cacheUtility = $this->cacheManager->getCache('nnhelpers');
		if ($data = $cacheUtility->get($identifier)) {
			$data = json_decode( $cacheUtility->get($identifier), true );
			if ($data['content'] && $data['expires'] < time()) return false;
			return $data['content'] ?: false;
		}
		return false;
	}

	/**
	 * Schreibt einen Eintrag in den Typo3-Cache.
	 * Der Identifier ist ein beliebiger String oder ein Array, der den Cache eindeutif Identifiziert.
	 * ```
	 * // Klassische Anwendung im Controller: Cache holen und setzen
	 * if ($cache = \nn\t3::Cache()->get('myid')) return $cache;
	 * ...
	 * $cache = $this->view->render();
	 * return \nn\t3::Cache()->set('myid', $cache);
	 * ```
	 * 
	 * ```
	 * // RAM-Cache verwenden? TRUE als dritter Parameter setzen
	 * \nn\t3::Cache()->set('myid', $dataToCache, true);
	 * 
	 * // Dauer des Cache auf 60 Minuten festlegen
	 * \nn\t3::Cache()->set('myid', $dataToCache, 3600);
	 * 
	 * // Als key kann auch ein Array angegeben werden
	 * \nn\t3::Cache()->set(['pid'=>1, 'uid'=>'7'], $html);
	 * ````
	 *  @param mixed $indentifier	String oder Array zum Identifizieren des Cache
	 *  @param mixed $data			Daten, die in den Cache geschrieben werden sollen. (String oder Array)
	 *	@param mixed $useRamCache	`true`: temporärer Cache in $GLOBALS statt Caching-Framework. 
	 * 								`integer`: Wie viele Sekunden cachen?
	 *
	 *  @return mixed
	 */
	public function set( $identifier = '', $data = null, $useRamCache = false ) {
		$identifier = self::getIdentifier( $identifier );
		$lifetime = 86400;
		
		if ($useRamCache === true) {
			return $GLOBALS['nnhelpers_cache_'.$identifier] = $data;
		} else if ( $useRamCache !== false ) {
			$lifetime = intval($useRamCache);
		}

		$expires = time() + $lifetime;

		$cacheUtility = $this->cacheManager->getCache('nnhelpers');
		$serializedData = json_encode(['content'=>$data, 'expires'=>$expires]);
		$cacheUtility->set($identifier, $serializedData, [], $lifetime);
		return $data;
	}

	/**
	 * 	Wandelt übergebenen Cache-Identifier in brauchbaren String um.
	 * 	Kann auch ein Array als Identifier verarbeiten.
	 * 
	 *  @param mixed $indentifier
	 *  @return string
	 */
	public static function getIdentifier( $identifier = null ) {
		if (is_array($identifier)) {
			$identifier = json_encode($identifier);
		}
		return md5($identifier);
	}
	
	/**
	 * 	Löscht den Seiten-Cache. Alias zu `\nn\t3::Page()->clearCache()`
	 * 	```
	 *	\nn\t3::Cache()->clearPageCache( 17 );		// Seiten-Cache für pid=17 löschen
	 *	\nn\t3::Cache()->clearPageCache();			// Cache ALLER Seiten löschen
	 *	```
	 *
	 *  @param mixed $pid 	pid der Seite, deren Cache gelöscht werden soll oder leer lassen für alle Seite
	 *  @return void
	 */
	public function clearPageCache( $pid = null ) {
		return \nn\t3::Page()->clearCache( $pid );
	}
	
	/**
	 * 	Löscht den Cache, der per `\nn\t3::Cache()->set()` gesetzt wurde
	 * 	```
	 *	\nn\t3::Cache()->clear();
	 *	```
	 *
	 *  @return void
	 */
	public function clear() {
		$cacheUtility = $this->cacheManager->flushCaches();
	}

	
}