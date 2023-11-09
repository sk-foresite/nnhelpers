<?php

namespace  Nng\Nnhelpers\ViewHelpers\File;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Absolute URL zu einer Datei holen.
 * ```
 * {nnt3:file.absPath(file:'pfad/zum/bild.jpg')}
 * ```
 * @return string
 */
class AbsPathViewHelper extends AbstractViewHelper {

	use CompileWithRenderStatic;

	protected $escapeOutput = false;

	public function initializeArguments() {
	   $this->registerArgument('file', 'string', 'Pfad zur Datei');
   }

	public static function renderStatic( array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext ) {
		$file = $arguments['file'];
		return $file ? \nn\t3::File()->absPath($file) : '';
	}

}