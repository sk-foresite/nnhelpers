<?php

namespace Nng\Nnhelpers\Utilities;

use TYPO3\CMS\Core\SingletonInterface;

/**
 * Methoden für die Konfiguration und den Zugriff auf Felder im TCA.
 */
class TCA implements SingletonInterface {

	/**
	 * 	Fügt Optionen aus TypoScript zur Auswahl in ein TCA ein.
	 * 	Alias zu \nn\t3::Flexform->insertOptions( $config, $a = null );
	 * 	Beschreibung und weitere Beispiele dort.
	 * 
	 * 	Beispiel im TCA:
	 *	```
	 *	'config' => [
	 *		'type' => 'select',
	 *		'itemsProcFunc' => 'nn\t3\Flexform->insertOptions',
	 *		'typoscriptPath' => 'plugin.tx_nnnewsroom.settings.templates',
	 *		//'pageconfigPath' => 'tx_nnnewsroom.colors',
	 *	]
	 *	```
	 * 	@return array
	 */
	public function insertOptions ( $config, $a = null ) {
		return \nn\t3::Flexform()->insertOptions( $config, $a );
	}

	/**
	 * Fügt Liste der Länder in ein TCA ein.
	 * Alias zu \nn\t3::Flexform->insertCountries( $config, $a = null );
	 * Beschreibung und weitere Beispiele dort.
	 * 
	 * Beispiel im TCA:
	 * ```
	 * 'config' => [
	 * 	'type' => 'select',
	 * 	'itemsProcFunc' => 'nn\t3\Flexform->insertCountries',
	 * 	'insertEmpty' => true,
	 * ]
	 * ```
	 * @return array
	 */
	public function insertCountries( $config, $a = null ) {
		return \nn\t3::Flexform()->insertCountries( $config, $a );
	}
	
	/**
	 * Fügt ein Flexform in ein TCA ein.
	 * 
	 * Beispiel im TCA:
	 * ```
	 * 'config' => \nn\t3::TCA()->insertFlexform('FILE:EXT:nnsite/Configuration/FlexForm/slickslider_options.xml');
	 * ```
	 * @return array
	 */
	public function insertFlexform( $path ) {
		return [
			'type' 	=> 'flex',
			'ds' 	=> ['default' => $path],
		];
	}

	/**
	 *	Holt Konfigurations-Array für ein Feld aus dem TCA.
	 *	Alias zu `\nn\t3::Db()->getColumn()`
	 *	```
	 *	\nn\t3::TCA()->getColumn( 'pages', 'media' );
	 *	```
	 *	@return array
	 */
	public function getColumn( $tableName = '', $fieldName = '', $useSchemaManager = false) {
		return \nn\t3::Db()->getColumn( $tableName, $fieldName, $useSchemaManager );
	}

	/**
	 *	Holt Konfigurations-Array für eine Tabelle aus dem TCA.
	 *	Alias zu `\nn\t3::Db()->getColumns()`
	 *	```
	 *	\nn\t3::TCA()->getColumns( 'pages' );
	 *	```
	 *	@return array
	 */
	public function getColumns( $tableName = '', $useSchemaManager = false) {
		return \nn\t3::Db()->getColumns( $tableName, $useSchemaManager );
	}
	
	/**
	 * Holt alle Feldnamen aus dem TCA-Array, die eine SysFileReference-Relation haben.
	 * Bei der Tabelle `tt_content` wären das z.B. `assets`, `media` etc.
	 * ```
	 * \nn\t3::TCA()->getFalFields( 'pages' );	// => ['media', 'assets', 'image']
	 * ```
	 * @return array
	 */
	public function getFalFields( $tableName = '' ) {
		$fields = array_filter( \nn\t3::Db()->getColumns( $tableName ), function ( $item ) {
			return ($item['config']['foreign_table'] ?? false) == 'sys_file_reference';
		});
		return array_keys( $fields );
	}

