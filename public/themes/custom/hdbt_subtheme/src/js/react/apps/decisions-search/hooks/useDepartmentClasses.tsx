export default function useDepartmentClasses(colorClassArray: string[]|undefined) {
  if(colorClassArray && colorClassArray.length >= 0) {
    const colorClass = colorClassArray[0];

    return `var(--${colorClass})`;
  }

  return '';
}