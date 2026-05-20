/* eslint-env jest */
/* eslint-disable import/first */
import React from 'react';
import {
  act,
  fireEvent,
  render,
  screen,
  waitFor,
} from '@testing-library/react';

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

jest.mock('redux', () => ({
  bindActionCreators: (actions) => actions,
}), { virtual: true });

jest.mock('react-redux', () => ({
  connect: () => (Component) => Component,
}), { virtual: true });

jest.mock('state/toasts/ToastsActions', () => ({}), { virtual: true });

jest.mock('reactstrap', () => {
  const ReactModule = jest.requireActual('react');

  return {
    Button: ({ children, ...props }) => ReactModule.createElement('button', props, children),
    Modal: ({
      children,
      isOpen,
      className = '',
      modalClassName = '',
    }) => (isOpen ? ReactModule.createElement('div', { className: `${className} ${modalClassName}`.trim() }, children) : null),
    ModalBody: ({ children }) => ReactModule.createElement('div', null, children),
    ModalHeader: ({ children, close }) => ReactModule.createElement('div', null, children, close),
    Spinner: () => ReactModule.createElement('span', null, 'Spinner'),
  };
}, { virtual: true });

import { AiComposeModal } from '../../src/components/AiComposeModal';

const buildActions = () => ({
  toasts: {
    error: jest.fn(),
    success: jest.fn(),
    warning: jest.fn(),
  },
});

const buildJsonResponse = (payload, ok = true, status = 200) => ({
  ok,
  status,
  json: jest.fn().mockResolvedValue(payload),
});

const buildSchemaPayload = (overrides = {}) => ({
  meta: {
    aiCompose: {
      title: 'Compose page content with AI',
      generateUrl: '/admin/ai-compose/schema-generate/12?fqcn=App%5CPage',
      applyUrl: '/admin/ai-compose/schema-apply/12?fqcn=App%5CPage',
      labels: {
        generate: 'Generate',
        regenerate: 'Regenerate',
        apply: 'Apply to page',
        generatedTitle: 'Generated Title',
        generatedContent: 'Generated Content',
        copy: 'Copy to clipboard',
      },
      messages: {
        warning: 'Applying will overwrite the page title and content with the generated text.',
        generateSuccess: 'Content generated successfully',
        generateFailure: 'Unable to generate content',
        applySuccess: 'Content applied to draft page',
        applyFailure: 'Unable to apply generated content',
        emptyInputs: 'Enter a purpose or facts before generating',
      },
      state: {
        supportsApply: true,
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
          rows: 6,
        },
      },
      {
        name: 'Substance',
        title: 'Facts & Background',
        attributes: {
          placeholder: 'Substance placeholder',
        },
        data: {
          rows: 8,
        },
      },
    ],
  },
  state: {
    fields: [
      {
        name: 'Objective',
        value: '',
      },
      {
        name: 'Substance',
        value: '',
      },
    ],
  },
  ...overrides,
});

beforeEach(() => {
  window.fetch = jest.fn();
  window.navigator.clipboard = {
    write: jest.fn().mockResolvedValue(),
    writeText: jest.fn().mockResolvedValue(),
  };
  window.Blob = window.Blob || Blob;
  window.ClipboardItem = jest.fn((payload) => payload);
});

test('loads schema metadata, shows the warning banner, and restores cached inputs', async () => {
  const actions = buildActions();
  let resolveSchema;

  window.fetch.mockImplementation(() => new Promise((resolve) => {
    resolveSchema = resolve;
  }));

  render(
    <AiComposeModal
      fqcn="App\\Page"
      recordId={12}
      initialInputs={{
        objective: 'Saved objective',
        substance: 'Saved substance',
      }}
      actions={actions}
    />
  );

  expect(screen.getByRole('status').textContent).toContain('Loading...');

  await act(async () => {
    resolveSchema(buildJsonResponse(buildSchemaPayload()));
  });

  expect(await screen.findByText('Compose page content with AI')).not.toBeNull();
  expect(await screen.findByText('Applying will overwrite the page title and content with the generated text.')).not.toBeNull();
  expect(screen.getByRole('button', { name: 'Close' }).className).toContain('modal__close-button');
  await waitFor(() => {
    expect(screen.getByRole('button', { name: 'Generate' }).disabled).toBe(false);
  });
  expect(screen.getByLabelText('Purpose & Format').value).toBe('Saved objective');
  expect(screen.getByLabelText('Facts & Background').value).toBe('Saved substance');
  expect(screen.getByRole('button', { name: 'Generate' }).disabled).toBe(false);
});

