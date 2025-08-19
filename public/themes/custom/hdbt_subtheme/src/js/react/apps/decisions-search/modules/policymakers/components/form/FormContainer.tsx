import React, { Component } from 'react';

import { ReactiveComponent } from '@appbaseio/reactivesearch';
import classNames from 'classnames';
import SearchBar from './SearchBar';
import SubmitButton from './SubmitButton';
import SearchComponents from '../../enum/SearchComponents';
import IndexFields from '../../enum/IndexFields';
import SelectedFilters from '../../../../common/components/form/SelectedFilters';
import { getQueryParam } from '../../../../utils/QueryString';

import SectorSelect from './filters/SectorSelect';

type Props = {
  langcode: string,
  searchTriggered: boolean,
  triggerSearch: Function,
  setLastRefreshed: Function
}

type FormContainerState = {
  phrase: string,
  sectors: string[],
  sectorsSelected: string[],
  querySectors: string[],
  filters: any[],
  wildcardPhrase: string
};

const getInitialValue = (key: string) => {
  const queryParam = getQueryParam(key);
  if(queryParam) {
    return JSON.parse(queryParam);
  }
  return [];
};

class FormContainer extends Component<Props> {
  state: FormContainerState = {
    phrase: '',
    sectors: [],
    sectorsSelected: [],
    querySectors: [],
    filters: [],
    wildcardPhrase: ''
  };

  componentDidMount() {
    const initialOrgans = getInitialValue(SearchComponents.ORGAN);
    if(initialOrgans) {
      this.setState({
        organs: initialOrgans,
        queryOrgans: initialOrgans
      });
    }
    const initialSectors = getInitialValue(SearchComponents.SECTOR);
    if(initialSectors) {
      this.setState({
        sectors: initialSectors,
        sectorsSelected: initialSectors,
        querySectors: initialSectors
      });
    }

    const initialPhrase = getInitialValue(SearchComponents.SEARCH_BAR);
    if(initialPhrase) {
      this.setState({
        wildcardPhrase: initialPhrase
      });
    }

    const initialPage = getQueryParam('results');
    if(![initialOrgans, initialSectors, initialPhrase, initialPage].every(value => Number(value) === 0)) {
      if(!this.props.searchTriggered) {
        this.props.triggerSearch();
      }
    }
  }

  setPhrase = (value: string) => {
    this.setState({
      phrase: value
    });
  };

  setWildCardPhrase = (value: string) => {
    this.setState({
      wildcardPhrase: value
    });
  };

  setSectors = (sectors: string[]) => {
    this.setState({
      sectors
    });
  };

  searchBar = React.createRef<any>();

  getFilters = () => {
    const { sectorsSelected } = this.state;
    const sectorFilters = sectorsSelected.map(sector => ({ value: sector, type: SearchComponents.SECTOR, deleteFilter: this.deleteFilter }));
    return sectorFilters;
  };

  updateFilters = () => {
    this.setState({
      querySectors: this.state.sectors,
      sectorsSelected: this.state.sectors
    });
  };

  deleteFilter = (value: string, type: string) => {
    const values = [...this.state.sectors];
    values.splice(values.indexOf(value), 1);
    this.setSectors(values);
    this.setState({
      sectors: values,
      sectorsSelected: values,
      querySectors: values
    });
  };

  clearAllFilters = () => {
    this.setState({
      sectors: [],
      querySectors: [],
      sectorsSelected: []
    });
  };

  handleSubmit = (event: any) => {
    if(event) {
      event.preventDefault();
    }

    if(this.searchBar.current) {
      if(!this.props.searchTriggered) {
        this.props.triggerSearch();
      }

      this.searchBar.current.triggerQuery();
    }

    this.setState({
      wildcardPhrase: this.state.phrase
    });

    this.updateFilters();
  };

  render() {
    const { phrase, sectors, querySectors, wildcardPhrase } = this.state;

    return (
      <div className={classNames(
          'decisions-search-form-container',
          'wrapper'
        )}
      >
        <form
          className={classNames(
            'decisions-search-form',
            'container'
          )}
          onSubmit={this.handleSubmit}
        >
          <div>
            <SearchBar
              ref={this.searchBar}
              value={phrase}
              setValue={this.setPhrase}
            />
          </div>
          <div className='decisions-search-form-container__lower-fields'>
            <ReactiveComponent
              componentId={SearchComponents.SECTOR}
              defaultQuery={() => (
                {
                  query: {
                    "bool": {
                      "must": [
                        {
                          "match": {
                            [IndexFields.LANGUAGE]: this.props.langcode
                          }
                        },
                        {
                          "match": {
                            [IndexFields.POLICYMAKER_EXISTING]: true
                          }
                        }
                      ]
                    }
                  },
                  aggs: {
                    [IndexFields.SECTOR]: {
                      terms: {
                        field: IndexFields.SECTOR,
                        size: 100,
                        order: { _key: 'asc' }
                      }
                    }
                  }
                }
              )}
              render={({ aggregations, setQuery }) => (
                <SectorSelect
                  aggregations={aggregations}
                  value={sectors}
                  setValue={this.setSectors}
                  setQuery={setQuery}
                  queryValue={querySectors}
                />
              )}
              URLParams={true}
            />
            <ReactiveComponent
              componentId={SearchComponents.WILDCARD}
              customQuery={() => ({
                query: {
                  wildcard: {
                    'title.keyword': `*${wildcardPhrase}*`
                  }
                }
              })}
            />
          </div>
          <SubmitButton />
          <SelectedFilters
            filters={this.getFilters()}
            clearAll={this.clearAllFilters}
          />
        </form>
      </div>
    );
  }
}

export default FormContainer;
