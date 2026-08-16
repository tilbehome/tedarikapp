import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { Activity, Home, Inbox, ListChecks, LogOut, Settings, Trash2 } from 'lucide-react';
import type { ComponentType } from 'react';
import { useSession } from '../store/session';
import { useReference } from '../store/reference';
import { Toaster } from './Toast';

/**
 * Uygulama kabuğu (docs/09 §3): telefonda alt gezinme çubuğu, masaüstünde yan menü.
 * Alt çubuk başparmak bölgesindedir; tüm hedefler ≥44px.
 */

interface NavItem {
  to: string;
  label: string;
  icon: ComponentType<{ className?: string }>;
  /** Faz 3'e kadar menüde "yakında" olarak durur (İE#8 Kapsam Dışı). */
  soon?: boolean;
}

const primary: NavItem[] = [
  { to: '/', label: 'Ana Ekran', icon: Home },
  { to: '/listeler', label: 'Listeler', icon: ListChecks },
  { to: '/gelen-kutusu', label: 'Gelen Kutusu', icon: Inbox, soon: true },
  { to: '/ayarlar', label: 'Ayarlar', icon: Settings },
];

const secondary: NavItem[] = [
  { to: '/cop-kutusu', label: 'Çöp Kutusu', icon: Trash2 },
  { to: '/aktivite', label: 'Aktivite', icon: Activity },
];

function itemClass(active: boolean): string {
  return `flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition-colors ${
    active ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-100'
  }`;
}

export default function Layout() {
  const user = useSession((state) => state.user);
  const logout = useSession((state) => state.logout);
  const resetReference = useReference((state) => state.reset);
  const navigate = useNavigate();

  const signOut = async () => {
    await logout();
    resetReference();
    navigate('/giris', { replace: true });
  };

  return (
    <div className="min-h-dvh md:flex">
      {/* Masaüstü yan menü */}
      <aside className="hidden w-64 shrink-0 border-r border-slate-200 bg-white p-4 md:flex md:flex-col">
        <div className="mb-6 px-2">
          <div className="text-lg font-bold tracking-tight">tedarikapp</div>
          <div className="text-xs text-slate-500">Tilbe Home tedarik yönetimi</div>
        </div>

        <nav className="flex flex-1 flex-col gap-1">
          {[...primary, ...secondary].map((item) =>
            item.soon ? (
              <span key={item.to} className={`${itemClass(false)} cursor-not-allowed opacity-60`} aria-disabled>
                <item.icon className="h-5 w-5" aria-hidden />
                <span className="flex-1">{item.label}</span>
                <span className="badge bg-slate-100 text-slate-500 ring-slate-200">Yakında</span>
              </span>
            ) : (
              <NavLink key={item.to} to={item.to} end={item.to === '/'} className={({ isActive }) => itemClass(isActive)}>
                <item.icon className="h-5 w-5" aria-hidden />
                {item.label}
              </NavLink>
            ),
          )}
        </nav>

        <div className="mt-4 border-t border-slate-100 pt-4">
          <div className="truncate px-3 text-xs text-slate-500">{user?.email}</div>
          <button type="button" className={`${itemClass(false)} w-full`} onClick={() => void signOut()}>
            <LogOut className="h-5 w-5" aria-hidden />
            Çıkış Yap
          </button>
        </div>
      </aside>

      {/* Telefon üst başlığı */}
      <header className="sticky top-0 z-30 flex items-center justify-between border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur md:hidden">
        <span className="text-base font-bold tracking-tight">tedarikapp</span>
        <button
          type="button"
          className="inline-flex h-11 w-11 items-center justify-center rounded-xl text-slate-600"
          aria-label="Çıkış yap"
          onClick={() => void signOut()}
        >
          <LogOut className="h-5 w-5" aria-hidden />
        </button>
      </header>

      {/* Alt çubuk yüksekliği kadar boşluk bırakılır ki son satır gizlenmesin. */}
      <main className="flex-1 px-4 pb-28 pt-4 md:px-8 md:pb-10 md:pt-8">
        <div className="mx-auto w-full max-w-6xl">
          <Outlet />
        </div>
      </main>

      {/* Telefon alt gezinme çubuğu */}
      <nav className="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden">
        <div className="grid grid-cols-4">
          {primary.map((item) =>
            item.soon ? (
              <span
                key={item.to}
                className="flex min-h-14 flex-col items-center justify-center gap-1 text-[11px] text-slate-300"
                aria-disabled
              >
                <item.icon className="h-5 w-5" aria-hidden />
                {item.label}
              </span>
            ) : (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.to === '/'}
                className={({ isActive }) =>
                  `flex min-h-14 flex-col items-center justify-center gap-1 text-[11px] ${
                    isActive ? 'text-brand-700' : 'text-slate-500'
                  }`
                }
              >
                <item.icon className="h-5 w-5" aria-hidden />
                {item.label}
              </NavLink>
            ),
          )}
        </div>
      </nav>

      <Toaster />
    </div>
  );
}
