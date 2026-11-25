/** Heure locale « HH:MM » de l'appareil : horodatage des bulles de la démo WhatsApp. */
export function clockTime(locale: string): string {
  return new Intl.DateTimeFormat(locale, { hour: "2-digit", minute: "2-digit" }).format(new Date());
}
