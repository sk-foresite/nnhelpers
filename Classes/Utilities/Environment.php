<?php

namespace Nng\Nnhelpers\Utilities;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use Psr\Http\Message\ServerRequestInterface;

use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Site\SiteFinder;

use TYPO3\CMS\Core\Core\ClassLoadingInformation;

/**
 * Alles, was man über die Umgebung der Anwendung wissen muss.
 * Von Sprach-ID des Users, der baseUrl bis zu der Frage, welche Extensions am Start sind.
 */
class Environment implements SingletonInterface {
   
	/**
	 * @var boolean
	 */
	public $_isFrontend = null;

	/**
	 * Das aktuelle `Site` Object holen.
	 * Über dieses Object kann z.B. ab TYPO3 9 auf die Konfiguration aus der site YAML-Datei zugegriffen werden.
	 *  
	 * Im Kontext einer MiddleWare ist evtl. die `site` noch nicht geparsed / geladen.
	 * In diesem Fall kann der `$request` aus der MiddleWare übergeben werden, um die Site zu ermitteln. 
	 * 
	 * Siehe auch `\nn\t3::Settings()->getSiteConfig()`, um die site-Konfiguration auszulesen.
	 * 
	 * ```
	 * \nn\t3::Environment()->getSite();
	 * \nn\t3::Environment()->getSite( $request );
	 * 
	 * \nn\t3::Environment()->getSite()->getConfiguration();
	 * \nn\t3::Environment()->getSite()->getIdentifier();
	 * ```
	 * @return \TYPO3\CMS\Core\Site\Entity\Site
	 */
	public function getSite ( $request = null ) {

		$request = $request ?: $GLOBALS['TYPO3_REQUEST'] ?? false;

		if (!$request) return [];
		$site = $request->getAttribute('site');

		if (!$site || is_a($site, \TYPO3\CMS\Core\Site\Entity\NullSite::class)) {
			$matcher = GeneralUtility::makeInstance( SiteMatcher::class );
			$routeResult = $matcher->matchRequest($request);
			$site = $routeResult->getSite();	
		}

		return $site;
	}
	
	/**
	 * 	Die aktuelle Sprache (als Zahl) des Frontends holen.
	 *	```
	 *	\nn\t3::Environment()->getLanguage();
	 *	```
	 * 	@return int
	 */
	public function getLanguage () {
		$languageAspect = GeneralUtility::makeInstance(Context::class)->getAspect('language');
		return $languageAspect->getId();
	}

	/**
	 * 	Die aktuelle Sprache (als Kürzel wie "de") im Frontend holen
	 *	```
	 *	\nn\t3::Environment()->getLanguageKey();
	 *	```
	 * 	@return string
	 */
	public function getLanguageKey () {
		if ($GLOBALS['TYPO3_REQUEST'] instanceof ServerRequestInterface) {
			$data = $GLOBALS['TYPO3_REQUEST']->getAttribute('language', null);
			return $data->getTwoLetterIsoCode();
		}
		return '';
	}

	/**
	 * Gibt eine Liste aller definierten Sprachen zurück. 
	 * Die Sprachen müssen in der YAML site configuration festgelegt sein.
	 * 
	 * ```
	 * // [['title'=>'German', 'typo3Language'=>'de', ....], ['title'=>'English', 'typo3Language'=>'en', ...]]
	 * \nn\t3::Environment()->getLanguages();
	 * 
	 * // ['de'=>['title'=>'German', 'typo3Language'=>'de'], 'en'=>['title'=>'English', 'typo3Language'=>'en', ...]]
	 * \nn\t3::Environment()->getLanguages('iso-639-1');
	 * 
	 * // ['de'=>0, 'en'=>1]
	 * \nn\t3::Environment()->getLanguages('typo3Language', 'languageId');
	 * 
	 * // [0=>'de', 1=>'en']
	 * \nn\t3::Environment()->getLanguages('languageId', 'typo3Language');
	 * ```
	 * @param string $key
	 * @param string $value
	 * @return string|array
	 */
	public function getLanguages( $key = 'languageId', $value = null ) {
		$languages = \nn\t3::Settings()->getSiteConfig()['languages'] ?? [];
		if (!$value) {
			return array_combine( array_column($languages, $key), array_values($languages) );
		}
		return array_combine( array_column($languages, $key), array_column($languages, $value) );
	}

