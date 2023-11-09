<?php
namespace Nng\Nnhelpers\ViewHelpers;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Macht nichts, außer das Script zu beenden.
 * 
 * Damit kann während des Rendering-Prozesses von Fluid das Script abgebrochen werden.
 * Praktisch, um z.B. Mail-Templates zu debuggen.
 * ```
 * {nnt3:die()}
 * ```
 * @return death
 */
class DieViewHelper extends AbstractViewHelper {

	use CompileWithRenderStatic;

	protected $escapeOutput = false;

	public static function renderStatic( array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext ) {
		die();
	}
    
}
