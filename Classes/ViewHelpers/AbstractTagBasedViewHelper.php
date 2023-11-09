<?php
namespace Nng\Nnhelpers\ViewHelpers;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper as AbstractTypo3TagBasedViewHelper;

/**
 * Dieser ViewHelper ist **keine eigener ViewHelper**, der in Fluid nutzbar ist.
 * Er dient als Basis-Klasse für Deine eigenen, Tag-basierten ViewHelper.
 * 
 * Nutze `extend` in Deinem eigenen ViewHelper, um ihn zu verwenden.
 * Hier ein Beispiel-Boilerplate, mit allem, was Du zum Loslegen brauchst:
 * 
 * ```
 * <?php
 * namespace My\Ext\ViewHelpers;
 *
 * use \Nng\Nnhelpers\ViewHelpers\AbstractTagBasedViewHelper;
 * 
 * class ExampleViewHelper extends AbstractTagBasedViewHelper {
 * 
 *  protected $tagName = 'div';
 * 
 *  public function initializeArguments() {
 *      parent::initializeArguments();
 *      $this->registerArgument('title', 'string', 'Infos', false);
 *  }
 *  public function render() {
 *      $args = ['item'];
 *      foreach ($args as $arg) ${$arg} = $this->arguments[$arg] ?: '';
 *      $content = $this->renderChildren();
 *      $this->tag->setContent($content);
 *      return $this->tag->render();
 *  }
 * }
 * ```
 */
class AbstractTagBasedViewHelper extends AbstractTypo3TagBasedViewHelper {

	protected $tagName = 'a';

	public function initializeArguments() {
        parent::initializeArguments();
        $this->registerUniversalTagAttributes();
    }

	public function render() {
		 // usw
		 $this->tag->addAttribute(
            'href',
            'https://www.99grad.de'
        );
        return $this->tag->render();
	}
    
}