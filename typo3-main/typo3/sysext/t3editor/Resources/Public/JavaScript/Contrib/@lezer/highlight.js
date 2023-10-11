import{NodeProp}from"@lezer/common";let nextTagID=0;class Tag{constructor(t,e,a){this.set=t,this.base=e,this.modified=a,this.id=nextTagID++}static define(t){if(null==t?void 0:t.base)throw new Error("Can not derive from a modified tag");let e=new Tag([],null,[]);if(e.set.push(e),t)for(let a of t.set)e.set.push(a);return e}static defineModifier(){let t=new Modifier;return e=>e.modified.indexOf(t)>-1?e:Modifier.get(e.base||e,e.modified.concat(t).sort(((t,e)=>t.id-e.id)))}}let nextModifierID=0;class Modifier{constructor(){this.instances=[],this.id=nextModifierID++}static get(t,e){if(!e.length)return t;let a=e[0].instances.find((a=>a.base==t&&sameArray(e,a.modified)));if(a)return a;let r=[],o=new Tag(r,t,e);for(let t of e)t.instances.push(o);let i=permute(e);for(let e of t.set)for(let t of i)r.push(Modifier.get(e,t));return o}}function sameArray(t,e){return t.length==e.length&&t.every(((t,a)=>t==e[a]))}function permute(t){let e=[t];for(let a=0;a<t.length;a++)for(let r of permute(t.slice(0,a).concat(t.slice(a+1))))e.push(r);return e}function styleTags(t){let e=Object.create(null);for(let a in t){let r=t[a];Array.isArray(r)||(r=[r]);for(let t of a.split(" "))if(t){let a=[],o=2,i=t;for(let e=0;;){if("..."==i&&e>0&&e+3==t.length){o=1;break}let r=/^"(?:[^"\\]|\\.)*?"|[^\/!]+/.exec(i);if(!r)throw new RangeError("Invalid path: "+t);if(a.push("*"==r[0]?"":'"'==r[0][0]?JSON.parse(r[0]):r[0]),e+=r[0].length,e==t.length)break;let s=t[e++];if(e==t.length&&"!"==s){o=0;break}if("/"!=s)throw new RangeError("Invalid path: "+t);i=t.slice(e)}let s=a.length-1,n=a[s];if(!n)throw new RangeError("Invalid path: "+t);let l=new Rule(r,o,s>0?a.slice(0,s):null);e[n]=l.sort(e[n])}}return ruleNodeProp.add(e)}const ruleNodeProp=new NodeProp;class Rule{constructor(t,e,a,r){this.tags=t,this.mode=e,this.context=a,this.next=r}sort(t){return!t||t.depth<this.depth?(this.next=t,this):(t.next=this.sort(t.next),t)}get depth(){return this.context?this.context.length:0}}function tagHighlighter(t,e){let a=Object.create(null);for(let e of t)if(Array.isArray(e.tag))for(let t of e.tag)a[t.id]=e.class;else a[e.tag.id]=e.class;let{scope:r,all:o=null}=e||{};return{style:t=>{let e=o;for(let r of t)for(let t of r.set){let r=a[t.id];if(r){e=e?e+" "+r:r;break}}return e},scope:r}}function highlightTags(t,e){let a=null;for(let r of t){let t=r.style(e);t&&(a=a?a+" "+t:t)}return a}function highlightTree(t,e,a,r=0,o=t.length){let i=new HighlightBuilder(r,Array.isArray(e)?e:[e],a);i.highlightRange(t.cursor(),r,o,"",i.highlighters),i.flush(o)}class HighlightBuilder{constructor(t,e,a){this.at=t,this.highlighters=e,this.span=a,this.class=""}startSpan(t,e){e!=this.class&&(this.flush(t),t>this.at&&(this.at=t),this.class=e)}flush(t){t>this.at&&this.class&&this.span(this.at,t,this.class)}highlightRange(t,e,a,r,o){let{type:i,from:s,to:n}=t;if(s>=a||n<=e)return;i.isTop&&(o=this.highlighters.filter((t=>!t.scope||t.scope(i))));let l=r,g=i.prop(ruleNodeProp),c=!1;for(;g;){if(!g.context||t.matchContext(g.context)){let t=highlightTags(o,g.tags);t&&(l&&(l+=" "),l+=t,1==g.mode?r+=(r?" ":"")+t:0==g.mode&&(c=!0));break}g=g.next}if(this.startSpan(t.from,l),c)return;let h=t.tree&&t.tree.prop(NodeProp.mounted);if(h&&h.overlay){let i=t.node.enter(h.overlay[0].from+s,1),g=this.highlighters.filter((t=>!t.scope||t.scope(h.tree.type))),c=t.firstChild();for(let m=0,d=s;;m++){let p=m<h.overlay.length?h.overlay[m]:null,f=p?p.from+s:n,u=Math.max(e,d),k=Math.min(a,f);if(u<k&&c)for(;t.from<k&&(this.highlightRange(t,u,k,r,o),this.startSpan(Math.min(a,t.to),l),!(t.to>=f)&&t.nextSibling()););if(!p||f>a)break;d=p.to+s,d>e&&(this.highlightRange(i.cursor(),Math.max(e,p.from+s),Math.min(a,d),r,g),this.startSpan(d,l))}c&&t.parent()}else if(t.firstChild()){do{if(!(t.to<=e)){if(t.from>=a)break;this.highlightRange(t,e,a,r,o),this.startSpan(Math.min(a,t.to),l)}}while(t.nextSibling());t.parent()}}}const t=Tag.define,comment=t(),name=t(),typeName=t(name),propertyName=t(name),literal=t(),string=t(literal),number=t(literal),content=t(),heading=t(content),keyword=t(),operator=t(),punctuation=t(),bracket=t(punctuation),meta=t(),tags={comment,lineComment:t(comment),blockComment:t(comment),docComment:t(comment),name,variableName:t(name),typeName,tagName:t(typeName),propertyName,attributeName:t(propertyName),className:t(name),labelName:t(name),namespace:t(name),macroName:t(name),literal,string,docString:t(string),character:t(string),attributeValue:t(string),number,integer:t(number),float:t(number),bool:t(literal),regexp:t(literal),escape:t(literal),color:t(literal),url:t(literal),keyword,self:t(keyword),null:t(keyword),atom:t(keyword),unit:t(keyword),modifier:t(keyword),operatorKeyword:t(keyword),controlKeyword:t(keyword),definitionKeyword:t(keyword),moduleKeyword:t(keyword),operator,derefOperator:t(operator),arithmeticOperator:t(operator),logicOperator:t(operator),bitwiseOperator:t(operator),compareOperator:t(operator),updateOperator:t(operator),definitionOperator:t(operator),typeOperator:t(operator),controlOperator:t(operator),punctuation,separator:t(punctuation),bracket,angleBracket:t(bracket),squareBracket:t(bracket),paren:t(bracket),brace:t(bracket),content,heading,heading1:t(heading),heading2:t(heading),heading3:t(heading),heading4:t(heading),heading5:t(heading),heading6:t(heading),contentSeparator:t(content),list:t(content),quote:t(content),emphasis:t(content),strong:t(content),link:t(content),monospace:t(content),strikethrough:t(content),inserted:t(),deleted:t(),changed:t(),invalid:t(),meta,documentMeta:t(meta),annotation:t(meta),processingInstruction:t(meta),definition:Tag.defineModifier(),constant:Tag.defineModifier(),function:Tag.defineModifier(),standard:Tag.defineModifier(),local:Tag.defineModifier(),special:Tag.defineModifier()},classHighlighter=tagHighlighter([{tag:tags.link,class:"tok-link"},{tag:tags.heading,class:"tok-heading"},{tag:tags.emphasis,class:"tok-emphasis"},{tag:tags.strong,class:"tok-strong"},{tag:tags.keyword,class:"tok-keyword"},{tag:tags.atom,class:"tok-atom"},{tag:tags.bool,class:"tok-bool"},{tag:tags.url,class:"tok-url"},{tag:tags.labelName,class:"tok-labelName"},{tag:tags.inserted,class:"tok-inserted"},{tag:tags.deleted,class:"tok-deleted"},{tag:tags.literal,class:"tok-literal"},{tag:tags.string,class:"tok-string"},{tag:tags.number,class:"tok-number"},{tag:[tags.regexp,tags.escape,tags.special(tags.string)],class:"tok-string2"},{tag:tags.variableName,class:"tok-variableName"},{tag:tags.local(tags.variableName),class:"tok-variableName tok-local"},{tag:tags.definition(tags.variableName),class:"tok-variableName tok-definition"},{tag:tags.special(tags.variableName),class:"tok-variableName2"},{tag:tags.definition(tags.propertyName),class:"tok-propertyName tok-definition"},{tag:tags.typeName,class:"tok-typeName"},{tag:tags.namespace,class:"tok-namespace"},{tag:tags.className,class:"tok-className"},{tag:tags.macroName,class:"tok-macroName"},{tag:tags.propertyName,class:"tok-propertyName"},{tag:tags.operator,class:"tok-operator"},{tag:tags.comment,class:"tok-comment"},{tag:tags.meta,class:"tok-meta"},{tag:tags.invalid,class:"tok-invalid"},{tag:tags.punctuation,class:"tok-punctuation"}]);export{Tag,classHighlighter,highlightTags,highlightTree,styleTags,tagHighlighter,tags};