test('generates compose output, updates the cached result callback, and switches to regenerate', async () => {
  const actions = buildActions();
  const onInputsChange = jest.fn();
  const onResultChange = jest.fn();

  window.fetch
    .mockResolvedValueOnce(buildJsonResponse(buildSchemaPayload()))
    .mockResolvedValueOnce(buildJsonResponse({
      generatedTitle: 'Community update',
      generatedContent: '<p>Residents are invited to the meeting.</p>',
    }));

  render(
    <AiComposeModal
      fqcn="App\\Page"
      recordId={12}
      onInputsChange={onInputsChange}
      onResultChange={onResultChange}
      actions={actions}
    />
  );

  await screen.findByText('Applying will overwrite the page title and content with the generated text.');
  await waitFor(() => {
    expect(screen.getByRole('button', { name: 'Generate' }).disabled).toBe(false);
  });

  fireEvent.change(screen.getByLabelText('Purpose & Format'), {
    target: {
      value: 'A short update for local residents',
    },
  });
  fireEvent.change(screen.getByLabelText('Facts & Background'), {
    target: {
      value: 'Meeting date: 15 March',
    },
  });
  fireEvent.click(screen.getByRole('button', { name: 'Generate' }));

  expect(await screen.findByText('Generated Title')).not.toBeNull();
  expect(screen.getByText('Community update')).not.toBeNull();
  expect(screen.getByRole('button', { name: 'Regenerate' })).not.toBeNull();

  const [generateUrl, generateOptions] = window.fetch.mock.calls[1];
  expect(generateUrl).toBe('/admin/ai-compose/schema-generate/12?fqcn=App%5CPage');
  expect(JSON.parse(generateOptions.body)).toEqual({
    objective: 'A short update for local residents',
    substance: 'Meeting date: 15 March',
  });
  expect(onInputsChange).toHaveBeenLastCalledWith({
    objective: 'A short update for local residents',
    substance: 'Meeting date: 15 March',
  });
  expect(onResultChange).toHaveBeenCalledWith({
    generatedTitle: 'Community update',
    generatedContent: '<p>Residents are invited to the meeting.</p>',
  });
  expect(actions.toasts.success).toHaveBeenCalledWith('Content generated successfully');
});

test('keeps the previous result visible when regeneration fails and copies title text plus rich content', async () => {
  const actions = buildActions();

  window.fetch
    .mockResolvedValueOnce(buildJsonResponse(buildSchemaPayload()))
    .mockResolvedValueOnce(buildJsonResponse({
      error: 'Provider failed',
    }, false, 500));

  render(
    <AiComposeModal
      fqcn="App\\Page"
      recordId={12}
      initialResult={{
        generatedTitle: 'Current title',
        generatedContent: '<p>Hello <strong>world</strong></p>',
      }}
      actions={actions}
    />
  );

  await screen.findByText('Applying will overwrite the page title and content with the generated text.');
  await waitFor(() => {
    expect(screen.getByRole('button', { name: 'Regenerate' }).disabled).toBe(false);
  });

  await act(async () => {
    fireEvent.click(screen.getByRole('button', { name: 'Regenerate' }));
  });

  await waitFor(() => {
    expect(actions.toasts.error).toHaveBeenCalledWith('Provider failed');
  });
  expect(screen.getByText('Current title')).not.toBeNull();

  fireEvent.click(screen.getByRole('button', { name: 'Copy Generated Title' }));
  fireEvent.click(screen.getByRole('button', { name: 'Copy Generated Content' }));

  await waitFor(() => {
    expect(window.navigator.clipboard.writeText).toHaveBeenNthCalledWith(1, 'Current title');
  });
  await waitFor(() => {
    expect(window.navigator.clipboard.write).toHaveBeenCalledTimes(1);
  });
  expect(window.ClipboardItem).toHaveBeenCalledWith(expect.objectContaining({
    'text/html': expect.any(Blob),
    'text/plain': expect.any(Blob),
  }));
  await waitFor(() => {
    expect(screen.getByRole('button', { name: 'Copy Generated Content' }).className).toContain('ai-compose-modal__copy-button--copied');
  });
});

test('applies generated content and passes the success toast to the reload handler', async () => {
  const actions = buildActions();
  const onApplied = jest.fn();
  let resolveApply;

  window.fetch
    .mockResolvedValueOnce(buildJsonResponse(buildSchemaPayload()))
    .mockImplementationOnce(() => new Promise((resolve) => {
      resolveApply = resolve;
    }));

  render(
    <AiComposeModal
      fqcn="App\\Page"
      recordId={12}
      initialResult={{
        generatedTitle: 'Community update',
        generatedContent: '<p>Residents are invited.</p>',
      }}
      onApplied={onApplied}
      actions={actions}
    />
  );

  await screen.findByText('Applying will overwrite the page title and content with the generated text.');
  await waitFor(() => {
    expect(screen.getByRole('button', { name: 'Apply to page' })).not.toBeNull();
  });

  await act(async () => {
    fireEvent.click(screen.getByRole('button', { name: 'Apply to page' }));
  });

  expect(screen.getByRole('status').textContent).toContain('Applying content...');

  await act(async () => {
    resolveApply(buildJsonResponse({
      applied: true,
      reloadRequired: true,
    }));
  });

  const [applyUrl, applyOptions] = window.fetch.mock.calls[1];
  expect(applyUrl).toBe('/admin/ai-compose/schema-apply/12?fqcn=App%5CPage');
  expect(JSON.parse(applyOptions.body)).toEqual({
    title: 'Community update',
    content: '<p>Residents are invited.</p>',
  });
  expect(onApplied).toHaveBeenCalledWith(
    {
      type: 'success',
      message: 'Content applied to draft page',
    },
    expect.objectContaining({
      applied: true,
    })
  );
});
