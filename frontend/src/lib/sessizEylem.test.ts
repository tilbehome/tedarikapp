import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, test } from 'vitest';

const kok = resolve(__dirname, '../..');

/**
 * V3-B F1 — "HİÇBİR EYLEM SESSİZ ÇALIŞMAZ" BEKÇİSİ.
 *
 * Bildirim kataloğunun ilkesi ("önemli hiçbir işlem sessiz çalışmaz") sunucu
 * tarafı için A bloğunda uygulandı. Panelde karşılığı şudur: kullanıcının
 * bastığı bir düğme, ne olduğunu SÖYLEMEDEN bitmemeli.
 *
 * Bu bekçi async tıklama işleyicilerini tarar ve gövdesinde HİÇBİR geri
 * bildirim izi olmayanları kırmızıya düşürür. Geri bildirim izi sayılanlar:
 * toast (`push`), hata durumu (`setHata`/`setError`), uzun işlem göstergesi
 * (`useUzunIslem`/`IslemDurumu`), meşgul bayrağı (`setBusy`) ya da veri
 * tazeleme (`.reload()`).
 *
 * TARAMANIN SINIRI AÇIKÇA YAZILIDIR: bu bir kanıt değil, bir elektir. Statik
 * tarama bir işleyicinin gerçekten anlamlı bir şey söylediğini bilemez; yalnız
 * HİÇBİR ŞEY söylemediğini fark eder. Yine de değeri var — ilk koşumunda
 * `ProductFormScreen.oneriIste` yakalandı: çeviri sağlayıcısı çöktüğünde
 * kullanıcı düğmeye basıyor ve hiçbir şey olmuyordu.
 */
const GERIBILDIRIM = [
  'push(',
  'setHata',
  'setError',
  'IslemDurumu',
  'useUzunIslem',
  'baslat(',
  'toast',
  '.reload()',
  'setBusy',
] as const;

/**
 * Gövdesi bu dosyada TANIMLI OLMAYAN işleyiciler: props ile gelen geri
 * çağrılar (`onOkundu`) ve başka modüldeki kancalar. Onların geri bildirimi
 * tanımlandıkları yerde sınanır; burada "bulunamadı" demek gürültü olurdu.
 */
const DISARIDA_TANIMLI = ['tikla', 'onOkundu', 'cikis', 'eylemCalistir', 'geriAl'];

interface Bulgu {
  dosya: string;
  isleyici: string;
}

async function tara(): Promise<Bulgu[]> {
  const { globSync } = await import('node:fs');
  const dosyalar = globSync('src/**/*.tsx', { cwd: resolve(kok) }).filter(
    (dosya) => !dosya.includes('.test.'),
  );

  const bulgular: Bulgu[] = [];

  for (const dosya of dosyalar) {
    const icerik = readFileSync(resolve(kok, dosya), 'utf8');
    const adlar = new Set(
      [...icerik.matchAll(/onClick=\{\(\) => void (\w+)\(/g)].map((eslesme) => eslesme[1] ?? ''),
    );

    for (const ad of adlar) {
      if (ad === '' || DISARIDA_TANIMLI.includes(ad)) continue;

      const bildirim = new RegExp(`const ${ad} = (?:async )?\\(`).exec(icerik);
      if (!bildirim) continue;

      // GÖVDE, BİLDİRİMİN BAŞINDAN sonraki üst düzey bildirime kadar alınır.
      //
      // Süslü paranteze göre kesmek YANLIŞTI ve ilk koşumda yanlış alarm
      // verdi: `const uret = (f) => uretim.baslat(async () => { ... })`
      // biçiminde bir işleyicide ilk `{` zaten iç fonksiyonun gövdesidir ve
      // ondan ÖNCEKİ `baslat(` çağrısı — yani geri bildirimin ta kendisi —
      // taramanın dışında kalıyordu.
      // Sonlandırıcı EN ÇOK İKİ BOŞLUK girintili olmalı: bileşen gövdesindeki
      // kardeş bildirimler o hizadadır. Dört boşluğa da izin vermek, işleyicinin
      // İÇİNDEKİ bir `const` satırında keserdi ve altındaki `push(...)`
      // görülmezdi — ikinci yanlış alarm tam olarak buydu (`gorunumKaydet`).
      const sonrasi = icerik.slice(bildirim.index + bildirim[0].length);
      const bitis = /\n {0,2}(?:const |function |export |useEffect\(|return )/.exec(sonrasi);
      const govde = sonrasi.slice(0, bitis ? bitis.index : sonrasi.length);

      if (!GERIBILDIRIM.some((iz) => govde.includes(iz))) {
        bulgular.push({ dosya: dosya.replace(/\\/g, '/'), isleyici: ad });
      }
    }
  }

  return bulgular;
}

describe('F1 — async tıklama işleyicileri geri bildirim verir', () => {
  test('geri bildirimsiz işleyici YOK', async () => {
    const bulgular = await tara();

    expect(
      bulgular,
      'Bu işleyiciler kullanıcıya hiçbir şey söylemiyor:\n'
        + bulgular.map((b) => `  ${b.dosya} → ${b.isleyici}`).join('\n'),
    ).toEqual([]);
  });

  test('tarama gerçekten dosya buluyor', async () => {
    // Glob bozulursa test "0 bulgu" der ve yeşil kalır. Hiçbir şeye bakmayan
    // bekçi, olmayan bekçiden tehlikelidir (tokens.test.ts ile aynı ders).
    const { globSync } = await import('node:fs');

    expect(globSync('src/**/*.tsx', { cwd: resolve(kok) }).length).toBeGreaterThan(20);
  });
});
