<?php

namespace  Nng\Nnhelpers\ViewHelpers\Content;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Rendert die Inhalte einer Spalte (colPos) des Backend Layouts.
 * Wird keine Seiten-ID über `pid` angegeben, verwendet er die aktuelle Seiten-ID.
 * ```
 * {nnt3:content.column(colPos:110)}
 * ```
 * Mit `slide` werden die Inhaltselement der übergeordnete Seite geholt, falls auf der angegeben Seiten kein Inhaltselement in der Spalte existiert.
 * ```
 * {nnt3:content.column(colPos:110, slide:1)}
 * ```
 * Mit `pid` kann der Spalten-Inhalt einer fremden Seite gerendert werden:
 * ```
 * {nnt3:content.column(colPos:110, pid:99)}
 * ```
 * Slide funktioniert auch für fremde Seiten:
 * ```
 * {nnt3:content.column(colPos:110, pid:99, slide:1)}
 * ```
 * @return string
 */
class ColumnViewHelper extends AbstractViewHelper {

	use CompileWithRenderStatic;

	protected $escapeOutput = false;

	public function initializeArguments() {
	   $this->registerArgument('pid', 'intval', 'PageUid von der die Spalte gerendert werden soll');
	   $this->registerArgument('colPos', 'intval', 'Spalten-Nr (colPos) des Backend-Layouts, die gerendert werden soll.');
	   $this->registerArgument('slide', 'boolean', 'Slide von übergeordneten Seiten.');
   }

	public static function renderStatic( array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext ) {
		return \nn\t3::Content()->column($arguments['colPos'], $arguments['pid'], $arguments['slide']);
	}

}