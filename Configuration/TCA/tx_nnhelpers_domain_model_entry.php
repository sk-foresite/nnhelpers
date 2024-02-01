<?php

// Stellt im Backend (Upgrade-Wizard / Check TCA etc.) sicher, dass nnhelpers geladen ist
if (!class_exists(\nn\t3::class)) {
    $extPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('nnhelpers');
    require_once($extPath . 'Classes/nnhelpers.php');
    require_once($extPath . 'Classes/aliases.php');
}

return [
    'ctrl' => [
        'title' => 'LLL:EXT:nnhelpers/Resources/Private/Language/locallang_db.xlf:tx_nnhelpers_domain_model_entry',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
        'searchFields' => 'data,media',
        'iconfile' => 'EXT:nnhelpers/Resources/Public/Icons/tx_nnhelpers_domain_model_entry.gif'
    ],
    'interface' => [
    ],
    'types' => [
        '1' => ['showitem' => '
        	sys_language_uid, l10n_parent, l10n_diffsource, hidden, 
        	data, media, categories,
        	--div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.access, starttime, endtime'],
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.language',
            'config' => [
                'type' => 'language',
                'renderType' => 'selectSingle',
                'special' => 'languages',
                'items' => [
                    [
                        'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.allLanguages',
                        'value' => -1,
                        'iconIdentifierChecked' => 'flags-multiple'
                    ]
                ],
                'default' => 0,
            ],
        ],
       
        'l10n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_parent',
            'config' => [
                'type' => 'group',
                'allowed' => 'tx_nnhelpers_domain_model_entry',
                'size' => 1,
                'maxitems' => 1,
                'minitems' => 0,
                'default' => 0,
            ],
        ],

        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        't3ver_label' => [
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.versionLabel',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
            ],
        ],
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
                'items' => [
                    [
                        'label' => 'LLL:EXT:lang/locallang_core.xlf:labels.enabled'
                    ]
                ],
            ],
        ],
        'starttime' => [
            'exclude' => true,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.starttime',
            'config' => [
                'type' => 'datetime',
                'default' => 0,				
                'behaviour' => [
                    'allowLanguageSynchronization' => true
                ],
            ],
        ],
        'endtime' => [
            'exclude' => true,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.endtime',
            'config' => [
                'type' => 'datetime',
                'default' => 0,	
                'range' => [
                    'upper' => mktime(0, 0, 0, 1, 1, 2038)
                ],
                'behaviour' => [
                    'allowLanguageSynchronization' => true
                ],
            ],
        ],

        'data' => [
            'exclude' => false,
            'label' => 'LLL:EXT:nnhelpers/Resources/Private/Language/locallang_db.xlf:tx_nnhelpers_domain_model_entry.data',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 2,
                'eval' => 'trim'
            ]
        ],
        	
        'media' => [
            'exclude' => false,
            'label' => 'LLL:EXT:nnhelpers/Resources/Private/Language/locallang_db.xlf:tx_nnhelpers_domain_model_entry.media',
            'config' => \nn\t3::TCA()->getFileFieldTCAConfig('media'),
        ],

        'categories' => [
            'exclude' => false,
            'label' => 'LLL:EXT:nnhelpers/Resources/Private/Language/locallang_db.xlf:tx_nnhelpers_domain_model_entry.categories',
            'config' => ['type' => 'category'],
        ],
    
    ],
];
