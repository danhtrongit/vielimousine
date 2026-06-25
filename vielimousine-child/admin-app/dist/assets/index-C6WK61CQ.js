import{s as b}from"./index-Cq99_dkG.js";import{B as p,o as l,c as u,G as d,p as o,ax as T,ay as V,z as g,av as E,a2 as h,v as R,az as P,s as A,y as m,f as y,E as $,g as k,a as B,a7 as C,m as _,ap as f,w as O,A as S,H as L,ar as F}from"./index-B9tqnjFD.js";import{s as D}from"./index-D0ZYQj6D.js";import{s as W}from"./index-B1tNZQT0.js";import{R as I,f as N}from"./index-DMaR35t7.js";var H=`
    .p-tabs {
        display: flex;
        flex-direction: column;
    }

    .p-tablist {
        display: flex;
        position: relative;
        overflow: hidden;
        background: dt('tabs.tablist.background');
    }

    .p-tablist-viewport {
        overflow-x: auto;
        overflow-y: hidden;
        scroll-behavior: smooth;
        scrollbar-width: none;
        overscroll-behavior: contain auto;
    }

    .p-tablist-viewport::-webkit-scrollbar {
        display: none;
    }

    .p-tablist-tab-list {
        position: relative;
        display: flex;
        border-style: solid;
        border-color: dt('tabs.tablist.border.color');
        border-width: dt('tabs.tablist.border.width');
    }

    .p-tablist-content {
        flex-grow: 1;
    }

    .p-tablist-nav-button {
        all: unset;
        position: absolute !important;
        flex-shrink: 0;
        inset-block-start: 0;
        z-index: 2;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: dt('tabs.nav.button.background');
        color: dt('tabs.nav.button.color');
        width: dt('tabs.nav.button.width');
        transition:
            color dt('tabs.transition.duration'),
            outline-color dt('tabs.transition.duration'),
            box-shadow dt('tabs.transition.duration');
        box-shadow: dt('tabs.nav.button.shadow');
        outline-color: transparent;
        cursor: pointer;
    }

    .p-tablist-nav-button:focus-visible {
        z-index: 1;
        box-shadow: dt('tabs.nav.button.focus.ring.shadow');
        outline: dt('tabs.nav.button.focus.ring.width') dt('tabs.nav.button.focus.ring.style') dt('tabs.nav.button.focus.ring.color');
        outline-offset: dt('tabs.nav.button.focus.ring.offset');
    }

    .p-tablist-nav-button:hover {
        color: dt('tabs.nav.button.hover.color');
    }

    .p-tablist-prev-button {
        inset-inline-start: 0;
    }

    .p-tablist-next-button {
        inset-inline-end: 0;
    }

    .p-tablist-prev-button:dir(rtl),
    .p-tablist-next-button:dir(rtl) {
        transform: rotate(180deg);
    }

    .p-tab {
        flex-shrink: 0;
        cursor: pointer;
        user-select: none;
        position: relative;
        border-style: solid;
        white-space: nowrap;
        gap: dt('tabs.tab.gap');
        background: dt('tabs.tab.background');
        border-width: dt('tabs.tab.border.width');
        border-color: dt('tabs.tab.border.color');
        color: dt('tabs.tab.color');
        padding: dt('tabs.tab.padding');
        font-weight: dt('tabs.tab.font.weight');
        transition:
            background dt('tabs.transition.duration'),
            border-color dt('tabs.transition.duration'),
            color dt('tabs.transition.duration'),
            outline-color dt('tabs.transition.duration'),
            box-shadow dt('tabs.transition.duration');
        margin: dt('tabs.tab.margin');
        outline-color: transparent;
    }

    .p-tab:not(.p-disabled):focus-visible {
        z-index: 1;
        box-shadow: dt('tabs.tab.focus.ring.shadow');
        outline: dt('tabs.tab.focus.ring.width') dt('tabs.tab.focus.ring.style') dt('tabs.tab.focus.ring.color');
        outline-offset: dt('tabs.tab.focus.ring.offset');
    }

    .p-tab:not(.p-tab-active):not(.p-disabled):hover {
        background: dt('tabs.tab.hover.background');
        border-color: dt('tabs.tab.hover.border.color');
        color: dt('tabs.tab.hover.color');
    }

    .p-tab-active {
        background: dt('tabs.tab.active.background');
        border-color: dt('tabs.tab.active.border.color');
        color: dt('tabs.tab.active.color');
    }

    .p-tabpanels {
        background: dt('tabs.tabpanel.background');
        color: dt('tabs.tabpanel.color');
        padding: dt('tabs.tabpanel.padding');
        outline: 0 none;
    }

    .p-tabpanel:focus-visible {
        box-shadow: dt('tabs.tabpanel.focus.ring.shadow');
        outline: dt('tabs.tabpanel.focus.ring.width') dt('tabs.tabpanel.focus.ring.style') dt('tabs.tabpanel.focus.ring.color');
        outline-offset: dt('tabs.tabpanel.focus.ring.offset');
    }

    .p-tablist-active-bar {
        z-index: 1;
        display: block;
        position: absolute;
        inset-block-end: dt('tabs.active.bar.bottom');
        height: dt('tabs.active.bar.height');
        background: dt('tabs.active.bar.background');
        transition: 250ms cubic-bezier(0.35, 0, 0.25, 1);
    }
`,U={root:function(t){var n=t.props;return["p-tabs p-component",{"p-tabs-scrollable":n.scrollable}]}},j=p.extend({name:"tabs",style:H,classes:U}),M={name:"BaseTabs",extends:b,props:{value:{type:[String,Number],default:void 0},lazy:{type:Boolean,default:!1},scrollable:{type:Boolean,default:!1},showNavigators:{type:Boolean,default:!0},tabindex:{type:Number,default:0},selectOnFocus:{type:Boolean,default:!1}},style:j,provide:function(){return{$pcTabs:this,$parentInstance:this}}},G={name:"Tabs",extends:M,inheritAttrs:!1,emits:["update:value"],data:function(){return{d_value:this.value}},watch:{value:function(t){this.d_value=t}},methods:{updateValue:function(t){this.d_value!==t&&(this.d_value=t,this.$emit("update:value",t))},isVertical:function(){return this.orientation==="vertical"}}};function Q(e,t,n,s,r,a){return l(),u("div",o({class:e.cx("root")},e.ptmi("root")),[d(e.$slots,"default")],16)}G.render=Q;var q={root:"p-tablist",content:"p-tablist-content p-tablist-viewport",tabList:"p-tablist-tab-list",activeBar:"p-tablist-active-bar",prevButton:"p-tablist-prev-button p-tablist-nav-button",nextButton:"p-tablist-next-button p-tablist-nav-button"},J=p.extend({name:"tablist",classes:q}),X={name:"BaseTabList",extends:b,props:{},style:J,provide:function(){return{$pcTabList:this,$parentInstance:this}}},Y={name:"TabList",extends:X,inheritAttrs:!1,inject:["$pcTabs"],data:function(){return{isPrevButtonEnabled:!1,isNextButtonEnabled:!0}},resizeObserver:void 0,inkBarObserver:void 0,watch:{showNavigators:function(t){t?this.bindResizeObserver():this.unbindResizeObserver()},activeValue:{flush:"post",handler:function(){this.updateInkBar(),this.bindInkBarObserver()}}},mounted:function(){var t=this;setTimeout(function(){t.updateInkBar(),t.bindInkBarObserver()},150),this.showNavigators&&(this.updateButtonState(),this.bindResizeObserver())},updated:function(){this.showNavigators&&this.updateButtonState()},beforeUnmount:function(){this.unbindResizeObserver(),this.unbindInkBarObserver()},methods:{onScroll:function(t){this.showNavigators&&this.updateButtonState(),t.preventDefault()},onPrevButtonClick:function(){var t=this.$refs.content,n=this.getVisibleButtonWidths(),s=T(t)-n,r=Math.abs(t.scrollLeft),a=s*.8,i=r-a,c=Math.max(i,0);t.scrollLeft=P(t)?-1*c:c},onNextButtonClick:function(){var t=this.$refs.content,n=this.getVisibleButtonWidths(),s=T(t)-n,r=Math.abs(t.scrollLeft),a=s*.8,i=r+a,c=t.scrollWidth-s,v=Math.min(i,c);t.scrollLeft=P(t)?-1*v:v},bindResizeObserver:function(){var t=this;this.resizeObserver=new ResizeObserver(function(){return t.updateButtonState()}),this.resizeObserver.observe(this.$refs.list)},unbindResizeObserver:function(){var t;(t=this.resizeObserver)===null||t===void 0||t.unobserve(this.$refs.list),this.resizeObserver=void 0},bindInkBarObserver:function(){var t=this;this.unbindInkBarObserver();var n=this.$refs.content,s=g(n,'[data-pc-name="tab"][data-p-active="true"]');s&&(this.inkBarObserver=new ResizeObserver(function(){return t.updateInkBar()}),this.inkBarObserver.observe(s))},unbindInkBarObserver:function(){var t;(t=this.inkBarObserver)===null||t===void 0||t.disconnect(),this.inkBarObserver=void 0},updateInkBar:function(){var t=this.$refs,n=t.content,s=t.inkbar,r=t.tabs;if(s){var a=g(n,'[data-pc-name="tab"][data-p-active="true"]');this.$pcTabs.isVertical()?(s.style.height=E(a)+"px",s.style.top=h(a).top-h(r).top+"px"):(s.style.width=R(a)+"px",s.style.left=h(a).left-h(r).left+"px")}},updateButtonState:function(){var t=this.$refs,n=t.list,s=t.content,r=s.scrollTop,a=s.scrollWidth,i=s.scrollHeight,c=s.offsetWidth,v=s.offsetHeight,w=Math.abs(s.scrollLeft),x=[T(s),V(s)],z=x[0],K=x[1];this.$pcTabs.isVertical()?(this.isPrevButtonEnabled=r!==0,this.isNextButtonEnabled=n.offsetHeight>=v&&parseInt(r)!==i-K):(this.isPrevButtonEnabled=w!==0,this.isNextButtonEnabled=n.offsetWidth>=c&&parseInt(w)!==a-z)},getVisibleButtonWidths:function(){var t=this.$refs,n=t.prevButton,s=t.nextButton,r=0;return this.showNavigators&&(r=((n==null?void 0:n.offsetWidth)||0)+((s==null?void 0:s.offsetWidth)||0)),r}},computed:{templates:function(){return this.$pcTabs.$slots},activeValue:function(){return this.$pcTabs.d_value},showNavigators:function(){return this.$pcTabs.showNavigators},prevButtonAriaLabel:function(){return this.$primevue.config.locale.aria?this.$primevue.config.locale.aria.previous:void 0},nextButtonAriaLabel:function(){return this.$primevue.config.locale.aria?this.$primevue.config.locale.aria.next:void 0},dataP:function(){return N({scrollable:this.$pcTabs.scrollable})}},components:{ChevronLeftIcon:D,ChevronRightIcon:W},directives:{ripple:I}},Z=["data-p"],tt=["aria-label","tabindex"],et=["data-p"],at=["aria-orientation"],nt=["aria-label","tabindex"];function st(e,t,n,s,r,a){var i=A("ripple");return l(),u("div",o({ref:"list",class:e.cx("root"),"data-p":a.dataP},e.ptmi("root")),[a.showNavigators&&r.isPrevButtonEnabled?m((l(),u("button",o({key:0,ref:"prevButton",type:"button",class:e.cx("prevButton"),"aria-label":a.prevButtonAriaLabel,tabindex:a.$pcTabs.tabindex,onClick:t[0]||(t[0]=function(){return a.onPrevButtonClick&&a.onPrevButtonClick.apply(a,arguments)})},e.ptm("prevButton"),{"data-pc-group-section":"navigator"}),[(l(),y($(a.templates.previcon||"ChevronLeftIcon"),o({"aria-hidden":"true"},e.ptm("prevIcon")),null,16))],16,tt)),[[i]]):k("",!0),B("div",o({ref:"content",class:e.cx("content"),onScroll:t[1]||(t[1]=function(){return a.onScroll&&a.onScroll.apply(a,arguments)}),"data-p":a.dataP},e.ptm("content")),[B("div",o({ref:"tabs",class:e.cx("tabList"),role:"tablist","aria-orientation":a.$pcTabs.orientation||"horizontal"},e.ptm("tabList")),[d(e.$slots,"default"),B("span",o({ref:"inkbar",class:e.cx("activeBar"),role:"presentation","aria-hidden":"true"},e.ptm("activeBar")),null,16)],16,at)],16,et),a.showNavigators&&r.isNextButtonEnabled?m((l(),u("button",o({key:1,ref:"nextButton",type:"button",class:e.cx("nextButton"),"aria-label":a.nextButtonAriaLabel,tabindex:a.$pcTabs.tabindex,onClick:t[2]||(t[2]=function(){return a.onNextButtonClick&&a.onNextButtonClick.apply(a,arguments)})},e.ptm("nextButton"),{"data-pc-group-section":"navigator"}),[(l(),y($(a.templates.nexticon||"ChevronRightIcon"),o({"aria-hidden":"true"},e.ptm("nextIcon")),null,16))],16,nt)),[[i]]):k("",!0)],16,Z)}Y.render=st;var rt={root:function(t){var n=t.instance,s=t.props;return["p-tab",{"p-tab-active":n.active,"p-disabled":s.disabled}]}},it=p.extend({name:"tab",classes:rt}),ot={name:"BaseTab",extends:b,props:{value:{type:[String,Number],default:void 0},disabled:{type:Boolean,default:!1},as:{type:[String,Object],default:"BUTTON"},asChild:{type:Boolean,default:!1}},style:it,provide:function(){return{$pcTab:this,$parentInstance:this}}},lt={name:"Tab",extends:ot,inheritAttrs:!1,inject:["$pcTabs","$pcTabList"],methods:{onFocus:function(){this.$pcTabs.selectOnFocus&&this.changeActiveValue()},onClick:function(){this.changeActiveValue()},onKeydown:function(t){switch(t.code){case"ArrowRight":this.onArrowRightKey(t);break;case"ArrowLeft":this.onArrowLeftKey(t);break;case"Home":this.onHomeKey(t);break;case"End":this.onEndKey(t);break;case"PageDown":this.onPageDownKey(t);break;case"PageUp":this.onPageUpKey(t);break;case"Enter":case"NumpadEnter":case"Space":this.onEnterKey(t);break}},onArrowRightKey:function(t){var n=this.findNextTab(t.currentTarget);n?this.changeFocusedTab(t,n):this.onHomeKey(t),t.preventDefault()},onArrowLeftKey:function(t){var n=this.findPrevTab(t.currentTarget);n?this.changeFocusedTab(t,n):this.onEndKey(t),t.preventDefault()},onHomeKey:function(t){var n=this.findFirstTab();this.changeFocusedTab(t,n),t.preventDefault()},onEndKey:function(t){var n=this.findLastTab();this.changeFocusedTab(t,n),t.preventDefault()},onPageDownKey:function(t){this.scrollInView(this.findLastTab()),t.preventDefault()},onPageUpKey:function(t){this.scrollInView(this.findFirstTab()),t.preventDefault()},onEnterKey:function(t){this.changeActiveValue()},findNextTab:function(t){var n=arguments.length>1&&arguments[1]!==void 0?arguments[1]:!1,s=n?t:t.nextElementSibling;return s?f(s,"data-p-disabled")||f(s,"data-pc-section")==="activebar"?this.findNextTab(s):g(s,'[data-pc-name="tab"]'):null},findPrevTab:function(t){var n=arguments.length>1&&arguments[1]!==void 0?arguments[1]:!1,s=n?t:t.previousElementSibling;return s?f(s,"data-p-disabled")||f(s,"data-pc-section")==="activebar"?this.findPrevTab(s):g(s,'[data-pc-name="tab"]'):null},findFirstTab:function(){return this.findNextTab(this.$pcTabList.$refs.tabs.firstElementChild,!0)},findLastTab:function(){return this.findPrevTab(this.$pcTabList.$refs.tabs.lastElementChild,!0)},changeActiveValue:function(){this.$pcTabs.updateValue(this.value)},changeFocusedTab:function(t,n){_(n),this.scrollInView(n)},scrollInView:function(t){var n;t==null||(n=t.scrollIntoView)===null||n===void 0||n.call(t,{block:"nearest"})}},computed:{active:function(){var t;return C((t=this.$pcTabs)===null||t===void 0?void 0:t.d_value,this.value)},id:function(){var t;return"".concat((t=this.$pcTabs)===null||t===void 0?void 0:t.$id,"_tab_").concat(this.value)},ariaControls:function(){var t;return"".concat((t=this.$pcTabs)===null||t===void 0?void 0:t.$id,"_tabpanel_").concat(this.value)},attrs:function(){return o(this.asAttrs,this.a11yAttrs,this.ptmi("root",this.ptParams))},asAttrs:function(){return this.as==="BUTTON"?{type:"button",disabled:this.disabled}:void 0},a11yAttrs:function(){return{id:this.id,tabindex:this.active?this.$pcTabs.tabindex:-1,role:"tab","aria-selected":this.active,"aria-controls":this.ariaControls,"data-pc-name":"tab","data-p-disabled":this.disabled,"data-p-active":this.active,onFocus:this.onFocus,onKeydown:this.onKeydown}},ptParams:function(){return{context:{active:this.active}}},dataP:function(){return N({active:this.active})}},directives:{ripple:I}};function ct(e,t,n,s,r,a){var i=A("ripple");return e.asChild?d(e.$slots,"default",{key:1,dataP:a.dataP,class:S(e.cx("root")),active:a.active,a11yAttrs:a.a11yAttrs,onClick:a.onClick}):m((l(),y($(e.as),o({key:0,class:e.cx("root"),"data-p":a.dataP,onClick:a.onClick},a.attrs),{default:O(function(){return[d(e.$slots,"default")]}),_:3},16,["class","data-p","onClick"])),[[i]])}lt.render=ct;var dt={root:"p-tabpanels"},ut=p.extend({name:"tabpanels",classes:dt}),bt={name:"BaseTabPanels",extends:b,props:{},style:ut,provide:function(){return{$pcTabPanels:this,$parentInstance:this}}},pt={name:"TabPanels",extends:bt,inheritAttrs:!1};function vt(e,t,n,s,r,a){return l(),u("div",o({class:e.cx("root"),role:"presentation"},e.ptmi("root")),[d(e.$slots,"default")],16)}pt.render=vt;var ht={root:function(t){var n=t.instance;return["p-tabpanel",{"p-tabpanel-active":n.active}]}},ft=p.extend({name:"tabpanel",classes:ht}),gt={name:"BaseTabPanel",extends:b,props:{value:{type:[String,Number],default:void 0},as:{type:[String,Object],default:"DIV"},asChild:{type:Boolean,default:!1},header:null,headerStyle:null,headerClass:null,headerProps:null,headerActionProps:null,contentStyle:null,contentClass:null,contentProps:null,disabled:Boolean},style:ft,provide:function(){return{$pcTabPanel:this,$parentInstance:this}}},mt={name:"TabPanel",extends:gt,inheritAttrs:!1,inject:["$pcTabs"],computed:{active:function(){var t;return C((t=this.$pcTabs)===null||t===void 0?void 0:t.d_value,this.value)},id:function(){var t;return"".concat((t=this.$pcTabs)===null||t===void 0?void 0:t.$id,"_tabpanel_").concat(this.value)},ariaLabelledby:function(){var t;return"".concat((t=this.$pcTabs)===null||t===void 0?void 0:t.$id,"_tab_").concat(this.value)},attrs:function(){return o(this.a11yAttrs,this.ptmi("root",this.ptParams))},a11yAttrs:function(){var t;return{id:this.id,tabindex:(t=this.$pcTabs)===null||t===void 0?void 0:t.tabindex,role:"tabpanel","aria-labelledby":this.ariaLabelledby,"data-pc-name":"tabpanel","data-p-active":this.active}},ptParams:function(){return{context:{active:this.active}}}}};function yt(e,t,n,s,r,a){var i,c;return a.$pcTabs?(l(),u(L,{key:1},[e.asChild?d(e.$slots,"default",{key:1,class:S(e.cx("root")),active:a.active,a11yAttrs:a.a11yAttrs}):(l(),u(L,{key:0},[!((i=a.$pcTabs)!==null&&i!==void 0&&i.lazy)||a.active?m((l(),y($(e.as),o({key:0,class:e.cx("root")},a.attrs),{default:O(function(){return[d(e.$slots,"default")]}),_:3},16,["class"])),[[F,(c=a.$pcTabs)!==null&&c!==void 0&&c.lazy?!0:a.active]]):k("",!0)],64))],64)):d(e.$slots,"default",{key:0})}mt.render=yt;export{Y as a,lt as b,pt as c,mt as d,G as s};
