import { isValid, parse } from 'date-fns';

export const  isValidDate = (date: string) => isValid(parse(date, 'd.M.y', new Date()));
