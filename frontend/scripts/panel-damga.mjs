/**
 * PANEL BUILD DAMGASI (v0.11.3 koruması — sürüm disiplini ihlali dersi).
 *
 * OLAY: v0.11.2 paketine panelin BAŞKA BİR DALDA (v3-faz1) derlenmiş build'i
 * girdi. `public/panel/` .gitignore'dadır; dal değiştirmek diskteki derlemeyi
 * geri almaz ve `bin/release.php` diskte NE VARSA paketler. Onaylanmamış bir
 * arayüz kimse fark etmeden canlıya çıktı.
 *
 * KORUMA: her derleme hangi DALDAN ve hangi COMMIT'ten çıktığını BUILD.json'a
 * yazar; `bin/release.php` damgayı arar, yoksa paketlemeyi REDDEDER ve
 * `--panel-dal=` ile eşleşme şartı koyabilir.
 *
 * Neden ayrı bir betik: vite.config.ts panelin TARAYICI tip bağlamında
 * derleniyor; oraya Node API'si koymak `@types/node` bağımlılığı isterdi
 * (yeni bağımlılık = PM onayı). Bu dosya yalnız derleme adımında koşar.
 */
import { execSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const git = (komut) => {
  try {
    return execSync(komut, { encoding: 'utf8' }).trim();
  } catch {
    // Git yoksa (temiz çıkarma) derleme durmaz; damga "bilinmiyor" olur ve
    // release doğrulaması bunu görüp uyarır.
    return 'bilinmiyor';
  }
};

const damga = {
  dal: git('git rev-parse --abbrev-ref HEAD'),
  commit: git('git rev-parse --short HEAD'),
  temiz: git('git status --porcelain -- src') === '',
  zaman: new Date().toISOString(),
};

const hedef = resolve(dirname(fileURLToPath(import.meta.url)), '../../public/panel/BUILD.json');
writeFileSync(hedef, `${JSON.stringify(damga, null, 2)}\n`, 'utf8');

console.log(`  panel damgasi: ${damga.dal} @ ${damga.commit}${damga.temiz ? '' : ' (kirli calisma kopyasi)'}`);
