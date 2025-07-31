import React from 'react';
import { withTranslation } from 'react-i18next';

import SearchBar from '../../../decisions/components/form/SearchBar';
import SubmitButton from '../../../decisions/components/form/SubmitButton';

import classNames from 'classnames';

type FormContainerProps = {
  langcode: string,
  searchTriggered: boolean,
  triggerSearch: Function,
  searchLabel: string,
  searchRedirect: string,
  t?: Function
};

type FormContainerState = {
  phrase: string,
};

class FormContainer extends React.Component<FormContainerProps, FormContainerState> {
  state: FormContainerState = {
    phrase: '',
  };

  searchBar = React.createRef<any>();

  handleSubmit = (event: any) => {
    if(event) {
      window.location.href = this.props.searchRedirect + '"' + encodeURIComponent(this.state.phrase) + '"';
      event.preventDefault();
    }
  };

  handleSelectedValue = (value: any) => {
    if (value) {
      window.location.href = this.props.searchRedirect + '"' + encodeURIComponent(value) + '"';
    }
  }

  changePhrase = (value: any) => {
    this.setState({
      phrase: value
    });
  };

  render() {
    const { phrase } = this.state;

    let containerStyle: any = {};

    return(
      <div>
        <div
          className={classNames(
            'decisions-search-form-container',
            'wrapper'
          )}
          style={containerStyle}
        >
          <form className={classNames(
              'decisions-search-form',
              'container',
              'container--search-frontpage'
            )}
            onSubmit={this.handleSubmit}
          >
            <div>
              <SearchBar
                ref={this.searchBar}
                value={phrase}
                setValue={this.changePhrase}
                URLParams={false}
                searchLabel={this.props.searchLabel}
                triggerSearch={this.handleSelectedValue}
              />
            </div>
            <SubmitButton
              disabled={false}
            />
          </form>
        </div>
      </div>
    );
  }
};

export default withTranslation()(FormContainer);
