import Injector from 'lib/Injector';
import AiComposeActionButtonComponent from 'components/AiComposeActionButton';
import AiComposeModalComponent from 'components/AiComposeModal';

const registerComponents = () => {
  Injector.component.register('AiComposeActionButton', AiComposeActionButtonComponent);
  Injector.component.register('AiComposeModal', AiComposeModalComponent);
};

export default registerComponents;
