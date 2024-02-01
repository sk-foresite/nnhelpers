<?php

namespace Nng\Nnhelpers\ViewHelpers\Format;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Maskiert "kritische" Zeichen, damit sie als Attribut an einen HTML-Tag verwendet werden können.
 * ```
 * <div data-example="{something->nnt3:format.attrEncode()}"> ... </div>
 * <a title="{title->nnt3:format.attrEncode()}"> ... </a>
 * ```
 * @return string
 */
class AttrEncodeViewHelper extends AbstractViewHelper {

	use CompileWithRenderStatic;

	protected $escapeOutput = false;

	public function initializeArguments() {
		$this->registerArgument('str', 'mixed', 'Text', false);
	}

	public static function renderStatic( array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext ) {
		$str = $arguments['str'] ?: $renderChildrenClosure();
		if (is_array($str)) $str = json_encode($str);
        return htmlspecialchars($str ?: '', ENT_QUOTES, 'UTF-8');
	}
}