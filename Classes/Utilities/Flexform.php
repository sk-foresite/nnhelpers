<?php

namespace Nng\Nnhelpers\Utilities;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * FlexForms laden und parsen
 */
class Flexform implements SingletonInterface {
    
	/**
	 * Wandelt ein Flexform-XML in ein Array um
	 * ```
	 * \nn\t3::Flexform()->parse('<?xml...>');
	 * ```
	 * Existiert auch als ViewHelper:
	 * ```
	 * {rawXmlString->nnt3:parse.flexForm()->f:debug()}
	 * ```
	 * @return array
	 */
	public function parse( $xml = '' ) {
		$flexFormService = \nn\t3::injectClass( \TYPO3\CMS\Core\Service\FlexFormService::class );

		if (!$xml) return [];
		if (is_array($xml)) {
			$data = [];
			foreach (($xml['data']['sDEF']['lDEF'] ?? []) as $k=>$node) {
				$data[$k] = $node['vDEF'];
			}
			return $data;
		}
		return $flexFormService->convertFlexFormContentToArray( $xml ) ?: [];
	}

	/**
	 * 	Holt das Flexform eines bestimmten Inhaltselementes als Array
	 *	```
	 *	\nn\t3::Flexform()->getFlexform( 1201 );
	 *	```
	 * 	@return array
	 */
    public function getFlexform( $ttContentUid = null ) {
		$data = \nn\t3::Content()->get( $ttContentUid );
		if (!$data) return [];
		
		$flexformData = $this->parse($data['pi_flexform']);
		return $flexformData;
	}

