<?php

namespace Nng\Nnhelpers\ViewHelpers\Parse;

use Nng\Nnhelpers\Helpers\JsonHelper;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Wandelt einen String mit Markdown in HTML um.
 * ```
 * {myMarkdownCode->nnt3:parse.markdown()}
 * ```
 * @return mixed
 */
class MarkdownViewHelper extends AbstractViewHelper {

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
		$this->registerArgument('str', 'mixed', 'String, der nach Markdown geparsed werden soll', false);
	}
	
	/**
	 * @return string
	 */
	public static function renderStatic( array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext) {
		$args = ['str'];
		foreach ($args as $arg) ${$arg} = $arguments[$arg] ?: '';

		if (!$str) $str = $renderChildrenClosure();
		
		return \Nng\Nnhelpers\Helpers\MarkdownHelper::toHTML( $str );
	}


}