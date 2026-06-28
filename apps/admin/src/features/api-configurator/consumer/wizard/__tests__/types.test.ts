import { describe, expect, it } from 'vitest';

import {
  credentialsFor,
  headersFor,
  INITIAL_FORM,
  slugify,
  toConnectionInput,
  type WizardForm,
} from '../types';

describe('wizard helpers', () => {
  it('slugifies a name to the backend code shape (^[a-z0-9-]+$)', () => {
    expect(slugify('Nexar Components!')).toBe('nexar-components');
    expect(slugify('  Acme / EU  ')).toBe('acme-eu');
    expect(slugify('ÜPER—weird__name')).toBe('per-weird-name');
  });

  it('folds credentials per auth scheme', () => {
    const form: WizardForm = {
      ...INITIAL_FORM,
      authType: 'api_key',
      apiKeyHeader: 'X-Key',
      apiKeyValue: 'secret',
    };
    expect(credentialsFor(form)).toEqual({ header: 'X-Key', value: 'secret' });
    expect(credentialsFor({ ...form, authType: 'bearer', bearer: 'tok' })).toEqual({
      token: 'tok',
    });
    expect(credentialsFor({ ...form, authType: 'oauth2_token', oauthToken: 'o' })).toEqual({
      token: 'o',
    });
    expect(credentialsFor({ ...form, authType: 'basic', basicUser: 'u', basicPass: 'p' })).toEqual({
      user: 'u',
      pass: 'p',
    });
    expect(credentialsFor({ ...form, authType: 'none' })).toEqual({});
  });

  it('drops header rows with an empty key', () => {
    const form: WizardForm = {
      ...INITIAL_FORM,
      headers: [
        { k: 'Accept', v: 'application/json' },
        { k: '  ', v: 'ignored' },
        { k: 'X-Trace', v: '1' },
      ],
    };
    expect(headersFor(form)).toEqual({ Accept: 'application/json', 'X-Trace': '1' });
  });

  it('nulls rateLimitHint when blank or non-positive', () => {
    expect(toConnectionInput({ ...INITIAL_FORM, rateLimit: '' }).rateLimitHint).toBeNull();
    expect(toConnectionInput({ ...INITIAL_FORM, rateLimit: '0' }).rateLimitHint).toBeNull();
    expect(toConnectionInput({ ...INITIAL_FORM, rateLimit: '600' }).rateLimitHint).toBe(600);
  });

  it('builds a ConnectionInput body that matches the API contract', () => {
    const form: WizardForm = {
      ...INITIAL_FORM,
      name: '  Shopify EU ',
      code: 'shopify-eu',
      baseUrl: ' https://eu.shopify.example/api ',
      authType: 'bearer',
      bearer: 'tok',
    };
    expect(toConnectionInput(form)).toEqual({
      code: 'shopify-eu',
      name: 'Shopify EU',
      baseUrl: 'https://eu.shopify.example/api',
      authType: 'bearer',
      credentials: { token: 'tok' },
      defaultHeaders: { Accept: 'application/json' },
      rateLimitHint: 600,
    });
  });
});
