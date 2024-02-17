<?php

namespace Nng\Nnhelpers\Utilities;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Extbase\Service\CacheService;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Backend\Template\DocumentTemplate;

use Nng\Nnhelpers\Provider\PageTitleProvider;

/**
 * Alles rund um die `pages` Tabelle.
 */
class Page implements SingletonInterface 
{
	/**
	 * Daten einer Seiten holen (aus Tabelle "pages")
	 * ```
	 * \nn\t3::Page()->get( $uid );
	 * ```
	 * @return array
	 */
    public function get ( $uid = null ) {
		$pageRepository = GeneralUtility::makeInstance( PageRepository::class );
		return $pageRepository->getPage( $uid );
	}
	
	/**
	 * Daten einer Seiten holen (Tabelle `pages`).
	 * 
	 * ```
	 * // data der aktuellen Seite
	 * \nn\t3::Page()->getData();
	 * 
	 * // data der Seite mit pid = 123 holen			
	 * \nn\t3::Page()->getData( 123 );
	 *
	 * // data der Seiten mit pids = 123 und 456 holen. Key des Arrays = pid
	 * \nn\t3::Page()->getData( [123, 456] );
	 * ```
	 * @return array
	 */
    public function getData ( $pids = null ) {
		if (!$pids) $pids = $this->getPid( $pids );
		$returnArray = is_array( $pids );
		if (!$returnArray) $pids = [$pids];
		if (\nn\t3::Environment()->isFrontend()) {
			$pages = [];
			foreach ($pids as $pid) {
				$pages[$pid] = $this->get( $pid );
			}
			if (!$returnArray) $pages = array_pop( $pages );
			return $pages;
		}
		return [];
	}


	/**
	 * Einzelnes Feld aus page-Data holen.
	 * Der Wert kann per `slide = true` von übergeordneten Seiten geerbt werden.
	 * 
	 * __(!) Wichtig__: 
	 * Eigene Felder müssen in der `ext_localconf.php` als rootLine definiert werden!
	 * Siehe auch `\nn\t3::Registry()->rootLineFields(['key', '...']);` 
	 * 
	 * ```
	 * \nn\t3::Page()->getField('layout');
	 * \nn\t3::Page()->getField('backend_layout_next_level', true, 'backend_layout');
	 * ```
	 * Exisitiert auch als ViewHelper:
	 * ```
	 * {nnt3:page.data(key:'uid')}
	 * {nnt3:page.data(key:'media', slide:1)}
	 * {nnt3:page.data(key:'backend_layout_next_level', slide:1, override:'backend_layout')}
	 * ```
	 * @return mixed
	 */
	public function getField( $key, $slide = false, $override = '' ) {

		// Rootline holen. Enthält Breadcrumb aller Menüpunkte von aktueller Seite aufwärts
		$rootline = $this->getRootline();
		$currentPage = $rootline[0];

		// Kein Slide? Dann nur aktuelle Seite verwenden
		if (!$slide) {
			$rootline = array_slice($rootline, 0, 1, true);
		}

		// Override gesetzt und Wert in aktueller Seite vorhanden? Dann verwenden.
		if ($override && $currentPage[$override]) {
			$key = $override;
		}

		// Infos zum gesuchten Column aus TCA holen
		$tcaColumn = \nn\t3::TCA()->getColumn( 'pages', $key )['config'] ?? [];

		foreach ($rootline as $page) {
			
			$val = false;
			if ($page[$key]) $val = $page[$key];

			if ($val) {

				// Ist es eine SysFileReference? Dann "echtes" SysFileReference-Object zurückgeben
				// ToDo: Prüfen, ob Typ besser ermittelt werden kann
				// evtl. bei \TYPO3\CMS\Core\Utility\RootlineUtility->enrichWithRelationFields() schauen.

				$isFal = in_array($tcaColumn['type'], ['inline', 'file']); 
				if ($isFal && $tcaColumn['foreign_table'] == 'sys_file_reference') {
					$fileRepository = GeneralUtility::makeInstance( \TYPO3\CMS\Core\Resource\FileRepository::class );
					$fileObjects = $fileRepository->findByRelation('pages', $key, $page['uid']);
					return $fileObjects;
				}
				return $val;
			}
		}

		return null;
	}

