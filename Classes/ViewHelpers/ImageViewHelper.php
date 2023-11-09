<?php
declare(strict_types=1);

namespace Nng\Nnhelpers\ViewHelpers;

use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * Vereinfacht die Verwendung des ImageViewhelpers.
 * 
 * Wirft keinen Fehler, falls kein `image` oder `src` übergeben wurde.
 * Erlaubt auch die Übergabe eines Arrays, zieht sich einfach das erste Bild.
 * 
 * ```
 * // tt_content.image ist eigentlich ein Array. Es wird einfach das erste Bild gerendert!
 * {nnt3:image(image:data.image)}
 * 
 * // wirft keinen Fehler (falls der Redakteur kein Bild hochgeladen hat!)
 * {nnt3:image(image:'')}
 * ```
 * 
 */
class ImageViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;

    protected $escapeOutput = false;
    
    /**
     * Ugly workaround to inherit the arguments from the Core ImageViewHelper.
     * The ImageViewHelper is marked as `final` and cannot be extended :(
     * 
     * @return void
     */
    public function initializeArguments(): void
    {
        $parent = \nn\t3::injectClass( \TYPO3\CMS\Fluid\ViewHelpers\ImageViewHelper::class );
        $parent->initializeArguments();
        $this->argumentDefinitions = $parent->argumentDefinitions;
        unset($this->argumentDefinitions['image']);
        $this->registerArgument('image', 'mixed', 'image');
    }
    
    /**
     *
     * @throws Exception
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext): string
    {
        $parent = \nn\t3::injectClass( \TYPO3\CMS\Fluid\ViewHelpers\ImageViewHelper::class );

        $src = (string)$arguments['src'];
        $image = $arguments['image'];

        if (!$src && !$image) return '';

        if (is_array($image)) {
            $arguments['image'] = $image[0];
        }

        return $parent::renderStatic( $arguments, $renderChildrenClosure, $renderingContext );
    }

}
