﻿/*
 * Copyright (C) 2013-2019 Combodo SARL
 *
 * This file is part of iTop.
 *
 * iTop is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * iTop is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 */
(function(){function p(a){var c=a.margin?"margin":a.MARGIN?"MARGIN":!1,d,k;if(c){k=CKEDITOR.tools.style.parse.margin(a[c]);for(d in k)a["margin-"+d]=k[d];delete a[c]}}var f,l=CKEDITOR.tools,n={};CKEDITOR.plugins.pastetools.filters.common=n;n.rules=function(a,c,d){return{elements:{table:function(a){a.filterChildren(d);var b=a.parent,c=b&&b.parent,e,h;if(b.name&&"div"===b.name&&b.attributes.align&&1===l.object.keys(b.attributes).length&&1===b.children.length){a.attributes.align=b.attributes.align;e=
b.children.splice(0);a.remove();for(h=e.length-1;0<=h;h--)c.add(e[h],b.getIndex());b.remove()}f.convertStyleToPx(a)},tr:function(a){a.attributes={}},td:function(a){var b=a.getAscendant("table"),b=l.parseCssText(b.attributes.style,!0),g=b.background;g&&f.setStyle(a,"background",g,!0);(b=b["background-color"])&&f.setStyle(a,"background-color",b,!0);var b=l.parseCssText(a.attributes.style,!0),g=b.border?CKEDITOR.tools.style.border.fromCssRule(b.border):{},g=l.style.border.splitCssValues(b,g),e=CKEDITOR.tools.clone(b),
h;for(h in e)0==h.indexOf("border")&&delete e[h];a.attributes.style=CKEDITOR.tools.writeCssText(e);b.background&&(h=CKEDITOR.tools.style.parse.background(b.background),h.color&&(f.setStyle(a,"background-color",h.color,!0),f.setStyle(a,"background","")));for(var m in g)h=b[m]?CKEDITOR.tools.style.border.fromCssRule(b[m]):g[m],"none"===h.style?f.setStyle(a,m,"none"):f.setStyle(a,m,h.toString());f.mapCommonStyles(a);f.convertStyleToPx(a);f.createStyleStack(a,d,c,/margin|text\-align|padding|list\-style\-type|width|height|border|white\-space|vertical\-align|background/i)}}}};
n.styles={setStyle:function(a,c,d,k){var b=l.parseCssText(a.attributes.style);k&&b[c]||(""===d?delete b[c]:b[c]=d,a.attributes.style=CKEDITOR.tools.writeCssText(b))},convertStyleToPx:function(a){var c=a.attributes.style;c&&(a.attributes.style=c.replace(/\d+(\.\d+)?pt/g,function(a){return CKEDITOR.tools.convertToPx(a)+"px"}))},mapStyles:function(a,c){for(var d in c)if(a.attributes[d]){if("function"===typeof c[d])c[d](a.attributes[d]);else f.setStyle(a,c[d],a.attributes[d]);delete a.attributes[d]}},
mapCommonStyles:function(a){return f.mapStyles(a,{vAlign:function(c){f.setStyle(a,"vertical-align",c)},width:function(c){f.setStyle(a,"width",c+"px")},height:function(c){f.setStyle(a,"height",c+"px")}})},normalizedStyles:function(a,c){var d="background-color:transparent border-image:none color:windowtext direction:ltr mso- visibility:visible div:border:none".split(" "),k="font-family font font-size color background-color line-height text-decoration".split(" "),b=function(){for(var a=[],b=0;b<arguments.length;b++)arguments[b]&&
a.push(arguments[b]);return-1!==l.indexOf(d,a.join(":"))},g=!0===CKEDITOR.plugins.pastetools.getConfigValue(c,"removeFontStyles"),e=l.parseCssText(a.attributes.style);"cke:li"==a.name&&(e["TEXT-INDENT"]&&e.MARGIN?(a.attributes["cke-indentation"]=n.lists.getElementIndentation(a),e.MARGIN=e.MARGIN.replace(/(([\w\.]+ ){3,3})[\d\.]+(\w+$)/,"$10$3")):delete e["TEXT-INDENT"],delete e["text-indent"]);for(var h=l.object.keys(e),m=0;m<h.length;m++){var f=h[m].toLowerCase(),r=e[h[m]],t=CKEDITOR.tools.indexOf;
(g&&-1!==t(k,f.toLowerCase())||b(null,f,r)||b(null,f.replace(/\-.*$/,"-"))||b(null,f)||b(a.name,f,r)||b(a.name,f.replace(/\-.*$/,"-"))||b(a.name,f)||b(r))&&delete e[h[m]]}var u=CKEDITOR.plugins.pastetools.getConfigValue(c,"keepZeroMargins");p(e);(function(){CKEDITOR.tools.array.forEach(["top","right","bottom","left"],function(a){a="margin-"+a;if(a in e){var b=CKEDITOR.tools.convertToPx(e[a]);b||u?e[a]=b?b+"px":0:delete e[a]}})})();return CKEDITOR.tools.writeCssText(e)},createStyleStack:function(a,
c,d,k){var b=[];a.filterChildren(c);for(c=a.children.length-1;0<=c;c--)b.unshift(a.children[c]),a.children[c].remove();f.sortStyles(a);c=l.parseCssText(f.normalizedStyles(a,d));d=a;var g="span"===a.name,e;for(e in c)if(!e.match(k||/margin((?!-)|-left|-top|-bottom|-right)|text-indent|text-align|width|border|padding/i))if(g)g=!1;else{var h=new CKEDITOR.htmlParser.element("span");h.attributes.style=e+":"+c[e];d.add(h);d=h;delete c[e]}CKEDITOR.tools.isEmpty(c)?delete a.attributes.style:a.attributes.style=
CKEDITOR.tools.writeCssText(c);for(c=0;c<b.length;c++)d.add(b[c])},sortStyles:function(a){for(var c=["border","border-bottom","font-size","background"],d=l.parseCssText(a.attributes.style),k=l.object.keys(d),b=[],g=[],e=0;e<k.length;e++)-1!==l.indexOf(c,k[e].toLowerCase())?b.push(k[e]):g.push(k[e]);b.sort(function(a,b){var e=l.indexOf(c,a.toLowerCase()),d=l.indexOf(c,b.toLowerCase());return e-d});k=[].concat(b,g);b={};for(e=0;e<k.length;e++)b[k[e]]=d[k[e]];a.attributes.style=CKEDITOR.tools.writeCssText(b)},
pushStylesLower:function(a,c,d){if(!a.attributes.style||0===a.children.length)return!1;c=c||{};var k={"list-style-type":!0,width:!0,height:!0,border:!0,"border-":!0},b=l.parseCssText(a.attributes.style),g;for(g in b)if(!(g.toLowerCase()in k||k[g.toLowerCase().replace(/\-.*$/,"-")]||g.toLowerCase()in c)){for(var e=!1,h=0;h<a.children.length;h++){var m=a.children[h];if(m.type===CKEDITOR.NODE_TEXT&&d){var q=new CKEDITOR.htmlParser.element("span");q.setHtml(m.value);m.replaceWith(q);m=q}m.type===CKEDITOR.NODE_ELEMENT&&
(e=!0,f.setStyle(m,g,b[g]))}e&&delete b[g]}a.attributes.style=CKEDITOR.tools.writeCssText(b);return!0},inliner:{filtered:"break-before break-after break-inside page-break page-break-before page-break-after page-break-inside".split(" "),parse:function(a){function c(a){var b=new CKEDITOR.dom.element("style"),c=new CKEDITOR.dom.element("iframe");c.hide();CKEDITOR.document.getBody().append(c);c.$.contentDocument.documentElement.appendChild(b.$);b.$.textContent=a;c.remove();return b.$.sheet}function d(a){var b=
a.indexOf("{"),c=a.indexOf("}");return k(a.substring(b+1,c),!0)}var k=CKEDITOR.tools.parseCssText,b=f.inliner.filter,g=a.is?a.$.sheet:c(a);a=[];var e;if(g)for(g=g.cssRules,e=0;e<g.length;e++)g[e].type===window.CSSRule.STYLE_RULE&&a.push({selector:g[e].selectorText,styles:b(d(g[e].cssText))});return a},filter:function(a){var c=f.inliner.filtered,d=l.array.indexOf,k={},b;for(b in a)-1===d(c,b)&&(k[b]=a[b]);return k},sort:function(a){return a.sort(function(a){var d=CKEDITOR.tools.array.map(a,function(a){return a.selector});
return function(a,b){var c=-1!==(""+a.selector).indexOf(".")?1:0,c=(-1!==(""+b.selector).indexOf(".")?1:0)-c;return 0!==c?c:d.indexOf(b.selector)-d.indexOf(a.selector)}}(a))},inline:function(a){var c=f.inliner.parse,d=f.inliner.sort,k=function(a){a=(new DOMParser).parseFromString(a,"text/html");return new CKEDITOR.dom.document(a)}(a);a=k.find("style");d=d(function(a){var d=[],e;for(e=0;e<a.count();e++)d=d.concat(c(a.getItem(e)));return d}(a));CKEDITOR.tools.array.forEach(d,function(a){var c=a.styles;
a=k.find(a.selector);var e,d,f;p(c);for(f=0;f<a.count();f++)e=a.getItem(f),d=CKEDITOR.tools.parseCssText(e.getAttribute("style")),p(d),d=CKEDITOR.tools.extend({},d,c),e.setAttribute("style",CKEDITOR.tools.writeCssText(d))});return k}}};f=n.styles;n.lists={getElementIndentation:function(a){a=l.parseCssText(a.attributes.style);if(a.margin||a.MARGIN){a.margin=a.margin||a.MARGIN;var c={styles:{margin:a.margin}};CKEDITOR.filter.transformationsTools.splitMarginShorthand(c);a["margin-left"]=c.styles["margin-left"]}return parseInt(l.convertToPx(a["margin-left"]||
"0px"),10)}};n.elements={replaceWithChildren:function(a){for(var c=a.children.length-1;0<=c;c--)a.children[c].insertAfter(a)}};n.createAttributeStack=function(a,c){var d,f=[];a.filterChildren(c);for(d=a.children.length-1;0<=d;d--)f.unshift(a.children[d]),a.children[d].remove();d=a.attributes;var b=a,g=!0,e;for(e in d)if(g)g=!1;else{var h=new CKEDITOR.htmlParser.element(a.name);h.attributes[e]=d[e];b.add(h);b=h;delete d[e]}for(d=0;d<f.length;d++)b.add(f[d])};n.parseShorthandMargins=p})();