(()=>{"use strict";var e={20:(e,t,r)=>{var o=r(609),n=Symbol.for("react.element"),s=(Symbol.for("react.fragment"),Object.prototype.hasOwnProperty),i=o.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED.ReactCurrentOwner,a={key:!0,ref:!0,__self:!0,__source:!0};t.jsx=function(e,t,r){var o,p={},c=null,d=null;for(o in void 0!==r&&(c=""+r),void 0!==t.key&&(c=""+t.key),void 0!==t.ref&&(d=t.ref),t)s.call(t,o)&&!a.hasOwnProperty(o)&&(p[o]=t[o]);if(e&&e.defaultProps)for(o in t=e.defaultProps)void 0===p[o]&&(p[o]=t[o]);return{$$typeof:n,type:e,key:c,ref:d,props:p,_owner:i.current}}},848:(e,t,r)=>{e.exports=r(20)},609:e=>{e.exports=window.React}},t={};const r=window.wp.i18n,o=window.wc.wcBlocksRegistry,n=window.wp.htmlEntities,s=window.wc.wcSettings;var i=function r(o){var n=t[o];if(void 0!==n)return n.exports;var s=t[o]={exports:{}};return e[o](s,s.exports,r),s.exports}(848);const a=(0,s.getSetting)("finpay_data",{}),p=(0,r.__)("Finpay Payments","woo-gutenberg-products-block"),c=(0,n.decodeEntities)(a.title)||p,d=()=>(0,n.decodeEntities)(a.description||""),w=e=>{const{PaymentMethodLabel:t}=e.components;return(0,i.jsx)(t,{text:c})},f={name:"finpay",label:(0,i.jsx)(w,{}),content:(0,i.jsx)(d,{}),edit:(0,i.jsx)(d,{}),canMakePayment:()=>!0,ariaLabel:c,supports:{features:a.supports}};(0,o.registerPaymentMethod)(f)})();