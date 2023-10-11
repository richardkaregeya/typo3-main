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
import{AbstractInteractableModule}from"@typo3/install/module/abstract-interactable-module.js";import Modal from"@typo3/backend/modal.js";import Notification from"@typo3/backend/notification.js";import AjaxRequest from"@typo3/core/ajax/ajax-request.js";import Router from"@typo3/install/router.js";import RegularEvent from"@typo3/core/event/regular-event.js";var Identifiers;!function(e){e.deleteTrigger=".t3js-clearTypo3temp-delete",e.outputContainer=".t3js-clearTypo3temp-output",e.statContainer=".t3js-clearTypo3temp-stat-container",e.statsTrigger=".t3js-clearTypo3temp-stats",e.statTemplate=".t3js-clearTypo3temp-stat-template",e.statNumberOfFiles=".t3js-clearTypo3temp-stat-numberOfFiles",e.statDirectory=".t3js-clearTypo3temp-stat-directory"}(Identifiers||(Identifiers={}));class ClearTypo3tempFiles extends AbstractInteractableModule{initialize(e){super.initialize(e),this.getStats(),new RegularEvent("click",(t=>{t.preventDefault(),e.querySelector(Identifiers.outputContainer).innerHTML="",this.getStats()})).delegateTo(e,Identifiers.statsTrigger),new RegularEvent("click",((e,t)=>{e.preventDefault();const r=t.dataset.folder,s=void 0!==t.dataset.storageUid?parseInt(t.dataset.storageUid,10):void 0;this.delete(r,s)})).delegateTo(e,Identifiers.deleteTrigger)}getStats(){this.setModalButtonsState(!1);const e=this.getModalBody();new AjaxRequest(Router.getUrl("clearTypo3tempFilesStats")).get({cache:"no-cache"}).then((async t=>{const r=await t.resolve();!0===r.success?(e.innerHTML=r.html,Modal.setButtons(r.buttons),Array.isArray(r.stats)&&r.stats.length>0&&r.stats.forEach((t=>{if(t.numberOfFiles>0){const r=e.querySelector(Identifiers.statTemplate).cloneNode(!0);r.querySelector(Identifiers.statNumberOfFiles).innerText=t.numberOfFiles,r.querySelector(Identifiers.statDirectory).innerText=t.directory,r.querySelector(Identifiers.deleteTrigger).setAttribute("data-folder",t.directory),void 0!==t.storageUid&&r.querySelector(Identifiers.deleteTrigger).setAttribute("data-storage-uid",t.storageUid),e.querySelector(Identifiers.statContainer).append(r)}}))):Notification.error("Something went wrong","The request was not processed successfully. Please check the browser's console and TYPO3's log.")}),(t=>{Router.handleAjaxError(t,e)}))}delete(e,t){const r=this.getModalBody(),s=this.getModuleContent().dataset.clearTypo3tempDeleteToken;new AjaxRequest(Router.getUrl()).post({install:{action:"clearTypo3tempFiles",token:s,folder:e,storageUid:t}}).then((async e=>{const t=await e.resolve();!0===t.success&&Array.isArray(t.status)?(t.status.forEach((e=>{Notification.success(e.title,e.message)})),this.getStats()):Notification.error("Something went wrong","The request was not processed successfully. Please check the browser's console and TYPO3's log.")}),(e=>{Router.handleAjaxError(e,r)}))}}export default new ClearTypo3tempFiles;