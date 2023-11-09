<?php
namespace Nng\Nnhelpers\ViewHelpers;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Macht aus einem String ein Array
 * 
 * ```
 * {nnt3:explode(str:'1,2,3')}
 * {mystring->nnt3:explode()}
 * {mystring->nnt3:explode(delimiter:';')}
 * {mystring->nnt3:explode(trim:0)}
 * ```
 * @return void
 */
class ExplodeViewHelper extends AbstractViewHelper {

	use CompileWithRenderStatic;

	protected $escapeOutput = false;

	public function initializeArguments() {
		parent::initializeArguments();	
		$this->registerArgument('str', 'string', 'text to split', false);
		$this->registerArgument('delimiter', 'string', 'delimiter to use', false, ',');
		$this->registerArgument('trim', 'boolean', 'trim the elements?', false, true);
    }

	public static function renderStatic( array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext ) 
	{
		$str = $arguments['str'] ?: $renderChildrenClosure();
		if ($arguments['trim']) {
			return \nn\t3::Arrays( $str )->trimExplode( $arguments['delimiter'] );
		}
		if (!trim($str)) return [];
		return explode( $arguments['delimiter'], $str );
	}
    
}