	/**
	 * PID der aktuellen Seite holen.
	 * Im Frontend: Die aktuelle `TSFE->id`
	 * Im Backend: Die Seite, die im Seitenbaum ausgewählt wurde
	 * Ohne Context: Die pid der site-Root
	 * ```
	 * \nn\t3::Page()->getPid();
	 * \nn\t3::Page()->getPid( $fallbackPid );
	 * ```
	 * @return int
	 */
    public function getPid ( $fallback = null ) {

		// Normaler Frontend-Content: Alles beim alten
		if (\nn\t3::Environment()->isFrontend()) {
			return $GLOBALS['TSFE']->id;
		}

		// Versuch, PID über den Request zu bekommen
		if ($pid = $this->getPidFromRequest()) return $pid;

		// Context nicht klar, dann PID der Site-Root holen
		if ($siteRoot = $this->getSiteRoot()) return $siteRoot['uid'];

		// Letzte Chance: Fallback angegeben?
		if ($fallback) return $fallback;

		// Keine Chance
		if (\nn\t3::Environment()->isFrontend()) {
			\nn\t3::Errors()->Exception('\nn\t3::Page()->getPid() could not determine pid');
		}
	}

	/**
	 * Liste der Child-Uids einer oder mehrerer Seiten holen.
	 * ```
	 * \nn\t3::Page()->getChildPids( 123, 1 );
	 * \nn\t3::Page()->getChildPids( [123, 124], 99 );
	 * ```
	 * @return array
	 */
	public function getChildPids( $parentPid = 0, $recursive = 999 ) {
		$cObj = \nn\t3::Tsfe()->cObj();
		if (!$cObj || !$parentPid) return [];
		if (!is_array($parentPid)) $parentPid = [$parentPid];
		$mergedPids = [];
		foreach ($parentPid as $pid) {
			$childPids = \nn\t3::Arrays( $cObj->getTreeList( $pid, $recursive ) )->intExplode();
			$mergedPids = array_merge( $childPids, $mergedPids );		
		}
		return $mergedPids;
	}

	/**
	 * Einen einfachen Link zu einer Seite im Frontend generieren.
	 * 
	 * Funktioniert in jedem Kontext - sowohl aus einem Backend-Modul oder Scheduler/CLI-Job heraus, als auch im Frontend-Kontext, z.B. im Controller oder einem ViewHelper.
	 * Aus dem Backend-Kontext werden absolute URLs ins Frontend generiert. Die URLs werden als lesbare URLs kodiert - der Slug-Pfad bzw. RealURL werden berücksichtigt.
	 * 
	 * ```
	 * \nn\t3::Page()->getLink( $pid );
	 * \nn\t3::Page()->getLink( $pid, $params );		
	 * \nn\t3::Page()->getLink( $params );
	 * \nn\t3::Page()->getLink( $pid, true );
	 * \nn\t3::Page()->getLink( $pid, $params, true );
	 * \nn\t3::Page()->getLink( 'david@99grad.de' )
	 * ```
	 * 
	 * Beispiel zum Generieren eines Links an einen Controller:
	 * 
	 * __Tipp:__ siehe auch `\nn\t3::Page()->getActionLink()` für eine Kurzversion!
	 * ```
	 * $newsDetailPid = 123;
	 * $newsArticleUid = 45;
	 *  
	 * $link = \nn\t3::Page()->getLink($newsDetailPid, [
     * 	'tx_news_pi1' => [
	 * 		'action'        => 'detail',
	 * 		'controller'    => 'News',
	 * 		'news'          => $newsArticleUid,
	 * 	]
	 * ]);
	 * ```
	 * 
	 * @return string
	 */
    public function getLink ( $pidOrParams = null, $params = [], $absolute = false ) {

		$pid = is_array($pidOrParams) ? $this->getPid() : $pidOrParams;
		$params = is_array($pidOrParams) ? $pidOrParams : $params;
		if ($params === true) {
			$params = [];
			$absolute = true;
		}

		if (!\nn\t3::Environment()->isFrontend()) {
			$tsfe = \nn\t3::Tsfe()->get( $pid );

			// Im Scheduler-context vom CLI aus kann es ein Problem geben, das TSFE zu initialisieren
			// ToDo: Prüfen, ob diese Methode global für alle TYPO3-Versionen funktioniert
			if (!$tsfe) {
				$site = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Site\SiteFinder::class)->getSiteByPageId( $pid );
				$uri = (string) $site->getRouter()->generateUri( $pid, $params );
				return $uri;
			}
		}

