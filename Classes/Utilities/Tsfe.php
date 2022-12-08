<?php

namespace Nng\Nnhelpers\Utilities;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Form\Mvc\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;

use TYPO3\CMS\Core\Http\ServerRequestFactory;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;

/**
 * Alles rund um das Typo3 Frontend.
 * Methoden zum Initialisieren des FE aus dem Backend-Context, Zugriff auf das
 * cObj und cObjData etc.
 */
class Tsfe implements SingletonInterface {


	/**
	 * 	$GLOBALS['TSFE'] holen.
	 * 	Falls nicht vorhanden (weil im BE) initialisieren.
	 *	```
	 *	\nn\t3::Tsfe()->get()
	 *	\nn\t3::Tsfe()->get( $pid )
	 *	```
	 *	@return \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController
	 */
	public function get( $pid = null ) {
		if (!isset($GLOBALS['TSFE'])) $this->init( $pid );
		return $GLOBALS['TSFE'] ?? '';
	}
	
	/**
	 * 	$GLOBALS['TSFE']->cObj holen.
	 *	```
	 *	\nn\t3::Tsfe()->cObj()
	 *	```
	 *	@return \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
	 */
	public function cObj() {
		if (!isset($GLOBALS['TSFE'])) $this->init();
		$configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
		if ($cObj = $GLOBALS['TSFE']->cObj) return $cObj;
		if ($cObj = $configurationManager->getContentObject()) return $cObj;
		return GeneralUtility::makeInstance(ContentObjectRenderer::class);
	}
	
	/**
	 * 	$GLOBALS['TSFE']->cObj->data holen.
	 *	```
	 *	\nn\t3::Tsfe()->cObjData();			=> array mit DB-row des aktuellen Content-Elementes
	 *	\nn\t3::Tsfe()->cObjData('uid');	=> uid des aktuellen Content-Elements
	 *	```
	 *	@return mixed	
	 */
	public function cObjData( $var = null ) {
		$cObj = $this->cObj();
		if (!$cObj) return false;
		return $var ? $cObj->data[$var] : $cObj->data;
	}
	
	/**
	 *	Ein TypoScript-Object rendern.
	 *	Früher: `$GLOBALS['TSFE']->cObj->cObjGetSingle()`
	 *	```
	 *	\nn\t3::Tsfe()->cObjGetSingle('IMG_RESOURCE', ['file'=>'bild.jpg', 'file.'=>['maxWidth'=>200]] )
	 *	```
	 */
	public function cObjGetSingle( $type = '', $conf = [] ) {
		return $this->cObj()->cObjGetSingle( $type, $conf );
	}

	/**
	 * 	Das TSFE initialisieren.
	 *	Funktioniert auch im Backend-Context, z.B. innerhalb eines
	 *	Backend-Moduls oder Scheduler-Jobs.	
	 *	```
	 *	\nn\t3::Tsfe()->init();
	 *	```
	 */
	public function init($pid = 0, $typeNum = 0) 
	{
		if (isset($GLOBALS['TSFE'])) {
			return;
		}

		if (!$pid) $pid = \nn\t3::Page()->getPid();

		try {
				
			$isCli = \TYPO3\CMS\Core\Core\Environment::isCli();
			$request = null;

			if (!$isCli) {
				$request = &$GLOBALS['TYPO3_REQUEST'] ?? ServerRequestFactory::fromGlobals();
				if (!$GLOBALS['TYPO3_REQUEST']) {
					$GLOBALS['TYPO3_REQUEST'] = &$request;
				}
			}

			$site = $request ? $request->getAttribute('site') : null;
			if (!$site instanceof Site) {
				$sites = GeneralUtility::makeInstance(SiteFinder::class)->getAllSites();
				$site = reset($sites);
				if (!$site instanceof Site) {
					$site = new NullSite();
				}
			}

			if (!$request) {
				$request = &GeneralUtility::makeInstance(ServerRequestFactory::class)->createServerRequest('GET', $site->getBase(), [] );
			}

			$language = $request->getAttribute('language');
			if (!($language instanceof SiteLanguage)) {
				$language = $site->getDefaultLanguage();
			}

			$id = $request->getQueryParams()['id'] ?? $request->getParsedBody()['id'] ?? $site->getRootPageId();
			$type = $request->getQueryParams()['type'] ?? $request->getParsedBody()['type'] ?? '0';
			
			$feUserAuth = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication::class);
			
			$pageArgumentsFromRouting = $request->getAttribute('routing') ?? false;
			$pageArguments = is_a($pageArgumentsFromRouting, PageArguments::class) 
				? $pageArgumentsFromRouting : new PageArguments( (int)$id, (string)$type, [] );


			$controller = GeneralUtility::makeInstance(
				TypoScriptFrontendController::class,
				GeneralUtility::makeInstance(Context::class),
				$site,
				$language,
				$pageArguments,
				$feUserAuth
			);
			$controller->determineId( $request );
			
			// @todo: Prüfen, ob weitere Initialisierung erforderlich sind.
			// Guter Startpunkt: EXT:redirects/Classes/Service/RedirectService-->bootFrontendController() 

			$contentObject = GeneralUtility::makeInstance(ContentObjectRenderer::class);
			$contentObject->setRequest( $request );
			$contentObject->start([]);

			$request = $request->withAttribute('frontend.controller', $controller);
			$request = $controller->getFromCache($request);

			$configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
			$setup = $configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);

			$GLOBALS['TSFE'] = $controller;
			$GLOBALS['TSFE']->sys_page = GeneralUtility::makeInstance(PageRepository::class);
			$GLOBALS['TSFE']->tmpl = GeneralUtility::makeInstance(TemplateService::class);

			$GLOBALS['TSFE']->tmpl->setup = $setup;

			// $contentObject->cObjectDepthCounter = 100;

			$GLOBALS['TSFE']->cObj = $contentObject;
			
			$userSessionManager = \TYPO3\CMS\Core\Session\UserSessionManager::create('FE');
			$userSession = $userSessionManager->createAnonymousSession();
			$GLOBALS['TSFE']->fe_user = $userSession;
			
			$GLOBALS['TSFE']->register['SYS_LASTCHANGED'] = 0;
			
			// Fixes `Invoked ContentObjectRenderer::parseFunc without any configuration` when rendering Content Elements in a Backend context
			// by disabling the IF condition for `$tsfeBackup = self::simulateFrontendEnvironment()` in the `TYPO3\CMS\Fluid\ViewHelpers\Format\HtmlViewHelper`
			// $request = $request
			// 	->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
			// 	->withAttribute('frontend.controller', $GLOBALS['TSFE']);

		} catch ( \Exception $e ) {

			// Wenn das TSFE nicht initialisiert werden konnte, liegt das evtl. daran dass:
			// - die Root-Seite gesperrt ist
			// - die Root-Seite nur für fe_user zugänglich ist 

		}
	}


	/**
	 * 	Bootstrap Typo3
	 *	```
	 *	\nn\t3::Tsfe()->bootstrap();
	 *	\nn\t3::Tsfe()->bootstrap( ['vendorName'=>'Nng', 'extensionName'=>'Nnhelpers', 'pluginName'=>'Foo'] );
	 *	```
	 */
	public function bootstrap ( $conf = [] ) {
		$bootstrap = new \TYPO3\CMS\Extbase\Core\Bootstrap();
		if (!$conf) {
			$conf = [
				'vendorName'	=> 'Nng',
				'extensionName'	=> 'Nnhelpers',
				'pluginName'	=> 'Foo',
			];
		}
		$bootstrap->initialize($conf);
	}
}