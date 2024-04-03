<?php

namespace Nng\Nnhelpers\Utilities;

use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;


/**
 * Methoden, um den Zugriff auf TypoScript Setup, Constanten und PageTsConfig
 * zu vereinfachen.
 */
class Settings extends \Nng\Nnhelpers\Singleton {
	
	/**
	 * @var array
	 */
	protected $typoscriptSetupCache;
	
	/**
	 * @var array
	 */
	protected $typoscriptFullCache;

	/**
     * @var \TYPO3\CMS\Core\TypoScript\ExtendedTemplateService
     */
    protected $typoscriptObjectCache;

	/**
	 * Holt das TypoScript-Setup und dort den Abschnitt "settings".
	 * Werte aus dem FlexForm werden dabei nicht gemerged.
	 * Alias zu `\nn\t3::Settings()->getSettings()`.
	 * 
	 * ```
	 * \nn\t3::Settings()->get( 'nnsite' );
	 * \nn\t3::Settings()->get( 'nnsite', 'path.in.settings' );
	 * ```
	 * @return array
	 */
	public function get( $extensionName = '', $path = '' ) {
		return $this->getSettings( $extensionName, $path );
	}


	/**
	 * Das Setup für ein bestimmtes Plugin holen.
	 * ```
	 * \nn\t3::Settings()->getPlugin('extname') ergibt TypoScript ab plugin.tx_extname...
	 * ```
	 * Wichtig: $extensionName nur angeben, wenn das Setup einer FREMDEN Extension
	 * geholt werden soll oder es keinen Controller-Context gibt, weil der Aufruf z.B. 
	 * aus dem Backend gemacht wird
	 * 
	 * @return array
	 */
	public function getPlugin($extName = null) {

		if (!$extName) {
			$configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
			$setup = $configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK) ?: [];
			return $setup;
		}

