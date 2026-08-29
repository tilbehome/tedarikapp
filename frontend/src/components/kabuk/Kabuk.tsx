import { useCallback, useEffect, useState } from 'react';
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { Compass, Inbox, LayoutDashboard, ListChecks, Menu as MenuIcon, X } from 'lucide-react';
import { useSession } from '../../store/session';
import { useReference } from '../../store/reference';
import { Toaster } from '../Toast';
import { ekranAdi, menuGruplari } from '../../lib/menu';
import { menuyuCevir } from '../../lib/kabukDurumu';
import { useGelenKutusuSayisi } from '../../lib/useGelenKutusuSayisi';
import YanMenu from './YanMenu';
import UstCubuk from './UstCubuk';
import AnlikKart from '../bildirim/AnlikKart';
import SurumBalonu from '../SurumBalonu';
import { useBildirimSayisi } from '../../lib/useBildirimSayisi';
import KomutPaleti from './KomutPaleti';

/**
 * UYGULAMA KABUĞU (İE#16 D1.4) — sabit sol menü + üst çubuk + içerik alanı.
 *
 * İçerik KISMİ güncellenir: gezinme React Router ile olur, sayfa yeniden
 * yüklenmez, beyaz flaş yaşanmaz. Kabuk (menü + üst çubuk) DOM'da kalır;
 * yalnız `<Outlet />` değişir.
 *
 * KISAYOLLAR (kanon §9): Ctrl+K komut paleti · Ctrl+B menü daraltma ·
 * "/" arama odağı (paleti açar) · Esc kapatır.
 *
 * SAYFA BAŞLIĞI anlamlıdır: "Ekran · Bölüm — TedarikApp" (D1.4) — sekmeler
 * arasında hangi ekranda olduğunuz başlıktan okunur.
 */
