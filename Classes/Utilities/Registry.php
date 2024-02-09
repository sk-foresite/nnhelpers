<?php

namespace Nng\Nnhelpers\Utilities;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Registry as CoreRegistry;

/**
 * Hilfreiche Methoden zum Registrieren von Extension-Komponenten wie Plugins,
 * Backend-Module, FlexForms etc.
 */
class Registry implements SingletonInterface {
   
	/**
	 *	Ein Plugin registrieren zur Auswahl über das Dropdown `CType` im Backend.
	 *	In `Configuration/TCA/Overrides/tt_content.php` nutzen – oder `ext_tables.php` (veraltet).
	 *	```
	 *	\nn\t3::Registry()->plugin( 'nncalendar', 'nncalendar', 'Kalender', 'EXT:pfad/zum/icon.svg' );
	 *	\nn\t3::Registry()->plugin( 'Nng\Nncalendar', 'nncalendar', 'Kalender', 'EXT:pfad/zum/icon.svg' );
	 *	```
	 *	
	 * 	@return void
	 */
	public function plugin ( $vendorName = '', $pluginName = '', $title = '', $icon = '', $tcaGroup = null ) {
		\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin( $this->getVendorExtensionName($vendorName), $pluginName, $title, $icon, $tcaGroup );
	}
	
	/**
	 * Vereinfacht das Registrieren einer Liste von Plugins, die im `list_type` Dropdown zu einer 
	 * Gruppe zusammengefasst werden.
	 * 
	 * In `Configuration/TCA/Overrides/tt_content.php` nutzen:
	 * ```
	 * \nn\t3::Registry()->pluginGroup(
	 * 	'Nng\Myextname',
	 * 	'LLL:EXT:myextname/Resources/Private/Language/locallang_db.xlf:pi_group_name',
	 * 	[
	 * 		'list' => [
	 * 			'title'		=> 'LLL:EXT:myextname/Resources/Private/Language/locallang_db.xlf:pi_list.name', 
	 * 			'icon'		=> 'EXT:myextname/Resources/Public/Icons/Extension.svg',
	 * 			'flexform'	=> 'FILE:EXT:myextname/Configuration/FlexForm/list.xml',
	 * 		],
	 * 		'show' => [
	 * 			'title'		=> 'LLL:EXT:myextname/Resources/Private/Language/locallang_db.xlf:pi_show.name', 
	 * 			'icon'		=> 'EXT:myextname/Resources/Public/Icons/Extension.svg',
	 * 			'flexform'	=> 'FILE:EXT:myextname/Configuration/FlexForm/show.xml'
	 * 		],
	 * 	]
	 * );
	 * ```
	 * @return void
	 */
	public function pluginGroup ( $vendorName = '', $groupLabel = '', $plugins = [] ) {

		// My\ExtName => ext_name
		$extName = GeneralUtility::camelCaseToLowerCaseUnderscored(array_pop(explode('\\', $vendorName)));
		$groupName = $extName . '_group';

		// ab TYPO3 10 können im Plugin-Dropdown optgroups gebildet werden
		\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTcaSelectItemGroup( 'tt_content', 'list_type', $groupName, $groupLabel, 'before:default' );

		foreach ($plugins as $listType=>$config) {
			$this->plugin( $vendorName, $listType, $config['title'] ?? '', $config['icon'] ?? '', $groupName );
			if ($flexform = $config['flexform'] ?? false) {
				$this->flexform( $vendorName, $listType, $flexform );
			}
		}
	}
	