		// Fallback: Setup für das Plugin aus globaler TS-Konfiguration holen
		$setup = $this->getFullTyposcript();
		if (!$setup || !$setup['plugin']) return [];
		if (isset($setup['plugin'][$extName])) {
			return $setup['plugin'][$extName];
		}
		if (isset($setup['plugin']["tx_{$extName}"])) {
			return $setup['plugin']["tx_{$extName}"];
		}
		return $setup['plugin']["tx_{$extName}_{$extName}"] ?? [];
	}

	/**
	 * Holt das TypoScript-Setup und dort den Abschnitt "settings".
	 * Werte aus dem FlexForm werden dabei nicht gemerged.
	 * ```
	 * \nn\t3::Settings()->getSettings( 'nnsite' );
	 * \nn\t3::Settings()->getSettings( 'nnsite', 'example.path' );
	 * ```
	 * @return array
	 */
	public function getSettings( $extensionName = '', $path = '' ) {
		$pluginSettings = $this->getPlugin( $extensionName );
		if (!$pluginSettings) return [];
		if (!$path) return $pluginSettings['settings'] ?? [];
		return $this->getFromPath( 'settings.'.$path, $pluginSettings ?? [] );
	}

	/**
	 * Merge aus TypoScript-Setup für ein Plugin und seinem Flexform holen.
	 * Gibt das TypoScript-Array ab `plugin.tx_extname.settings`... zurück.
	 *
	 * Wichtig: $extensionName nur angeben, wenn das Setup einer FREMDEN Extension
	 * geholt werden soll oder es keinen Controller-Context gibt, weil der
	 * Aufruf aus dem Backend gemacht wird... sonst werden die FlexForm-Werte nicht berücksichtigt!
	 *
	 * Im FlexForm `<settings.flexform.varName>` verwenden!
	 * `<settings.flexform.varName>` überschreibt dann `settings.varName` im TypoScript-Setup
	 * 
	 * `$ttContentUidOrSetupArray` kann uid eines `tt_content`-Inhaltselementes sein 
	 * oder ein einfaches Array zum Überschreiben der Werte aus dem TypoScript / FlexForm
	 * ```
	 * \nn\t3::Settings()->getMergedSettings();
	 * \nn\t3::Settings()->getMergedSettings( 'nnsite' );
	 * \nn\t3::Settings()->getMergedSettings( $extensionName, $ttContentUidOrSetupArray );
	 * ```
	 * @return array
	 */
	public function getMergedSettings( $extensionName = null, $ttContentUidOrSetupArray = [] ) {

		// Setup für das aktuelle Plugin holen, inkl. Felder aus dem FlexForm
		$configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
		$pluginSettings = $configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS, $extensionName) ?: [];

		// Fallback: Setup für das Plugin aus globaler TS-Konfiguration holen
		if (!$pluginSettings) {
			$setup = $this->getPlugin( $extensionName );
			$pluginSettings = $setup['settings'] ?? [];
		}
		
		// Eine tt_content.uid wurde übergeben. FlexForm des Elementes aus DB laden
		if ($ttContentUidOrSetupArray && !is_array($ttContentUidOrSetupArray)) {
			$flexform =  \nn\t3::Flexform()->getFlexform($ttContentUidOrSetupArray);
			$ttContentUidOrSetupArray =  $flexform['settings'] ?? [];
		}
		
		// Im Flexform sollten die Felder über settings.flexform.varname definiert werden
		$flexformSettings = $ttContentUidOrSetupArray['flexform'] ?? $pluginSettings['flexform'] ?? [];

		// Merge
		ArrayUtility::mergeRecursiveWithOverrule( $pluginSettings, $flexformSettings, true, false );

		// Referenz zu settings.flexform behalten
		if ($flexformSettings) {
			$pluginSettings['flexform'] = $flexformSettings;
		}
		
		return $pluginSettings;
	}

	/**
	 * Das komplette TypoScript Setup holen, als einfaches Array - ohne "."-Syntax
	 * Funktioniert sowohl im Frontend als auch Backend, mit und ohne übergebener pid
	 * ```
	 * \nn\t3::Settings()->getFullTyposcript();
	 * \nn\t3::Settings()->getFullTyposcript( $pid );
	 * ```
	 * @return array
	 */
	public function getFullTyposcript( $pid = null ) {

		if ($this->typoscriptSetupCache) return $this->typoscriptSetupCache;

		$setup = false;
		\nn\t3::Tsfe()->softDisableCache();

		try {
			if ($request = $GLOBALS['TYPO3_REQUEST'] ?? false) {
				if ($ts = $request->getAttribute('frontend.typoscript')) {
					$setup = $ts->getSetupArray();
				}
			}
			if (!$setup) {
				$configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
				$setup = $configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);
			}	
		} catch ( \Exception $e ) {
			// this might be related to https://forge.typo3.org/projects/typo3cms-core/issues
			$setup = false;
		}

		// Try to manually instanciate TypoScript setup
		if (!$setup) {
			$setup = $this->getTyposcriptObject( $pid )->setup ?: [];
		}

		if (!$setup) {
			\nn\t3::Exception('TypoScript-Setup could not be loaded. If you are trying to access it from a Middleware or the CLI, try using $request->getAttribute(\'frontend.controller\')->config[\'INTincScript\'][] = []; in your Middleware to disable caching.');
		}

		$this->typoscriptSetupCache = \nn\t3::TypoScript()->convertToPlainArray($setup);
		return $this->typoscriptSetupCache;
	}

	/**
	 * High-Peformance-Version für das Initialisieren des TSFE im Backend. 
	 * Das komplette TypoScript Setup holen, inkl. '.'-Syntax.
	 * 
	 * Wird über Filecache gespeichert.
	 * ```
	 * \nn\t3::Settings()->getCachedTyposcript();
	 * ```
	 * @return array
	 */
	public function getCachedTyposcript() 
	{
		if ($cache = \nn\t3::Cache()->read('nnhelpers_fullTsCache')) {
			return $cache;
		}
		$configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
		$setup = $configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);

		\nn\t3::Cache()->write('nnhelpers_fullTsCache', $setup);
		return $this->typoscriptFullCache = $setup;
	}

	/**
	 * TemplateService instanziieren, TypoScript-Config und Setup parsen.
	 * Interne Funktion – nicht zur Verwendung gedacht. 
	 * `getFullTyposcript` nutzen.
	 * 
	 * @return object
	 */
	public function getTyposcriptObject ( $pid = null ) 
	{
		if ($this->typoscriptObjectCache) return $this->typoscriptObjectCache;

		// @todo: FIX for TYPO3 v12
		return [];
		/*
		$rootline = \nn\t3::Page()->getRootline( $pid );
		\nn\t3::debug($rootline); die();
		$TsObj = GeneralUtility::makeInstance( \TYPO3\CMS\Core\TypoScript\ExtendedTemplateService::class );
		$TsObj->tt_track = 0;
		if (method_exists($TsObj, 'init')) $TsObj->init();
		$TsObj->runThroughTemplates($rootline);
		$TsObj->generateConfig();

		return $this->typoscriptObjectCache = $TsObj;
		*/
	}


	/**
	 * Setup von einem gegebenen Pfad holen, z.B. 'plugin.tx_example.settings'
	 * ```
	 * \nn\t3::Settings()->getFromPath('plugin.pfad');
	 * \nn\t3::Settings()->getFromPath('L', \nn\t3::Request()->GP());
	 * \nn\t3::Settings()->getFromPath('a.b', ['a'=>['b'=>1]]);
	 * ```
	 * Existiert auch als ViewHelper:
	 * ```
	 * {nnt3:ts.setup(path:'pfad.zur.setup')}
	 * ```
	 * @return array
	 */
	public function getFromPath( $tsPath = '', $setup = null ) {
		
		if (is_object($setup)) {
			$setup = (array) $setup;
		}

		$parts = \nn\t3::Arrays($tsPath)->trimExplode('.');
		$setup = $setup ?: $this->getFullTyposcript();

		$root = array_shift($parts);
		$plugin = array_shift($parts);

		$setup = $setup[$root] ?? [];
		if (!$plugin) return $setup;

		$setup = $setup[$plugin] ?? [];
		if (!count($parts)) return $setup;

		while (count($parts) > 0) {
			$part = array_shift($parts);
			if (count($parts) == 0) {
				return isset($setup[$part]) && is_array($setup[$part]) ? $setup[$part] : ($setup[$part] ?? ''); 
			}
			if (is_array($setup)) {
				$setup = $setup[$part] ?? '';
			}
		}

		return $setup;
	}
	
	/**
	 * Aktuelle (ERSTE) StoragePid für das aktuelle Plugin holen.
	 * Gespeichert im TypoScript-Setup der Extension unter
	 * `plugin.tx_extname.persistence.storagePid` bzw. im
	 * FlexForm des Plugins auf der jeweiligen Seite.
	 *
	 * WICHTIG: Merge mit gewählter StoragePID aus dem FlexForm
	 * passiert nur, wenn `$extName`leer gelassen wird.
	 * ```
	 * \nn\t3::Settings()->getStoragePid();			// 123
	 * \nn\t3::Settings()->getStoragePid('nnsite');	// 466
	 * ```
	 * @return string
	 */
	public function getStoragePid ( $extName = null ) {
		$pids = $this->getStoragePids( $extName );
		return array_pop( $pids );
	}

	/**
	 * ALLE storagePids für das aktuelle Plugin holen.
	 * Gespeichert als komma-separierte Liste im TypoScript-Setup der Extension unter
	 * `plugin.tx_extname.persistence.storagePid` bzw. im
	 * FlexForm des Plugins auf der jeweiligen Seite.
	 *
	 * WICHTIG: Merge mit gewählter StoragePID aus dem FlexForm
	 * passiert nur, wenn `$extName`leer gelassen wird.
	 * ```
	 * \nn\t3::Settings()->getStoragePids();					// [123, 466]
	 * \nn\t3::Settings()->getStoragePids('nnsite');			// [123, 466]
	 * ```
	 *
	 * Auch die child-PageUids holen?
	 * `true` nimmt den Wert für "Rekursiv" aus dem FlexForm bzw. aus dem 
	 * TypoScript der Extension von `plugin.tx_extname.persistence.recursive`
	 * ```
	 * \nn\t3::Settings()->getStoragePids(true);				// [123, 466, 124, 467, 468]
	 * \nn\t3::Settings()->getStoragePids('nnsite', true);		// [123, 466, 124, 467, 468]
	 * ```
	 * 
	 * Alternativ kann für die Tiefe / Rekursion auch ein numerischer Wert 
	 * übergeben werden.
	 * ```
	 * \nn\t3::Settings()->getStoragePids(2);				// [123, 466, 124, 467, 468]
	 * \nn\t3::Settings()->getStoragePids('nnsite', 2);		// [123, 466, 124, 467, 468]
	 * ```
	 * 
	 * @return array
	 */
	public function getStoragePids ( $extName = null, $recursive = 0 ) {

		// numerischer Wert: ->getStoragePids( 3 ) oder Boolean: ->getStoragePids( true )
		if (is_numeric($extName) || $extName === true ) {
			$recursive = $extName;
			$extName = null;
		}

		// $cObjData nur holen, falls kein extName angegeben wurde
		$cObjData = $extName === null ? [] : \nn\t3::Tsfe()->cObjData();
		$setup = $this->getPlugin( $extName  );

		// Wenn `recursive = true`, dann Wert aus FlexForm bzw. TypoScript nehmen
		$recursive = $recursive === true ? ($cObjData['recursive'] ?? $setup['persistence']['recursive']) : $recursive;

		$pids = $cObjData['pages'] ?? $setup['persistence']['storagePid'];
		$pids = \nn\t3::Arrays( $pids )->intExplode();
		
		// Child-Uids ergänzen?
		$childList = $recursive > 0 ? \nn\t3::Page()->getChildPids( $pids, $recursive ) : [];

		return array_merge( $pids, $childList );
	}
	
	/**
	 * Array der TypoScript-Konstanten holen.
	 * ```
	 * \nn\t3::Settings()->getConstants();
	 * \nn\t3::Settings()->getConstants('pfad.zur.konstante');
	 * ```
	 * Existiert auch als ViewHelper:
	 * ```
	 * {nnt3:ts.constants(path:'pfad.zur.konstante')}
	 * ```
	 * @return array 
	 */
	public function getConstants ( $tsPath = '' ) 
	{
		if ($request = $GLOBALS['TYPO3_REQUEST'] ?? false) {
			if ($ts = $request->getAttribute('frontend.typoscript')) {
				try {
					$constants = $ts->getSettingsTree()->toArray();
					\nn\t3::Tsfe()->softDisableCache();
				} catch ( \Exception $e ) {
					// this might be related to https://forge.typo3.org/projects/typo3cms-core/issues
					\nn\t3::Exception('TypoScript-Setup could not be loaded. If you are trying to access it from a Middleware or the CLI, try using $request->getAttribute(\'frontend.controller\')->config[\'INTincScript\'][] = []; in your Middleware to disable caching.');
				}
			}
		}
		$config = \nn\t3::TypoScript()->convertToPlainArray( $constants );
		return $tsPath ? $this->getFromPath( $tsPath, $config ) : $config;
	}

	/**
	 * Page-Configuration holen
	 * ```
	 * \nn\t3::Settings()->getPageConfig();
	 * \nn\t3::Settings()->getPageConfig('RTE.default.preset');
	 * \nn\t3::Settings()->getPageConfig( $tsPath, $pid );
	 * ```
	 * Existiert auch als ViewHelper:
	 * ```
	 * {nnt3:ts.page(path:'pfad.zur.pageconfig')}
	 * ```
	 * @return array
	 */
	public function getPageConfig( $tsPath = '', $pid = null ) 
	{
		if (\nn\t3::Environment()->isFrontend()) {
			$config = $GLOBALS['TSFE']->getPagesTSconfig();
		} else {
			$config = \TYPO3\CMS\Backend\Utility\BackendUtility::getPagesTSconfig( $pid ?: \nn\t3::Page()->getPid() );
		}
		$config = \nn\t3::TypoScript()->convertToPlainArray( $config );
		return $tsPath ? $this->getFromPath( $tsPath, $config ) : $config;
	}
	
	/**
	 * Page-Config hinzufügen
	 * Alias zu `\nn\t3::Registry()->addPageConfig( $str );`
	 * ```
	 * \nn\t3::Settings()->addPageConfig( 'test.was = 10' );
	 * \nn\t3::Settings()->addPageConfig( '<INCLUDE_TYPOSCRIPT: source="FILE:EXT:extname/Configuration/TypoScript/page.txt">' );
	 * \nn\t3::Settings()->addPageConfig( '@import "EXT:extname/Configuration/TypoScript/page.ts"' );
	 * ```
	 * @return void
	 */
	public function addPageConfig( $str = '' ) {
		\nn\t3::Registry()->addPageConfig( $str );
	}

	/**
	 * Extension-Konfiguration holen.
	 * Kommen aus der `LocalConfiguration.php`, werden über die Extension-Einstellungen
	 * im Backend bzw. `ext_conf_template.txt` definiert
	 * 
	 * Früher: `$GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['your_extension_key']`
	 * ```
	 * \nn\t3::Settings()->getExtConf( 'extname' );
	 * ```
	 * @return mixed
	 */
	public function getExtConf( $extName = '' ) {
		return GeneralUtility::makeInstance(ExtensionConfiguration::class)->get($extName) ?: [];
	}

	/**
	 * Extension-Konfiguration schreiben.
	 * Schreibt eine Extension-Konfiguration in die `LocalConfiguration.php`. Die Werte können bei
	 * entsprechender Konfiguration in der `ext_conf_template.txt` auch über den Extension-Manager / die
	 * Extension Konfiguration im Backend bearbeitet werden.
	 * ```
	 * \nn\t3::Settings()->setExtConf( 'extname', 'key', 'value' );
	 * ```
	 * @return mixed
	 */
	public function setExtConf( $extName = '', $key = '', $value = '' ) {
		$coreConfigurationManager = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ConfigurationManager::class);
		$result = $coreConfigurationManager->setLocalConfigurationValueByPath("EXTENSIONS/{$extName}/{$key}", $value);
	}


	/**
	 * Site-Konfiguration holen.
	 * Das ist die Konfiguration, die ab TYPO3 9 in den YAML-Dateien im Ordner `/sites` definiert wurden.
	 * Einige der Einstellungen sind auch über das Seitenmodul "Sites" einstellbar.
	 * 
	 * Im Kontext einer MiddleWare ist evtl. die `site` noch nicht geparsed / geladen.
	 * In diesem Fall kann der `$request` aus der MiddleWare übergeben werden, um die Site zu ermitteln. 
	 * ```
	 * $config = \nn\t3::Settings()->getSiteConfig();
	 * $config = \nn\t3::Settings()->getSiteConfig( $request );
	 * ```
	 * @return array
	 */
	public function getSiteConfig( $request = null ) {
		
		$site = \nn\t3::Environment()->getSite();
		if (!$site) return [];

		if (!is_a($site, \TYPO3\CMS\Core\Site\Entity\NullSite::class)) {
			return $site->getConfiguration() ?? [];
		}

		return [];
	}
}