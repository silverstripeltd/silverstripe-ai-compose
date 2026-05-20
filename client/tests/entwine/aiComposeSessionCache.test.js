/* eslint-env jest */
import {
  createAiComposeSessionCache,
  hasRecordContext,
} from '../../src/entwine/aiComposeSessionCache';

test('stores inputs and replaces only the latest result', () => {
  const cache = createAiComposeSessionCache();

  expect(cache.getState()).toEqual({
    inputs: {
      objective: '',
      substance: '',
    },
    result: null,
  });

  cache.setInputs({
    objective: 'Audience',
    substance: 'Facts',
  });
  cache.setResult({
    generatedTitle: 'First title',
    generatedContent: '<p>First body</p>',
  });
  cache.setResult({
    generatedTitle: 'Second title',
    generatedContent: '<p>Second body</p>',
  });

  expect(cache.getInputs()).toEqual({
    objective: 'Audience',
    substance: 'Facts',
  });
  expect(cache.getResult()).toEqual({
    generatedTitle: 'Second title',
    generatedContent: '<p>Second body</p>',
  });
});

test('normalises invalid cache input and validates record context', () => {
  const cache = createAiComposeSessionCache({
    inputs: {
      objective: 'Saved objective',
    },
    result: {
      generatedTitle: 'Saved title',
    },
  });

  expect(cache.getInputs()).toEqual({
    objective: 'Saved objective',
    substance: '',
  });
  expect(cache.getResult()).toBeNull();

  expect(hasRecordContext('App\\Page', 12)).toBe(true);
  expect(hasRecordContext('', 12)).toBe(false);
  expect(hasRecordContext('App\\Page', 0)).toBe(false);
});
