// biome-ignore-all lint/complexity/useOptionalChain: @todo UHF-12501
import { Button, ButtonPresetTheme, Select, TextInput } from 'hds-react';
import { useAtomValue, useSetAtom } from 'jotai';
import { RESET } from 'jotai/utils';
import { type FormEvent, useState } from 'react';
import { DateRangeSelect } from '@/react/common/DateRangeSelect';
import FilterButton from '@/react/common/FilterButton';
import SelectionsWrapper from '@/react/common/SelectionsWrapper';
import { selectionsAtom, setSelectionsAtom } from '../store';
import type { Selections } from '../types/Selections';
import { useEffect } from 'react';

export const FormContainer = ({ typeOptions }: { typeOptions?: Array<{ label: string; value: string }> }) => {
  const selections = useAtomValue(selectionsAtom);
  const setSelections = useSetAtom(setSelectionsAtom);
  const [type, setType] = useState<Array<{ label: string; value: string }> | undefined>(selections.type);
  const [dates, setDates] = useState<{ start: string | undefined; end: string | undefined }>({
    start: selections.start,
    end: selections.end,
  });
  const [endDisabled, setEndDisabled] = useState<boolean>(false);
  // When the component mounts and endDisabled is enabled and start date is set
  // but end date is not, set end date to match start date.
  useEffect(() => {
    if (endDisabled && dates.start && !dates.end) {
      setDates(prev => ({ ...prev, end: prev.start }));
    }
  }, [endDisabled, dates.start, dates.end]);

  const onSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = event.currentTarget;
    const { q } = form.elements as typeof form.elements & { q: { value: string } };

    const values: Selections = {};

    if (q?.value.length) {
      values.q = q.value;
    }
    if (type?.length) {
      values.type = type;
    }
    if (dates.start) {
      values.start = dates.start;
    }
    if (dates.end) {
      values.end = dates.end;
    }

    setSelections(values);
  };

  const resetForm = () => {
    setSelections(RESET);
    setType(undefined);
    setDates({ start: undefined, end: undefined });
  };

  return (
    // biome-ignore lint/a11y/useSemanticElements: @todo UHF-12501
    <form className='hdbt-search--react__form-container' onSubmit={onSubmit} role='search'>
      <TextInput
        className='hdbt-search__filter hdbt-search--react__text-field'
        defaultValue={selections.q}
        id='q'
        label={Drupal.t(
          'Name of street or park',
          {},
          { context: 'Allu decision search' },
        )}
        placeholder={Drupal.t(
          'Eg. Mannerheimintie',
          {},
          { context: 'Allu decision search' },
        )}
        type='search'
      />
      <div className='hdbt-search--react__dropdown-filters'>
        <Select
          className='hdbt-search--react__dropdown'
          clearable
          disabled={!typeOptions}
          multiSelect
          noTags
          onChange={setType}
          options={typeOptions || []}
          texts={{
            label: Drupal.t(
              'Type of decision',
              {},
              { context: 'Allu decision search' },
            ),
            placeholder: Drupal.t(
              'All types',
              {},
              { context: 'Allu decision search' },
            ),
          }}
          value={type}
        />
        <DateRangeSelect
          endDate={dates.end}
          helperText={Drupal.t('Eg. 5.11.2024 - 10.11.2024', {}, { context: 'Allu decision search' })}
          id='date-range-select'
          label={Drupal.t('Date of decision', {}, { context: 'Allu decision search' })}
          title={Drupal.t('Date of decision', {}, { context: 'Allu decision search' })}
          setStart={(d?: string) => {
            if (endDisabled && d) {
              setDates({ start: d, end: d });
            } else {
              setDates({ ...dates, start: d });
            }
          }}
          setEnd={(d?: string) => setDates({ ...dates, end: d })}
          setEndDisabled={(disabled) => {
            setEndDisabled(disabled);
            if (disabled && dates.start) {
              // When endDisabled is enabled, set end date to match start date.
              setDates(prev => ({ ...prev, end: prev.start }));
            }
          }}
          startDate={dates.start}
          endDisabled={endDisabled}
        />
      </div>
      <Button className='hdbt-search--react__submit-button' theme={ButtonPresetTheme.Black} type='submit'>
        {Drupal.t('Search')}
      </Button>
      <SelectionsWrapper resetForm={resetForm} showClearButton>
        {selections.type &&
          selections.type.map((typeSelection) => (
            <FilterButton
              key={typeSelection.value}
              value={typeSelection.label}
              clearSelection={() => {
                const filtered = selections.type?.filter((currentType) => currentType.value !== typeSelection.value);
                const newValue = !filtered || !filtered.length ? undefined : filtered;
                setSelections({ type: newValue }, true);
                setType(newValue);
              }}
            />
          ))}
        {(selections.start || selections.end) && (
          <FilterButton
            clearSelection={() => {
              setSelections({ start: undefined, end: undefined }, true);
              setDates({ start: undefined, end: undefined });
            }}
            value={`${selections.start || ''} - ${selections.end || ''}`}
          />
        )}
      </SelectionsWrapper>
    </form>
  );
};
