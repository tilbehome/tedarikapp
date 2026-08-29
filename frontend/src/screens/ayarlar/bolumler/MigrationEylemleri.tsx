import { system as systemApi } from '../../../api/endpoints';
import { useUzunIslem } from '../../../lib/useUzunIslem';
import IslemDurumu from '../../../components/IslemDurumu';
import { count } from '../../../lib/format';

/**
 * Güncelleme eylemleri — bekleyen migration varken görünür (İE#11 sonrası düzeltme:
 * panelde "migrate" düğmesi YOKTU, kullanıcı yeni sürümü kuramıyordu).
 *
 *  • "Güncellemeyi çalıştır" (migrate): bekleyen migration'ları uygular — ASIL yol.
 *  • "Defteri eşitle" (K49 baseline): tablolar VAR ama defter geride kalmışsa
 *    kayıtları KOŞMADAN işler; DDL çalıştırmaz, idempotenttir.
 */
export default function MigrationActions({ onDone }: { onDone: () => void }) {
  // İE#14 C2: migration TEK ATIMLIK bir iştir — iptal isteği sunucudaki işi
  // yarıda bırakmaz (yarım migration tehlikelidir), yalnız sonucu işaretler.
  const guncelleme = useUzunIslem();
  const defter = useUzunIslem();

  const migrate = () =>
    void guncelleme.baslat(async () => {
      const result = await systemApi.migrate();
      onDone();

      return result.applied_count === 0
        ? 'Uygulanacak yeni migration yoktu.'
        : `Güncelleme tamam: ${count(result.applied_count)} migration uygulandı.`;
    });

  const baseline = () =>
    void defter.baslat(async () => {
      const result = await systemApi.migrateBaseline();
      onDone();

      return result.skipped.length === 0
        ? `Defter eşitlendi: ${count(result.recorded.length)} kayıt işlendi, bekleyen ${count(result.pending_count)}.`
        : `${count(result.recorded.length)} kayıt işlendi; ${count(result.skipped.length)} kayıt atlandı (nesnesi yok) — bekleyen ${count(result.pending_count)}.`;
    });

  const busy = guncelleme.calisiyor || defter.calisiyor;

  return (
    <div className="mt-3 border-t border-line-soft pt-3">
      <p className="text-xs text-ink-3">
        Yeni sürüm veritabanı güncellemesi bekliyor. "Güncellemeyi çalıştır" bekleyen migration'ları uygular.
        Tablolar zaten varsa (defter geride kalmışsa) "Defteri eşitle" kullanılır — o işlem tablo oluşturmaz.
      </p>
      <div className="mt-2 flex flex-wrap gap-2">
        <button type="button" className="btn-primary" disabled={busy} onClick={migrate}>
          {guncelleme.calisiyor ? 'Çalışıyor…' : 'Güncellemeyi çalıştır'}
        </button>
        <button type="button" className="btn-ghost" disabled={busy} onClick={baseline}>
          {defter.calisiyor ? 'Eşitleniyor…' : 'Defteri eşitle'}
        </button>
      </div>
      <IslemDurumu islem={guncelleme} fiil="Veritabanı güncelleniyor" onTekrar={migrate} />
      <IslemDurumu islem={defter} fiil="Migration defteri eşitleniyor" onTekrar={baseline} />
    </div>
  );
}
