<?php

namespace Nng\Nnhelpers\Utilities;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Frontend\ContentObject\RecordsContentObject;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Context\LanguageAspect;

/**
 * Inhaltselemente und Inhalte einer Backend-Spalten (`colPos`) lesen und rendern
 */
class Content implements SingletonInterface {
  
	/**
 	 * Lädt die Daten eines tt_content-Element als einfaches Array:
	 * ```
	 * \nn\t3::Content()->get( 1201 );
	 * ```
	 * Laden von Relationen (`media`, `assets`, ...)
	 * ```
	 * \nn\t3::Content()->get( 1201, true );
	 * ```
	 * 
	 * Übersetzungen / Localization:
	 * 
	 * Element NICHT automatisch übersetzen, falls eine andere Sprache eingestellt wurde
	 * ```
	 * \nn\t3::Content()->get( 1201, false, false );
	 * ```
	 * Element in einer ANDEREN Sprache holen, als im Frontend eingestellt wurde.
	 * Berücksichtigt die Fallback-Chain der Sprache, die in der Site-Config eingestellt wurde
	 * ```
	 * \nn\t3::Content()->get( 1201, false, 2 );
	 * ```
	 * Element mit eigener Fallback-Chain holen. Ignoriert dabei vollständig die Chain, 
	 * die in der Site-Config definiert wurde.
	 * ```
	 * \nn\t3::Content()->get( 1201, false, [2,3,0] );
	 * ```
	 * Eigenes Feld zur Erkennung verwenden
	 * ```
	 * \nn\t3::Content()->get( 'footer', true, true, 'content_uuid' );
	 * ``` 
	 * @param int|string $ttContentUid		Content-Uid in der Tabelle tt_content (oder string mit einem key)
	 * @param bool $getRelations			Auch Relationen / FAL holen?
	 * @param bool $localize				Übersetzen des Eintrages?
	 * @param string $localize				Übersetzen des Eintrages?
	 * @param string $field					Falls anderes Feld als `uid` verwendet werden soll
	 * @return array
	 */
	public function get( $ttContentUid = null, $getRelations = false, $localize = true, $field = 'uid' ) 
	{
		if (!$ttContentUid) return [];

		// Datensatz in der Standard-Sprache holen
		$queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
		$data = $queryBuilder
			->select('*')
			->from('tt_content')
			->andWhere($queryBuilder->expr()->eq($field, $queryBuilder->createNamedParameter($ttContentUid)))
			->executeQuery()
			->fetchAssociative();
		if (!$data) return [];

		$data = $this->localize( 'tt_content', $data, $localize );

		if ($getRelations) {
			$data = $this->addRelations( $data );
		}

		return $data;
	}

