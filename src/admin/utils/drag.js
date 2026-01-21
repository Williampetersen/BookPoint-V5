export function snapMinutes(mins, step = 15){
  return Math.round(mins / step) * step;
}
