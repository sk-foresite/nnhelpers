<?php

namespace Nng\Nnhelpers\Controller;

use Nng\Nnhelpers\Domain\Repository\EntryRepository;
use Nng\Nnhelpers\Helpers\DocumentationHelper;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use TYPO3\CMS\Extbase\Utility\FrontendSimulatorUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class ModuleController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController 
{
	protected ModuleTemplateFactory $moduleTemplateFactory;
    protected PageRenderer $pageRenderer;

	public function __construct(
		ModuleTemplateFactory $moduleTemplateFactory,
        UriBuilder $uriBuilder,
        PageRenderer $pageRenderer
    ) {
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->uriBuilder = $uriBuilder;
        $this->pageRenderer = $pageRenderer;
    }

	/** 
	 * 	Cache des Source-Codes für die Doku
	 * 	@var array
	 */
	protected $sourceCodeCache = [];
	protected $maxTranslationsPerLoad = 10;


	/**
	 * Sprache der Annotations und
	 * 
	 * Zielsprache der Doku für das Typo3 TER wird über die Spracheinstellung des Backend-Users bestimmt. 
	 * Dadurch kann die gesamte Doku leicht in verschiedenen Sprachen übersetzt werden.
	 * 
	 */
	protected $sourceLang = 'de';

	/**
	 * 	Initialize View
	 * 
	 */
	public function initializeView () 
	{
		$this->moduleView = $this->moduleTemplateFactory->create($this->request);
		$this->moduleView->getDocHeaderComponent()->disable();

		$this->pageRenderer->loadJavaScriptModule('@vendor/nnhelpers/NnhelpersBackendModule.js');
		
		$this->pageRenderer->addCssFile('EXT:nnhelpers/Resources/Public/Vendor/fontawesome/css/all.css');
		$this->pageRenderer->addCssFile('EXT:nnhelpers/Resources/Public/Vendor/bootstrap/bootstrap.min.css');
		$this->pageRenderer->addCssFile('EXT:nnhelpers/Resources/Public/Vendor/prism/prism.css');
		$this->pageRenderer->addCssFile('EXT:nnhelpers/Resources/Public/Css/styles.css');
		$this->pageRenderer->addJsFile('EXT:nnhelpers/Resources/Public/Vendor/prism/prism.js');
	}

	/**
	 * @return void
	 */
	public function indexAction (): ResponseInterface
	{	
		$args = $this->request->getArguments();
		$isDevMode = \nn\t3::Environment()->getExtConf('nnhelpers', 'devModeEnabled');
		$updateTranslation = $args['updateTranslation'] ?? false;
		$enableCache = !$updateTranslation && !$isDevMode;

		$beUserLang = $GLOBALS['BE_USER']->user['lang'] ?: 'en';

		if ($beUserLang == 'default') $beUserLang = 'en';
		
		if ($enableCache && $html = \nn\t3::Cache()->get([__METHOD__=>$beUserLang])) {
			
			// nothing to do. Page generated from cache.

		} else {

			// Composer libraries laden (z.B. Markdown)
			$autoload = \nn\t3::Environment()->extPath('nnhelpers') . 'Resources/Libraries/vendor/autoload.php';
			require_once( $autoload );

			$doc = $this->generateDocumentation();
			$docViewhelper = $this->generateViewhelperDocumentation();
			$docAdditional = $this->generateAdditionalClassesDocumentation();

			$this->localizeDocumentation( $doc, $beUserLang );
			$this->localizeDocumentation( $docViewhelper, $beUserLang );
			$this->localizeDocumentation( $docAdditional, $beUserLang );

			$this->view->assignMultiple([
				'version'			=> ExtensionManagementUtility::getExtensionVersion('nnhelpers'),
				'documentation' 	=> $doc,
				'viewhelpers'		=> $docViewhelper,
				'additional'		=> $docAdditional,
				'docLang' 			=> $beUserLang,
				'docSrcLang' 		=> $this->sourceLang,
				'updateTranslation' => $updateTranslation,
			]);

			$html = $this->view->render();
			\nn\t3::Cache()->set([__METHOD__=>$beUserLang], $html);
		}

		$this->moduleView->assignMultiple(['content'=>$html]);
		return $this->moduleView->renderResponse( 'Backend/BackendModule' );
	}
	
	/**
	 * 	Die Dokumentation aus den PHP-Annotations generieren
	 * 
	 * 	@return array
	 */
	public function generateDocumentation () {

		$path = \nn\t3::Environment()->extPath('nnhelpers') . 'Classes/Utilities/';
		$classes = DocumentationHelper::parseFolder( $path );

		$strip = ['Nng\Nnhelpers\Utilities\\'];

		foreach ($classes as $className=>&$info) {
			$info = array_merge($info, $this->createNamespacesForClassname($className, $strip));
			foreach ($info['methods'] as $methodName=>&$methodInfo) {
				$methodInfo = array_merge($methodInfo, $this->createNamespacesForClassname($methodName, $strip));
			}
		}
		
		return $classes;
	}

	/**
	 * Dokumentation der ViewHelper generieren
	 * Mit `@hideFromDocumentation' kann Klasse aus Doku ausgeschlossen werden.
	 * Berücksichtigt nur den Class-Comment überhalb der Klassen-Definition.
	 * 
	 * @return array
	 */
	public function generateViewhelperDocumentation() {

		$path = \nn\t3::Environment()->extPath('nnhelpers') . 'Classes/ViewHelpers/';
		$classes = DocumentationHelper::parseFolder( $path, ['parseMethods'=>false] );

		$strip = ['Nng\Nnhelpers\ViewHelpers\\', 'ViewHelper'];

		foreach ($classes as $className=>&$info) {
			$info = array_merge($info, $this->createNamespacesForClassname($className, $strip));
		}

		return $classes;
	}
	
	/**
	 * Dokumentation der zusätzliche Klassen generieren
	 * 
	 * @return array
	 */
	public function generateAdditionalClassesDocumentation() {

		$path = \nn\t3::Environment()->extPath('nnhelpers') . 'Classes/Helpers/';
		$classes = DocumentationHelper::parseFolder( $path );

		$strip = ['Nng\Nnhelpers\Helpers\\'];

		foreach ($classes as $className=>&$info) {
			$info = array_merge($info, $this->createNamespacesForClassname($className, $strip));
			foreach ($info['methods'] as $methodName=>&$methodInfo) {
				$methodInfo = array_merge($methodInfo, $this->createNamespacesForClassname($methodName, $strip));
			}
		}
		
		return $classes;
	}

	/**
	 * Varianten der Schreibweise für einen Klassennamen generieren.
	 * Für die bessere Darstellung in der Doku
	 * 
	 * @return array
	 */
	public function createNamespacesForClassname( $className = '', $strip = [] ) {

		// Format\WhatEver
		$classNameShort = $className;
		foreach ($strip as $str) {
			$classNameShort = str_ireplace($str, '', $classNameShort);
		}

		// Format/WhatEver
		$classNameSlash = str_replace('\\', '/', $classNameShort);

		// format.whatEver
		$vhName = join('.', array_map( function ($str) { return lcfirst($str); }, explode('\\', $classNameShort) ));

		return [
			'classNameShort'		=> $classNameShort,
			'classNameSlash'		=> $classNameSlash,
			'vhName'				=> $vhName,
		];
	}


	/**
	 * Exportiert die Doku aller Methoden für die ReST Dokumentation im TER
	 * 
	 */
	function exportDocumentationAction() {
		$this->exportDocumentationActionForLanguage('en');
		$this->exportDocumentationActionForLanguage('de', 'Localization.de_DE/');
		return $this->htmlResponse('');
	}

	/**
	 * Exportiert die Doku aller Methoden für die ReST Dokumentation im TER
	 * 
	 */
	function exportDocumentationActionForLanguage( $language = 'en', $path = '' ) {

		$autoload = \nn\t3::Environment()->extPath('nnhelpers') . 'Resources/Libraries/vendor/autoload.php';
		require_once( $autoload );

		echo "<pre style='max-height:100vh; overflow: scroll;'>
			<h1>Doku für `{$language}` generieren:</h1>
		\n\n";

		echo "\n\n<h3>Export der Klassen:</h3>\n";

		$doc = $this->generateDocumentation();
		if ($language != 'de') {
			$this->localizeDocumentation( $doc, $language, true );
		}

		foreach ($doc as $className=>$infos) {
			$rendering = \nn\t3::Template()->render(
				'EXT:nnhelpers/Resources/Private/Backend/Templates/Documentation/ClassTemplate.html', [
					'className' => $className,
					'infos'		=> $infos
				]
			);
			$rendering = preg_replace("/(\r?\n){2,}/", "\n\n", $rendering);

			$filename = $infos['vhName'] . '.rst';
			$file = \nn\t3::File()->absPath('EXT:nnhelpers/Documentation/' . $path . 'Helpers/Classes/' . $filename);
			
			$result = file_put_contents( $file, $rendering );
			if (!$result) {
				echo "\n( !! ) Classes: {$filename} konnte nicht geschrieben werden. Ordner-Rechte?";
			} else {
				echo "\n" . $filename;
			}
		}
		
		echo "\n\n<h3>Export der zusätzlichen Helper:</h3>\n";

		$doc = $this->generateAdditionalClassesDocumentation();
		if ($language != 'de') {
			$this->localizeDocumentation( $doc, $language, true );
		}

		foreach ($doc as $className=>$infos) {
			$rendering = \nn\t3::Template()->render(
				'EXT:nnhelpers/Resources/Private/Backend/Templates/Documentation/AdditionalClassTemplate.html', [
					'className' => $className,
					'infos'		=> $infos
				]
			);
			$rendering = preg_replace("/(\r?\n){2,}/", "\n\n", $rendering);

			$filename = $infos['vhName'] . '.rst';
			$file = \nn\t3::File()->absPath('EXT:nnhelpers/Documentation/' . $path . 'AdditionalClasses/Classes/' . $filename);
			
			$result = file_put_contents( $file, $rendering );
			if (!$result) {
				echo "\n( !! ) AdditionalClasses: {$filename} konnte nicht geschrieben werden. Ordner-Rechte?";
			} else {
				echo "\n" . $filename;
			}
		}

		echo "\n\n<h3>Export der ViewHelper:</h3>\n";
		$docViewhelper = $this->generateViewhelperDocumentation();
		if ($language != 'de') {
			$this->localizeDocumentation( $docViewhelper, $language, true );
		}

		foreach ($docViewhelper as $className=>$infos) {
			$rendering = \nn\t3::Template()->render(
				'EXT:nnhelpers/Resources/Private/Backend/Templates/Documentation/ViewHelperTemplate.html', [
					'className' => $className,
					'infos'		=> $infos
				]
			);
			$rendering = preg_replace("/(\r?\n){2,}/", "\n\n", $rendering);

			$filename = $infos['vhName'] . '.rst';
			$file = \nn\t3::File()->absPath('EXT:nnhelpers/Documentation/' . $path . 'ViewHelpers/Classes/' . $filename);
			$result = file_put_contents( $file, $rendering );
			
			if (!$result) {
				echo "\n( !! ) ViewHelper: {$filename} konnte nicht geschrieben werden. Ordner-Rechte?";
			} else {
				echo "\n" . $filename;
			}
			
		}
		
		return '';
	}


	/**
	 * Übersetzt die Dokumentation in Zielsprache.
	 * Verwendet Deep-L und smartes Caching.
	 * 
	 * @return array
	 */
	function localizeDocumentation ( &$doc = [], $targetLang = 'de', $autoTranslate = false ) {

		if (strtolower($targetLang) == strtolower($this->sourceLang)) return $doc;
		$targetDocLang = strtoupper($targetLang);

		$translationHelper = \nn\t3::injectClass( \Nng\Nnhelpers\Helpers\TranslationHelper::class );
		$translationHelper->setL18nFolderpath( 'EXT:nnhelpers/Resources/Private/Language/' );
		$translationHelper->setTargetLanguage( $targetDocLang );

		$translationHelper->setEnableApi( $autoTranslate );
		$translationHelper->setMaxTranslations( $this->maxTranslationsPerLoad );

		foreach ($doc as $className=>$infos) {
			$doc[$className]['comment'] = $translationHelper->translate([$className, 'comment'], $infos['comment']);
			foreach ($infos['methods'] as $methodName=>$methodInfos) {
				$doc[$className]['methods'][$methodName]['comment'] = $translationHelper->translate([$className, $methodName, 'comment'], $methodInfos['comment']);
			}
		}
		return $doc;
	}




}
