const categoryMap = new Map([
  ['00', 'Hallintoasiat'],
  ['01', 'Henkilöstöasiat'],
  ['02', 'Talousasiat, verotus ja omaisuuden hallinta'],
  ['03', 'Lainsäädäntö ja lainsäädännön soveltaminen'],
  ['04', 'Kansainvälinen toiminta ja maahanmuuttopolitiikka'],
  ['05', 'Sosiaalitoimi'],
  ['06', 'Terveydenhuolto'],
  ['07', 'Tiedon hallinta'],
  ['08', 'Liikenne'],
  ['09', 'Turvallisuus ja yleinen järjestys'],
  ['10', 'Maankäyttö, rakentaminen ja asuminen'],
  ['11', 'Ympäristöasia'],
  ['12', 'Opetus- ja sivistystoimi'],
  ['13', 'Tutkimus- ja kehittämistoiminta'],
  ['14', 'Elinkeino- ja työvoimapalvelut'],
]);

export const categoryToLabel = (category: string): string|undefined => categoryMap.get(category);
