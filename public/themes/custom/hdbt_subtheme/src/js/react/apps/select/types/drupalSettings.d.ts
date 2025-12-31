declare namespace Drupal {
  const behaviors: { helfiSelect: { attach: (context: HTMLElement) => void } };
}

declare namespace drupalSettings {
  const path: { currentLanguage: 'fi' | 'en' | 'sv' };
}

type HelfiSelectSettings = { value?: string; empty_option?: string; options: { [key: string]: string } };
