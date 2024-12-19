<?php
namespace Nng\Nnhelpers\ViewHelpers;

use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Gibt eine Flash-Message aus.
 * 
 * Im Controller:
 * ```
 * \nn\t3::Message()->OK('Titel', 'Infotext');
 * \nn\t3::Message()->setId('oben')->ERROR('Titel', 'Infotext');
 * ```
 * Im Fluid:
 * ```
 * <nnt3:flashMessages />
 * <nnt3:flashMessages id='oben' />
 * ```
 * Die Core-Funktionen:
 * ```
 * <f:flashMessages queueIdentifier='core.template.flashMessages' />
 * <f:flashMessages queueIdentifier='oben' />
 * ```
 * @return string
 */
class FlashMessagesViewHelper extends AbstractViewHelper {


	protected $escapeOutput = false;

	public function initializeArguments() {
		parent::initializeArguments();	
		$this->registerArgument('id', 'string', 'queueIdentifier der Messages', false, 'core.template.flashMessages');
		$this->registerArgument('queueIdentifier', 'string', 'Flash-message queue to use');
        $this->registerArgument('as', 'string', 'The name of the current flashMessage variable for rendering inside');
    }

    public function render(): string
    {
        /** @var \TYPO3\CMS\Fluid\ViewHelpers\FlashMessagesViewHelper $flashMessagesViewHelper */
        $flashMessagesViewHelper = GeneralUtility::makeInstance(
            \TYPO3\CMS\Fluid\ViewHelpers\FlashMessagesViewHelper::class
        );
        $flashMessagesViewHelper->setArguments($this->arguments);
        $flashMessagesViewHelper->setRenderingContext($this->renderingContext);
        $flashMessagesViewHelper->initializeArguments();
        $flashMessagesViewHelper->setChildNodes($this->childNodes);

        $html = $flashMessagesViewHelper->render();
        return html_entity_decode( $html );
    }

}
