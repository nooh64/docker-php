<?php
namespace TYPO3\CMS\Core\Tests\Acceptance\Backend\Language;

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

use TYPO3\Components\TestingFramework\Core\Acceptance\Step\Backend\Admin;

/**
 * Language tests
 */
class LanguageCest
{
    /**
     * @param Admin $I
     * @return void
     */
    public function _before(Admin $I)
    {
        $I->useExistingSession();
        // Ensure main content frame is fully loaded, otherwise there are load-race-conditions
        $I->switchToIFrame('list_frame');
        $I->waitForText('Web Content Management System');
        $I->switchToIFrame();

        $I->see('Languages');
        $I->click('Languages');

        // switch to content iframe
        $I->switchToIFrame('list_frame');
    }

    /**
     * @param Admin $I
     * @return void
     */
    public function showsHeadingAndListsInstalledLanguages(Admin $I)
    {
        $I->see('Installed Languages');

        $I->wantTo('See the table of languages');
        $I->waitForElementVisible('#typo3-language-list');
    }

    /**
     * @param Admin $I
     * @return void
     */
    public function filterInstalledLanguages(Admin $I)
    {
        $I->wantTo('Filter the list of translations with a valid language');
        $I->fillField('#typo3-language-searchfield', 'Danish');
        $I->canSeeNumberOfElements('#typo3-language-list tbody tr', 1);
        $I->seeElement('#language-da');

        $I->fillField('#typo3-language-searchfield', '');

        $I->wantTo('Filter the list of translations with an valid locale');
        $I->fillField('#typo3-language-searchfield', 'pt_BR');
        $I->canSeeNumberOfElements('#typo3-language-list tbody tr', 1);
        $I->seeElement('#language-pt_BR');
        $I->see('Brazilian Portuguese');
    }

    /**
     * @param Admin $I
     * @return void
     */
    public function activateAndDeactivateALanguage(Admin $I)
    {
        $I->wantTo('Install a language');
        $I->seeElement('#language-da');
        $I->seeElement('#language-da.disabled');
        $I->click('#language-da td a.activateLanguageLink');
        $I->seeElement('#language-da.enabled');

        $this->seeAlert($I, 'Success', 'Language was successfully activated.');

        $I->click('#language-da td a.deactivateLanguageLink');
        $I->seeElement('#language-da.disabled');

        $this->seeAlert($I, 'Success', 'Language was successfully deactivated.');
    }

    /**
     * @param Admin $I
     * @return void
     */
    public function downloadALanguage(Admin $I)
    {
        $I->wantTo('Download a language with no selection and see error message');

        $I->click('a[data-action="updateActiveLanguages"]');
        $this->seeAlert($I, 'Error', 'No language activated. Please activate at least one language.');

        // Download only a single translation for a specific extension for performance reasons
        $I->wantTo('Download a single translation for a selected language');

        $I->seeElement('#language-da');
        $I->seeElement('#language-da.disabled');
        $I->click('#language-da td a.activateLanguageLink');

        $I->selectOption('.t3-js-jumpMenuBox', 'Translation Overview');
        $I->waitForElementVisible('#typo3-translation-list');
        $I->click('#extension-core td a.updateTranslationLink');
        $I->waitForElement('#extension-core td:nth-child(3).complete');
        $this->seeAlert($I, 'Success', 'The translation update has been successfully completed.');
    }

    /**
     * @param Admin $I
     * @return void
     */
    public function showsHeadingAndListsTranslationOverview(Admin $I)
    {
        $I->wantToTest('Select Translation Overview');
        $I->selectOption('.t3-js-jumpMenuBox', 'Translation Overview');
        $I->waitForElementVisible('#typo3-translation-list');
        $I->see('Translation Overview');
    }

    /**
     * @param Admin $I
     * @return void
     */
    public function filterTranslationOverview(Admin $I)
    {
        $I->wantToTest('Select Translation Overview and Filter');
        $I->selectOption('.t3-js-jumpMenuBox', 'Translation Overview');
        $I->waitForElementVisible('#typo3-translation-list');

        $I->wantTo('Filter the list of translations with a valid Extension');
        $I->fillField('#typo3-language-searchfield', 'TYPO3 Core');
        $I->canSeeNumberOfElements('#typo3-translation-list tbody tr', 1);

        $I->wantTo('Filter the list of translations with an invalid Extension');
        $I->fillField('#typo3-language-searchfield', 'TYPO3 FooBar');
        $I->canSeeNumberOfElements('#typo3-translation-list tbody tr', 1);
    }

    /**
     * @param Admin $I
     * @param string $alertTitle
     * @param string $alertMessage
     */
    protected function seeAlert(Admin $I, $alertTitle, $alertMessage)
    {
        // switch back to body
        $I->switchToIFrame();

        $I->wait(1);
        $I->waitForElement('//div[contains(@role, "alert")]', 2);
        $I->see($alertTitle);
        $I->see($alertMessage);

        // switch to content iframe
        $I->switchToIFrame('list_frame');
    }
}