export default function Kabuk() {
  const konum = useLocation();
  const navigate = useNavigate();
  const user = useSession((state) => state.user);
  const logout = useSession((state) => state.logout);
  const resetReference = useReference((state) => state.reset);

  const [paletAcik, setPaletAcik] = useState(false);
  const [mobilMenu, setMobilMenu] = useState(false);

  const [bolum, ekran] = ekranAdi(konum.pathname);

  useEffect(() => {
    document.title = `${ekran} · ${bolum} — TedarikApp`;
  }, [bolum, ekran]);

  useEffect(() => {
    const dinle = (olay: KeyboardEvent) => {
      const hedef = olay.target as HTMLElement | null;
      const yaziyor =
        hedef !== null && (hedef.tagName === 'INPUT' || hedef.tagName === 'TEXTAREA' || hedef.isContentEditable);

      if ((olay.ctrlKey || olay.metaKey) && olay.key.toLowerCase() === 'k') {
        olay.preventDefault();
        setPaletAcik(true);

        return;
      }
      if ((olay.ctrlKey || olay.metaKey) && olay.key.toLowerCase() === 'b') {
        olay.preventDefault();
        menuyuCevir();

        return;
      }
      // "/" yalnız yazı yazılmıyorken paleti açar — form alanında eğik çizgi yazılabilmeli.
      if (olay.key === '/' && !yaziyor) {
        olay.preventDefault();
        setPaletAcik(true);
      }
    };

    window.addEventListener('keydown', dinle);

    return () => window.removeEventListener('keydown', dinle);
  }, []);

  const cikis = useCallback(async () => {
    await logout();
    resetReference();
    navigate('/giris', { replace: true });
  }, [logout, navigate, resetReference]);

  // Gelen kutusu rozeti (0 ise menüde basılmaz — kanon §3).
  const gelenKutusuSayisi = useGelenKutusuSayisi();
  const bildirim = useBildirimSayisi();

  return (
    <div className="flex min-h-dvh bg-app" data-testid="kabuk">
      {/* A5: kritik olayın anlık kartı — modal değil, köşe kartı. */}
      <AnlikKart />
      {/* B4: yeni sürümün "Yenilikler" balonu — sürüm başına bir kez. */}
      <SurumBalonu />
      <YanMenu gelenKutusuSayisi={gelenKutusuSayisi} onKomut={() => setPaletAcik(true)} onCikis={() => void cikis()} />

      <div className="flex min-w-0 flex-1 flex-col">
        <div className="hidden md:block">
          <UstCubuk
            bolum={bolum}
            ekran={ekran}
            onKomut={() => setPaletAcik(true)}
            bildirimSayisi={bildirim.sayi}
            onBildirimSayaci={bildirim.ayarla}
          />
        </div>

        {/* ── Telefon üst başlığı: menü düğmesi + ekran adı + komut ── */}
        <header className="sticky top-0 z-30 flex h-14 items-center gap-2 border-b border-line bg-surface px-3 md:hidden">
          <button
            type="button"
            className="flex size-10 items-center justify-center rounded-lg text-ink-2"
            onClick={() => setMobilMenu(true)}
            aria-label="Menüyü aç"
          >
            <MenuIcon size={19} aria-hidden />
          </button>
          <b className="min-w-0 flex-1 truncate text-base font-semibold text-ink">{ekran}</b>
          <button
            type="button"
            className="flex size-10 items-center justify-center rounded-lg text-ink-2"
            onClick={() => setPaletAcik(true)}
            aria-label="Komut paleti"
          >
            <Compass size={19} aria-hidden />
          </button>
        </header>

        <main className="flex-1 px-4 pb-24 pt-4 md:px-6 md:pb-10 md:pt-6">
          <div className="mx-auto w-full max-w-[1400px]">
            <Outlet />
          </div>
        </main>
      </div>

      {/* ── Telefon: tam ekran menü çekmecesi ── */}
      {mobilMenu && (
        <div className="fixed inset-0 z-50 md:hidden" role="dialog" aria-modal="true" aria-label="Menü">
          <div className="absolute inset-0 bg-g900/45" onClick={() => setMobilMenu(false)} />
          <nav className="absolute inset-y-0 left-0 flex w-[86%] max-w-[320px] flex-col overflow-y-auto bg-surface p-3 shadow-3">
            <div className="mb-2 flex items-center justify-between px-1">
              <b className="text-base font-bold text-ink">tedarikapp</b>
              <button
                type="button"
                className="flex size-9 items-center justify-center rounded-lg text-ink-3"
                onClick={() => setMobilMenu(false)}
                aria-label="Kapat"
              >
                <X size={18} aria-hidden />
              </button>
            </div>
            {menuGruplari.map((grup) => (
              <div key={grup.baslik} className="mb-2">
                <div className="px-3 py-1.5 text-[10px] font-bold tracking-[0.12em] text-ink-3">{grup.baslik}</div>
                {grup.ogeler.map((oge) =>
                  oge.hazir ? (
                    <NavLink
                      key={oge.to}
                      to={oge.to}
                      end={oge.to === '/'}
                      className={({ isActive }) =>
                        `flex min-h-11 items-center gap-3 rounded-lg px-3 text-base ${
                          isActive ? 'bg-blue-soft font-semibold text-navy' : 'text-ink-2'
                        }`
                      }
                      // Gezinme anında kapanır — efekt kurmaya gerek yok, olay burada.
                      onClick={() => setMobilMenu(false)}
                    >
                      <oge.icon size={18} aria-hidden />
                      {oge.label}
                    </NavLink>
                  ) : (
                    <span
                      key={oge.to}
                      className="flex min-h-11 items-center gap-3 rounded-lg px-3 text-base text-ink-3 opacity-70"
                      aria-disabled="true"
                    >
                      <oge.icon size={18} aria-hidden />
                      {oge.label}
                      <span className="ml-auto rounded-md bg-g100 px-1.5 py-0.5 text-[10px] font-semibold">Yakında</span>
                    </span>
                  ),
                )}
              </div>
            ))}
            <div className="mt-auto border-t border-line pt-3">
              <div className="truncate px-3 pb-2 text-sm text-ink-3">{user?.email}</div>
              <button type="button" className="btn-ghost w-full" onClick={() => void cikis()}>
                Çıkış yap
              </button>
            </div>
          </nav>
        </div>
      )}

      {/* ── Telefon alt sekme çubuğu (kanon §3: Panorama · Keşif · Listeler · Daha fazla) ── */}
      <nav className="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface pb-[env(safe-area-inset-bottom)] md:hidden">
        <div className="grid grid-cols-4">
          {[
            { to: '/', label: 'Panorama', icon: LayoutDashboard },
            { to: '/listeler', label: 'Listeler', icon: ListChecks },
            { to: '/gelen-kutusu', label: 'Gelen', icon: Inbox },
          ].map((oge) => (
            <NavLink
              key={oge.to}
              to={oge.to}
              end={oge.to === '/'}
              className={({ isActive }) =>
                `flex min-h-14 flex-col items-center justify-center gap-1 text-[11px] ${
                  isActive ? 'font-semibold text-navy' : 'text-ink-3'
                }`
              }
            >
              <oge.icon size={19} aria-hidden />
              {oge.label}
            </NavLink>
          ))}
          <button
            type="button"
            className="flex min-h-14 flex-col items-center justify-center gap-1 text-[11px] text-ink-3"
            onClick={() => setMobilMenu(true)}
          >
            <MenuIcon size={19} aria-hidden />
            Daha fazla
          </button>
        </div>
      </nav>

      {/* Palet yalnız AÇIKKEN monte edilir: her açılışta sorgu ve seçim temiz
          başlar, durumu efektle sıfırlamak gerekmez. */}
      {paletAcik && <KomutPaleti onKapat={() => setPaletAcik(false)} />}
      <Toaster />
    </div>
  );
}