	/**
	 * Gibt die Standard-Sprache (Default Language) zurück. Bei TYPO3 ist das immer die Sprache mit der ID `0`.
	 * Die Sprachen müssen in der YAML site configuration festgelegt sein.
	 * 
	 * ```
	 * // 'de'
	 * \nn\t3::Environment()->getDefaultLanguage();
	 * 
	 * // 'de-DE'
	 * \nn\t3::Environment()->getDefaultLanguage('hreflang');
	 * 
	 * // ['title'=>'German', 'typo3Language'=>'de', ...]
	 * \nn\t3::Environment()->getDefaultLanguage( true );
	 * ```
	 * @param string|boolean $returnKey
	 * @return string|array
	 */
	public function getDefaultLanguage( $returnKey = 'typo3Language' ) {
		$firstLanguage = $this->getLanguages('languageId')[0] ?? [];
		if ($returnKey === true) return $firstLanguage;
		return $firstLanguage[$returnKey] ?? '';
	}

	/**
	 * Gibt eine Liste der Sprachen zurück, die verwendet werden sollen, falls
	 * z.B. eine Seite oder ein Element nicht in der gewünschten Sprache existiert.
	 * 
	 * Wichtig: Die Fallback-Chain enthält an erster Stelle die aktuelle bzw. in $langUid
	 * übergebene Sprache.
	 * 
	 * ```
	 * // Einstellungen für aktuelle Sprache verwenden (s. Site-Config YAML)
	 * \nn\t3::Environment()->getLanguageFallbackChain();	// --> z.B. [0] oder [1,0]
	 * 
	 * // Einstellungen für eine bestimmte Sprache holen
	 * \nn\t3::Environment()->getLanguageFallbackChain( 1 );	
	 * // --> [1,0] - falls Fallback in Site-Config definiert wurde und der fallbackMode auf "fallback" steht
	 * // --> [1] - falls es keinen Fallback gibt oder der fallbackMode auf "strict" steht
	 * ```
	 * @param string|boolean $returnKey
	 * @return string|array
	 */
	public function getLanguageFallbackChain( $langUid = true ) 
	{
		if ($langUid === true) {
			$langUid = $this->getLanguage();
		}

		$langSettings = $this->getLanguages()[$langUid] ?? [];
		$fallbackType = $langSettings['fallbackType'] ?? 'strict';
		$fallbackChain = $langSettings['fallbacks'] ?? '';

		if ($fallbackType == 'strict') {
			$fallbackChain = '';
		}

		$fallbackChainArray = array_map( function ( $uid ) { 
			return intval( $uid );
		}, \nn\t3::Arrays($fallbackChain)->intExplode() );
		array_unshift( $fallbackChainArray, $langUid );

		return $fallbackChainArray;
	}

	/**
	 *  Gibt die baseUrl (`config.baseURL`) zurück, inkl. http(s) Protokoll z.B. https://www.webseite.de/
	 *	```
	 *	\nn\t3::Environment()->getBaseURL();
	 *	```
	 * 	@return string
	 */
	public function getBaseURL () {
		if ($baseUrl = $GLOBALS['TSFE']->config['config']['baseURL'] ?? false) return $baseUrl;
		$setup = \nn\t3::Settings()->getFullTyposcript();
		if ($baseUrl = $setup['config']['baseURL'] ?? false) return $baseUrl;
		$host = $_SERVER['HTTP_HOST'] ?? '';
		$server = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://{$host}/";
		return $server;
	}

	/**
	 * 	Die Domain holen z.B. www.webseite.de
	 *	```
	 *	\nn\t3::Environment()->getDomain();
	 *	```
	 * 	@return string
	 */
	public function getDomain () {
		$domain = preg_replace('/(http)([s]*)(:)\/\//i', '', $this->getBaseURL());
		return rtrim($domain, '/');
	}
	
	/**
	 * 	Prüft, ob Installation auf lokalem Server läuft
	 *	```
	 *	\nn\t3::Environment()->isLocalhost()
	 *	```
	 * 	@return boolean
	 */
	public function isLocalhost () {
		$localhost = ['127.0.0.1', '::1'];
		return in_array($_SERVER['REMOTE_ADDR'], $localhost);
	}

