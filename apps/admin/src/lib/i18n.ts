import i18n from 'i18next';
import LanguageDetector from 'i18next-browser-languagedetector';
import { initReactI18next } from 'react-i18next';

import en from '@/locales/en.json';
import pl from '@/locales/pl.json';

void i18n
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    fallbackLng: 'pl',
    supportedLngs: ['pl', 'en'],
    interpolation: { escapeValue: false },
    resources: {
      pl: { translation: pl },
      en: { translation: en },
    },
  });

export default i18n;
