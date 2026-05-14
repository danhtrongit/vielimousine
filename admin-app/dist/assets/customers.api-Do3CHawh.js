import{W as e}from"./index-CiAJSMUT.js";const o={list:(t={})=>e.get("/customers",{params:t}).then(s=>s.data),get:t=>e.get(`/customers/${t}`).then(s=>s.data)};export{o as c};
