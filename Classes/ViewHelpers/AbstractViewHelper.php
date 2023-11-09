<?php
namespace Nng\Nnhelpers\ViewHelpers;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper as AbstractTypo3ViewHelper;

/**
 * Dieser ViewHelper ist **keine eigener ViewHelper**, der in Fluid nutzbar ist.
 * 
 * Er dient als Basis-Klasse für Deine eigenen ViewHelper.
 * 
 * `$escapeOutput = false` wird als Default gesetzt. 
 * Falls XSS-Angriffe bei Deinem ViewHelper ein Problem sein könnten, solltest dies überschreiben.
 * 
 * Nutze `extend` in Deinem eigenen ViewHelper, um ihn zu verwenden.
 * Hier ein Beispiel-Boilerplate, mit allem, was Du zum Loslegen brauchst:
 * 
 * ```
 * <?php
 * namespace My\Ext\ViewHelpers;
 *
 * use Nng\Nnhelpers\ViewHelpers\AbstractViewHelper;
 * use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
 * 
 * class ExampleViewHelper extends AbstractTagBasedViewHelper {
 *  
 *  public function initializeArguments() {
 *      parent::initializeArguments();
 *      $this->registerArgument('title', 'string', 'Infos', false);
 *  }
 * 
 *  public static function renderStatic( array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext ) {
 * 
 *      // Einfach `$title` statt `$arguments['title']` nutzen
 *      foreach ($arguments as $k=>$v) {
 *      	${$k} = $v;
 *      }
 * 
 *      // Rendert Inhalt zwischen dem ViewHelper-Tag
 *      if (!$title) $title = $renderChildrenClosure();
 * 
 *      // Beispiel, um an alle aktuellen Variable im Fluid-Template zu kommen
 *      // $templateVars = \nn\t3::Template()->getVariables( $renderingContext );
 *      
 *      return $title;
 *  }
 * }
 * ```
 */
class AbstractViewHelper extends AbstractTypo3ViewHelper {

	use CompileWithRenderStatic;

	protected $escapeOutput = false;

	public static function renderStatic( array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext ) {
		 // usw
	}
	
}
