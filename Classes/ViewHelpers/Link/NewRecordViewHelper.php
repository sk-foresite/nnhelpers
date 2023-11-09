<?php
namespace Nng\Nnhelpers\ViewHelpers\Link;

use Nng\Nnhelpers\ViewHelpers\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;
use TYPO3\CMS\Fluid\ViewHelpers\Link\TypolinkViewHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Link zum Erstellen eines neuen Datensatzes in einem Backend-Modul generieren.
 * ```
 * <nnt3:link.newRecord afterUid="{item.uid}" pid="" table="tx_myext_domain_model_entry" returnUrl="...">
 * 	<i class="fas fa-plus"></i>
 * </nnt3:link.newRecord>
 * ```
 * Alternativ kann auch der Core-ViewHelper genutzt werden:
 * ```
 * {namespace be=TYPO3\CMS\Backend\ViewHelpers}
 * <be:link.newRecord uid="42" table="a_table" returnUrl="foo/bar" />
 * ```
 */
class NewRecordViewHelper extends AbstractViewHelper {
	
	public function initializeArguments() {
		parent::initializeArguments();
		$this->registerArgument('parameter', 'string', 'Parameter für Typolink', false);
		$this->registerArgument('table', 'string', 'DB Tabellen-Name', true);
		$this->registerArgument('returnUrl', 'string', 'Nach Schließen des Editors: Zu welcher URL zurückkehren?', false);
		$this->registerArgument('pid', 'string', 'PID auf der das Element erzeugt werden soll.', false);
		$this->registerArgument('data', 'array', 'additional data attributes', false, []);
   }

   // https://tetronik-web2print.99grad.dev/typo3/record/edit?token=293233e1863f8eb271a3695939771986dd4be2ef&edit%5Btx_nntetronik_domain_model_product%5D%5B7%5D=new&returnUrl=%2Ftypo3%2Fmodule%2Fweb%2Flist%3Ftoken%3D149dcf8145cc38c0ecaa3952952695388e6203b9%26id%3D7%26table%3D%26pointer%3D1

	public static function renderStatic( array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext ) 
	{			
		$args = ['table', 'uid', 'returnUrl', 'pid', 'data'];

		foreach ($args as $arg) {
			$$arg = $arguments[$arg] ?? '';
		}

		if (!$returnUrl) {
			$returnUrl = \nn\t3::Request()->getUri();
		}
		
		$pid = $pid ?: \nn\t3::Page()->getPid();

		$uriBuilder = \nn\t3::injectClass( \TYPO3\CMS\Backend\Routing\UriBuilder::class );

		$uri = $uriBuilder->buildUriFromRoute( 'record_edit', [
			'returnUrl'	=> $returnUrl,
			'edit' 		=> [$table=>[$pid=>'new']]
		]);
		
		// data="{ajax:1}" in additionalAttributes für TypolinkViewHelper konvertieren.
		$dataAttr = [];
		foreach ($data as $k=>$v) {
			$dataAttr["data-{$k}"] = (string) $v;
		}
	
		$arguments['parameter'] = $uri;
		$arguments['additionalAttributes'] = array_merge( $arguments['additionalAttributes'] ?? [], $dataAttr );

		return TypolinkViewHelper::renderStatic( $arguments, $renderChildrenClosure, $renderingContext );
	}
	
}