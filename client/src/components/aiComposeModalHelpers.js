import Config from 'lib/Config';
import { joinUrlPaths } from 'lib/urls';

export const CONTROLLER_CONFIG_KEY = 'SilverstripeLtd\\AiCompose\\Controllers\\ComposeController';

export const defaultSchemaConfig = {
  title: 'Compose page content with AI',
  labels: {
    generate: 'Generate',
    regenerate: 'Regenerate',
    apply: 'Apply to page',
    objective: 'Purpose & Format',
    substance: 'Facts & Background',
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
    blockClassError: 'The configured content block type is not allowed on this page. Update the default_content_block_class setting in your project YML configuration.',
  },
  generateUrl: '',
  applyUrl: '',
  form: {
    name: '',
    action: '',
    fields: {
      objective: 'Objective',
      substance: 'Substance',
    },
  },
  fields: {
    objective: {
      name: 'Objective',
      label: 'Purpose & Format',
      placeholder: 'Describe what you want to create, who the target audience is, and the style of the page (e.g. a community notice, an event summary, or an internal policy update).',
      rows: 6,
      value: '',
    },
    substance: {
      name: 'Substance',
      label: 'Facts & Background',
      placeholder: 'Supply the raw data, bullet points, dates, and core details that must be included. The AI will use this as its single source of truth to ensure accuracy.',
      rows: 8,
      value: '',
    },
  },
  state: {
    supportsApply: false,
    hasElemental: false,
    storesResultsServerSide: false,
  },
  errors: {
    provider: {
      mode: 'generic',
      genericMessage: 'There was an error connecting to the AI provider',
    },
  },
};

export const getControllerConfig = () => Config.getSection(CONTROLLER_CONFIG_KEY) || {};

export const getModalConfig = () => ({
  className: 'ai-compose-modal',
  modalClassName: 'ai-compose-modal',
  size: 'xl',
  ...(getControllerConfig().form?.aiCompose || {}),
});

const buildRecordActionUrl = (fqcn, recordId, configuredUrl, fallbackUrl) => {
  const base = joinUrlPaths(configuredUrl || fallbackUrl, recordId.toString());
  return `${base}?fqcn=${encodeURIComponent(fqcn)}`;
};

export const buildSchemaUrl = (fqcn, recordId, modalConfig = getModalConfig()) => (
  buildRecordActionUrl(fqcn, recordId, modalConfig.schemaUrl, '/admin/ai-compose/schema')
);

export const buildGenerateUrl = (fqcn, recordId, modalConfig = getModalConfig()) => (
  buildRecordActionUrl(fqcn, recordId, modalConfig.generateUrl, '/admin/ai-compose/generate')
);

export const buildApplyUrl = (fqcn, recordId, modalConfig = getModalConfig()) => (
  buildRecordActionUrl(fqcn, recordId, modalConfig.applyUrl, '/admin/ai-compose/apply')
);

export const getSchemaHeaders = () => ({
  'X-FormSchema-Request': 'schema,state',
});

export const getGenerateHeaders = () => ({
  Accept: 'application/json',
  'Content-Type': 'application/json',
  'X-SecurityID': Config.get('SecurityID') || '',
});

export const getApplyHeaders = () => ({
  ...getGenerateHeaders(),
});

export const getResponseErrorMessage = (payload, fallback) => {
  if (payload?.error) {
    return payload.error;
  }
  if (Array.isArray(payload?.errors) && payload.errors[0]?.value) {
    return payload.errors[0].value;
  }
  if (payload?.message) {
    return payload.message;
  }
  return fallback;
};

export const hasGeneratedResult = (result) => (
  typeof result?.generatedTitle === 'string'
  && result.generatedTitle.trim() !== ''
  && typeof result?.generatedContent === 'string'
  && result.generatedContent.trim() !== ''
);

const getString = (value, fallback = '') => (
  typeof value === 'string' ? value : fallback
);

const getPositiveInteger = (value, fallback) => (
  Number.isInteger(value) && value > 0 ? value : fallback
);

const findFieldSchema = (schemaPayload, fieldName) => (
  Array.isArray(schemaPayload?.schema?.fields)
    ? schemaPayload.schema.fields.find((field) => field?.name === fieldName) || null
    : null
);

