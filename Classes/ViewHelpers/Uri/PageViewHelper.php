<?php
namespace Nng\Nnhelpers\ViewHelpers\Uri;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Erzeugt ein URL zu einer Seite im Frontend.
 * Entspricht fast exakt dem Typo3 ViewHelper `{f:uri.page()}` - kann allerdings auch in einem Kontext
 * verwendet werden, bei dem kein Frontend (`TSFE`) existiert, z.B. im Template eines Backend-Moduls oder in 
 * Mail-Templates eines Scheduler-Jobs.
 * ```
 * {nnt3:uri.page(pageUid:1, additionalParams:'...')}
 * ```
 */
class PageViewHelper extends AbstractViewHelper {
	
	public function initializeArguments() {
        $this->registerArgument('pageUid', 'int', 'target PID');
        $this->registerArgument('additionalParams', 'array', 'query parameters to be attached to the resulting URI', false, []);
        $this->registerArgument('pageType', 'int', 'type of the target page. See typolink.parameter', false, 0);
        $this->registerArgument('noCache', 'bool', 'set this to disable caching for the target page. You should not need this.', false, false);
        $this->registerArgument('language', 'string', 'link to a specific language - defaults to the current language, use a language ID or "current" to enforce a specific language', false);
        $this->registerArgument('section', 'string', 'the anchor to be added to the URI', false, '');
        $this->registerArgument('linkAccessRestrictedPages', 'bool', 'If set, links pointing to access restricted pages will still link to the page even though the page cannot be accessed.', false, false);
        $this->registerArgument('absolute', 'bool', 'If set, the URI of the rendered link is absolute', false, false);
        $this->registerArgument('addQueryString', 'bool', 'If set, the current query parameters will be kept in the URI', false, false);
        $this->registerArgument('argumentsToBeExcludedFromQueryString', 'array', 'arguments to be removed from the URI. Only active if $addQueryString = TRUE', false, []);
   }

	public static function renderStatic( array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext ) {

		$args = ['pageUid', 'additionalParams', 'pageType', 'absolute'];
	   
		foreach ($args as $arg) {
			$$arg = $arguments[$arg] ?? '';
		}

		if (!$additionalParams) {
			$additionalParams = [];
		}
		if ($pageType) {
			$additionalParams['type'] = $pageType;
		}
		return \nn\t3::Page()->getLink( $pageUid, $additionalParams, $absolute );
	}
	
}