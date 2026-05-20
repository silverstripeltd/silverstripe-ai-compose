export const PENDING_AI_COMPOSE_TOAST_KEY = 'ai-compose.pending-toast';

const getSessionStorage = () => {
  if (typeof window === 'undefined') {
    return null;
  }

  try {
    return window.sessionStorage;
  } catch {
    return null;
  }
};

const isValidToast = (toast) => (
  typeof toast?.message === 'string'
  && toast.message.trim() !== ''
  && ['success', 'warning', 'error'].includes(toast.type)
);

export const clearPendingAiComposeToast = () => {
  const storage = getSessionStorage();
  if (!storage) {
    return false;
  }

  try {
    storage.removeItem(PENDING_AI_COMPOSE_TOAST_KEY);
    return true;
  } catch {
    return false;
  }
};

export const storePendingAiComposeToast = (toast) => {
  const storage = getSessionStorage();
  if (!storage || !isValidToast(toast)) {
    return false;
  }

  try {
    storage.setItem(PENDING_AI_COMPOSE_TOAST_KEY, JSON.stringify({
      type: toast.type,
      message: toast.message.trim(),
    }));
    return true;
  } catch {
    return false;
  }
};

export const consumePendingAiComposeToast = () => {
  const storage = getSessionStorage();
  if (!storage) {
    return null;
  }

  try {
    const rawValue = storage.getItem(PENDING_AI_COMPOSE_TOAST_KEY);
    storage.removeItem(PENDING_AI_COMPOSE_TOAST_KEY);
    if (!rawValue) {
      return null;
    }

    const parsedValue = JSON.parse(rawValue);

    return isValidToast(parsedValue)
      ? {
        type: parsedValue.type,
        message: parsedValue.message.trim(),
      }
      : null;
  } catch {
    return null;
  }
};
