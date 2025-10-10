import React from 'react';

type Props = {
  parsedData: any[]
  getItemProps: any
  highlightedIndex: any
  selectedItem: any
}

const SearchBarAutocomplete = ({ parsedData, getItemProps, highlightedIndex, selectedItem }: Props) => (
  <div className="search-autocomplete__wrapper">
    <div className="search-autocomplete">
      {parsedData.map((suggestion, index: Number) => (
        <div className="search-autocomplete__item" key={suggestion.value} {...getItemProps({
          item: suggestion,
          style: {
            color: highlightedIndex === index ? 'white' : 'black',
            backgroundColor: highlightedIndex === index ? 'black' : 'white',
            fontWeight: selectedItem === suggestion ? 'bold' : 'normal',
          }
        })}>
          {suggestion.value}
        </div>
      ))}
    </div>
  </div>
);

export default SearchBarAutocomplete;