	/**
	 * FAL Konfiguration für das TCA holen.
	 *
	 * Standard-Konfig inkl. Image-Cropper, Link und alternativer Bildtitel
	 * Diese Einstellung ändert sich regelmäßig, was bei der Menge an Parametern
	 * und deren wechselnden Position im Array eine ziemliche Zumutung ist.
	 *
	 * https://bit.ly/2SUvASe
	 *
	 * ```
	 * \nn\t3::TCA()->getFileFieldTCAConfig('media');
	 * \nn\t3::TCA()->getFileFieldTCAConfig('media', ['maxitems'=>1, 'fileExtensions'=>'jpg']);
	 * ```
	 *
	 * Wird im TCA so eingesetzt:
	 * ```
	 * 'falprofileimage' => [
	 * 	'config' => \nn\t3::TCA()->getFileFieldTCAConfig('falprofileimage', ['maxitems'=>1]),
	 * ],
	 * ```
	 * @return array	
	 */
	public function getFileFieldTCAConfig( $fieldName = 'media', $override = [] ) {

		// Vereinfachte Übergabe der Optionen
		$options = array_merge([
			'label' => 'LLL:EXT:frontend/Resources/Private/Language/Database.xlf:tt_content.asset_references',
			'maxitems' => 999,
			'fileExtensions' => 'common-media-types',
			// 'fileUploadAllowed' => true,
			// 'fileByUrlAllowed' => true,
		], $override);

		/*
		$config = [
			'label' => $options['label'],
			'appearance' => [
				'fileUploadAllowed' => $options['fileUploadAllowed'],
				'fileByUrlAllowed' => $options['fileByUrlAllowed'],
			],
			'config' => [
				'type' => 'file',
				'maxitems' => $options['maxitems'],
				'allowed' => $options['fileExtensions'],
			]
		];
		*/
		$config = [
			'type' => 'file',
			'maxitems' => $options['maxitems'],
			'allowed' => $options['fileExtensions'],
		];

		if ($childConfig = $options['overrideChildTca'] ?? false) {
			$config['overrideChildTca'] = $childConfig;
		}

		return $config;

	}


	/**
	 * RTE Konfiguration für das TCA holen.
	 * ```
	 * 'config' => \nn\t3::TCA()->getRteTCAConfig(),
	 * ```
	 * @return array
	 */
	public function getRteTCAConfig() {
		return [
			'type' => 'text',
			'enableRichtext' => true,
		];
	}
	
	/**
	 * Standard-Slug Konfiguration für das TCA holen.
	 * 
	 * ```
	 * 'config' => \nn\t3::TCA()->getSlugTCAConfig( 'title' )
	 * 'config' => \nn\t3::TCA()->getSlugTCAConfig( ['title', 'header'] )
	 * ```
	 * @param array|string $fields
	 * @return array
	 */
	public function getSlugTCAConfig( $fields = [] ) {
		if (is_string($fields)) {
			$fields = [$fields];
		}
		return [
			'type' => 'slug',
			'size' => 50,
			'generatorOptions' => [
				'fields' => $fields,
				'replacements' => [
					'/' => '-'
				],
			],
			'fallbackCharacter' => '-',
			'eval' => 'unique',
			'default' => ''
		];
	}
 
	/**
	 * Color Picker Konfiguration für das TCA holen.
	 * ```
	 * 'config' => \nn\t3::TCA()->getColorPickerTCAConfig(),
	 * ```
	 * @return array
	 */
	public function getColorPickerTCAConfig() {
		return [
			'type' => 'input',
			'renderType' => 'colorpicker',
			'size' => 10,
		];
	}

	
	/**
	 * In den Seiteneigenschaften unter "Verhalten -> Enthält Erweiterung" eine Auswahl-Option hinzufügen.
	 * Klassischerweise in `Configuration/TCA/Overrides/pages.php` genutzt, früher in `ext_tables.php`
	 * 
	 * ```
	 * // In ext_localconf.php das Icon registrieren (16 x 16 px SVG)
	 * \nn\t3::Registry()->icon('icon-identifier', 'EXT:myext/Resources/Public/Icons/module.svg');
	 * 
	 * // In Configuration/TCA/Overrides/pages.php
	 * \nn\t3::TCA()->addModuleOptionToPage('Beschreibung', 'identifier', 'icon-identifier');
	 * ```
	 * 
	 * @return void
	 */
	public function addModuleOptionToPage( $label, $identifier, $iconIdentifier = '') {

		// Auswahl-Option hinzufügen
		$GLOBALS['TCA']['pages']['columns']['module']['config']['items'][] = [
			0 => $label,
			1 => $identifier,
			2 => $iconIdentifier
		];

		// Icon im Seitenbaum verwenden
		if ($iconIdentifier) {
			$GLOBALS['TCA']['pages']['ctrl']['typeicon_classes']['contains-'.$identifier] = $iconIdentifier;
		}
	}

