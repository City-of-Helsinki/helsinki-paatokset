const operatorTest = /(\+|\||-|"|\*|\(|\)|~)/;

export const isOperatorSearch = (value: string) => operatorTest.test(value);
