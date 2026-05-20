/* global window */
/* eslint-disable react/no-danger */
import React, {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
} from 'react';
import PropTypes from 'prop-types';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';
import {
  Button,
  Modal,
  ModalBody,
  ModalHeader,
  Spinner,
} from 'reactstrap';
import * as toastsActions from 'state/toasts/ToastsActions';
import {
  buildApplyRequestBody,
  buildApplyUrl,
  buildComposeResult,
  buildGenerateUrl,
  buildGenerationRequestBody,
  buildSchemaUrl,
  canApplyResult,
  defaultSchemaConfig,
  getApplyHeaders,
  getGenerateButtonLabel,
  getGenerateHeaders,
  getModalConfig,
  getResponseErrorMessage,
  getSchemaHeaders,
  hasGeneratedResult,
  mergeSchemaConfig,
  stripHtmlToText,
} from './aiComposeModalHelpers';

const COPY_RESET_DELAY = 1500;

const fetchJson = async (url, options = {}) => {
  const response = await window.fetch(url, {
    credentials: 'same-origin',
    ...options,
  });

  return {
    response,
    payload: await response.json(),
  };
};

const showToast = (toasts, toast) => {
  if (toast.type === 'warning') {
    toasts.warning(toast.message);
    return;
  }

  if (toast.type === 'error') {
    toasts.error(toast.message);
    return;
  }

  toasts.success(toast.message);
};

const clearCopyTimer = (timerRef) => {
  if (timerRef.current) {
    window.clearTimeout(timerRef.current);
    timerRef.current = null;
  }
};

const writeTextToClipboard = async (value) => {
  if (window.navigator?.clipboard?.writeText) {
    await window.navigator.clipboard.writeText(value);
    return true;
  }

  if (!window.document?.body) {
    return false;
  }

  const textarea = window.document.createElement('textarea');
  textarea.setAttribute('readonly', 'readonly');
  textarea.style.position = 'fixed';
  textarea.style.opacity = '0';
  textarea.value = value;
  window.document.body.appendChild(textarea);
  textarea.select();

  let copied = false;

  try {
    copied = window.document.execCommand('copy');
  } finally {
    textarea.remove();
  }

  return copied;
};

const writeHtmlToClipboard = async (html, plainText) => {
  if (
    window.navigator?.clipboard?.write
    && typeof window.ClipboardItem !== 'undefined'
    && typeof window.Blob !== 'undefined'
  ) {
    await window.navigator.clipboard.write([
      new window.ClipboardItem({
        'text/html': new window.Blob([html], { type: 'text/html' }),
        'text/plain': new window.Blob([plainText], { type: 'text/plain' }),
      }),
    ]);
    return true;
  }

  if (
    !window.document?.body
    || typeof window.document.execCommand !== 'function'
    || typeof window.getSelection !== 'function'
    || typeof window.document.createRange !== 'function'
  ) {
    return writeTextToClipboard(plainText);
  }

  const container = window.document.createElement('div');
  container.setAttribute('contenteditable', 'true');
  container.style.position = 'fixed';
  container.style.opacity = '0';
  container.style.pointerEvents = 'none';
  container.innerHTML = html;
  window.document.body.appendChild(container);

  const selection = window.getSelection();
  const range = window.document.createRange();
  range.selectNodeContents(container);
  selection?.removeAllRanges();
  selection?.addRange(range);

  let copied = false;

  try {
    copied = window.document.execCommand('copy');
  } finally {
    selection?.removeAllRanges();
    container.remove();
  }

  if (copied) {
    return true;
  }

  return writeTextToClipboard(plainText);
};

const normaliseInputs = (inputs = null) => ({
  objective: typeof inputs?.objective === 'string' ? inputs.objective : '',
  substance: typeof inputs?.substance === 'string' ? inputs.substance : '',
});

