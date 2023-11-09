<?php

namespace Nng\Nnhelpers\ViewHelpers\Widget;

use Nng\Nnhelpers\ViewHelpers\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * Widget zur Darstellung eines Akkordeons.
 * 
 * Wird in `nnhelpers` massenhaft in den Templates des Backend-Moduls genutzt.
 * 
 * ```
 * <nnt3:widget.accordion title="Titel" icon="fas fa-plus" class="nice-thing">
 *   ...
 * </nnt3:widget.accordion>
 * ```
 * ```
 * <nnt3:widget.accordion template="EXT:myext/path/to/template.html" title="Titel" icon="fas fa-plus" class="nice-thing">
 *   ...
 * </nnt3:widget.accordion>
 * ```
 * ```
 * {nnt3:widget.accordion(title:'Titel', content:'...' icon:'fas fa-plus', class:'nice-thing')}
 * ```
 * @return string
 */
class AccordionViewHelper extends AbstractViewHelper
{

    /**
     * Initialize arguments
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('template', 'string', 'Pfad zum Template', false, 'EXT:nnhelpers/Resources/Private/Backend/ViewHelpers/Widget/Accordion/Index.html');
        $this->registerArgument('title', 'string', 'Titel des Akkordeons');
        $this->registerArgument('icon', 'string', 'Icon-Klasse');
        $this->registerArgument('class', 'string', 'Accordeon-Klasse');
        $this->registerArgument('content', 'string', 'Inhalt');
    }

    /**
     * Render everything
     *
     * @param string $title
     * @return string
     */
    public static function renderStatic( array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext ) {

        $vars = array_merge(
            $renderingContext->getVariableProvider()->getAll(), 
            $arguments, [
            'renderedChildren'  => $arguments['content'] ?: $renderChildrenClosure(),
            'uniqid'            => uniqid('acc-'),
        ]);

        return \nn\t3::Template()->render( $arguments['template'], $vars );
    }
}