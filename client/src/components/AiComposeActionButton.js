import React from 'react';
import PropTypes from 'prop-types';
import Button from 'components/Button/Button';

export const AiComposeActionButton = ({
  fqcn,
  recordId,
  title = 'Compose',
  tooltip = 'Compose',
}) => (
  <Button
    type="button"
    color="secondary"
    className="ai-compose__action ai-compose-toolbar__button"
    icon="edit"
    title={tooltip}
    data-fqcn={fqcn}
    data-record-id={recordId}
  >
    {title}
  </Button>
);

AiComposeActionButton.propTypes = {
  fqcn: PropTypes.string.isRequired,
  recordId: PropTypes.number.isRequired,
  title: PropTypes.string,
  tooltip: PropTypes.string,
};

export default AiComposeActionButton;