export const AiComposeModal = ({
  fqcn,
  recordId,
  initialInputs = null,
  initialResult = null,
  onApplied = null,
  onClosed = null,
  onInputsChange = null,
  onResultChange = null,
  actions,
}) => {
  const [isOpen, setIsOpen] = useState(true);
  const [isLoadingSchema, setIsLoadingSchema] = useState(true);
  const [schemaError, setSchemaError] = useState('');
  const [schemaConfig, setSchemaConfig] = useState(defaultSchemaConfig);
  const [inputs, setInputs] = useState(() => normaliseInputs(initialInputs));
  const [result, setResult] = useState(initialResult || null);
  const [isGenerating, setIsGenerating] = useState(false);
  const [isApplying, setIsApplying] = useState(false);
  const [copiedField, setCopiedField] = useState('');
  const copyTimerRef = useRef(null);

  const modalConfig = useMemo(() => getModalConfig(), []);
  const schemaUrl = useMemo(() => buildSchemaUrl(fqcn, recordId, modalConfig), [fqcn, modalConfig, recordId]);
  const defaultGenerateUrl = useMemo(
    () => buildGenerateUrl(fqcn, recordId, modalConfig),
    [fqcn, modalConfig, recordId]
  );
  const defaultApplyUrl = useMemo(
    () => buildApplyUrl(fqcn, recordId, modalConfig),
    [fqcn, modalConfig, recordId]
  );

  useEffect(() => {
    setInputs(normaliseInputs(initialInputs));
  }, [initialInputs]);

  useEffect(() => {
    setResult(initialResult || null);
  }, [initialResult]);

  useEffect(() => () => {
    clearCopyTimer(copyTimerRef);
  }, []);

  useEffect(() => {
    let isMounted = true;

    const loadSchema = async () => {
      try {
        const { response, payload } = await fetchJson(schemaUrl, {
          headers: getSchemaHeaders(),
        });

        if (!response.ok) {
          throw new Error(getResponseErrorMessage(payload, defaultSchemaConfig.messages.generateFailure));
        }

        if (!isMounted) {
          return;
        }

        setSchemaConfig(mergeSchemaConfig(payload));
        setSchemaError('');
      } catch (error) {
        if (!isMounted) {
          return;
        }

        const message = error?.message || defaultSchemaConfig.messages.generateFailure;
        setSchemaError(message);
        actions.toasts.error(message);
      } finally {
        if (isMounted) {
          setIsLoadingSchema(false);
        }
      }
    };

    loadSchema();

    return () => {
      isMounted = false;
    };
  }, [actions, schemaUrl]);

  const handleClosed = useCallback(() => {
    clearCopyTimer(copyTimerRef);
    setIsOpen(false);

    if (typeof onClosed === 'function') {
      onClosed();
    }
  }, [onClosed]);

  const handleInputChange = useCallback((fieldName) => (event) => {
    const { value } = event.target;

    setInputs((currentInputs) => {
      const nextInputs = {
        ...currentInputs,
        [fieldName]: value,
      };

      if (typeof onInputsChange === 'function') {
        onInputsChange(nextInputs);
      }

      return nextInputs;
    });
  }, [onInputsChange]);

  const handleGenerate = useCallback(async () => {
    setIsGenerating(true);

    try {
      const { response, payload } = await fetchJson(
        schemaConfig.generateUrl || defaultGenerateUrl,
        {
          method: 'POST',
          headers: getGenerateHeaders(),
          body: JSON.stringify(buildGenerationRequestBody(inputs)),
        }
      );

      if (!response.ok) {
        const message = getResponseErrorMessage(payload, schemaConfig.messages.generateFailure);
        if (message === schemaConfig.messages.emptyInputs || response.status === 400) {
          actions.toasts.warning(message);
        } else {
          actions.toasts.error(message);
        }
        return;
      }

      const nextResult = buildComposeResult(payload);
      if (!nextResult) {
        actions.toasts.error(schemaConfig.messages.generateFailure);
        return;
      }

      setResult(nextResult);
      if (typeof onResultChange === 'function') {
        onResultChange(nextResult);
      }
      actions.toasts.success(schemaConfig.messages.generateSuccess);
    } catch (error) {
      actions.toasts.error(error?.message || schemaConfig.messages.generateFailure);
    } finally {
      setIsGenerating(false);
    }
  }, [actions.toasts, defaultGenerateUrl, inputs, onResultChange, schemaConfig]);

  const handleApply = useCallback(async () => {
    if (!canApplyResult(result)) {
      return;
    }

    try {
      setIsApplying(true);

      const { response, payload } = await fetchJson(schemaConfig.applyUrl || defaultApplyUrl, {
        method: 'POST',
        headers: getApplyHeaders(),
        body: JSON.stringify(buildApplyRequestBody(result)),
      });

      if (!response.ok) {
        actions.toasts.error(getResponseErrorMessage(payload, schemaConfig.messages.applyFailure));
        return;
      }

      const applyToast = {
        type: 'success',
        message: schemaConfig.messages.applySuccess,
      };

      if (payload.reloadRequired !== false && typeof onApplied === 'function') {
        onApplied(applyToast, payload);
      } else {
        showToast(actions.toasts, applyToast);
      }
    } catch (error) {
      actions.toasts.error(error?.message || schemaConfig.messages.applyFailure);
    } finally {
      setIsApplying(false);
    }
  }, [actions.toasts, defaultApplyUrl, onApplied, result, schemaConfig]);

  const handleCopy = useCallback(async (fieldName) => {
    const isTitleField = fieldName === 'title';
    const plainTextValue = isTitleField
      ? (result?.generatedTitle || '')
      : stripHtmlToText(result?.generatedContent || '');
    const htmlValue = isTitleField ? '' : (result?.generatedContent || '');

    if (plainTextValue.trim() === '') {
      return;
    }

    const copied = isTitleField
      ? await writeTextToClipboard(plainTextValue)
      : await writeHtmlToClipboard(htmlValue, plainTextValue);
    if (!copied) {
      return;
    }

    clearCopyTimer(copyTimerRef);
    setCopiedField(fieldName);
    copyTimerRef.current = window.setTimeout(() => {
      setCopiedField('');
      copyTimerRef.current = null;
    }, COPY_RESET_DELAY);
  }, [result]);

  const supportsApply = schemaConfig.state?.supportsApply ?? defaultSchemaConfig.state.supportsApply;
  const actionsDisabled = isLoadingSchema || isGenerating || isApplying || !!schemaError;
  const showResult = hasGeneratedResult(result);
  let loadingMessage = 'Loading...';
  if (isApplying) {
    loadingMessage = 'Applying content...';
  } else if (isGenerating) {
    loadingMessage = 'Generating content...';
  }
  const closeButton = (
    <button
      type="button"
      className="btn btn-close btn--icon-xl btn--no-text modal__close-button"
      aria-label="Close"
      title="Close"
      onClick={handleClosed}
    >
      <span aria-hidden="true" className="font-icon-cancel btn__icon" />
    </button>
  );

  return (
    <Modal
      isOpen={isOpen}
      toggle={handleClosed}
      size={modalConfig.size}
      className={modalConfig.className}
      modalClassName={modalConfig.modalClassName}
    >
      <ModalHeader close={closeButton}>{schemaConfig.title}</ModalHeader>
      <ModalBody>
        {schemaError ? (
          <div className="ai-compose-modal__banner ai-compose-modal__banner--error">
            {schemaError}
          </div>
        ) : null}

        {!schemaError ? (
          <>
            {schemaConfig.messages.warning ? (
              <div className="ai-compose-modal__banner ai-compose-modal__banner--info">
                {schemaConfig.messages.warning}
              </div>
            ) : null}

            <div className="ai-compose-modal__fields">
              <div className="ai-compose-modal__field">
                <label className="ai-compose-modal__label" htmlFor="ai-compose-objective">
                  {schemaConfig.fields.objective.label}
                </label>
                <textarea
                  id="ai-compose-objective"
                  className="ai-compose-modal__textarea"
                  rows={schemaConfig.fields.objective.rows}
                  value={inputs.objective}
                  placeholder={schemaConfig.fields.objective.placeholder}
                  onChange={handleInputChange('objective')}
                />
              </div>

              <div className="ai-compose-modal__field">
                <label className="ai-compose-modal__label" htmlFor="ai-compose-substance">
                  {schemaConfig.fields.substance.label}
                </label>
                <textarea
                  id="ai-compose-substance"
                  className="ai-compose-modal__textarea"
                  rows={schemaConfig.fields.substance.rows}
                  value={inputs.substance}
                  placeholder={schemaConfig.fields.substance.placeholder}
                  onChange={handleInputChange('substance')}
                />
              </div>
            </div>

            <div className="ai-compose-modal__actions">
              <Button
                color="info"
                type="button"
                onClick={handleGenerate}
                disabled={actionsDisabled}
              >
                {getGenerateButtonLabel(result, schemaConfig)}
              </Button>
            </div>

            {isLoadingSchema || isGenerating || isApplying ? (
              <div className="ai-compose-modal__loading" role="status">
                <Spinner size="sm" />
                <span>{loadingMessage}</span>
              </div>
            ) : null}

            {showResult ? (
              <div className="ai-compose-modal__result">
                <article className="ai-compose-modal__result-card">
                  <div className="ai-compose-modal__result-header">
                    <h5>{schemaConfig.labels.generatedTitle}</h5>
                    <button
                      type="button"
                      className={`ai-compose-modal__copy-button ${copiedField === 'title' ? 'ai-compose-modal__copy-button--copied' : ''}`.trim()}
                      onClick={() => handleCopy('title')}
                      aria-label={`Copy ${schemaConfig.labels.generatedTitle}`}
                    >
                      <span
                        aria-hidden="true"
                        className={`font-icon-${copiedField === 'title' ? 'tick' : 'clipboard'} ai-compose-modal__copy-icon`}
                      />
                      <span>{schemaConfig.labels.copy}</span>
                    </button>
                  </div>
                  <p className="ai-compose-modal__preview-text">{result.generatedTitle}</p>
                </article>

                <article className="ai-compose-modal__result-card">
                  <div className="ai-compose-modal__result-header">
                    <h5>{schemaConfig.labels.generatedContent}</h5>
                    <button
                      type="button"
                      className={`ai-compose-modal__copy-button ${copiedField === 'content' ? 'ai-compose-modal__copy-button--copied' : ''}`.trim()}
                      onClick={() => handleCopy('content')}
                      aria-label={`Copy ${schemaConfig.labels.generatedContent}`}
                    >
                      <span
                        aria-hidden="true"
                        className={`font-icon-${copiedField === 'content' ? 'tick' : 'clipboard'} ai-compose-modal__copy-icon`}
                      />
                      <span>{schemaConfig.labels.copy}</span>
                    </button>
                  </div>
                  <div
                    className="ai-compose-modal__preview-html"
                    dangerouslySetInnerHTML={{ __html: result.generatedContent || '' }}
                  />
                </article>
              </div>
            ) : null}

            {supportsApply && showResult ? (
              <div className="ai-compose-modal__footer-actions">
                <Button
                  color="primary"
                  type="button"
                  onClick={handleApply}
                  disabled={actionsDisabled || !canApplyResult(result)}
                >
                  {schemaConfig.labels.apply}
                </Button>
              </div>
            ) : null}
          </>
        ) : null}
      </ModalBody>
    </Modal>
  );
};

AiComposeModal.propTypes = {
  fqcn: PropTypes.string.isRequired,
  recordId: PropTypes.number.isRequired,
  initialInputs: PropTypes.shape({
    objective: PropTypes.string,
    substance: PropTypes.string,
  }),
  initialResult: PropTypes.shape({
    generatedTitle: PropTypes.string,
    generatedContent: PropTypes.string,
  }),
  onApplied: PropTypes.func,
  onClosed: PropTypes.func,
  onInputsChange: PropTypes.func,
  onResultChange: PropTypes.func,
  actions: PropTypes.shape({
    toasts: PropTypes.shape({
      error: PropTypes.func.isRequired,
      success: PropTypes.func.isRequired,
      warning: PropTypes.func.isRequired,
    }).isRequired,
  }).isRequired,
};

const mapDispatchToProps = (dispatch) => ({
  actions: {
    toasts: bindActionCreators(toastsActions, dispatch),
  },
});

export default connect(null, mapDispatchToProps)(AiComposeModal);