		$request = $GLOBALS['TYPO3_REQUEST'] ?? new \TYPO3\CMS\Core\Http\ServerRequest();
		$cObj = GeneralUtility::makeInstance( \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class );
		$cObj->setRequest( $request );

		$uri = $cObj->typolink_URL([
			'parameter' => $pid,
			'forceAbsoluteUrl' => ($absolute == true),
			'additionalParams' => GeneralUtility::implodeArrayForUrl(NULL, $params),
		]);

		return $uri;
	}

	/**
	 * Link zu einer Action / Controller holen
	 * ```
	 * \nn\t3::Page()->getActionLink( $pid, $extName, $pluginName, $controllerName, $actionName, $args );
	 * ```
	 * Beispiel für die News-Extension:
	 * ```
	 * $newsArticleUid = 45;
	 * $newsDetailPid = 123;
	 * \nn\t3::Page()->getActionLink( $newsDetailPid, 'news', 'pi1', 'News', 'detail', ['news'=>$newsArticleUid]);
	 * ```
	 * @return string
	 */
	public function getActionLink( $pid = null, $extensionName = '', $pluginName = '', $controllerName = '', $actionName = '', $params = [], $absolute = false ) {
		$extensionService = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Service\ExtensionService::class);
		$argumentsPrefix = $extensionService->getPluginNamespace($extensionName, $pluginName);
		$arguments = [
			$argumentsPrefix => [
				'action' => $actionName,
				'controller' => $controllerName,
			],
		];
		$arguments[$argumentsPrefix] = array_merge($arguments[$argumentsPrefix], $params);
		return $this->getLink( $pid, $arguments, $absolute );
	}

	/**
	 * Einen absoluten Link zu einer Seite generieren
	 * ```
	 * \nn\t3::Page()->getAbsLink( $pid );
	 * \nn\t3::Page()->getAbsLink( $pid, ['type'=>'232322'] );
	 * \nn\t3::Page()->getAbsLink( ['type'=>'232322'] );
	 * ```
	 * @return string
	 */
	public function getAbsLink( $pidOrParams = null, $params = [] ) {
		return $this->getLink( $pidOrParams, $params, true );
	}

	/**
	 * PID der Site-Root(s) holen.
	 * Entspricht der Seite im Backend, die die "Weltkugel" als Symbol hat 
	 * (in den Seiteneigenschaften "als Anfang der Webseite nutzen")
	 * ```
	 * \nn\t3::Page()->getSiteRoot();
	 * ```
	 * @return int
	 */
	public function getSiteRoot( $returnAll = false ) {
		$queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
		$queryBuilder
			->select('*')
			->from('pages')
			->andWhere($queryBuilder->expr()->eq('is_siteroot', '1'));
		
		if ($returnAll) return $queryBuilder->executeQuery()->fetchAllAssociative();
		return $queryBuilder->executeQuery()->fetchAssociative();
	}


	/**
	 * PID aus Request-String holen, z.B. in Backend Modulen.
	 * Hacky. ToDo: Prüfen, ob es eine bessere Methode gibt.
	 * ```
	 * \nn\t3::Page()->getPidFromRequest();
	 * ```
	 * @return int
	 */
	public function getPidFromRequest () {
		$pageUid = $GLOBALS['_REQUEST']['popViewId'] ?? false;
		if (!$pageUid) $pageUid = preg_replace( '/(.*)(id=)([0-9]*)(.*)/i', '\\3', $GLOBALS['_REQUEST']['returnUrl'] ?? '' );
		if (!$pageUid) $pageUid = preg_replace( '/(.*)(id=)([0-9]*)(.*)/i', '\\3', $GLOBALS['_POST']['returnUrl'] ?? '' );
		if (!$pageUid) $pageUid = preg_replace( '/(.*)(id=)([0-9]*)(.*)/i', '\\3', $GLOBALS['_GET']['returnUrl'] ?? '' );
		if (!$pageUid && ($_GET['edit']['pages'] ?? false)) $pageUid = array_keys($_GET['edit']['pages'] ?? [])[0];
		if (!$pageUid) $pageUid = $_GET['id'] ?? 0;
		return (int) $pageUid;
	}

	/**
	 * Rootline für gegebene PID holen
	 * ```
	 * \nn\t3::Page()->getRootline();
	 * ```
	 * @return array
	 */
	public function getRootline( $pid = null ) {
		if (!$pid) $pid = $this->getPid();
		if (!$pid) return [];
		$rootLine = GeneralUtility::makeInstance(RootlineUtility::class, $pid);
		return $rootLine->get() ?: [];
	}
	
	/**
	 * Menü für gegebene PID holen
	 * ```
	 * \nn\t3::Page()->getSubpages();
	 * \nn\t3::Page()->getSubpages( $pid );
	 * \nn\t3::Page()->getSubpages( $pid, true );	// Auch versteckte Seiten holen
	 * ```
	 * @return array
	 */
	public function getSubpages( $pid = null, $includeHidden = false ) {

		if (!$pid) $pid = $this->getPid();
		if (!$pid) return [];
		$page = GeneralUtility::makeInstance( PageRepository::class );

		$hideTypes = [
			$page::DOKTYPE_SPACER, 
			$page::DOKTYPE_BE_USER_SECTION,
			$page::DOKTYPE_RECYCLER,
			$page::DOKTYPE_SYSFOLDER
		];

		$constraints = [];
        $constraints[] = 'hidden = 0';
        $constraints[] = 'doktype NOT IN (' . join(',', $hideTypes) . ')';
		
        if (!$includeHidden) {
    		$constraints[] = 'nav_hide = 0';
		}
				
		return $page->getMenu( $pid, '*', 'sorting', join(' AND ', $constraints) );
	}
	
	/**
	 * Prüft, ob eine Seite Untermenüs hat
	 * ```
	 * \nn\t3::Page()->hasSubpages();
	 * ```
	 * @return boolean
	 */
	public function hasSubpages( $pid = null ) {
		return count( $this->getSubpages($pid) ) > 0;
	}


	/**
	 * PageTitle (<title>-Tag) ändern
	 * Funktioniert __nicht__, wenn EXT:advancedtitle aktiviert ist!
	 * ```
	 * \nn\t3::Page()->setTitle('YEAH!');
	 * ```
	 * Auch als ViewHelper vorhanden:
	 * ```
	 * {nnt3:page.title(title:'Yeah')}
	 * {entry.title->nnt3:page.title()}
	 * ```
	 * @return void
	 */
	public function setTitle ( $title = '' ) {
		$titleProvider = GeneralUtility::makeInstance( PageTitleProvider::class );
		$titleProvider->setTitle( htmlspecialchars(strip_tags($title)) );
	}
	
	/**
	 * Aktuellen Page-Title (ohne Suffix) holen
	 * ```
	 * \nn\t3::Page()->getTitle();
	 * ```
	 * @return string
	 */
	public function getTitle () {
		$titleProvider = GeneralUtility::makeInstance( PageTitleProvider::class );
		return $titleProvider->getRawTitle();
	}

	/**
	 * Page-Renderer holen
	 * ```
	 * \nn\t3::Page()->getPageRenderer();
	 * ```
	 * @return PageRenderer
	 */
	public function getPageRenderer() {
		if (\nn\t3::Environment()->isFrontend()) {
			return GeneralUtility::makeInstance( PageRenderer::class );
		}
		return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Page\PageRenderer::class);
	}

	/**
	 * HTML-Code in `<head>` einschleusen
	 * Siehe `\nn\t3::Page()->addHeader()` für einfachere Version.
	 * ```
	 * \nn\t3::Page()->addHeaderData( '<script src="..."></script>' );
	 * ```
	 * @return void
	 */
	public function addHeaderData( $html = '' ) {
		$this->getPageRenderer()->addHeaderData( $html );
	}
	
	/**
	 * HTML-Code vor Ende der `<body>` einschleusen
	 * Siehe `\nn\t3::Page()->addFooter()` für einfachere Version.
	 * ```
	 * \nn\t3::Page()->addFooterData( '<script src="..."></script>' );
	 * ```
	 * @return void
	 */
	public function addFooterData( $html = '' ) {
		$this->getPageRenderer()->addFooterData( $html );
	}

	/**
	 * JS-Library in `<head>` einschleusen.
	 * ```
	 * \nn\t3::Page()->addJsLibrary( 'path/to/file.js' );
	 * ```
	 * @return void
	 */
	public function addJsLibrary($path, $compress = false, $atTop = false, $wrap = false, $concat = false ) {
		$pageRenderer = $this->getPageRenderer();
		$pageRenderer->addJsLibrary( $path, $path, NULL, $compress, $atTop, $wrap, $concat );
	}
	
	/**
	 * JS-Library am Ende der `<body>` einschleusen
	 * ```
	 * \nn\t3::Page()->addJsFooterLibrary( 'path/to/file.js' );
	 * ```
	 * @return void
	 */
	public function addJsFooterLibrary($path, $compress = false, $atTop = false, $wrap = false, $concat = false ) {
		$this->getPageRenderer()->addJsFooterLibrary( $path, $path, NULL, $compress, $atTop, $wrap, $concat );
	}

	/**
	 * JS-Datei in `<head>` einschleusen
	 * Siehe `\nn\t3::Page()->addHeader()` für einfachere Version.
	 * ```
	 * \nn\t3::Page()->addJsFile( 'path/to/file.js' );
	 * ```
	 * @return void
	 */
	public function addJsFile($path, $compress = false, $atTop = false, $wrap = false, $concat = false ) {
		$pageRenderer = $this->getPageRenderer();
		$pageRenderer->addJsFile( $path, NULL, $compress, $atTop, $wrap, $concat );
	}
	
	/**
	 * JS-Datei am Ende der ``<body>`` einschleusen
	 * Siehe `\nn\t3::Page()->addJsFooterFile()` für einfachere Version.
	 * ```
	 * \nn\t3::Page()->addJsFooterFile( 'path/to/file.js' );
	 * ```
	 * @return void
	 */
	public function addJsFooterFile($path, $compress = false, $atTop = false, $wrap = false, $concat = false ) {
		$this->getPageRenderer()->addJsFooterFile( $path, NULL, $compress, $atTop, $wrap, $concat );
	}
	
	/**
	 * CSS-Library in `<head>` einschleusen
	 * ```
	 * \nn\t3::Page()->addCssLibrary( 'path/to/style.css' );
	 * ```
	 * @return void
	 */
	public function addCssLibrary($path, $compress = false, $atTop = false, $wrap = false, $concat = false ) {
		$this->getPageRenderer()->addCssLibrary( $path, 'stylesheet', 'all', '', $compress, $atTop, $wrap, $concat );
	}

	/**
	 * CSS-Datei in `<head>` einschleusen
	 * Siehe `\nn\t3::Page()->addHeader()` für einfachere Version.
	 * ```
	 * \nn\t3::Page()->addCss( 'path/to/style.css' );
	 * ```
	 * @return void
	 */
	public function addCssFile($path, $compress = false, $atTop = false, $wrap = false, $concat = false ) {
		$this->getPageRenderer()->addCssFile( $path, 'stylesheet', 'all', '', $compress, $atTop, $wrap, $concat );
	}

	/**
	 * CSS oder JS oder HTML-Code an Footer anhängen.
	 * Entscheidet selbst, welche Methode des PageRenderes zu verwenden ist.
	 * ```
	 * \nn\t3::Page()->addFooter( 'fileadmin/style.css' );
	 * \nn\t3::Page()->addFooter( ['fileadmin/style.css', 'js/script.js'] );
	 * \nn\t3::Page()->addFooter( 'js/script.js' );
	 * ```
	 * @return void
	 */
	public function addFooter ( $str = '' ) {
		if (!is_array($str)) $str = [$str];
		foreach ($str as $n) {
			if (strpos($n, '<') !== false) {
				$this->addFooterData( $n );
			} else {
				$suffix = \nn\t3::File()->suffix($n);
				if ($suffix == 'js') {
					$this->addJsFooterFile( $n );
				} else {
					// addCssFooterFile() scheint nicht zu existieren
					$this->addCssLibrary( $n );
				}
			}
			
		}
	}
	
	/**
	 * CSS oder JS oder HTML-Code an Footer anhängen.
	 * Entscheidet selbst, welche Methode des PageRenderes zu verwenden ist.
	 * ```
	 * \nn\t3::Page()->addHeader( 'fileadmin/style.css' );
	 * \nn\t3::Page()->addHeader( ['fileadmin/style.css', 'js/script.js'] );
	 * \nn\t3::Page()->addHeader( 'js/script.js' );
	 * \nn\t3::Page()->addHeader( '<script>....</script>' );
	 * ```
	 * @return void
	 */
	public function addHeader ( $str = '' ) {
		if (!is_array($str)) $str = [$str];
		foreach ($str as $n) {
			if (strpos($n, '<') !== false) {
				$this->addHeaderData( $n );
			} else {
				$suffix = \nn\t3::File()->suffix($n);
				if ($suffix == 'js') {
					$this->addJsFile( $n );
				} else {
					// addCssFooterFile() scheint nicht zu existieren
					$this->addCssLibrary( $n );
				}
			}
			
		}
	}

	/**
	 * Seiten-Cache einer (oder mehrerer) Seiten löschen
	 * ```
	 * \nn\t3::Page()->clearCache( $pid );
	 * \nn\t3::Page()->clearCache( [1,2,3] );
	 * \nn\t3::Page()->clearCache();
	 * ```
	 *  @return void
	 */
    public function clearCache ( $pid = null ) {
		$pidList = \nn\t3::Arrays($pid ?: 'all')->trimExplode();

		if (\nn\t3::Environment()->isFrontend()) {

			// Im Frontend-Context
			$cacheService = GeneralUtility::makeInstance( CacheService::class );
			$cacheManager = GeneralUtility::makeInstance( CacheManager::class );
			foreach ($pidList as $pid) {
				if ($pid == 'all') {
					$cacheService->clearCachesOfRegisteredPageIds();
					$cacheService->clearPageCache();
				} else {
					$cacheService->clearPageCache($pid);
					$cacheManager->flushCachesInGroupByTags('pages', [ 'pageId_'.$pid ]);
					$cacheService->getPageIdStack()->push($pid);
					$cacheService->clearCachesOfRegisteredPageIds();
				}
			}

		} else {

			// Im Backend-Context kann der DataHandler verwendet werden
			$dataHandler = GeneralUtility::makeInstance( DataHandler::class );
			$dataHandler->admin = 1;
			$dataHandler->start([], []);

			foreach ($pidList as $pid) {
				$dataHandler->clear_cacheCmd($pid);
			}
		}
	}

}