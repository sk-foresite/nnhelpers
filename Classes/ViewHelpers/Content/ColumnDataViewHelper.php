<?php

namespace  Nng\Nnhelpers\ViewHelpers\Content;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Lädt die rohen Daten einer Spalte (colPos) des Backend Layouts.
 * 
 * Es handelt sich hier um das rohe `tt_content`-data-Array einer Spalte (colPos) aus dem Backend-Layout. 
 * Per default werden auch die Relationen (FAL, assets, media...) geladen. Kann per `relations:0` verhindert werden.
 * ```
 * {nnt3:content.columnData(colPos:110)}
 * {nnt3:content.columnData(colPos:110, pid:99, relations:0)}
 * ```
 * @return array
 */
class ColumnDataViewHelper extends AbstractViewHelper {

	use CompileWithRenderStatic;

	protected $escapeOutput = false;

	public function initializeArguments() {
	   $this->registerArgument('pid', 'intval', 'PageUid von der die Spalte gerendert werden soll');
	   $this->registerArgument('colPos', 'intval', 'Spalten-Nr (colPos) des Backend-Layouts, die gerendert werden soll.');
	   $this->registerArgument('relations', 'boolean', 'Relationen (media, assets, ...) holen?', false, true);
   }

	public static function renderStatic( array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext ) {
		return \nn\t3::Content()->columnData($arguments['colPos'], $arguments['relations'], $arguments['pid']);
	}

}