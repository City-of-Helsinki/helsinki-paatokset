import { Select } from 'hds-react';
import React from 'react';
import ReactDOM from 'react-dom';
import reactToWebComponent from 'react-to-webcomponent';
import PropTypes from 'prop-types';

class Dropdown extends React.Component {
  constructor() {
    super();
    this.state = {
      value: [],
      options: []
    }
  }

  componentDidMount() {
    if(this.props.value) {
      this.setState({
        value: JSON.parse(this.props.value)
      });
    }

    document.addEventListener('hds-dropdown:clear', (event) => {
      const { target } = event.detail;
      if(!target || target === this.props.selector) {
        this.clear();
      }
    });
  }

  componentDidUpdate(prevProps, prevState) {
    const { value } = this.state;
    const { selector } = this.props;
    if(selector && prevState.value !== value) {      
      const options = document.querySelectorAll(`select[selector="${selector}"] option`);
      if(value.length) {
        const values = value.map(({ value }) => value);
        options.forEach(element => {
          if(values.includes(element.getAttribute('value'))) {
            element.setAttribute('selected', 'selected');
          }
          else {
            element.removeAttribute('selected');
          }
        });
      }
    }
  }

  onChange(selected) {
    const { name } = this.props;

    if(this.props['link-to-selection'] === 'link-to-selection') {
      window.location.href = selected.value;
    }

    this.setState({value: selected});
    document.dispatchEvent(new CustomEvent('hds-dropdown:change', {
      detail: {
        origin: name,
        selected: selected
      }
    }));
  }

  clear() {
    this.setState({value: []});
  }

  render() {
    const { name, multiple, placeholder } = this.props;
    const className = this.props.class;
    const options = JSON.parse(this.props.options);
    const { value } = this.state;
    const multiselect = multiple === 'multiple';

    return options && options.length ?
      <Select
        name={name}
        className={className}
        multiselect={multiselect}
        placeholder={placeholder}
        options={options}
        onChange={(selected) => this.onChange(selected)}
        value={value}
      /> : 
      null;
  }
}

Dropdown.propTypes = {
  selector: PropTypes.string,
  multiple: PropTypes.string,
  options: PropTypes.array,
  value: PropTypes.array,
  name: PropTypes.string,
  class: PropTypes.string,
  placeholder: PropTypes.string,
  'link-to-selection': PropTypes.string
};

customElements.define('hds-dropdown', reactToWebComponent(Dropdown, React, ReactDOM));
