import { definePreset } from '@primeuix/themes';
import Aura from '@primeuix/themes/aura';

/**
 * VieLimo Admin theme preset.
 * Extends Aura with brand green (#00a651) scale + slate neutrals.
 * Single source of truth for all PrimeVue token resolution.
 */
export const VieLimoPreset = definePreset(Aura, {
  primitive: {
    vielimo: {
      50: '#e8f8ef',
      100: '#c6ecd5',
      200: '#93dcb0',
      300: '#57c987',
      400: '#1eb568',
      500: '#00a651',
      600: '#009247',
      700: '#00793a',
      800: '#00602f',
      900: '#004f28',
      950: '#002c16',
    },
  },
  semantic: {
    primary: {
      50: '{vielimo.50}',
      100: '{vielimo.100}',
      200: '{vielimo.200}',
      300: '{vielimo.300}',
      400: '{vielimo.400}',
      500: '{vielimo.500}',
      600: '{vielimo.600}',
      700: '{vielimo.700}',
      800: '{vielimo.800}',
      900: '{vielimo.900}',
      950: '{vielimo.950}',
    },
    focusRing: {
      width: '2px',
      style: 'solid',
      color: '{primary.500}',
      offset: '2px',
      shadow: '0 0 0 4px rgba(0, 166, 81, 0.15)',
    },
    formField: {
      borderRadius: '8px',
      paddingX: '0.75rem',
      paddingY: '0.55rem',
      transitionDuration: '180ms',
    },
    list: {
      padding: '0.25rem 0.25rem',
      gap: '2px',
      option: {
        padding: '0.5rem 0.75rem',
        borderRadius: '6px',
      },
    },
    navigation: {
      item: {
        padding: '0.5rem 0.75rem',
        borderRadius: '8px',
        gap: '0.5rem',
      },
    },
    colorScheme: {
      light: {
        primary: {
          color: '{primary.500}',
          contrastColor: '#ffffff',
          hoverColor: '{primary.600}',
          activeColor: '{primary.700}',
        },
        highlight: {
          background: '{primary.50}',
          focusBackground: '{primary.100}',
          color: '{primary.700}',
          focusColor: '{primary.800}',
        },
        surface: {
          0: '#ffffff',
          50: '#f8fafc',
          100: '#f1f5f9',
          200: '#e2e8f0',
          300: '#cbd5e1',
          400: '#94a3b8',
          500: '#64748b',
          600: '#475569',
          700: '#334155',
          800: '#1e293b',
          900: '#0f172a',
          950: '#020617',
        },
        formField: {
          background: '#ffffff',
          borderColor: '{surface.300}',
          hoverBorderColor: '{surface.400}',
          focusBorderColor: '{primary.500}',
          invalidBorderColor: '{red.500}',
          color: '{surface.900}',
          placeholderColor: '{surface.400}',
        },
        text: {
          color: '{surface.900}',
          mutedColor: '{surface.500}',
        },
        content: {
          background: '#ffffff',
          hoverBackground: '{surface.50}',
          borderColor: '{surface.200}',
          color: '{surface.900}',
        },
      },
      dark: {
        primary: {
          color: '{primary.400}',
          contrastColor: '{surface.950}',
          hoverColor: '{primary.300}',
          activeColor: '{primary.200}',
        },
        highlight: {
          background: 'color-mix(in srgb, {primary.400}, transparent 80%)',
          focusBackground: 'color-mix(in srgb, {primary.400}, transparent 70%)',
          color: '{primary.300}',
          focusColor: '{primary.200}',
        },
        surface: {
          0: '#ffffff',
          50: '#f8fafc',
          100: '#f1f5f9',
          200: '#e2e8f0',
          300: '#cbd5e1',
          400: '#94a3b8',
          500: '#64748b',
          600: '#475569',
          700: '#334155',
          800: '#1e293b',
          900: '#0f172a',
          950: '#020617',
        },
        formField: {
          background: '{surface.900}',
          borderColor: '{surface.700}',
          hoverBorderColor: '{surface.600}',
          focusBorderColor: '{primary.400}',
          invalidBorderColor: '{red.400}',
          color: '{surface.0}',
          placeholderColor: '{surface.500}',
        },
        text: {
          color: '{surface.0}',
          mutedColor: '{surface.400}',
        },
        content: {
          background: '{surface.900}',
          hoverBackground: '{surface.800}',
          borderColor: '{surface.700}',
          color: '{surface.0}',
        },
      },
    },
  },
  components: {
    button: {
      root: {
        borderRadius: '8px',
        paddingX: '0.85rem',
        paddingY: '0.55rem',
        gap: '0.5rem',
        transitionDuration: '180ms',
      },
    },
    card: {
      root: {
        borderRadius: '12px',
      },
      body: {
        padding: '1.25rem',
        gap: '0.75rem',
      },
    },
    datatable: {
      headerCell: {
        padding: '0.75rem 0.9rem',
      },
      bodyCell: {
        padding: '0.75rem 0.9rem',
      },
    },
    dialog: {
      root: {
        borderRadius: '14px',
      },
    },
    tag: {
      root: {
        borderRadius: '6px',
        padding: '0.2rem 0.55rem',
        gap: '0.35rem',
        fontWeight: '500',
      },
    },
    menu: {
      root: {
        borderRadius: '10px',
      },
    },
    toast: {
      root: {
        borderRadius: '10px',
      },
    },
  },
});

export default VieLimoPreset;
