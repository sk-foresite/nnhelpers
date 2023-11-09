<?php

namespace  Nng\Nnhelpers\ViewHelpers\Ts;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Konfiguration für eine Extension aus dem Extension-Manager holen.
 *
 * ```
 * {nnt3:ts.extConf(path:'nnfiletransfer.pathLogo')}
 * {nnt3:ts.extConf(path:'nnfiletransfer', key:'pathLogo')}
 * ```
 * @return mixed
 */
class ExtConfViewHelper extends AbstractViewHelper {

	use CompileWithRenderStatic;

	protected $escapeOutput = false;

	public function initializeArguments() {
	   $this->registerArgument('key', 'string', 'Key, der geholt werden soll', false, '');
	   $this->registerArgument('path', 'string', 'Pfad zum Typoscript', false, '');
   }

	public static function renderStatic( array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext ) {
		$path = explode('.', $arguments['path']);
		$extension = array_shift( $path );
		$key = $arguments['key'] ?: join('.', $path);
		$ts = \nn\t3::Settings()->getExtConf( $extension );
		if (!$key) return $ts;
		return \nn\t3::Settings()->getFromPath( $key, $ts );
	}

}