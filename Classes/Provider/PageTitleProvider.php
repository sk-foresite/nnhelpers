<?php

namespace Nng\Nnhelpers\Provider;

use TYPO3\CMS\Core\PageTitle\AbstractPageTitleProvider;

class PageTitleProvider extends AbstractPageTitleProvider {
		
	/**
     * @param string $title
     */
    public function setTitle( $title = '' ) {
    	$this->title = strip_tags($title);
	}
	
	/**
     * @return string
     */
    public function getRawTitle() {
    	return $this->title;
	}

	public function getTitle(): string {
		$title = $this->title
			?: $GLOBALS['TSFE']->page['title']
			?: $GLOBALS['TSFE']->altPageTitle;
		
		$rootLine = \nn\t3::Page()->getRootline();

		foreach ($rootLine as $page) {
			if ($suffix = trim($page['nnsite_suffix'] ?? '')) break;
		}
		return $title . ' ' . $suffix;
	}


}