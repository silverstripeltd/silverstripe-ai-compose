/* eslint-env jest */
import {
  clearPendingAiComposeToast,
  consumePendingAiComposeToast,
  PENDING_AI_COMPOSE_TOAST_KEY,
  storePendingAiComposeToast,
} from '../../src/toasts/aiComposePendingToast';

beforeEach(() => {
  window.sessionStorage.clear();
});

test('stores, consumes, and clears a pending compose toast', () => {
  expect(storePendingAiComposeToast({
    type: 'success',
    message: ' Content applied to draft page ',
  })).toBe(true);
  expect(window.sessionStorage.getItem(PENDING_AI_COMPOSE_TOAST_KEY)).toBe(JSON.stringify({
    type: 'success',
    message: 'Content applied to draft page',
  }));

  expect(consumePendingAiComposeToast()).toEqual({
    type: 'success',
    message: 'Content applied to draft page',
  });
  expect(window.sessionStorage.getItem(PENDING_AI_COMPOSE_TOAST_KEY)).toBeNull();

  storePendingAiComposeToast({
    type: 'warning',
    message: 'Try again later',
  });
  expect(clearPendingAiComposeToast()).toBe(true);
  expect(window.sessionStorage.getItem(PENDING_AI_COMPOSE_TOAST_KEY)).toBeNull();
});

test('rejects invalid pending toast payloads', () => {
  expect(storePendingAiComposeToast({
    type: 'info',
    message: 'Ignored',
  })).toBe(false);
  expect(consumePendingAiComposeToast()).toBeNull();
});