	/**
	 * Eine Konfiguration aus dem TCA holen für einen Pfad holen.
	 * Liefert eine Referenz zu dem `config`-Array des ensprechenden Feldes zurück.
	 *  
	 * ```
	 * \nn\t3::TCA()->getConfig('tt_content.columns.tx_mask_iconcollection');
	 * ```
	 * @return array
	 */
	public function &getConfig( $path = '' ) {
		
		$parts = \nn\t3::Arrays($path)->trimExplode('.');
		$ref = &$GLOBALS['TCA'];

		while (count($parts) > 0) {
			$part = array_shift($parts);
			$ref = &$ref[$part];
		}
		$ref = &$ref['config'];
		return $ref;
	}

	/**
	 * Eine Konfiguration des TCA überschreiben, z.B. um ein `mask`-Feld mit einem eigenen renderType zu
	 * überschreiben oder Core-Einstellungen im TCA an den Tabellen `pages` oder `tt_content` zu ändern.
	 * 
	 * Folgendes Beispiel setzt/überschreibt im `TCA` das `config`-Array unter:
	 * ```
	 * $GLOBALS['TCA']['tt_content']['columns']['mycol']['config'][...]
	 * ```
	 * ```
	 * \nn\t3::TCA()->setConfig('tt_content.columns.mycol', [
	 * 	'renderType' => 'nnsiteIconCollection',
	 * 	'iconconfig' => 'tx_nnsite.iconcollection',
	 * ]);
	 * ```
	 * Siehe auch `\nn\t3::TCA()->setContentConfig()` für eine Kurzfassung dieser Methode, wenn es um 
	 * die Tabelle `tt_content` geht und `\nn\t3::TCA()->setPagesConfig()` für die Tabelle `pages`
	 * 
	 * @return array
	 */
	public function setConfig( $path = '', $override = [] ) {
		if ($config = &$this->getConfig( $path )) {
			$config = \nn\t3::Arrays()->merge( $config, $override );
		}
		return $config;
	}

	/**
	 * Eine Konfiguration des TCA für die Tabelle `tt_content` setzen oder überschreiben.
	 * 
	 * Diese Beispiel überschreibt im `TCA` das `config`-Array der Tabelle `tt_content` für:
	 * ```
	 * $GLOBALS['TCA']['tt_content']['columns']['title']['config'][...]
	 * ```
	 * ```
	 * \nn\t3::TCA()->setContentConfig( 'header', 'text' );		// ['type'=>'text', 'rows'=>2]
	 * \nn\t3::TCA()->setContentConfig( 'header', 'text', 10 );	// ['type'=>'text', 'rows'=>10]
	 * \nn\t3::TCA()->setContentConfig( 'header', ['type'=>'text', 'rows'=>10] ); // ['type'=>'text', 'rows'=>10]
	 * ```
	 * @return array
	 */
	public function setContentConfig( $field = '', $override = [], $shortParams = null ) {
		$config = &$GLOBALS['TCA']['tt_content']['columns'][$field]['config'] ?? [];
		return $config = \nn\t3::Arrays()->merge( $config, $this->getConfigForType($override, $shortParams) );
	}
	
	/**
	 * Eine Konfiguration des TCA für die Tabelle `pages` setzen oder überschreiben.
	 * 
	 * Diese Beispiel überschreibt im `TCA` das `config`-Array der Tabelle `pages` für:
	 * ```
	 * $GLOBALS['TCA']['pages']['columns']['title']['config'][...]
	 * ```
	 * ```
	 * \nn\t3::TCA()->setPagesConfig( 'title', 'text' );			// ['type'=>'text', 'rows'=>2]
	 * \nn\t3::TCA()->setPagesConfig( 'title', 'text', 10 );		// ['type'=>'text', 'rows'=>10]
	 * \nn\t3::TCA()->setPagesConfig( 'title', ['type'=>'text', 'rows'=>2] ); // ['type'=>'text', 'rows'=>2]
	 * ```
	 * @return array
	 */
	public function setPagesConfig( $field = '', $override = [], $shortParams = null ) {
		$config = &$GLOBALS['TCA']['pages']['columns'][$field]['config'] ?? [];
		return $config = \nn\t3::Arrays()->merge( $config, $this->getConfigForType($override, $shortParams) );
	}

