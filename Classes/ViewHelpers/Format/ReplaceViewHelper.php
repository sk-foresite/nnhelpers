<?php

namespace Nng\Nnhelpers\ViewHelpers\Format;

use Nng\Nnhelpers\ViewHelpers\AbstractViewHelper;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Text in einem String suchen und ersetzen.
 *  
 * ```
 * {nnt3:format.replace(str:'alles schön im Juli.', from:'Juli', to:'Mai')}
 * {varname->nnt3:format.replace(from:'Juli', to:'Mai')}
 * ```
 * @return string  
 */
class ReplaceViewHelper extends AbstractViewHelper {

	protected $escapingInterceptorEnabled = false;

	public function initializeArguments() {
		$this->registerArgument('str', 'string', 'String', false);
		$this->registerArgument('from', 'string', 'Was suchen?', false);
		$this->registerArgument('to', 'string', 'Mit was ersetzen?', false);
	}

	public static function renderStatic( array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext ) {

		$str = $arguments['str'] ?: $renderChildrenClosure();
		$from = $arguments['from'] ?: '';
		$to = $arguments['to'] ?: '';

		return str_replace( $from, $to, $str );
	}
}