const findFieldState = (schemaPayload, fieldName) => (
  Array.isArray(schemaPayload?.state?.fields)
    ? schemaPayload.state.fields.find((field) => field?.name === fieldName) || null
    : null
);

const buildFieldConfig = (
  schemaPayload,
  fieldName,
  labelOverride,
  fallbackConfig
) => {
  const schemaField = findFieldSchema(schemaPayload, fieldName);
  const stateField = findFieldState(schemaPayload, fieldName);

  return {
    ...fallbackConfig,
    name: fieldName,
    label: getString(schemaField?.title, getString(labelOverride, fallbackConfig.label)),
    placeholder: getString(
      schemaField?.attributes?.placeholder,
      fallbackConfig.placeholder
    ),
    rows: getPositiveInteger(schemaField?.data?.rows, fallbackConfig.rows),
    value: getString(stateField?.value),
  };
};

export const getGenerateButtonLabel = (result, schemaConfig = defaultSchemaConfig) => (
  hasGeneratedResult(result)
    ? schemaConfig.labels?.regenerate || defaultSchemaConfig.labels.regenerate
    : schemaConfig.labels?.generate || defaultSchemaConfig.labels.generate
);

export const buildGenerationRequestBody = (inputs) => ({
  objective: getString(inputs?.objective).trim(),
  substance: getString(inputs?.substance).trim(),
});

export const buildApplyRequestBody = (result) => ({
  title: getString(result?.generatedTitle).trim(),
  content: typeof result?.generatedContent === 'string' ? result.generatedContent : '',
});

export const buildComposeResult = (payload) => {
  const generatedTitle = getString(payload?.generatedTitle).trim();
  const generatedContent = typeof payload?.generatedContent === 'string'
    ? payload.generatedContent
    : null;

  if (
    generatedTitle === ''
    || generatedContent === null
    || generatedContent.trim() === ''
  ) {
    return null;
  }

  return {
    generatedTitle,
    generatedContent,
  };
};

export const stripHtmlToText = (value) => {
  if (typeof value !== 'string' || value.trim() === '') {
    return '';
  }

  if (typeof window === 'undefined' || !window.document) {
    return value.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
  }

  const container = window.document.createElement('div');
  container.innerHTML = value;

  return (container.textContent || container.innerText || '')
    .replace(/\s+/g, ' ')
    .trim();
};

export const canApplyResult = (result) => (
  typeof result?.generatedTitle === 'string'
  && result.generatedTitle.trim() !== ''
  && typeof result?.generatedContent === 'string'
  && stripHtmlToText(result.generatedContent) !== ''
);

export const mergeSchemaConfig = (schemaPayload) => {
  const serverConfig = schemaPayload?.meta?.aiCompose || {};
  const serverFormConfig = serverConfig.form || {};
  const serverFieldMap = serverFormConfig.fields || {};

  const fields = {
    objective: buildFieldConfig(
      schemaPayload,
      serverFieldMap.objective || defaultSchemaConfig.form.fields.objective,
      serverConfig.labels?.objective,
      defaultSchemaConfig.fields.objective
    ),
    substance: buildFieldConfig(
      schemaPayload,
      serverFieldMap.substance || defaultSchemaConfig.form.fields.substance,
      serverConfig.labels?.substance,
      defaultSchemaConfig.fields.substance
    ),
  };

  return {
    ...defaultSchemaConfig,
    ...serverConfig,
    labels: {
      ...defaultSchemaConfig.labels,
      ...(serverConfig.labels || {}),
    },
    messages: {
      ...defaultSchemaConfig.messages,
      ...(serverConfig.messages || {}),
    },
    generateUrl: getString(serverConfig.generateUrl),
    applyUrl: getString(serverConfig.applyUrl),
    form: {
      ...defaultSchemaConfig.form,
      ...serverFormConfig,
      fields: {
        ...defaultSchemaConfig.form.fields,
        ...serverFieldMap,
      },
    },
    fields,
    state: {
      ...defaultSchemaConfig.state,
      ...(serverConfig.state || {}),
    },
    errors: {
      ...defaultSchemaConfig.errors,
      ...(serverConfig.errors || {}),
      provider: {
        ...defaultSchemaConfig.errors.provider,
        ...(serverConfig.errors?.provider || {}),
      },
    },
  };
};
