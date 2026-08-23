import { NavLink, useLocation } from 'react-router-dom';
import { ChevronDown, LogOut, Moon, Plus, Sun, SunMoon } from 'lucide-react';
import { menuGruplari, type MenuOgesi } from '../../lib/menu';
import { grubuCevir, useKabukDurumu } from '../../lib/kabukDurumu';
import { temaDondur, temaEtiketleri, useTema } from '../../lib/tema';
import { useSession } from '../../store/session';
import markaAmblem from '../../assets/marka-amblem.svg';

/**
 * SOL MENÜ (İE#16 D1.5 — prototip birebir).
 *
 * Sıra: marka bloğu → "Yeni" düğmesi → gruplu gezinme → son bakılanlar →
 * hesap satırı + tema anahtarı. Aktif öğede SOL KENARDA ALTIN ŞERİT (altın
 * yalnız vurgu içindir — kanon §4). Rozet sayısı 0 ise BASILMAZ.
 *
 * `Ctrl+B` ile 68 px ikon şeridine iner (ölçü token'dan: --sidebar).
 * Daraltılmışken etiketler `title` ile ipucu olarak kalır.
 */
export default function YanMenu({
  gelenKutusuSayisi,
  onKomut,
  onCikis,
}: {
  gelenKutusuSayisi: number;
  onKomut: () => void;
  onCikis: () => void;
}) {
  const { daraltilmis, kapaliGruplar, sonBakilanlar } = useKabukDurumu();
  const tema = useTema();
  const user = useSession((state) => state.user);
  const konum = useLocation();

  const TemaSimgesi = tema === 'koyu' ? Moon : tema === 'acik' ? Sun : SunMoon;

  return (
    <aside
      data-testid="yan-menu"
      data-daraltilmis={daraltilmis ? 'evet' : 'hayir'}
      className={`hidden shrink-0 flex-col border-r border-line bg-surface md:flex ${
        daraltilmis ? 'w-[68px]' : 'w-[264px]'
      }`}
    >
      {/* ── Marka bloğu (İE#21 B13 — HİBRİT KİMLİK) ──
           Amblem TURUNCUDUR (marka kiti), arayüzün teması LACİVERT KALIR.
           İkisi çelişmez: turuncu ürünün imzasıdır ve tek bir yerde durur;
           lacivert çalışma yüzeyinin rengidir ve her yerdedir. Amblemi de
           lacivert yapmak markayı görünmez kılardı, arayüzü turuncuya
           çevirmek ise günlerce bakılan bir ekranı yorucu hâle getirirdi. */}
      <div className="flex h-14 items-center gap-2.5 border-b border-line px-3">
        <img
          src={markaAmblem}
          alt=""
          width={36}
          height={36}
          className="size-9 shrink-0 rounded-xl"
        />
        {!daraltilmis && (
          <span className="min-w-0">
            <span className="block text-[10px] font-semibold tracking-[0.14em] text-ink-3">TİLBE HOME</span>
            <span className="flex items-center gap-1.5 text-base font-bold tracking-tight text-ink">
              TedarikApp
              <i className="not-italic rounded-md bg-g100 px-1.5 py-0.5 text-[10px] font-semibold text-ink-3">
                {import.meta.env.VITE_SURUM ?? '1.0'}
              </i>
            </span>
          </span>
        )}
      </div>

      {/* ── Hızlı işlem: komut paletini açar ── */}
      <div className="p-3">
        <button
          type="button"
          className="flex min-h-10 w-full items-center justify-center gap-2 rounded-xl bg-navy text-base font-semibold text-white transition-colors hover:bg-navy-2"
          onClick={onKomut}
          title="Yeni (Ctrl+K)"
        >
          <Plus size={17} aria-hidden />
          {!daraltilmis && <span>Yeni</span>}
        </button>
      </div>

      {/* ── Gruplu gezinme ── */}
      <nav className="flex-1 overflow-y-auto px-2 pb-2">
        {menuGruplari.map((grup) => {
          const kapali = kapaliGruplar.includes(grup.baslik);

          return (
            <div key={grup.baslik} className="mb-1">
              {!daraltilmis && (
                <button
                  type="button"
                  className="flex w-full items-center justify-between px-3 py-2 text-[10px] font-bold tracking-[0.12em] text-ink-3 hover:text-ink-2"
                  onClick={() => grubuCevir(grup.baslik)}
                  aria-expanded={!kapali}
                >
                  {grup.baslik}
                  <ChevronDown size={13} className={kapali ? '-rotate-90 transition-transform' : 'transition-transform'} aria-hidden />
                </button>
              )}
              {!kapali &&
                grup.ogeler.map((oge) => (
                  <MenuSatiri
                    key={oge.to}
                    oge={oge}
                    daraltilmis={daraltilmis}
                    rozet={oge.rozet === 'gelenKutusu' ? gelenKutusuSayisi : 0}
                  />
                ))}
            </div>
          );
        })}

        {/* ── Son bakılanlar (kanon §3) — boşsa bölüm hiç basılmaz ── */}
        {!daraltilmis && sonBakilanlar.length > 0 && (
          <div className="mt-3 border-t border-line-soft pt-3">
            <div className="px-3 pb-1 text-[10px] font-bold tracking-[0.12em] text-ink-3">SON BAKILANLAR</div>
            {sonBakilanlar.map((kayit) => (
              <NavLink
                key={kayit.to}
                to={kayit.to}
                className="block truncate rounded-lg px-3 py-1.5 text-sm text-ink-2 hover:bg-g50 hover:text-ink"
                title={kayit.label}
              >
                {kayit.label}
              </NavLink>
            ))}
          </div>
        )}
      </nav>

      {/* ── Hesap satırı + tema ── */}
      <div className="flex items-center gap-2 border-t border-line p-3">
        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-blue-soft text-sm font-bold text-navy">
          {(user?.email ?? 'T').charAt(0).toLocaleUpperCase('tr-TR')}
        </span>
        {!daraltilmis && <span className="min-w-0 flex-1 truncate text-sm text-ink-2">{user?.email}</span>}
        <button
          type="button"
          className="flex size-8 shrink-0 items-center justify-center rounded-lg text-ink-3 hover:bg-g50 hover:text-ink"
          onClick={() => temaDondur()}
          title={temaEtiketleri[tema]}
          aria-label={temaEtiketleri[tema]}
        >
          <TemaSimgesi size={16} aria-hidden />
        </button>
        {!daraltilmis && (
          <button
            type="button"
            className="flex size-8 shrink-0 items-center justify-center rounded-lg text-ink-3 hover:bg-g50 hover:text-ink"
            onClick={onCikis}
            title="Çıkış yap"
            aria-label="Çıkış yap"
          >
            <LogOut size={16} aria-hidden />
          </button>
        )}
      </div>

      {/* Ekran okuyucular için: hangi ekrandayız (görsel karşılığı üst çubuktadır). */}
      <span className="sr-only" aria-live="polite">
        {konum.pathname}
      </span>
    </aside>
  );
}