	/**
	 * Default Konfiguration für verschiedene, typische `types` im `TCA` holen.
	 * Dient als eine Art Alias, um die häufigst verwendeten `config`-Arrays schneller
	 * und kürzer schreiben zu können
	 * 
	 * ```
	 * \nn\t3::TCA()->getConfigForType( 'pid' );			// => ['type'=>'group', 'internal_type'=>'db', 'allowed'=>'pages', 'maxItems'=>1]
	 * \nn\t3::TCA()->getConfigForType( 'contentElement' );	// => ['type'=>'group', 'internal_type'=>'db', 'allowed'=>'tt_content', 'maxItems'=>1]
	 * \nn\t3::TCA()->getConfigForType( 'text' );			// => ['type'=>'text', 'rows'=>2, ...]
	 * \nn\t3::TCA()->getConfigForType( 'rte' );			// => ['type'=>'text', 'enableRichtext'=>'true', ...]
	 * \nn\t3::TCA()->getConfigForType( 'color' );			// => ['type'=>'input', 'renderType'=>'colorpicker', ...]
	 * \nn\t3::TCA()->getConfigForType( 'fal', 'image' );	// => ['type'=>'input', 'renderType'=>'colorpicker', ...]
	 * ```
	 * Default-Konfigurationen können einfach überschrieben / erweitert werden:
	 * ```
	 * \nn\t3::TCA()->getConfigForType( 'text', ['rows'=>5] );	// => ['type'=>'text', 'rows'=>5, ...]
	 * ```
	 * Für jeden Typ lässt sich der am häufigsten überschriebene Wert im `config`-Array auch
	 * per Übergabe eines fixen Wertes statt eines `override`-Arrays setzen:
	 * ```
	 * \nn\t3::TCA()->getConfigForType( 'pid', 3 );				// => ['maxItems'=>3, ...]
	 * \nn\t3::TCA()->getConfigForType( 'contentElement', 3 );	// => ['maxItems'=>3, ...]
	 * \nn\t3::TCA()->getConfigForType( 'text', 10 );			// => ['rows'=>10, ...]
	 * \nn\t3::TCA()->getConfigForType( 'rte', 'myRteConfig' );	// => ['richtextConfiguration'=>'myRteConfig', ...]
	 * \nn\t3::TCA()->getConfigForType( 'color', '#ff6600' );	// => ['default'=>'#ff6600', ...]
	 * \nn\t3::TCA()->getConfigForType( 'fal', 'image' );		// => [ config für das Feld mit dem Key `image` ]
	 * ```
	 * @return array 
	 */
	public function getConfigForType( $type = '', $override = [] ) {
		if (is_array($type)) return $type;

		// Fixer Wert statt Array in `override`? Für welches Key im `config`-Array verwenden?
		$overrideKey = false;

		switch ($type) {
			case 'pid':
				$config = ['type'=>'group', 'internal_type'=>'db', 'allowed'=>'pages', 'size' => 1, 'maxItems'=>1];
				$overrideKey = 'maxItems';
				break;
			case 'cid':
			case 'contentElement':
				$config = ['type'=>'group', 'internal_type'=>'db', 'allowed'=>'tt_content', 'size' => 1, 'maxItems'=>1];
				$overrideKey = 'maxItems';
				break;
			case 'text':
				$config = ['type'=>'text', 'rows'=>2, 'cols'=>50];
				$overrideKey = 'rows';
				break;
			case 'color':
				$config = \nn\t3::TCA()->getColorPickerTCAConfig();
				$overrideKey = 'default';
				break;
			case 'rte':
				$config = \nn\t3::TCA()->getRteTCAConfig(); 
				$overrideKey = 'richtextConfiguration';
				break;
			case 'fal':
				if (!$override) \nn\t3::Exception('`field` muss definiert sein!');
				if (is_string($override)) $override = ['field'=>$override];
				$config = \nn\t3::TCA()->getFileFieldTCAConfig( $override['field'], $override ); 
				break;
			default:
				$config = [];
		}
		if ($override) {
			if (!is_array($override) && $overrideKey) {
				$override = [$overrideKey=>$override];
			}
			$config = \nn\t3::Arrays()->merge( $config, $override );
		}
		return $config;
	}