	/**
	 * Mehrere Content-Elemente (aus `tt_content`) holen.
	 * 
	 * Die Datensätze werden automatisch lokalisiert – außer `$localize` wird auf `false`
	 * gesetzt. Siehe `\nn\t3::Content()->get()` für weitere `$localize` Optionen.
	 * 
	 * Anhand einer Liste von UIDs:
	 * ```
	 * \nn\t3::Content()->getAll( 1 );
	 * \nn\t3::Content()->getAll( [1, 2, 7] );
	 * ```
	 * 
	 * Anhand von Filter-Kriterien: 
	 * ```
	 * \nn\t3::Content()->getAll( ['pid'=>1] );
	 * \nn\t3::Content()->getAll( ['pid'=>1, 'colPos'=>1] );
	 * \nn\t3::Content()->getAll( ['pid'=>1, 'CType'=>'mask_section_cards', 'colPos'=>1] );
	 * ```
	 * 
	 * @param mixed $ttContentUid	Content-Uids oder Constraints für Abfrage der Daten
	 * @param bool $getRelations	Auch Relationen / FAL holen?
	 * @param bool $localize		Übersetzen des Eintrages?
	 * @return array
	 */
   public function getAll( $constraints = [], $getRelations = false, $localize = true ) 
   {
		if (!$constraints) return [];

		// ist es eine uid-Liste, z.B. [1, 2, 3]?
		$isUidList = count(array_filter( array_flip($constraints), function ($v) {
			return !is_numeric($v);
		})) == 0;

		$results = [];

		// ... dann einfach \nn\t3::Content()->get() verwenden
		if ($isUidList) {
			foreach ($constraints as $uid) {
				if ($element = $this->get( $uid, $getRelations, $localize)) {
					$results[] = $element;
				}
			}
			return $results;
		}

		// und sonst: eine Query bauen

		// Datensatz in der Standard-Sprache holen
		$queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
		$query = $queryBuilder
			->select('*')
			->from('tt_content')
			->addOrderBy('sorting', \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING)
			->andWhere($queryBuilder->expr()->eq('sys_language_uid', 0));

		if (isset($constraints['pid'])) {
			$pid = $constraints['pid'];
			$query->andWhere($queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid)));
		}
		if (isset($constraints['colPos'])) {
			$colPos = $constraints['colPos'];
			$query->andWhere($queryBuilder->expr()->eq('colPos', $queryBuilder->createNamedParameter($colPos)));
		}
		if ($cType = $constraints['CType'] ?? false) {
			$query->andWhere($queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter($cType)));
		}

		$data =	$query->executeQuery()->fetchAllAssociative();
		if (!$data) return [];

		foreach ($data as $row) {
			if ($row = $this->localize( 'tt_content', $row, $localize )) {
				if ($getRelations) {
					$data = $this->addRelations( $data );
				}
				$results[] = $row;
			}
		}

		return $results;
   }

	/**
	 * Daten lokalisieren / übersetzen.
	 * 
	 * Beispiele:
	 * 
	 * Daten übersetzen, dabei die aktuelle Sprache des Frontends verwenden.
	 * ```
	 * \nn\t3::Content()->localize( 'tt_content', $data );
	 * ```
	 * 
	 * Daten in einer ANDEREN Sprache holen, als im Frontend eingestellt wurde.
	 * Berücksichtigt die Fallback-Chain der Sprache, die in der Site-Config eingestellt wurde
	 * ```
	 * \nn\t3::Content()->localize( 'tt_content', $data, 2 );
	 * ```
	 * 
	 * Daten mit eigener Fallback-Chain holen. Ignoriert dabei vollständig die Chain, 
	 * die in der Site-Config definiert wurde.
	 * ```
	 * \nn\t3::Content()->localize( 'tt_content', $data, [3, 2, 0] );
	 * ```
	 * @param string $table 	Datenbank-Tabelle
	 * @param array $data 		Array mit den Daten der Standard-Sprache (languageUid = 0)
	 * @param mixed $localize	Angabe, wie übersetzt werden soll. Boolean, uid oder Array mit uids
	 * @return array
	 */
	public function localize( $table = 'tt_content', $data = [], $localize = true ) 
	{
		// Irgendwas ging schief?
		$pageRepository = \nn\t3::injectClass( PageRepository::class );
		if (!$pageRepository) return $data;

		// Aktuelle Sprache aus dem Frontend-Request
		$currentLanguageUid = \nn\t3::Environment()->getLanguage();

		// `false` angegeben - oder Zielsprache ist Standardsprache? Dann nichts tun. 
		if ($localize === false || $localize === $currentLanguageUid) {
			return $data;
		}

		// `true` angegeben: Dann ist die Zielsprache == die aktuelle Sprache im FE
		if ($localize === true) {
			$localize = $currentLanguageUid;
			$fallbackChain =  \nn\t3::Environment()->getLanguageFallbackChain( $localize );
			$languageId = $localize;
		}

		// `uid` der Zielsprache angegeben (z.B. `1`)? Dann Fallback-Chain aus TYPO3-Site-Konfiguration laden
		if (is_numeric($localize)) {
			$languageId = (int) $localize;
			$fallbackChain = \nn\t3::Environment()->getLanguageFallbackChain( $localize );
		}

		// `[2,1,0]` als Zielsprachen angegeben? Dann als Fallback-Chain verwenden
		if (is_array($localize)) {
			$languageId = $localize[0] ?? 0;
			$fallbackChain = $localize;
		}

		$overlayType = LanguageAspect::OVERLAYS_ON;
		foreach ($fallbackChain as $langUid) {
			$langAspect = GeneralUtility::makeInstance( LanguageAspect::class, $langUid, $langUid, $overlayType, $fallbackChain);
			if ($overlay = $pageRepository->getLanguageOverlay( $table, $data, $langAspect )) {
				return $overlay;
			}
		}

		return $data;
	}

	/**
	 * Lädt Relationen (`media`, `assets`, ...) zu einem `tt_content`-Data-Array. 
	 * Falls `EXT:mask` installiert ist, wird die entsprechende Methode aus mask genutzt.
	 * 
	 * ```
	 * \nn\t3::Content()->addRelations( $data );
	 * ```
	 * @return array
	 */
	public function addRelations ( $data = [] ) {
		if (!$data) return [];

		if (\nn\t3::Environment()->extLoaded('mask')) {
			$maskProcessor = GeneralUtility::makeInstance( \MASK\Mask\DataProcessing\MaskProcessor::class );
			$cObjRenderer = GeneralUtility::makeInstance( \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class );
			$request = $GLOBALS['TYPO3_REQUEST'] ?? new \TYPO3\CMS\Core\Http\ServerRequest();
			$cObjRenderer->setRequest( $request );

			$dataWithRelations = $maskProcessor->process( $cObjRenderer, [], [], ['data'=>$data, 'current'=>null] );
			$data = $dataWithRelations['data'] ?: [];
		} else {
			$falFields = \nn\t3::Tca()->getFalFields('tt_content');
			$fileRepository = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\FileRepository::class);
			foreach ($falFields as $field) {
				$data[$field] = $fileRepository->findByRelation('tt_content', $field, $data['uid']);
			}
		}
		return $data;
	}

	/**
	 * Rendert ein `tt_content`-Element als HTML
	 * ```
	 * \nn\t3::Content()->render( 1201 );
	 * \nn\t3::Content()->render( 1201, ['key'=>'value'] );
	 * \nn\t3::Content()->render( 'footer', ['key'=>'value'], 'content_uuid' );
	 * ```
	 * Auch als ViewHelper vorhanden:
	 * ```
	 * {nnt3:contentElement(uid:123, data:feUser.data)}
	 * {nnt3:contentElement(uid:'footer', field:'content_uuid')}
	 * ```
	 * @return string
	 */
	public function render( $ttContentUid = null, $data = [], $field = null ) 
	{
		if (!$ttContentUid) return '';

		if ($field && $field !== 'uid') {
			$row = \nn\t3::Db()->findOneByValues('tt_content', [$field=>$ttContentUid]);
			if (!$row) return '';
			$ttContentUid = $row['uid'];
		}

		$conf = [
			'tables' => 'tt_content',
			'source' => $ttContentUid,
			'dontCheckPid' => 1
		];

		$cObj = \nn\t3::Tsfe()->cObj();
		$request = $GLOBALS['TYPO3_REQUEST'] ?? new \TYPO3\CMS\Core\Http\ServerRequest();

	
		$timeTracker = new \TYPO3\CMS\Core\TimeTracker\TimeTracker;
		$recordsContentObject = new RecordsContentObject( $timeTracker );
		$recordsContentObject->setRequest( $request );
		$recordsContentObject->setContentObjectRenderer( $cObj );

		$html = $recordsContentObject->render($conf);

		// Wenn data-Array übergeben wurde, Ergebnis erneut über Fluid Standalone-View parsen.
		if ($data) {
			$html = \nn\t3::Template()->renderHtml( $html, $data );
		}
		return $html;
	}

	/**
	 * Lädt den Content für eine bestimmte Spalte (`colPos`) und Seite.
	 * Wird keine pageUid angegeben, verwendet er die aktuelle Seite.
	 * Mit `slide` werden die Inhaltselement der übergeordnete Seite geholt, falls auf der angegeben Seiten kein Inhaltselement in der Spalte existiert.
	 * 
	 * Inhalt der `colPos = 110` von der aktuellen Seite holen:
	 * ```
	 * \nn\t3::Content()->column( 110 );
	 * ```
	 * Inhalt der `colPos = 110` von der aktuellen Seite holen. Falls auf der aktuellen Seite kein Inhalt in der Spalte ist, den Inhalt aus der übergeordneten Seite verwenden:
	 * ``` 
	 * \nn\t3::Content()->column( 110, true );
	 * ```
	 * Inhalt der `colPos = 110` von der Seite mit id `99` holen:
	 * ```
	 * \nn\t3::Content()->column( 110, 99 );
	 * ```
	 * Inhalt der `colPos = 110` von der Seite mit der id `99` holen. Falls auf Seite `99` kein Inhalt in der Spalte ist, den Inhalt aus der übergeordneten Seite der Seite `99` verwenden:
	 * ``` 
	 * \nn\t3::Content()->column( 110, 99, true );
	 * ```
	 * 
	 * Auch als ViewHelper vorhanden:
	 * ```
	 * {nnt3:content.column(colPos:110)}
	 * {nnt3:content.column(colPos:110, slide:1)}
	 * {nnt3:content.column(colPos:110, pid:99)}
	 * {nnt3:content.column(colPos:110, pid:99, slide:1)}
	 * ```
	 * @return string
	 */
	public function column( $colPos, $pageUid = null, $slide = null ) 
	{
		if ($slide === null && $pageUid === true) {
			$pageUid = null;
			$slide = true;
		}
		if (!$pageUid && !$slide) $pageUid = \nn\t3::Page()->getPid();
		$conf = [
			'table' => 'tt_content',
			'select.' => [
				'orderBy' => 'sorting',
				'where' => 'colPos=' . intval($colPos),
			],
		];
		if ($pageUid) {
			$conf['select.']['pidInList'] = intval($pageUid);
		}
		if ($slide) {
			$conf['slide'] = -1;
		}
		$html = \nn\t3::Tsfe()->cObjGetSingle('CONTENT', $conf);
		return $html;
	}

	/**
	 * Lädt die "rohen" `tt_content` Daten einer bestimmten Spalte (`colPos`).
	 * ```
	 * \nn\t3::Content()->columnData( 110 );
	 * \nn\t3::Content()->columnData( 110, true );
	 * \nn\t3::Content()->columnData( 110, true, 99 );
	 * ```
	 * Auch als ViewHelper vorhanden.
	 * `relations` steht im ViewHelper als default auf `TRUE`
	 * ```
	 * {nnt3:content.columnData(colPos:110)}
	 * {nnt3:content.columnData(colPos:110, pid:99, relations:0)}
	 * ```
	 * @return array
	 */
	public function columnData( $colPos, $addRelations = false, $pageUid = null ) {
		
		if (!$pageUid) $pageUid = \nn\t3::Page()->getPid();

		$queryBuilder = \nn\t3::Db()->getQueryBuilder('tt_content');		
		$data = $queryBuilder
			->select('*')
			->from('tt_content')
			->andWhere($queryBuilder->expr()->eq('colPos', $queryBuilder->createNamedParameter($colPos)))
			->andWhere($queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageUid)))
			->orderBy('sorting')
			->executeQuery()
			->fetchAllAssociative();
		if (!$data) return [];

		if ($addRelations) {
			foreach ($data as $n=>$row) {
				$data[$n] = $this->addRelations( $row );
			}
		}

		return $data;
	}
}