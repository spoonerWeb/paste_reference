<?php

namespace EHAERER\PasteReference\EventListener;

use TYPO3\CMS\Backend\Clipboard\Clipboard;
use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Routing\RouteNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class ModifyPageLayoutContentEventListener
{

    protected IconFactory $iconFactory;

    public function __construct()
    {
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
    }

    public function __invoke(ModifyPageLayoutContentEvent $event): void
    {
        /** @var PageRenderer $pageRenderer */
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->addJsInlineCode('gridelementsExtOnReady', $this->getInlineJavaScriptCode(), true, false, true);
        $pageRenderer->addInlineLanguageLabelFile(
            'EXT:paste_reference/Resources/Private/Language/locallang_db.xml',
            'tx_paste_reference_js'
        );
    }

    protected function getInlineJavaScriptCode(): string
    {
        $clipObj = GeneralUtility::makeInstance(Clipboard::class); // Start clipboard
        $clipObj->initializeClipboard();
        $clipObj->lockToNormal();
        $clipBoard = $clipObj->clipData['normal'];

//        $pAddExtOnReadyCode = '
//                TYPO3.l10n = {
//                    localize: function(langKey){
//                        return TYPO3.lang[langKey];
//                    }
//                }
//            ';
        $pAddExtOnReadyCode = '';

        // add Ext.onReady() code from file
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        try {
            $pAddExtOnReadyCode .= '
                top.pasteReferenceAllowed = ' . ($this->getBackendUser()->checkAuthMode(
                    'tt_content',
                    'CType',
                    'shortcut'
                ) ? 'true' : 'false') . ';
                top.browserUrl = ' . json_encode((string)$uriBuilder->buildUriFromRoute('wizard_element_browser')) . ';';
        } catch (RouteNotFoundException $e) {
        }

        if (!empty($clipBoard) && !empty($clipBoard['el'])) {
            $clipBoardElement = GeneralUtility::trimExplode('|', key($clipBoard['el']));
            if ($clipBoardElement[0] === 'tt_content') {
                $clipBoardElementData = BackendUtility::getRecord('tt_content', (int)$clipBoardElement[1]);
                $pAddExtOnReadyCode .= '
            top.clipBoardElementCType = ' . json_encode($clipBoardElementData['CType']) . ';
            top.clipBoardElementListType = ' . json_encode($clipBoardElementData['list_type']) . ';';
            } else {
                $pAddExtOnReadyCode .= "
            top.clipBoardElementCType = '';
            top.clipBoardElementListType = '';";
            }
        }

        if (!($this->extensionConfiguration['disableCopyFromPageButton'] ?? false)
            && !($this->getBackendUser()->uc['disableCopyFromPageButton'] ?? false)
        ) {
            $pAddExtOnReadyCode .= '
                    top.copyFromAnotherPageLinkTemplate = ' . json_encode(
                    '<a class="t3js-paste-new btn btn-default" title="' . $this->getLanguageService()->sL(
                        'LLL:EXT:gridelements/Resources/Private/Language/locallang_db.xml:tx_gridelements_js.copyfrompage'
                    ) . '">' . $this->iconFactory->getIcon(
                        'actions-insert-reference',
                        Icon::SIZE_SMALL
                    )->render() . '</a>'
                ) . ';';
        }

        return $pAddExtOnReadyCode;
    }

    /**
     * Gets the current backend user.
     *
     * @return BackendUserAuthentication
     */
    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * getter for language service
     *
     * @return LanguageService
     */
    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