	/**
	 * Lädt FAL-Media, die in direkt im FlexForm angegeben wurden
	 * ```
	 * \nn\t3::Flexform()->getFalMedia( 'falmedia' );
	 * \nn\t3::Flexform()->getFalMedia( 'settings.falmedia' );
	 * \nn\t3::Flexform()->getFalMedia( 1201, 'falmedia' );
	 * ```
	 * ```
	 * $cObjData = \nn\t3::Tsfe()->cObjData();
	 * $falMedia = \nn\t3::Flexform()->getFalMedia( $cObjData['uid'], 'falmedia' );
	 * ```
	 * @return array
	 */
	public function getFalMedia( $ttContentUid = null, $field = '' ) {
		if (!$field && $ttContentUid) {
			$field = $ttContentUid;
			$ttContentUid = \nn\t3::Tsfe()->cObjData()['uid'];
		}
		$fileRepository = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\FileRepository::class);
		$fileObjects = $fileRepository->findByRelation('tt_content', $field, $ttContentUid);
		foreach ($fileObjects as $n=>$fal) {
			$fileObjects[$n] = \nn\t3::Convert( $fal )->toFileReference();
		}
		return $fileObjects;
	}


	/**
	 * 	Fügt Optionen aus TypoScript zur Auswahl in ein FlexForm oder TCA ein.
	 *	```
	 *	<config>
	 *		<type>select</type>
	 *		<items type="array"></items>
	 *		<itemsProcFunc>nn\t3\Flexform->insertOptions</itemsProcFunc>
	 *		<typoscriptPath>plugin.tx_extname.settings.templates</typoscriptPath>
	 *		<!-- Alternativ: Settings aus PageTSConfig laden: -->
	 *		<pageconfigPath>tx_extname.colors</pageconfigPath>
	 *		<insertEmpty>1</insertEmpty>
	 *		<hideKey>1</hideKey>
	 *	</config>
	 *	```
	 *	Beim Typoscript sind verschiedene Arten des Aufbaus erlaubt:
	 *	```
	 *	plugin.tx_extname.settings.templates {
	 *		# Direkte key => label Paare	 
	 *		small = Small Design
	 *		# ... oder: Label im Subarray gesetzt
	 *		mid {
	 *			label = Mid Design
	 *		}
	 *		# ... oder: Key im Subarray gesetzt, praktisch z.B. für CSS-Klassen
	 *		10 {
	 *			label = Big Design
	 *			classes = big big-thing
	 *		}
	 *		# ... oder eine userFunc. Gibt eine der Varianten oben als Array zurück
	 *		30 {
	 *			userFunc = nn\t3\Flexform->getOptions
	 *		}
	 *	}
	 *	```
	 *	Die Auswahl kann im TypoScript auf bestimmte Controller-Actions beschränkt werden.
	 *	In diesem Beispiel wird die Option "Gelb" nur angezeigt, wenn in der `switchableControllerAction`
	 *	`Category->list` gewählt wurde.
	 *	```
	 *	plugin.tx_extname.settings.templates {
	 *		yellow {
	 *			label = Gelb
	 *			controllerAction = Category->list,...
	 *		}
	 *	}
	 *	```
	 * 	@return array
	 */
	public function insertOptions( $config, $a = null ) {
		
		if ($path = $config['config']['typoscriptPath'] ?? false) {
			// 'typoscriptPath' angegeben: Standard TypoScript-Setup verwenden 
			$setup = \nn\t3::Settings()->getFromPath( $path );
		} elseif ( $path = $config['config']['pageconfigPath'] ?? false) {
			// 'pageconfigPath' angegeben: PageTSConfig verwenden 
			$setup = \nn\t3::Settings()->getPageConfig( $path );
		}

		if (!$setup) {
			if ($config['items'] ?? false) return $config;
			$config['items'] = [
				['label' => 'Keine Konfiguration gefunden - Auswahl kann in '.$path.' definiert werden', 'value'=>'']
			];
			return $config;
		}

		$respectControllerAction = false;

		// TypoScript setup vorbereiten 		
		foreach ($setup as $k=>$v) {
			
			// controllerAction in Typoscript gesetzt?
			if (is_array($v) && ($v['controllerAction'] ?? false)) {
				$respectControllerAction = true;
			}

			// userFunc vorhanden? Dann auflösen...
			if (is_array($v) && ($v['userFunc'] ?? false)) {
				$result = \nn\t3::call( $v['userFunc'], $v );
				unset($setup[$k]);
				$setup = \nn\t3::Arrays($result)->merge($setup);
			}
		}

		// Ausgewählte Action aus FlexForm 'switchableControllerActions' holen
		$selectedAction = $config['row']['switchableControllerActions'] ?? false;

		// Leeren Wert einfügen?
		if ($config['config']['insertEmpty'] ?? false) {
			$config['items'] = array_merge( $config['items'], [['label'=>'', 'value'=>0]] );
		}

		// Key in Klammern zeigen?
		$hideKey = ($config['config']['hideKey'] ?? 0) == 1;

		foreach ($setup as $k=>$v) {
			if (is_array($v)) {
				$label = $v['_typoScriptNodeValue'] ?? $v['label'] ?? $v['title'] ?? $v;
				$key = $v['classes'] ?? $k;
				$keyStr = $hideKey ? '' : " ({$key})";
				$limitToAction = \nn\t3::Arrays($v['controllerAction'] ?? '')->trimExplode();
				if ($limitToAction && $selectedAction) {
					if (array_intersect($limitToAction, $selectedAction)) {
						$config['items'] = array_merge( $config['items'], [['label'=>$label.$keyStr, 'value'=>$k]] );
					}
				} else {
					$config['items'] = array_merge( $config['items'], [['label'=>$label.$keyStr, 'value'=>$k]] );
				}
			} else {
				$key = $v['classes'] ?? $k;
				$keyStr = $hideKey ? '' : " ({$key})";
				$config['items'] = array_merge( $config['items'], [['label'=>$v.$keyStr, 'value'=>$k]] );
			}
		}

		return $config;	
	}

	/**
	 * Fügt Optionen aus TypoScript zur Auswahl in ein FlexForm oder TCA ein.
	 * ```
	 * <config>
	 * 	<type>select</type>
	 * 	<items type="array"></items>
	 * 	<itemsProcFunc>nn\t3\Flexform->insertCountries</itemsProcFunc>
	 * 	<insertEmpty>1</insertEmpty>
	 * </config>
	 * ```
	 *
	 * @return array
	 */
	public function insertCountries ( $config, $a = null ) {
		if ($config['config']['insertEmpty'] ?? false) {
			$config['items'] = array_merge( $config['items'], [['', '0', '']] );
		}
		$countriesByShortCode = \nn\t3::Environment()->getCountries() ?: [];
		if (!$countriesByShortCode) {
			$countriesByShortCode['DE'] = 'static_info_tables installieren!';
		}
		foreach ($countriesByShortCode as $cn => $title) {
			$config['items'][] = [$title, $cn];
		}
		return $config;
	}

}