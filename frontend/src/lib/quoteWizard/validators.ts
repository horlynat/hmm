export function isValidEmail(value: string) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

export function isValidPhone(value: string) {
  return /^\+?[0-9\s-]{7,20}$/.test(value.trim());
}
