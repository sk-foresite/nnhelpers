<?php
namespace Nng\Nnhelpers\ViewHelpers\Color;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * Eine Farben konvertieren
 * ```
 * {hex->nn:color.convert(to:'rgb')}
 * ```
 */
class ConvertViewHelper extends AbstractViewHelper 
{
	use CompileWithRenderStatic;

	protected $escapeOutput = false;

	public function initializeArguments() {
	   $this->registerArgument('color', 'string', 'Farbe', false);
	   $this->registerArgument('to', 'string', 'In welches Format konvertieren?', false, 'rgb');
   }

	public static function renderStatic( array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext ) {
		$color = $arguments['color'] ?: $renderChildrenClosure();
		return \nn\t3::Convert( $color )->toRGB();
	}
}
