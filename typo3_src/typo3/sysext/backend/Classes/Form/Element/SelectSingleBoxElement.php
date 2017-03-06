<?php
namespace TYPO3\CMS\Backend\Form\Element;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * Create a widget with a select box where multiple items can be selected
 *
 * This is rendered for config type=select, renderType=selectSingleBox
 */
class SelectSingleBoxElement extends AbstractFormElement
{
    /**
     * Default field controls for this element.
     *
     * @var array
     */
    protected $defaultFieldControl = [
        'resetSelection' => [
            'renderType' => 'resetSelection',
        ],
    ];

    /**
     * Default field wizards enabled for this element.
     *
     * @var array
     */
    protected $defaultFieldWizard = [
        'localizationStateSelector' => [
            'renderType' => 'localizationStateSelector',
        ],
        'otherLanguageContent' => [
            'renderType' => 'otherLanguageContent',
            'after' => [
                'localizationStateSelector'
            ],
        ],
        'defaultLanguageDifferences' => [
            'renderType' => 'defaultLanguageDifferences',
            'after' => [
                'otherLanguageContent',
            ],
        ],
    ];

    /**
     * This will render a selector box element, or possibly a special construction with two selector boxes.
     *
     * @return array As defined in initializeResultArray() of AbstractNode
     */
    public function render()
    {
        $languageService = $this->getLanguageService();
        $resultArray = $this->initializeResultArray();

        $parameterArray = $this->data['parameterArray'];
        // Field configuration from TCA:
        $config = $parameterArray['fieldConf']['config'];
        $selectItems = $parameterArray['fieldConf']['config']['items'];
        $disabled = !empty($config['readOnly']);

        // Get values in an array (and make unique, which is fine because there can be no duplicates anyway):
        $itemArray = array_flip($parameterArray['itemFormElValue']);
        $width = $this->formMaxWidth($this->defaultInputWidth);

        $optionElements = [];
        foreach ($selectItems as $i => $item) {
            $value = $item[1];
            $attributes = [];
            // Selected or not by default
            if (isset($itemArray[$value])) {
                $attributes['selected'] = 'selected';
                unset($itemArray[$value]);
            }
            // Non-selectable element
            if ((string)$value === '--div--') {
                $attributes['disabled'] = 'disabled';
                $attributes['class'] = 'formcontrol-select-divider';
            }
            $optionElements[] = $this->renderOptionElement($value, $item[0], $attributes);
        }

        $selectElement = $this->renderSelectElement($optionElements, $parameterArray, $config);

        $legacyWizards = $this->renderWizards();
        $legacyFieldControlHtml = implode(LF, $legacyWizards['fieldControl']);
        $legacyFieldWizardHtml = implode(LF, $legacyWizards['fieldWizard']);

        $fieldInformationResult = $this->renderFieldInformation();
        $fieldInformationHtml = $fieldInformationResult['html'];
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldInformationResult, false);

        $fieldControlResult = $this->renderFieldControl();
        $fieldControlHtml = $legacyFieldControlHtml . $fieldControlResult['html'];
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldControlResult, false);

        $fieldWizardResult = $this->renderFieldWizard();
        $fieldWizardHtml = $legacyFieldWizardHtml . $fieldWizardResult['html'];
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldWizardResult, false);

        $html = [];
        $html[] = '<div class="formengine-field-item t3js-formengine-field-item">';
        if (!$disabled) {
            $html[] = $fieldInformationHtml;
        }
        $html[] =   '<div class="form-control-wrap" style="max-width: ' . $width . 'px">';
        $html[] =       '<div class="form-wizards-wrap form-wizards-aside">';
        $html[] =           '<div class="form-wizards-element">';
        if (!$disabled) {
            // Add an empty hidden field which will send a blank value if all items are unselected.
            $html[] =           '<input type="hidden" name="' . htmlspecialchars($parameterArray['itemFormElName']) . '" value="">';
        }
        $html[] =               $selectElement;
        $html[] =           '</div>';
        if (!$disabled) {
            $html[] =       '<div class="form-wizards-items-aside">';
            $html[] =           $fieldControlHtml;
            $html[] =       '</div>';
            $html[] =   '</div>';

            $html[] =   '<p>';
            $html[] =       '<em>' . htmlspecialchars($languageService->sL('LLL:EXT:lang/Resources/Private/Language/locallang_core.xlf:labels.holdDownCTRL')) . '</em>';
            $html[] =   '</p>';
            $html[] =   '<div class="form-wizards-items-bottom">';
            $html[] =       $fieldWizardHtml;
            $html[] =   '</div>';
        }
        $html[] =   '</div>';
        $html[] = '</div>';

        $resultArray['html'] = implode(LF, $html);
        return $resultArray;
    }

    /**
     * Renders a <select> element
     *
     * @param array $optionElements List of rendered <option> elements
     * @param array $parameterArray
     * @param array $config Field configuration
     * @return string
     */
    protected function renderSelectElement(array $optionElements, array $parameterArray, array $config)
    {
        $selectItems = $parameterArray['fieldConf']['config']['items'];
        $size = (int)$config['size'];
        $cssPrefix = $size === 1 ? 'tceforms-select' : 'tceforms-multiselect';

        if ($config['autoSizeMax']) {
            $size = MathUtility::forceIntegerInRange(
                count($selectItems) + 1,
                MathUtility::forceIntegerInRange($size, 1),
                $config['autoSizeMax']
            );
        }

        $attributes = [
            'name' => $parameterArray['itemFormElName'] . '[]',
            'multiple' => 'multiple',
            'onchange' => implode('', $parameterArray['fieldChangeFunc']),
            'id' => StringUtility::getUniqueId($cssPrefix),
            'class' => 'form-control ' . $cssPrefix,
            'data-formengine-validation-rules' => $this->getValidationDataAsJsonString($config),
        ];
        if ($size) {
            $attributes['size'] = $size;
        }
        if ($config['readOnly']) {
            $attributes['disabled'] = 'disabled';
        }
        if (isset($config['itemListStyle'])) {
            $attributes['style'] = $config['itemListStyle'];
        }

        $html = [];
        $html[] = '<select ' . GeneralUtility::implodeAttributes($attributes, true) . '>';
        $html[] =   implode(LF, $optionElements);
        $html[] = '</select>';

        return implode(LF, $html);
    }

    /**
     * Renders a single <option> element
     *
     * @param string $value The option value
     * @param string $label The option label
     * @param array $attributes Map of attribute names and values
     * @return string
     */
    protected function renderOptionElement($value, $label, array $attributes = [])
    {
        $attributes['value'] = $value;
        $html = [
            '<option ' . GeneralUtility::implodeAttributes($attributes, true) . '>',
                htmlspecialchars($label, ENT_COMPAT, 'UTF-8', false),
            '</option>'

        ];

        return implode('', $html);
    }
}
