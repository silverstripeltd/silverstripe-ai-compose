const normaliseInputs = (inputs = null) => ({
  objective: typeof inputs?.objective === 'string' ? inputs.objective : '',
  substance: typeof inputs?.substance === 'string' ? inputs.substance : '',
});

const normaliseResult = (result = null) => {
  if (!result || typeof result !== 'object') {
    return null;
  }

  const generatedTitle = typeof result.generatedTitle === 'string' ? result.generatedTitle : '';
  const generatedContent = typeof result.generatedContent === 'string'
    ? result.generatedContent
    : null;

  if (
    generatedTitle.trim() === ''
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

const normaliseState = (state = null) => ({
  inputs: normaliseInputs(state?.inputs),
  result: normaliseResult(state?.result),
});

export const createAiComposeSessionCache = (initialState = null) => {
  let cachedState = normaliseState(initialState);

  return {
    getState: () => cachedState,
    getInputs: () => cachedState.inputs,
    setInputs: (inputs) => {
      cachedState = {
        ...cachedState,
        inputs: normaliseInputs({
          ...cachedState.inputs,
          ...inputs,
        }),
      };
      return cachedState.inputs;
    },
    updateInput: (fieldName, value) => {
      cachedState = {
        ...cachedState,
        inputs: normaliseInputs({
          ...cachedState.inputs,
          [fieldName]: value,
        }),
      };
      return cachedState.inputs;
    },
    getResult: () => cachedState.result,
    setResult: (result) => {
      cachedState = {
        ...cachedState,
        result: normaliseResult(result),
      };
      return cachedState.result;
    },
    clearResult: () => {
      cachedState = {
        ...cachedState,
        result: null,
      };
      return cachedState.result;
    },
    clear: () => {
      cachedState = normaliseState();
      return cachedState;
    },
  };
};

export const hasRecordContext = (fqcn, recordId) => (
  typeof fqcn === 'string'
  && fqcn.trim() !== ''
  && Number.isInteger(recordId)
  && recordId > 0
);
