/**
 * Desteklenen kaynak alan adları (İE#11 EK-2 4 — yumuşak uyarı; ENGEL DEĞİL).
 * Canlı gözlem: link alanına panel adresi girilmişti — firma ürünü bulamaz.
 * Liste genişletilebilir sabittir (F37 modülleriyle büyür).
 */
const SUPPORTED_SOURCE_HOSTS = ['1688.com'];

export function isSupportedSourceUrl(value: string): boolean {
  try {
    const host = new URL(value).hostname.toLowerCase();
    return SUPPORTED_SOURCE_HOSTS.some((allowed) => host === allowed || host.endsWith('.' + allowed));
  } catch {
    return false;
  }
}
