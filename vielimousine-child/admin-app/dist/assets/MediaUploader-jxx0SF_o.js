import{M as F,m as H}from"./media.api-BwYa-kzX.js";import{d as D,c as r,g as $,H as T,I as q,o as s,f as j,W as K,_ as x,B as O,p as y,G as W,k as B,t as G,a as g,b as X,e as I,h as N,A as V,r as k}from"./index-Bs41WYln.js";import{f as J,s as Q}from"./index-Dp5fz-ZG.js";import{s as R}from"./index-CthKSdX3.js";import{u as Y}from"./useNotify-DJ5RL4tH.js";const Z={class:"grid"},ee={key:0,class:"empty"},ne={key:1,class:"empty"},te=D({__name:"MediaGrid",props:{items:{},loading:{type:Boolean,default:!1},selectable:{default:"none"},selected:{default:()=>[]}},emits:["update:selected","open"],setup(e,{emit:f}){const u=e,v=f,c=K(()=>new Set(u.selected));function l(o){if(u.selectable==="none"){v("open",o.id);return}if(u.selectable==="single"){v("update:selected",c.value.has(o.id)?[]:[o.id]);return}const p=new Set(c.value);p.has(o.id)?p.delete(o.id):p.add(o.id),v("update:selected",Array.from(p))}return(o,p)=>(s(),r("div",Z,[e.loading&&e.items.length===0?(s(),r("div",ee,"Đang tải…")):e.items.length===0?(s(),r("div",ne,"Chưa có ảnh nào.")):$("",!0),(s(!0),r(T,null,q(e.items,i=>(s(),j(F,{key:i.id,item:i,size:"md",clickable:"",selected:c.value.has(i.id),onClick:P=>l(i)},null,8,["item","selected","onClick"]))),128))]))}}),Be=x(te,[["__scopeId","data-v-83259f44"]]);var ae=`
    .p-progressbar {
        display: block;
        position: relative;
        overflow: hidden;
        height: dt('progressbar.height');
        background: dt('progressbar.background');
        border-radius: dt('progressbar.border.radius');
    }

    .p-progressbar-value {
        margin: 0;
        background: dt('progressbar.value.background');
    }

    .p-progressbar-label {
        color: dt('progressbar.label.color');
        font-size: dt('progressbar.label.font.size');
        font-weight: dt('progressbar.label.font.weight');
    }

    .p-progressbar-determinate .p-progressbar-value {
        height: 100%;
        width: 0%;
        position: absolute;
        display: none;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        transition: width 1s ease-in-out;
    }

    .p-progressbar-determinate .p-progressbar-label {
        display: inline-flex;
    }

    .p-progressbar-indeterminate .p-progressbar-value::before {
        content: '';
        position: absolute;
        background: inherit;
        inset-block-start: 0;
        inset-inline-start: 0;
        inset-block-end: 0;
        will-change: inset-inline-start, inset-inline-end;
        animation: p-progressbar-indeterminate-anim 2.1s cubic-bezier(0.65, 0.815, 0.735, 0.395) infinite;
    }

    .p-progressbar-indeterminate .p-progressbar-value::after {
        content: '';
        position: absolute;
        background: inherit;
        inset-block-start: 0;
        inset-inline-start: 0;
        inset-block-end: 0;
        will-change: inset-inline-start, inset-inline-end;
        animation: p-progressbar-indeterminate-anim-short 2.1s cubic-bezier(0.165, 0.84, 0.44, 1) infinite;
        animation-delay: 1.15s;
    }

    @keyframes p-progressbar-indeterminate-anim {
        0% {
            inset-inline-start: -35%;
            inset-inline-end: 100%;
        }
        60% {
            inset-inline-start: 100%;
            inset-inline-end: -90%;
        }
        100% {
            inset-inline-start: 100%;
            inset-inline-end: -90%;
        }
    }
    @-webkit-keyframes p-progressbar-indeterminate-anim {
        0% {
            inset-inline-start: -35%;
            inset-inline-end: 100%;
        }
        60% {
            inset-inline-start: 100%;
            inset-inline-end: -90%;
        }
        100% {
            inset-inline-start: 100%;
            inset-inline-end: -90%;
        }
    }

    @keyframes p-progressbar-indeterminate-anim-short {
        0% {
            inset-inline-start: -200%;
            inset-inline-end: 100%;
        }
        60% {
            inset-inline-start: 107%;
            inset-inline-end: -8%;
        }
        100% {
            inset-inline-start: 107%;
            inset-inline-end: -8%;
        }
    }
    @-webkit-keyframes p-progressbar-indeterminate-anim-short {
        0% {
            inset-inline-start: -200%;
            inset-inline-end: 100%;
        }
        60% {
            inset-inline-start: 107%;
            inset-inline-end: -8%;
        }
        100% {
            inset-inline-start: 107%;
            inset-inline-end: -8%;
        }
    }
`,se={root:function(f){var u=f.instance;return["p-progressbar p-component",{"p-progressbar-determinate":u.determinate,"p-progressbar-indeterminate":u.indeterminate}]},value:"p-progressbar-value",label:"p-progressbar-label"},ie=O.extend({name:"progressbar",style:ae,classes:se}),re={name:"BaseProgressBar",extends:R,props:{value:{type:Number,default:null},mode:{type:String,default:"determinate"},showValue:{type:Boolean,default:!0}},style:ie,provide:function(){return{$pcProgressBar:this,$parentInstance:this}}},L={name:"ProgressBar",extends:re,inheritAttrs:!1,computed:{progressStyle:function(){return{width:this.value+"%",display:"flex"}},indeterminate:function(){return this.mode==="indeterminate"},determinate:function(){return this.mode==="determinate"},dataP:function(){return J({determinate:this.determinate,indeterminate:this.indeterminate})}}},le=["aria-valuenow","data-p"],oe=["data-p"],de=["data-p"],ue=["data-p"];function pe(e,f,u,v,c,l){return s(),r("div",y({role:"progressbar",class:e.cx("root"),"aria-valuemin":"0","aria-valuenow":e.value,"aria-valuemax":"100","data-p":l.dataP},e.ptmi("root")),[l.determinate?(s(),r("div",y({key:0,class:e.cx("value"),style:l.progressStyle,"data-p":l.dataP},e.ptm("value")),[e.value!=null&&e.value!==0&&e.showValue?(s(),r("div",y({key:0,class:e.cx("label"),"data-p":l.dataP},e.ptm("label")),[W(e.$slots,"default",{},function(){return[B(G(e.value+"%"),1)]})],16,de)):$("",!0)],16,oe)):l.indeterminate?(s(),r("div",y({key:1,class:e.cx("value"),"data-p":l.dataP},e.ptm("value")),null,16,ue)):$("",!0)],16,le)}L.render=pe;const ce=["accept"],me={key:0,class:"queue"},ge={class:"name"},fe={key:1,class:"badge done"},ve=["title"],be=D({__name:"MediaUploader",props:{accept:{default:"image/jpeg,image/png,image/webp,image/gif"},maxBytes:{default:10*1024*1024}},emits:["uploaded"],setup(e,{emit:f}){const u=e,v=f,c=Y(),l=k(null),o=k(!1),p=k(!1),i=k([]);function P(){var d;(d=l.value)==null||d.click()}function U(d){var n,t;d.preventDefault(),o.value=!1,(t=(n=d.dataTransfer)==null?void 0:n.files)!=null&&t.length&&S(Array.from(d.dataTransfer.files))}function E(d){var t;const n=d.target;(t=n.files)!=null&&t.length&&(S(Array.from(n.files)),n.value="")}async function S(d){const n=new Set(u.accept.split(",").map(a=>a.trim())),t=[];for(const a of d){if(!n.has(a.type)){c.error(`Bỏ qua "${a.name}": định dạng không hỗ trợ.`);continue}if(a.size>u.maxBytes){c.error(`Bỏ qua "${a.name}": vượt 10MB.`);continue}t.push(a)}if(t.length===0)return;p.value=!0,i.value=t.map(a=>({name:a.name,percent:0,status:"pending"}));const h=await Promise.all(t.map((a,b)=>H.upload(a,m=>{i.value[b].percent=m}).then(m=>(i.value[b].percent=100,i.value[b].status="done",m.data)).catch(m=>{var M,z,_,A;i.value[b].status="error";const C=((A=(_=(z=(M=m==null?void 0:m.response)==null?void 0:M.data)==null?void 0:z.errors)==null?void 0:_[0])==null?void 0:A.message)??"Lỗi không xác định";return i.value[b].error=C,c.error(`Upload "${a.name}" lỗi: ${C}`),null})));p.value=!1;const w=h.filter(a=>a!==null);w.length>0&&(c.success(`Đã upload ${w.length}/${t.length} ảnh.`),v("uploaded",w)),h.every(a=>a!==null)&&setTimeout(()=>{i.value=[]},1500)}return(d,n)=>(s(),r("div",{class:V(["dropzone",{over:o.value,uploading:p.value}]),onDragover:n[0]||(n[0]=N(t=>o.value=!0,["prevent"])),onDragleave:n[1]||(n[1]=N(t=>o.value=!1,["prevent"])),onDrop:U},[n[4]||(n[4]=g("i",{class:"pi pi-cloud-upload icon"},null,-1)),n[5]||(n[5]=g("p",{class:"hint"},"Kéo thả ảnh vào đây hoặc",-1)),X(I(Q),{label:"Chọn file",icon:"pi pi-folder-open",outlined:"",onClick:P,disabled:p.value},null,8,["disabled"]),n[6]||(n[6]=g("p",{class:"muted"},"jpg / png / webp / gif — tối đa 10MB / file",-1)),g("input",{ref_key:"fileInput",ref:l,type:"file",accept:e.accept,multiple:"",hidden:"",onChange:E},null,40,ce),i.value.length>0?(s(),r("ul",me,[(s(!0),r(T,null,q(i.value,(t,h)=>(s(),r("li",{key:h,class:V(t.status)},[g("span",ge,G(t.name),1),t.status==="pending"?(s(),j(I(L),{key:0,value:t.percent,"show-value":!1,style:{height:"6px"}},null,8,["value"])):t.status==="done"?(s(),r("span",fe,[...n[2]||(n[2]=[g("i",{class:"pi pi-check"},null,-1),B(" Xong",-1)])])):(s(),r("span",{key:2,class:"badge err",title:t.error},[...n[3]||(n[3]=[g("i",{class:"pi pi-times"},null,-1),B(" Lỗi",-1)])],8,ve))],2))),128))])):$("",!0)],34))}}),Pe=x(be,[["__scopeId","data-v-8245ea3f"]]);export{Be as M,Pe as a};