function MenuSatiri({ oge, daraltilmis, rozet }: { oge: MenuOgesi; daraltilmis: boolean; rozet: number }) {
  const ortak =
    'relative flex min-h-10 items-center gap-2.5 rounded-lg px-3 text-md font-medium transition-colors';

  // Hazır olmayan ekran GİZLENMEZ: nereye gidildiği görünsün, menü sonradan
  // büyüyüp öğelerin yerini değiştirmesin (kas hafızası — kanon §3).
  if (!oge.hazir) {
    return (
      <span
        className={`${ortak} cursor-not-allowed text-ink-3 opacity-70`}
        aria-disabled="true"
        title={daraltilmis ? `${oge.label} — yakında` : 'Sonraki fazda'}
      >
        <oge.icon size={18} aria-hidden />
        {!daraltilmis && (
          <>
            <span className="flex-1 truncate">{oge.label}</span>
            <span className="rounded-md bg-g100 px-1.5 py-0.5 text-[10px] font-semibold text-ink-3">Yakında</span>
          </>
        )}
      </span>
    );
  }

  return (
    <NavLink
      to={oge.to}
      end={oge.to === '/'}
      title={daraltilmis ? oge.label : undefined}
      className={({ isActive }) =>
        `${ortak} ${isActive ? 'bg-blue-soft font-semibold text-navy' : 'text-ink-2 hover:bg-g50 hover:text-ink'}`
      }
    >
      {({ isActive }) => (
        <>
          {/* Aktif öğede sol kenarda ince ALTIN şerit (kanon §3/§4). */}
          {isActive && (
            <span className="absolute inset-y-1.5 left-0 w-[3px] rounded-r bg-gold" aria-hidden />
          )}
          <oge.icon size={18} aria-hidden />
          {!daraltilmis && <span className="flex-1 truncate">{oge.label}</span>}
          {/* Rozet: SIFIRSA BASILMAZ (kanon §3). */}
          {rozet > 0 && !daraltilmis && (
            <span className="rounded-full bg-navy px-1.5 py-0.5 text-[10px] font-bold text-white">{rozet}</span>
          )}
          {rozet > 0 && daraltilmis && (
            <span className="absolute right-1 top-1 size-2 rounded-full bg-gold" aria-hidden />
          )}
        </>
      )}
    </NavLink>
  );
}
