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
import DocumentService from"@typo3/core/document-service.js";import{MessageUtility}from"@typo3/backend/utility/message-utility.js";import NProgress from"nprogress";import AjaxRequest from"@typo3/core/ajax/ajax-request.js";import Modal,{Types}from"@typo3/backend/modal.js";import Notification from"@typo3/backend/notification.js";import Severity from"@typo3/backend/severity.js";import RegularEvent from"@typo3/core/event/regular-event.js";import{topLevelModuleImport}from"@typo3/backend/utility/top-level-module-import.js";class OnlineMedia{constructor(){DocumentService.ready().then((async()=>{await topLevelModuleImport("@typo3/backend/form-engine/element/online-media-form-element.js"),this.registerEvents()}))}registerEvents(){new RegularEvent("click",((e,t)=>{this.triggerModal(t)})).delegateTo(document,".t3js-online-media-add-btn")}addOnlineMedia(e,t,o){const i=e.dataset.targetFolder,r=e.dataset.onlineMediaAllowed,a=e.dataset.fileIrreObject;NProgress.start(),new AjaxRequest(TYPO3.settings.ajaxUrls.online_media_create).post({url:o,targetFolder:i,allowed:r}).then((async e=>{const o=await e.resolve();if(o.file){const e={actionName:"typo3:foreignRelation:insert",objectGroup:a,table:"sys_file",uid:o.file};MessageUtility.send(e),t.hideModal()}else Notification.error(top.TYPO3.lang["online_media.error.new_media.failed"],o.error);NProgress.done()}))}triggerModal(e){const t=e.dataset.btnSubmit||"Add",o=e.dataset.placeholder||"Paste media url here...",i=e.dataset.onlineMediaAllowedHelpText||"Allow to embed from sources:",r=document.createElement("typo3-backend-formengine-online-media-form");r.placeholder=o,r.setAttribute("help-text",i),r.setAttribute("extensions",e.dataset.onlineMediaAllowed),Modal.advanced({type:Types.default,title:e.title,content:r,severity:Severity.notice,callback:t=>{t.querySelector("typo3-backend-formengine-online-media-form").addEventListener("typo3:formengine:online-media-added",(o=>{this.addOnlineMedia(e,t,o.detail["online-media-url"])}))},buttons:[{text:t,btnClass:"btn btn-primary",name:"ok",trigger:()=>{r.querySelector("form").requestSubmit()}}]})}}export default new OnlineMedia;