	/**
	 * Configuration aus `ext_conf_template.txt` holen (Backend, Extension Configuration)
	 * ```
	 * \nn\t3::Environment()->getExtConf('nnhelpers', 'varname');
	 * ```
	 * Existiert auch als ViewHelper:
	 * ```
	 * {nnt3:ts.extConf(path:'nnhelper')}
	 * {nnt3:ts.extConf(path:'nnhelper.varname')}
	 * {nnt3:ts.extConf(path:'nnhelper', key:'varname')}
	 * ```	
	 * @return mixed
	 */
	public function getExtConf ( $ext = 'nnhelpers', $param = '' ) {
		$extConfig = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][$ext] ?? [];
		return $param ? ($extConfig[$param] ?? '') : $extConfig;
	}

	/**
	 * 	Konfiguration aus der `LocalConfiguration.php` holen
	 *	```
	 *	\nn\t3::Environment()->getLocalConf('FE.cookieName');
	 *	```
	 * 	@return string
	 */
	public function getLocalConf ( $path = '' ) {
		if (!$path) return $GLOBALS['TYPO3_CONF_VARS'];
		return \nn\t3::Settings()->getFromPath( $path, $GLOBALS['TYPO3_CONF_VARS'] ) ?: '';
	}

	/**
	 * 	Die Cookie-Domain holen z.B. www.webseite.de
	 *	```
	 *	\nn\t3::Environment()->getCookieDomain()
	 *	```
	 * 	@return string
	 */
	public function getCookieDomain ( $loginType = 'FE' ) {
		$cookieDomain = $this->getLocalConf( $loginType . '.cookieDomain' ) 
			?: $this->getLocalConf( 'SYS.cookieDomain' );
		return $cookieDomain;
	}

	/**
	 * Liste der PSR4 Prefixes zurückgeben.
	 * 
	 * Das ist ein Array mit allen Ordnern, die beim autoloading / Bootstrap von TYPO3 nach Klassen 
	 * geparsed werden müssen. In einer TYPO3 Extension ist das per default der Ordern `Classes/*`.
	 * Die Liste wird von Composer/TYPO3 generiert.
	 * 
	 * Zurückgegeben wird ein array. Key ist `Vendor\Namespace\`, Wert ist ein Array mit Pfaden zu den Ordnern, 
	 * die rekursiv nach Klassen durchsucht werden. Es spielt dabei keine Rolle, ob TYPO3 im composer
	 * mode läuft oder nicht.
	 * 
	 * ```
	 * \nn\t3::Environment()->getPsr4Prefixes();
	 * ```
	 * 
	 * Beispiel für Rückgabe:
	 * ```
	 * [
	 * 	'Nng\Nnhelpers\' => ['/pfad/zu/composer/../../public/typo3conf/ext/nnhelpers/Classes', ...],
	 * 	'Nng\Nnrestapi\' => ['/pfad/zu/composer/../../public/typo3conf/ext/nnrestapi/Classes', ...]
	 * ]
	 * ```
	 * @return array
	 */
	public function getPsr4Prefixes() {
		$composerClassLoader = ClassLoadingInformation::getClassLoader();
		$psr4prefixes = $composerClassLoader->getPrefixesPsr4();
		return $psr4prefixes;
	}

	/**
	 * Absoluten Pfad zu dem `/var`-Verzeichnis von Typo3 holen.
	 * 
	 * Dieses Verzeichnis speichert temporäre Cache-Dateien.
	 * Je nach Version von Typo3 und Installationstyp (Composer oder Non-Composer mode)
	 * ist dieses Verzeichnis an unterschiedlichen Orten zu finden.
	 * 
	 * ```
	 * // /full/path/to/typo3temp/var/
	 * $path = \nn\t3::Environment()->getVarPath();
	 * ```
	 */
	public function getVarPath() {
		return rtrim(\TYPO3\CMS\Core\Core\Environment::getVarPath(), '/').'/';
	}

	/**
	 * 	Absoluten Pfad zum Typo3-Root-Verzeichnis holen. z.B. `/var/www/website/`
	 *	```
	 *	\nn\t3::Environment()->getPathSite()
	 *	```
	 * 	früher: `PATH_site`
	 */
	public function getPathSite () {
		return \TYPO3\CMS\Core\Core\Environment::getPublicPath().'/';
	}
	
	/**
	 * 	Relativen Pfad zum Typo3-Root-Verzeichnis holen. z.B. `../`
	 *	```
	 *	\nn\t3::Environment()->getRelPathSite()
	 *	```
	 * 	@return string
	 */
	public function getRelPathSite () {
		return \nn\t3::File()->relPath();
	}
		
	/**
	 * 	absoluten Pfad zu einer Extension holen 
	 * 	z.B. `/var/www/website/ext/nnsite/`
	 *	```
	 *	\nn\t3::Environment()->extPath('extname');
	 *	```
	 * 	@return string
	 */
	public function extPath ( $extName = '' ) {
		return ExtensionManagementUtility::extPath( $extName );
	}

	/**
	 * 	relativen Pfad (vom aktuellen Script aus) zu einer Extension holen 
	 * 	z.B. `../typo3conf/ext/nnsite/`
	 *	```
	 *	\nn\t3::Environment()->extRelPath('extname');
	 *	```
	 *	@return string
	 */
	public function extRelPath ( $extName = '' ) {
		return PathUtility::getRelativePathTo( $this->extPath($extName) );
	}
	
	/**
	 * 	Prüfen, ob Extension geladen ist.
	 *	```
	 *	\nn\t3::Environment()->extLoaded('news');
	 *	```
	 */
	public function extLoaded ( $extName = '' ) {
		return ExtensionManagementUtility::isLoaded( $extName );
	}
	
	/**
	 * 	Prüfen, ob wir uns im Frontend-Context befinden
	 *	```
	 * 	\nn\t3::Environment()->isFrontend();
	 *	```
	 * 	@return bool
	 */
	public function isFrontend () {
		if ($this->_isFrontend !== null) return $this->_isFrontend;
		if (($GLOBALS['TYPO3_REQUEST'] ?? null) instanceof ServerRequestInterface
    		&& ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isFrontend()
		) {
			return $this->_isFrontend = true;
		}
		return $this->_isFrontend = false;
	}
	
	/**
	 * 	Prüfen, ob wir uns im Backend-Context befinden
	 *	```
	 * 	\nn\t3::Environment()->isBackend();
	 *	```
	 * 	@return bool
	 */
	public function isBackend () {
		return !$this->isFrontend();
	}
	
	/**
	 * Die Version von Typo3 holen, als Ganzzahl, z.b "8"
	 * Alias zu `\nn\t3::t3Version()`
	 * ```
	 * \nn\t3::Environment()->t3Version();
	 * 
	 * if (\nn\t3::t3Version() >= 8) {
     * 	// nur für >= Typo3 8 LTS
	 * }
	 * ```
	 * 	@return int
	 */
	public function t3Version () {
		return \nn\t3::t3Version();
	}

	/**
	 * Alle im System verfügbaren Ländern holen
	 * ```
	 * \nn\t3::Environment()->getCountries();
	 * ```
	 * @return array
	 */
	public function getCountries ( $lang = 'de', $key = 'cn_iso_2' ) {
		if (!ExtensionManagementUtility::isLoaded('static_info_tables')) return [];
/*
		$languageAspect = GeneralUtility::makeInstance(Context::class)->getAspect('language');
		debug( $GLOBALS['TYPO3_REQUEST']->getAttribute('language') );
*/
		$data = \nn\t3::Db()->findAll( 'static_countries' );
		return \nn\t3::Arrays($data)->key($key)->pluck('cn_short_'.$lang)->toArray();
	}

	/**
	 * 	Ein Land aus der Tabelle `static_countries` 
	 *	anhand seines Ländercodes (z.B. `DE`) holen
	 *	```
	 *	\nn\t3::Environment()->getCountryByIsocode( 'DE' );
	 *	\nn\t3::Environment()->getCountryByIsocode( 'DEU', 'cn_iso_3' );
	 *	```
	 * 	@return array
	 */
	public function getCountryByIsocode ( $cn_iso_2 = null, $field = 'cn_iso_2' ) {
		if (!ExtensionManagementUtility::isLoaded('static_info_tables')) return [];
		$data = \nn\t3::Db()->findByValues( 'static_countries', [$field=>$cn_iso_2] );
		return $data ? array_pop($data) : [];
	}

	/**
	 * Maximale Upload-Größe für Dateien aus dem Frontend zurückgeben.
	 * Diese Angabe ist der Wert, der in der php.ini festgelegt wurde und ggf.
	 * über die .htaccess überschrieben wurde.
	 * ```
	 * \nn\t3::Environment()->getPostMaxSize();  // z.B. '1048576' bei 1MB
	 * ```
	 * @return integer
	 */
	public function getPostMaxSize() {
		$postMaxSize = ini_get('post_max_size');
		return \nn\t3::Convert($postMaxSize)->toBytes();
	}
}
