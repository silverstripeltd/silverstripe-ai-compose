/* global document */
import registerComponents from './registerComponents';

const bootAiCompose = () => {
  registerComponents();
};

document.addEventListener('DOMContentLoaded', () => {
  bootAiCompose();
});

export default bootAiCompose;
