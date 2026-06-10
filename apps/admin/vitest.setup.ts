import '@testing-library/jest-dom/vitest';

import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

import en from './src/locales/en.json';
import pl from './src/locales/pl.json';

// Deterministic i18n for component tests — Polish, no browser detection.
void i18n.use(initReactI18next).init({
  lng: 'pl',
  fallbackLng: 'pl',
  interpolation: { escapeValue: false },
  resources: {
    pl: { translation: pl },
    en: { translation: en },
  },
});
