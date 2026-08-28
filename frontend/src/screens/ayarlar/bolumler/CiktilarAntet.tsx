import { settings as settingsApi } from '../../../api/endpoints';
import { useAsync } from '../../../lib/useAsync';
import { ErrorNote, Skeleton } from '../../../components/ui';
import BelgeAntedi from '../BelgeAntedi';

/**
 * AYARLAR > 10 ÇIKTILAR & ANTET (V3-B C1).
 *
 * Antet Excel, PDF ve paylaşım sayfasının ÜÇÜNE birden basılır: tek kaynak.
 * Sarmalayıcının işi yalnız ayarları okuyup mevcut bileşene vermek — bileşen
 * İE#13 F1'den beri çalışıyor ve davranışı değişmedi.
 */
export default function CiktilarAntet() {
  const durum = useAsync(() => settingsApi.read(), []);

  if (durum.loading) return <Skeleton rows={2} />;
  if (durum.error) return <ErrorNote message={durum.error} onRetry={durum.reload} />;
  if (!durum.data) return null;

  return <BelgeAntedi mevcut={durum.data.document_header} onSaved={durum.reload} />;
}
