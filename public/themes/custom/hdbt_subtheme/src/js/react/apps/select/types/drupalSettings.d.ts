declare namespace drupalSettings {
  const path: {
    currentLanguage: 'fi' | 'en' | 'sv';
  };
  const helfi_select: {
    value?: string;
    empty_option?: string;
    options: {
      [key: string]: string;
    };
  };
}
