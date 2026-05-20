/* global window */
import React from 'react';
import { createRoot } from 'react-dom/client';
import { loadComponent } from 'lib/Injector';
import {
  createAiComposeSessionCache,
  hasRecordContext,
} from './aiComposeSessionCache';
import {
  clearPendingAiComposeToast,
  consumePendingAiComposeToast,
  storePendingAiComposeToast,
} from '../toasts/aiComposePendingToast';

const jQuery = window.jQuery || window.$;
const AI_COMPOSE_RECORD_CLASS_FIELD = 'AiComposeRecordClass';

const getCmsContent = ($element) => $element.closest('.cms-content');

const getEditForm = ($element) => getCmsContent($element).find('.cms-edit-form').first();

const getAiComposeRecordId = ($element) => parseInt(
  getEditForm($element).find('input[name=ID]').val(),
  10,
);

const getAiComposeRecordClass = ($element) => {
  const value = getEditForm($element).find(`input[name=${AI_COMPOSE_RECORD_CLASS_FIELD}]`).val();
  return typeof value === 'string' ? value.trim() : '';
};

const getAiComposeRecordContext = ($element) => {
  const fqcn = getAiComposeRecordClass($element);
  const recordId = getAiComposeRecordId($element);

  if (!hasRecordContext(fqcn, recordId)) {
    return null;
  }

  return {
    fqcn,
    recordId,
  };
};

const getAiComposeInjectorContext = ($element) => {
  const cmsContent = getCmsContent($element).attr('id');
  return cmsContent ? { context: cmsContent } : {};
};

const getActionRecordContext = ($element) => {
  const fqcn = $element.attr('data-fqcn');
  const recordId = parseInt($element.attr('data-record-id'), 10);

  if (!hasRecordContext(fqcn, recordId)) {
    return null;
  }

  return {
    fqcn,
    recordId,
  };
};

const getSharedAiComposeSessionCache = ($element) => {
  const cmsContent = getCmsContent($element);
  if (!cmsContent.length) {
    return createAiComposeSessionCache();
  }

  let cache = cmsContent.data('aiComposeSessionCache');
  if (!cache) {
    cache = createAiComposeSessionCache();
    cmsContent.data('aiComposeSessionCache', cache);
  }

  return cache;
};

const clearRenderedReactTree = (context) => {
  const root = context.getReactRoot();
  if (root) {
    root.unmount();
    context.setReactRoot(null);
  }

  const container = context.getReactContainer();
  if (container) {
    container.remove();
    context.setReactContainer(null);
  }
};

const showPendingAiComposeToast = (activeJQuery) => {
  const toast = consumePendingAiComposeToast();
  if (!toast) {
    return;
  }

  activeJQuery.noticeAdd({
    text: toast.message,
    type: toast.type,
    stayTime: 5000,
    inEffect: { left: '0', opacity: 'show' },
  });
};

export const registerEntwine = (jQueryInstance = null) => {
  const activeJQuery = jQueryInstance || jQuery;
  if (!activeJQuery || !activeJQuery.entwine) {
    return;
  }

  activeJQuery.entwine('ss.ai-compose', ($) => {
    $('.js-injector-boot .preview-mode-selector').entwine({
      ReactRoot: null,
      ReactContainer: null,
      Component: null,

      clearToolbarButton() {
        clearRenderedReactTree(this);
      },

      getOrCreateToolbarButtonContainer() {
        let container = this.getReactContainer();
        if (container) {
          return container;
        }

        container = $('<span class="ai-compose__placeholder"></span>');
        const metadataPlaceholder = this.find('> .ai-metadata__placeholder').first();
        if (metadataPlaceholder.length) {
          metadataPlaceholder.before(container);
        } else {
          const sharePlaceholder = this.find('> .share-draft-content__placeholder').first();
          if (sharePlaceholder.length) {
            sharePlaceholder.before(container);
          } else {
            const firstChild = this.children().first();
            if (firstChild.length) {
              firstChild.before(container);
            } else {
              this.prepend(container);
            }
          }
        }

        this.setReactContainer(container);

        return container;
      },

      onmatch() {
        const recordContext = getAiComposeRecordContext(this);
        if (!recordContext) {
          this.clearToolbarButton();
          this._super();
          return;
        }

        let Component = this.getComponent();
        if (!Component) {
          Component = loadComponent('AiComposeActionButton', getAiComposeInjectorContext(this));
          this.setComponent(Component);
        }

        const container = this.getOrCreateToolbarButtonContainer();
        let root = this.getReactRoot();
        if (!root) {
          root = createRoot(container[0]);
          this.setReactRoot(root);
        }

        root.render(
          <Component
            fqcn={recordContext.fqcn}
            recordId={recordContext.recordId}
          />
        );

        this._super();
      },

      onunmatch() {
        this.clearToolbarButton();
        this._super();
      },
    });
  });

  activeJQuery.entwine('ss', ($) => {
    $('.ai-compose__action').entwine({
      ReactRoot: null,
      ReactContainer: null,
      Component: null,

      getOrCreateAiComposeSessionCache() {
        return getSharedAiComposeSessionCache(this);
      },

      getCachedAiComposeInputs() {
        return this.getOrCreateAiComposeSessionCache().getInputs();
      },

      setCachedAiComposeInputs(inputs) {
        this.getOrCreateAiComposeSessionCache().setInputs(inputs);
      },

      getCachedAiComposeResult() {
        return this.getOrCreateAiComposeSessionCache().getResult();
      },

      setCachedAiComposeResult(result) {
        this.getOrCreateAiComposeSessionCache().setResult(result);
      },

      onmatch() {
        showPendingAiComposeToast(activeJQuery);
        this._super();
      },

      reloadAfterApply(toast) {
        const storedToast = storePendingAiComposeToast(toast);

        try {
          window.location.reload();
        } catch (error) {
          if (storedToast) {
            clearPendingAiComposeToast();
          }
          throw error;
        }
      },

      renderAiComposeModal(createIfMissing = false) {
        const recordContext = getActionRecordContext(this);
        if (!recordContext) {
          return;
        }

        let container = this.getReactContainer();
        if (!container) {
          if (!createIfMissing) {
            return;
          }

          container = $('<div class="ai-compose-modal__container"></div>');
          $('body').append(container);
          this.setReactContainer(container);
        }

        let root = this.getReactRoot();
        if (!root) {
          if (!createIfMissing) {
            return;
          }

          root = createRoot(container[0]);
          this.setReactRoot(root);
        }

        let Component = this.getComponent();
        if (!Component) {
          Component = loadComponent('AiComposeModal');
          this.setComponent(Component);
        }

        const self = this;
        const handleClosed = () => {
          clearRenderedReactTree(self);
        };

        root.render(
          <Component
            fqcn={recordContext.fqcn}
            recordId={recordContext.recordId}
            initialInputs={this.getCachedAiComposeInputs()}
            initialResult={this.getCachedAiComposeResult()}
            onApplied={(toast) => this.reloadAfterApply(toast)}
            onInputsChange={(nextInputs) => this.setCachedAiComposeInputs(nextInputs)}
            onResultChange={(nextResult) => this.setCachedAiComposeResult(nextResult)}
            onClosed={handleClosed}
          />
        );
      },

      onclick(e) {
        e.preventDefault();
        if (!getActionRecordContext(this)) {
          activeJQuery.noticeAdd({
            text: 'Save the page before opening AI compose.',
            type: 'warning',
          });
          return false;
        }

        this.renderAiComposeModal(true);

        return false;
      },

      onunmatch() {
        clearRenderedReactTree(this);
      },
    });
  });
};

registerEntwine();
