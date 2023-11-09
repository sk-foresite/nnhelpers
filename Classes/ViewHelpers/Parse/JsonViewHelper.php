<?php

namespace Nng\Nnhelpers\ViewHelpers\Parse;

use Nng\Nnhelpers\Helpers\JsonHelper;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Wandelt ein normales JavaScript-Object, dass als String übergeben wird in ein Array um.
 * Erlaubt es, Konfigurationen für Slider und andere JS-Bibliotheken im TypoScript anzulegen und später per JS zu parsen.
 * 
 * Siehe `JsonHelper` für Beispiele.
 * ```
 * {myConfig->nnt3:parse.json()->f:debug()}
 * ```
 * ```
 * <div data-config="{myConfig->nnt3:parse.json()->nnt3:format.attrEncode()}">
 *   ...
 * </div>
 * ```
 * @return mixed
 */
class JsonViewHelper extends AbstractViewHelper {

	use CompileWithRenderStatic;

	/**
	 * @var boolean
	 */
	protected $escapeChildren = false;

	/**
	 * @var boolean
	 */
	protected $escapeOutput = false;
	
	
	/**
	 * Initialize arguments.
	 *
	 * @return void
	 */
	public function initializeArguments() {
		parent::initializeArguments();
		$this->registerArgument('str', 'mixed', 'Raw String, Array oder JS-Object, das in ein JSON umgewandelt werden soll', false);
	}
	
	/**
	 * @return string
	 */
	public static function renderStatic( array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext) {
		$args = ['str'];
		foreach ($args as $arg) ${$arg} = $arguments[$arg] ?: '';

		if (!$str) $str = $renderChildrenClosure();
		
		// Array wurde übergeben: Direkt zurückgeben
		if (is_array($str) || is_object($str)) return $str;

		// JavaScript Object (z.B. aus TypoScript-Setup) in JSON konvertieren
		$data = \Nng\Nnhelpers\Helpers\JsonHelper::decode( $str );

		return $data;
	}


}