	/**
	 * Ein Flexform für ein Plugin registrieren.
	 * ```
	 * \nn\t3::Registry()->flexform( 'nncalendar', 'nncalendar', 'FILE:EXT:nnsite/Configuration/FlexForm/flexform.xml' );
	 * \nn\t3::Registry()->flexform( 'Nng\Nncalendar', 'nncalendar', 'FILE:EXT:nnsite/Configuration/FlexForm/flexform.xml' );
	 * ```
	 * @return void
	 */
	public function flexform ( $vendorName = '', $pluginName = '', $path = '' ) {
		// \Nng\Nnsite => nnsite
		$extName = strtolower( array_pop(explode('\\', $vendorName)) );
		$pluginKey = "{$extName}_{$pluginName}";
		$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$pluginKey] = 'pi_flexform';
		\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue($pluginKey, $path); 
	}

	/**
	 * 	Plugin-Name generieren.
	 * 	Abhängig von Typo3-Version wird der Plugin-Name mit oder ohne Vendor zurückgegeben.
	 * 	```
	 * 	\nn\t3::Registry()->getVendorExtensionName( 'nncalendar' );  	// => Nng.Nncalendar
	 * 	\nn\t3::Registry()->getVendorExtensionName( 'Nng\Nncalendar' );  	// => Nng.Nncalendar
	 * 	```
	 * 	@return string
	 */
	public function getVendorExtensionName( $combinedVendorPluginName = '' ) {
		
		// Nng als Vendor-Name verwenden, falls nichts angegeben.
		$combinedVendorPluginName = str_replace('\\', '.', $combinedVendorPluginName);
		if (strpos($combinedVendorPluginName, '.') === false) {
			$combinedVendorPluginName = 'Nng.'.$combinedVendorPluginName;
		}

		$parts = explode('.', $combinedVendorPluginName);
		$vendorName = GeneralUtility::underscoredToUpperCamelCase( $parts[0] );
		$pluginName = GeneralUtility::underscoredToUpperCamelCase( $parts[1] );

		$registrationName = "{$pluginName}";
		return $registrationName;
	}


	/**
	 *	Liste mit `'ControllerName' => 'action,list,show'` parsen.
	 *	Immer den vollen Klassen-Pfad in der `::class` Schreibweise angeben.
	 *	Berücksichtigt, dass vor Typo3 10 nur der einfache Klassen-Name (z.B. `Main`)
	 *	als Key verwendet wird.
	 *	```
	 *	\nn\t3::Registry()->parseControllerActions(
	 *		[\Nng\ExtName\Controller\MainController::class => 'index,list'], 
	 *	);
	 *	```
	 *	@return array
	 */
	public function parseControllerActions( $controllerActionList = [] ) {
		return $controllerActionList;
	}

	/**
	 *	Ein Plugin konfigurieren.
	 *	In `ext_localconf.php` nutzen.
	 *	```
	 *	\nn\t3::Registry()->configurePlugin( 'Nng\Nncalendar', 'Nncalendar', 
	 *		[\Nng\ExtName\Controller\MainController::class => 'index,list'], 
	 *		[\Nng\ExtName\Controller\MainController::class => 'show']
	 *	);
	 *	```
	 *	
	 * 	@return void
	 */
	public function configurePlugin ( $vendorName = '', $pluginName = '', $cacheableActions = [], $uncacheableActions = [] ) {
		$registrationName = $this->getVendorExtensionName($vendorName);
		$pluginName = GeneralUtility::underscoredToUpperCamelCase( $pluginName );
		\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin( 
			$registrationName, $pluginName, 
			$this->parseControllerActions($cacheableActions), 
			$this->parseControllerActions($uncacheableActions)
		);
	}

	/**
	 *	Ein Feld in der Tabelle pages registrieren, das auf Unterseiten vererbbar / geslided werden soll. 
	 *	In der `ext_localconf.php` registrieren:
	 *	```
	 *	\nn\t3::Registry()->rootLineFields(['slidefield']);
	 *	\nn\t3::Registry()->rootLineFields('slidefield');
	 *	```
	 *	Typoscript-Setup:
	 *	```
	 *	page.10 = FLUIDTEMPLATE
	 *	page.10.variables {
	 *		footer = TEXT
	 *		footer {
	 *			data = levelfield:-1, footerelement, slide
	 *		}
	 *	}
	 *	```
	 *	
	 * 	@return void
	 */
	public function rootLineFields ( $fields = [], $translate = true ) {
		if (is_string($fields)) $fields = [$fields];
		$rootlinefields = &$GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'] ?? '';
		$rootlinefields .= ($rootlinefields ? ',' : '') . join(',', $fields);
		if ($translate) {
			if (!($GLOBALS['TYPO3_CONF_VARS']['FE']['pageOverlayFields'] ?? false)) {
				$GLOBALS['TYPO3_CONF_VARS']['FE']['pageOverlayFields'] = '';
			}
			$GLOBALS['TYPO3_CONF_VARS']['FE']['pageOverlayFields'] .= join(',', $fields);
		}
	}
	
	/**
	 *	Globalen Namespace für Fluid registrieren. 
	 *	Meistens in `ext_localconf.php` genutzt.
	 *	```
	 *	\nn\t3::Registry()->fluidNamespace( 'nn', 'Nng\Nnsite\ViewHelpers' );
	 *	\nn\t3::Registry()->fluidNamespace( ['nn', 'nng'], 'Nng\Nnsite\ViewHelpers' );
	 *	\nn\t3::Registry()->fluidNamespace( ['nn', 'nng'], ['Nng\Nnsite\ViewHelpers', 'Other\Namespace\Fallback'] );
	 *	```
	 *	
	 * 	@return void
	 */
	public function fluidNamespace ( $referenceNames = [], $namespaces = [] ) {

		if (is_string($referenceNames)) $referenceNames = [$referenceNames];
		if (is_string($namespaces)) $namespaces = [$namespaces];
		foreach ($referenceNames as $key) {
			$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces'][$key] = $namespaces;
		}
	}
	
	
	/**
	 * Ein Icon registrieren. Klassischerweise in ext_tables.php genutzt.
	 * ```
	 * \nn\t3::Registry()->icon('nncalendar-plugin', 'EXT:myextname/Resources/Public/Icons/wizicon.svg');
	 * ```
	 * @return void
	 */
	public function icon ( $identifier = '', $path = '' ) {

		$iconRegistry = GeneralUtility::makeInstance( \TYPO3\CMS\Core\Imaging\IconRegistry::class );

		$suffix = strtolower(pathinfo( $path, PATHINFO_EXTENSION ));
		
		if ($suffix != 'svg') {
			$suffix = 'bitmap';
		}

		$provider = 'TYPO3\\CMS\\Core\\Imaging\\IconProvider\\' . ucfirst($suffix) . 'IconProvider';

		$iconRegistry->registerIcon(
			$identifier,
			$provider,
			['source' => $path]
		);
	}


	/**
	 * Eine Wert aus der Tabelle sys_registry holen.
	 * ```
	 * \nn\t3::Registry()->get( 'nnsite', 'lastRun' );
	 * ```
	 * @return void
	 */
	public function get ( $extName = '', $path = '' ) {
		$registry = GeneralUtility::makeInstance( CoreRegistry::class );
		return $registry->get( $extName, $path );
	}
	

	/**
	 * Einen Wert in der Tabelle sys_registry speichern.
	 * Daten in dieser Tabelle bleiben über die Session hinaus erhalten.
	 * Ein Scheduler-Job kann z.B. speichern, wann er das letzte Mal
	 * ausgeführt wurde.
	 * 
	 * Arrays werden per default rekursiv zusammengeführt / gemerged:
	 * ```
	 * \nn\t3::Registry()->set( 'nnsite', 'lastRun', ['eins'=>'1'] );
	 * \nn\t3::Registry()->set( 'nnsite', 'lastRun', ['zwei'=>'2'] );
	 * 
	 * \nn\t3::Registry()->get( 'nnsite', 'lastRun' ); // => ['eins'=>1, 'zwei'=>2]
	 * ```
	 *  
	 * Mit `true` am Ende werden die vorherigen Werte gelöscht:
	 * ```
	 * \nn\t3::Registry()->set( 'nnsite', 'lastRun', ['eins'=>'1'] );
	 * \nn\t3::Registry()->set( 'nnsite', 'lastRun', ['zwei'=>'2'], true );
	 *
	 * \nn\t3::Registry()->get( 'nnsite', 'lastRun' ); // => ['zwei'=>2]
	 * ```
	 * @return array
	 */
	public function set ( $extName = '', $path = '', $settings = [], $clear = false ) {
		$registry = GeneralUtility::makeInstance( CoreRegistry::class );
		if (!$clear && is_array($settings)) {
			$curSettings = $this->get( $extName, $path ) ?: [];
			$settings = \nn\t3::Arrays( $curSettings )->merge( $settings, true, true );
		}
		$registry->set( $extName,  $path, $settings );
		return $settings;
	}

	/**
	 * Page-Config hinzufügen
	 * ```
	 * \nn\t3::Registry()->addPageConfig( 'test.was = 10' );
	 * \nn\t3::Registry()->addPageConfig( '<INCLUDE_TYPOSCRIPT: source="FILE:EXT:extname/Configuration/TypoScript/page.txt">' );
	 * \nn\t3::Settings()->addPageConfig( '@import "EXT:extname/Configuration/TypoScript/page.ts"' );
	 * ```
	 * @return void
	 */
	public function addPageConfig( $str = '' ) {
		\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig( $str );
	}

	/**
	 * Fügt einen Hook ein, der beim Klick auf "Cache löschen" ausgeführt wird.
	 * Folgendes Script kommt in die `ext_localconf.php` der eigenen Extension:
	 * ```
	 * \nn\t3::Registry()->clearCacheHook( \My\Ext\Path::class . '->myMethod' );
	 * ```
	 * @return void
	 */
	public function clearCacheHook( $classMethodPath = '' ) {
		$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc'][] = $classMethodPath;
	}
}