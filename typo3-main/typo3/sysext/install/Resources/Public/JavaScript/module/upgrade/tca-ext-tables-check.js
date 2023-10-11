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
import{AbstractInteractableModule}from"@typo3/install/module/abstract-interactable-module.js";import Modal from"@typo3/backend/modal.js";import Notification from"@typo3/backend/notification.js";import AjaxRequest from"@typo3/core/ajax/ajax-request.js";import{InfoBox}from"@typo3/install/renderable/info-box.js";import Severity from"@typo3/install/renderable/severity.js";import Router from"@typo3/install/router.js";import RegularEvent from"@typo3/core/event/regular-event.js";var Identifiers;!function(e){e.checkTrigger=".t3js-tcaExtTablesCheck-check",e.outputContainer=".t3js-tcaExtTablesCheck-output"}(Identifiers||(Identifiers={}));class TcaExtTablesCheck extends AbstractInteractableModule{initialize(e){super.initialize(e),this.check(),new RegularEvent("click",(e=>{e.preventDefault(),this.check()})).delegateTo(e,Identifiers.checkTrigger)}check(){this.setModalButtonsState(!1);const e=document.querySelector(Identifiers.outputContainer);null!==e&&this.renderProgressBar(e,{},"append");const t=this.getModalBody();new AjaxRequest(Router.getUrl("tcaExtTablesCheck")).get({cache:"no-cache"}).then((async e=>{const o=await e.resolve();t.innerHTML=o.html,Modal.setButtons(o.buttons),!0===o.success&&Array.isArray(o.status)?o.status.length>0?(t.querySelector(Identifiers.outputContainer).append(InfoBox.create(Severity.warning,"Following extensions change TCA in ext_tables.php","Check ext_tables.php files, look for ExtensionManagementUtility calls and $GLOBALS['TCA'] modifications")),o.status.forEach((e=>{t.querySelector(Identifiers.outputContainer).append(InfoBox.create(e.severity,e.title,e.message))}))):t.querySelector(Identifiers.outputContainer).append(InfoBox.create(Severity.ok,"No TCA changes in ext_tables.php files. Good job!")):Notification.error("Something went wrong",'Please use the module "Check for broken extensions" to find a possible extension causing this issue.')}),(e=>{Router.handleAjaxError(e,t)}))}}export default new TcaExtTablesCheck;