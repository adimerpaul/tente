// Icono de Material para cada categoría del menú. Se decide por palabra clave
// para que una categoría nueva (escrita libre en Productos) también tenga icono.
const ICONOS = [
  [/TODO/, 'restaurant_menu'],
  [/HAMBURG|BURG/, 'lunch_dining'],
  [/SALCHI|PAPA/, 'fastfood'],
  [/POLLO|ALITA|BROASTER/, 'kebab_dining'],
  [/PIQUE|PLATO|ALMUERZO/, 'dinner_dining'],
  [/PIZZA/, 'local_pizza'],
  [/BEBIDA|REFRESCO|GASEOSA|JUGO/, 'local_drink'],
  [/POSTRE|HELADO/, 'icecream'],
  [/COMBO|PROMO/, 'redeem']
]

export const categoriaIcon = c => (ICONOS.find(([re]) => re.test(c || '')) || [null, 'restaurant'])[1]
