<?php

namespace Nng\Nnhelpers\Controller;

use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

abstract class AbstractController extends ActionController 
{
	protected ModuleTemplateFactory $moduleTemplateFactory;

	public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
    ) {
        $this->moduleTemplateFactory = $moduleTemplateFactory;
    }

	/** 
	 * 	Cache des Source-Codes für die Doku
	 * 	@var array
	 */
	protected $sourceCodeCache = [];
	protected $maxTranslationsPerLoad = 10;

}
