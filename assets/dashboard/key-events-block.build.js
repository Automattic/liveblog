!function(e){function t(r){if(n[r])return n[r].exports;var l=n[r]={i:r,l:!1,exports:{}};return e[r].call(l.exports,l,l.exports,t),l.l=!0,l.exports}var n={};t.m=e,t.c=n,t.d=function(e,n,r){t.o(e,n)||Object.defineProperty(e,n,{configurable:!1,enumerable:!0,get:r})},t.n=function(e){var n=e&&e.__esModule?function(){return e.default}:function(){return e};return t.d(n,"a",n),n},t.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},t.p="",t(t.s=816)}({816:function(e,t,n){"use strict";!function(){var e=window.wp.i18n.__,t=window.wp.blocks,n=t.registerBlockType,r=t.Editable;n("gutenberg/liveblog-key-events-block",{title:e("Liveblog Key Events"),icon:"universal-access-alt",category:"widgets",useOnce:!0,customClassName:!1,html:!1,attributes:{title:{type:"string",default:"Key Events"}},edit:function(e){var t=e.className,n=e.setAttributes,l=e.attributes,i=e.focus,o=l.title,s=function(e){return n({title:e})};return React.createElement("div",{className:t},React.createElement("h2",null,"Liveblog Key Events"),React.createElement("p",null,"A list of key events displayed when the user is viewing a Liveblog post."),React.createElement("div",{style:{display:i?"block":"none"}},React.createElement("h3",{style:{fontSize:"1.2rem",marginBottom:".25rem"}},"Title:"),React.createElement(r,{tagName:"p",style:{display:"block",padding:".5rem",border:"1px solid #eee",flexGrow:"1"},className:t,onChange:s,value:o,focus:!1,onFocus:!1})))},save:function(e){var t=e.attributes.title;return'[liveblog_key_events title="'+(void 0===t?"Key Events":t)+'"]'}})}()}});