	/**
	 * Basis-Konfiguration für das TCA holen.
	 * Das sind die Felder wie `hidden`, `starttime` etc., die bei (fast) allen Tabellen immer gleich sind.
	 * 
	 * ALLE typischen Felder holen:
	 * ```
	 * 'columns' => \nn\t3::TCA()->createConfig(
	 * 	'tx_myext_domain_model_entry', true,
	 * 	['title'=>...]
	 * )
	 * ```
	 * 
	 * Nur bestimmte Felder holen:
	 * ```
	 * 'columns' => \nn\t3::TCA()->createConfig(
	 * 	'tx_myext_domain_model_entry',
	 * 	['sys_language_uid', 'l10n_parent', 'l10n_source', 'l10n_diffsource', 'hidden', 'cruser_id', 'pid', 'crdate', 'tstamp', 'sorting', 'starttime', 'endtime', 'fe_group'],
	 * 	['title'=>...]
	 * )
	 * ```
	 * @return array
	 */
	public function createConfig( $tablename = '', $basics = [], $custom = [] ) {

		if ($basics === true) {
			$basics = ['sys_language_uid', 'l10n_parent', 'l10n_source', 'l10n_diffsource', 'hidden', 'cruser_id', 'pid', 'crdate', 'tstamp', 'sorting', 'starttime', 'endtime', 'fe_group'];
		}

		$defaults = [
			'sys_language_uid' => [
				'exclude' => true,
				'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
				'config' => [
					'type' => 'language',
					'renderType' => 'selectSingle',
					'special' => 'languages',
					'items' => [
						[
							'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.allLanguages',
							'value' => -1,
							'iconIdentifierChecked' => 'flags-multiple'
						],
					],
					'default' => 0,
				]
			],
			'l10n_parent' => [
				'displayCond' => 'FIELD:sys_language_uid:>:0',
				'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_parent',
				'config' => [
					'type' => 'group',
					'allowed' => $tablename,
					'size' => 1,
					'maxitems' => 1,
					'minitems' => 0,
					'default' => 0,
				],
			],
			'l10n_source' => [
				'config' => [
					'type' => 'passthrough'
				]
			],
			'l10n_diffsource' => [
				'config' => [
					'type' => 'passthrough',
					'default' => ''
				]
			],
			'hidden' => [
				'exclude' => true,
				'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
				'config' => [
					'type' => 'check',
					'renderType' => 'checkboxToggle',
					'default' => 0,
					'items' => [
						[
							'label' => '',
							'value' => '',
						]
					],
				]
			],
			'cruser_id' => [
				'label' => 'cruser_id',
				'config' => [
					'type' => 'passthrough'
				]
			],
			'pid' => [
				'label' => 'pid',
				'config' => [
					'type' => 'passthrough'
				]
			],
			'crdate' => [
				'label' => 'crdate',
				'config' => [
					'type' => 'input',
				]
			],
			'tstamp' => [
				'label' => 'tstamp',
				'config' => [
					'type' => 'input',
				]
			],
			'sorting' => [
				'label' => 'sorting',
				'config' => [
					'type' => 'passthrough',
				]
			],
			'starttime' => [
				'exclude' => true,
				'label' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:starttime_formlabel',
				'config' => [
					'type' => 'datetime',
					'default' => 0,
					'behaviour' => [
						'allowLanguageSynchronization' => true,
					],
				]
			],
			'endtime' => [
				'exclude' => true,
				'label' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:endtime_formlabel',
				'config' => [
					'type' => 'datetime',
					'default' => 0,
					'range' => [
						'upper' => mktime(0, 0, 0, 1, 1, 2038),
					],
					'behaviour' => [
						'allowLanguageSynchronization' => true,
					],
				]
			],
			'fe_group' => [
				'exclude' => true,
				'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.fe_group',
				'config' => [
					'type' => 'select',
					'renderType' => 'selectMultipleSideBySide',
					'size' => 5,
					'maxitems' => 20,
					'items' => [
						[
							'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hide_at_login',
							'value' => -1,
						],
						[
							'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.any_login',
							'value' => -2,
						],
						[
							'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.usergroups',
							'value' => '--div--',
						],
					],
					'exclusiveKeys' => '-1,-2',
					'foreign_table' => 'fe_groups',
					'foreign_table_where' => 'ORDER BY fe_groups.title',
				],
			],
		];

		$result = [];
		foreach ($basics as $key) {
			if ($config = $defaults[$key] ?? false) {
				$result[$key] = $config;
			}
		}

		return array_merge( $result, $custom );
	}
}