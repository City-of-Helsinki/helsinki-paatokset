// Augment hds-react to expose internal types that hdbt's common helpers rely on.
// These types exist in hds-react but are not re-exported from the package root.
// The `export {}` makes this a module, which enables merging (augmentation) rather than replacing.
export {};

declare module 'hds-react' {
  export type SelectProps = import('hds-react/components/dropdownComponents/select/types').SelectProps;
  export type SearchFunction = import('hds-react/components/dropdownComponents/select/types').SearchFunction;
  export type SelectData = import('hds-react/components/dropdownComponents/select/types').SelectData;
  export type SupportedLanguage =
    import('hds-react/components/dropdownComponents/modularOptionList/types').SupportedLanguage;
  export type Texts = import('hds-react/components/dropdownComponents/modularOptionList/types').Texts;
}
