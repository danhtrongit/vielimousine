function l(){function o(e){if(e==null)return"";const n=String(e);return n.includes(",")||n.includes('"')||n.includes(`
`)?`"${n.replace(/"/g,'""')}"`:n}function r(e,n,u){const s="\uFEFF"+n.map(o).join(",")+`
`+u.map(i=>i.map(o).join(",")).join(`
`),d=new Blob([s],{type:"text/csv;charset=utf-8;"}),c=URL.createObjectURL(d),t=document.createElement("a");t.href=c,t.download=e,document.body.appendChild(t),t.click(),document.body.removeChild(t),URL.revokeObjectURL(c)}return{downloadCsv:r}}export{l as u};
