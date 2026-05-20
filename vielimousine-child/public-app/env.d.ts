/// <reference types="vite/client" />

declare module '*.vue' {
  import type { DefineComponent } from 'vue';
  const component: DefineComponent<{}, {}, any>;
  export default component;
}

interface Window {
  VieRest?: {
    root: string;
    nonce: string;
    checkoutUrl?: string;
    successUrl?: string;
  };
}
