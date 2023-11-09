<?php

namespace  Nng\Nnhelpers\ViewHelpers\File;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Prüft, ob eine Datei existiert.
 * ```
 * {nnt3:file.exists(file:'pfad/zum/bild.jpg')}
 * ```
 * ```
 * <f:if condition="!{nnt3:file.exists(file:'pfad/zum/bild.jpg')}">
 *   Wo ist das Bild hin?
 * </f:if>
 * ```
 * @return boolean
 */
class ExistsViewHelper extends AbstractViewHelper {

	use CompileWithRenderStatic;

	protected $escapeOutput = false;

	public function initializeArguments() {
	   $this->registerArgument('file', 'string', 'Pfad zur Datei');
   }

	public static function renderStatic( array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext ) {
		$file = $arguments['file'];
		return $file ? \nn\t3::File()->exists($file) : false;
	}

}