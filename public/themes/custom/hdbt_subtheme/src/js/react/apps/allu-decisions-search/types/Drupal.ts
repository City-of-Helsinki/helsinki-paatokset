declare namespace Drupal {
  function t(str: string, options?: object, context?: object): string;
  function formatPlural(count: string, singular: string, plural: string, args?: object, options?: object): string;
  function theme(id: string): string;
}
