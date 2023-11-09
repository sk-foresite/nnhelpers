<?php

namespace  Nng\Nnhelpers\ViewHelpers\File;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Fügt den Inhalt einer Datei ein.
 * ```
 * {nnt3:file.include(file:'pfad/zur/datei.html')}
 * ```
 * @return string
 */
class IncludeViewHelper extends AbstractViewHelper {

	use CompileWithRenderStatic;

	protected $escapeOutput = false;

	public function initializeArguments() {
	   $this->registerArgument('file', 'string', 'Pfad zur Datei');
   }

	public static function renderStatic( array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext ) {
		$file = $arguments['file'];
		return $file ? \nn\t3::File()->read($file) : '';
	}

}