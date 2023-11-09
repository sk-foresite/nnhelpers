<?php
namespace Nng\Nnhelpers\ViewHelpers\BackendUser;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Frontend-User holen
 * 
 * Gibt ein Array mit den Daten des Backend-Users zurück,
 * enthält auch die Einstellungen des Users.
 * 
 * ```
 * {nnt3:backendUser.get(key:'uc.example')}
 * {nnt3:backendUser.get()->f:variable.set(name:'beUser')}
 * ```
 */
class GetViewHelper extends AbstractViewHelper {
	
	public function initializeArguments() {
		parent::initializeArguments();
		$this->registerArgument('key', 'string', 'Welches Feld holen?', true);
   }

	public static function renderStatic( array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext ) {

		$args = ['key'];
		foreach ($args as $arg) {
			$$arg = $arguments[$arg] ?? '';
		}

		$backendUser = \nn\t3::BackendUser()->get();
		return $key ? \nn\t3::Settings()->getFromPath($key, $backendUser) : $backendUser;
	}
	
}