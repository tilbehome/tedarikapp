import { settings as settingsApi } from '../../../api/endpoints';
import { useAsync } from '../../../lib/useAsync';
import { ErrorNote, Skeleton } from '../../../components/ui';
import PaylasimIletisimi from '../PaylasimIletisimi';

/**
 * AYARLAR > 11 PAYLAŞIM & WHATSAPP (V3-B C1).
 *
 * Kilit ekranındaki "anahtarı iste" köprüsünün numarası (İE#21 EK-4 B7).
 * Numara E.164'e normalize edilir ve mesaj erişim anahtarını İÇERMEZ.
 */
export default function PaylasimSekmesi() {
  const durum = useAsync(() => settingsApi.read(), []);

  if (durum.loading) return <Skeleton rows={2} />;
  if (durum.error) return <ErrorNote message={durum.error} onRetry={durum.reload} />;
  if (!durum.data) return null;

  return <PaylasimIletisimi mevcut={durum.data.share_contact_phone} onSaved={durum.reload} />;
}
