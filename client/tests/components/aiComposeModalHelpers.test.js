/* eslint-env jest */
/* eslint-disable import/first */
jest.mock('lib/Config', () => ({
  __esModule: true,
  default: {
    get: jest.fn((key) => (key === 'SecurityID' ? 'security-token' : '')),
    getSection: jest.fn(() => ({
      form: {
        aiCompose: {
          schemaUrl: '/admin/ai-compose/schema',
          generateUrl: '/admin/ai-compose/generate',
          applyUrl: '/admin/ai-compose/apply',
        },
      },
    })),
  },
}), { virtual: true });

jest.mock('lib/urls', () => ({
  joinUrlPaths: (...parts) => parts.join('/'),
}), { virtual: true });

import {
  buildApplyRequestBody,
  buildApplyUrl,
  buildComposeResult,
  buildGenerateUrl,
  buildGenerationRequestBody,
  buildSchemaUrl,
  getApplyHeaders,
  getGenerateButtonLabel,
  getGenerateHeaders,
  getSchemaHeaders,
  hasGeneratedResult,
  mergeSchemaConfig,
  stripHtmlToText,
} from '../../src/components/aiComposeModalHelpers';

test('builds record URLs and request headers from controller config', () => {
  expect(buildSchemaUrl('App\\Page', 12)).toBe('/admin/ai-compose/schema/12?fqcn=App%5CPage');
  expect(buildGenerateUrl('App\\Page', 12)).toBe('/admin/ai-compose/generate/12?fqcn=App%5CPage');
  expect(buildApplyUrl('App\\Page', 12)).toBe('/admin/ai-compose/apply/12?fqcn=App%5CPage');
  expect(getSchemaHeaders()).toEqual({
    'X-FormSchema-Request': 'schema,state',
  });
  expect(getGenerateHeaders()).toEqual({
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-SecurityID': 'security-token',
  });
  expect(getApplyHeaders()).toEqual({
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-SecurityID': 'security-token',
  });
});

test('merges schema defaults with server overrides and field schema details', () => {
  expect(mergeSchemaConfig({
    meta: {
      aiCompose: {
        title: 'Compose page content with AI',
        generateUrl: '/admin/ai-compose/custom-generate/12?fqcn=App%5CPage',
        applyUrl: '/admin/ai-compose/custom-apply/12?fqcn=App%5CPage',
        labels: {
          apply: 'Apply now',
        },
        messages: {
          warning: 'Applying will overwrite the page title and create a new content block.',
        },
        state: {
          supportsApply: true,
          hasElemental: true,
        },
        form: {
          fields: {
            objective: 'Objective',
            substance: 'Substance',
          },
        },
      },
    },
    schema: {
      fields: [
        {
          name: 'Objective',
          title: 'Purpose & Format',
          attributes: {
            placeholder: 'Objective placeholder',
          },
          data: {
            rows: 4,
          },
        },
        {
          name: 'Substance',
          title: 'Facts & Background',
          attributes: {
            placeholder: 'Substance placeholder',
          },
          data: {
            rows: 7,
          },
        },
      ],
    },
    state: {
      fields: [
        {
          name: 'Objective',
          value: 'Saved objective',
        },
        {
          name: 'Substance',
          value: 'Saved substance',
        },
      ],
    },
  })).toEqual(expect.objectContaining({
    title: 'Compose page content with AI',
    labels: expect.objectContaining({
      apply: 'Apply now',
    }),
    messages: expect.objectContaining({
      warning: 'Applying will overwrite the page title and create a new content block.',
    }),
    generateUrl: '/admin/ai-compose/custom-generate/12?fqcn=App%5CPage',
    applyUrl: '/admin/ai-compose/custom-apply/12?fqcn=App%5CPage',
    state: expect.objectContaining({
      supportsApply: true,
      hasElemental: true,
    }),
    fields: {
      objective: expect.objectContaining({
        label: 'Purpose & Format',
        placeholder: 'Objective placeholder',
        rows: 4,
        value: 'Saved objective',
      }),
      substance: expect.objectContaining({
        label: 'Facts & Background',
        placeholder: 'Substance placeholder',
        rows: 7,
        value: 'Saved substance',
      }),
    },
  }));
});

test('builds request bodies and result data for generate and apply', () => {
  expect(buildGenerationRequestBody({
    objective: '  Audience  ',
    substance: '  Facts  ',
  })).toEqual({
    objective: 'Audience',
    substance: 'Facts',
  });

  expect(buildComposeResult({
    generatedTitle: '  Council update  ',
    generatedContent: '<p>Meeting details</p>',
  })).toEqual({
    generatedTitle: 'Council update',
    generatedContent: '<p>Meeting details</p>',
  });

  expect(buildApplyRequestBody({
    generatedTitle: '  Council update  ',
    generatedContent: '<p>Meeting details</p>',
  })).toEqual({
    title: 'Council update',
    content: '<p>Meeting details</p>',
  });
});

test('switches the generate button label and recognises complete results', () => {
  expect(getGenerateButtonLabel(null)).toBe('Generate');
  expect(getGenerateButtonLabel({
    generatedTitle: 'Generated title',
    generatedContent: '<p>Generated body</p>',
  })).toBe('Regenerate');

  expect(hasGeneratedResult({
    generatedTitle: 'Generated title',
    generatedContent: '<p>Generated body</p>',
  })).toBe(true);
  expect(hasGeneratedResult({
    generatedTitle: '',
    generatedContent: null,
  })).toBe(false);
});

test('strips HTML to plain text for clipboard copies', () => {
  expect(stripHtmlToText('<p>Hello <strong>world</strong></p>')).toBe('Hello world');
});
