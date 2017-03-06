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

/**
 * Module: TYPO3/CMS/Backend/Toolbar/SystemInformationMenu
 * System information menu handler
 */
define([
	'jquery',
	'TYPO3/CMS/Backend/Icons',
	'TYPO3/CMS/Backend/Storage',
	'TYPO3/CMS/Backend/Viewport'
], function($, Icons, Storage, Viewport) {
	'use strict';

	/**
	 *
	 * @type {{identifier: {containerSelector: string, toolbarIconSelector: string, menuContainerSelector: string, moduleLinks: string}, elements: {$counter: (*|jQuery|HTMLElement)}}}
	 * @exports TYPO3/CMS/Backend/Toolbar/SystemInformationMenu
	 */
	var SystemInformationMenu = {
		identifier: {
			containerSelector: '#typo3-cms-backend-backend-toolbaritems-systeminformationtoolbaritem',
			toolbarIconSelector: '.toolbar-item-icon .t3js-icon',
			menuContainerSelector: '.dropdown-menu',
			moduleLinks: '.t3js-systeminformation-module',
			counter: '.t3js-systeminformation-counter'
		}
	};

	/**
	 * Initialize the events
	 */
	SystemInformationMenu.initialize = function() {
		$(SystemInformationMenu.identifier.moduleLinks).on('click', SystemInformationMenu.openModule);
	};

	/**
	 * Updates the menu
	 */
	SystemInformationMenu.updateMenu = function() {
		var $toolbarItemIcon = $(SystemInformationMenu.identifier.toolbarIconSelector, SystemInformationMenu.identifier.containerSelector),
			$existingIcon = $toolbarItemIcon.clone(),
			$menuContainer = $(SystemInformationMenu.identifier.containerSelector).find(SystemInformationMenu.identifier.menuContainerSelector);

		// hide the menu if it's active
		if ($menuContainer.is(':visible')) {
			$menuContainer.click();
		}

		Icons.getIcon('spinner-circle-light', Icons.sizes.small).done(function(spinner) {
			$toolbarItemIcon.replaceWith(spinner);
		});

		$.ajax({
			url: TYPO3.settings.ajaxUrls['systeminformation_render'],
			type: 'post',
			cache: false,
			success: function(data) {
				$menuContainer.html(data);
				SystemInformationMenu.updateCounter();
				$(SystemInformationMenu.identifier.toolbarIconSelector, SystemInformationMenu.identifier.containerSelector).replaceWith($existingIcon);

				SystemInformationMenu.initialize();
			}
		});
	};

	/**
	 * Updates the counter
	 */
	SystemInformationMenu.updateCounter = function() {
		var $container = $(SystemInformationMenu.identifier.containerSelector).find(SystemInformationMenu.identifier.menuContainerSelector).find('.t3js-systeminformation-container'),
			$counter = $(SystemInformationMenu.identifier.counter),
			count = $container.data('count'),
			badgeClass = $container.data('severityclass');

		$counter.text(count).toggle(parseInt(count) > 0);
		$counter.removeClass();

		if (badgeClass !== '') {
			$counter.addClass('toolbar-item-badge badge ' + badgeClass);
		}
	};

	/**
	 * Updates the UC and opens the linked module
	 *
	 * @param {Event} e
	 */
	SystemInformationMenu.openModule = function(e) {
		e.preventDefault();
		e.stopPropagation();

		var storedSystemInformationSettings = {},
			moduleStorageObject = {},
			requestedModule = $(e.currentTarget).data('modulename'),
			timestamp = Math.floor((new Date()).getTime() / 1000);

		if (Storage.Persistent.isset('systeminformation')) {
			storedSystemInformationSettings = JSON.parse(Storage.Persistent.get('systeminformation'));
		}

		moduleStorageObject[requestedModule] = {lastAccess: timestamp};
		$.extend(true, storedSystemInformationSettings, moduleStorageObject);
		var $ajax = Storage.Persistent.set('systeminformation', JSON.stringify(storedSystemInformationSettings));
		$ajax.done(function() {
			// finally, open the module now
			TYPO3.ModuleMenu.App.showModule(requestedModule);
			Viewport.Topbar.refresh();
		});
	};

	Viewport.Topbar.Toolbar.registerEvent(SystemInformationMenu.updateMenu);

	return SystemInformationMenu;
});
