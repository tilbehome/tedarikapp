import { useEffect, useState } from 'react';
import { Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom';
import { onMigrationPending, onUnauthorized } from './api/client';
import { system as systemApi } from './api/endpoints';
import { messageOf } from './lib/useAsync';
import { useSession } from './store/session';
import { useReference } from './store/reference';
import Layout from './components/Layout';
import { Spinner } from './components/ui';
import LoginScreen from './screens/LoginScreen';
import HomeScreen from './screens/HomeScreen';
import ListsScreen from './screens/ListsScreen';
import ListDetailScreen from './screens/ListDetailScreen';
import ProductFormScreen from './screens/ProductFormScreen';
import SettingsScreen from './screens/SettingsScreen';
import CategoriesScreen from './screens/CategoriesScreen';
import TrashScreen from './screens/TrashScreen';
import ActivityScreen from './screens/ActivityScreen';

/**
 * Rota haritası ve oturum koruması.
 *
 * Açılışta bir kez `GET /api/auth/me` sorulur; sonuç gelene kadar hiçbir ekran
 * çizilmez ki oturumu açık kullanıcı bir an giriş formunu görmesin.
 */
export default function App() {
  const { stage, checked, check } = useSession();
  const loadReference = useReference((state) => state.load);
  const referenceLoaded = useReference((state) => state.loaded);
  const navigate = useNavigate();
  const location = useLocation();

  useEffect(() => {
    void check();
  }, [check]);

  // API istemcisi 401 gördüğünde oturumu düşürüp girişe yollar (İE#8 §1).
  useEffect(
    () =>
      onUnauthorized(() => {
        useSession.getState().drop();
        useReference.getState().reset();
        navigate('/giris', { replace: true });
      }),
    [navigate],
  );

  useEffect(() => {
    if (stage === 'authenticated' && !referenceLoaded) {
      void loadReference();
    }
  }, [stage, referenceLoaded, loadReference]);

  // İE#10.5 Blok 2: veri uçları 503 MIGRATION_PENDING dönerse tam sayfa
  // "Güncelleme tamamlanmalı" ekranı — hata kartları yerine tek yönlendirme.
  const [migrationPending, setMigrationPending] = useState(false);
  useEffect(() => onMigrationPending(() => setMigrationPending(true)), []);

  if (!checked) {
    return <Spinner label="Oturum kontrol ediliyor…" />;
  }

  if (migrationPending) {
    return <MigrationPendingScreen onDone={() => setMigrationPending(false)} />;
  }

  if (stage !== 'authenticated') {
    return (
      <Routes>
        <Route path="/giris" element={<LoginScreen />} />
        {/* Nereye gitmek istediyse girişten sonra oraya dönebilsin. */}
        <Route path="*" element={<Navigate to="/giris" replace state={{ from: location.pathname }} />} />
      </Routes>
    );
  }

  return (
    <Routes>
      <Route element={<Layout />}>
        <Route path="/" element={<HomeScreen />} />
        <Route path="/listeler" element={<ListsScreen />} />
        <Route path="/listeler/:id" element={<ListDetailScreen />} />
        <Route path="/listeler/:id/urun/yeni" element={<ProductFormScreen />} />
        <Route path="/listeler/:id/urun/:productId" element={<ProductFormScreen />} />
        <Route path="/ayarlar" element={<SettingsScreen />} />
        <Route path="/ayarlar/kategoriler" element={<CategoriesScreen />} />
        <Route path="/cop-kutusu" element={<TrashScreen />} />
        <Route path="/aktivite" element={<ActivityScreen />} />
        <Route path="/giris" element={<Navigate to="/" replace />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Route>
    </Routes>
  );
}

/**
 * İE#10.5 Blok 2 — "Güncelleme tamamlanmalı" ekranı: bekleyen migration varken
 * veri uçları 503 döner; kullanıcı buradan migrate + defter eşitlemeyi koşar.
 */
function MigrationPendingScreen({ onDone }: { onDone: () => void }) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const run = async () => {
    setBusy(true);
    setError(null);
    try {
      // Önce defter eşitlenir (K49 — tablolar var ama defter geride olabilir),
      // sonra kalan gerçek migration'lar koşulur.
      await systemApi.migrateBaseline();
      await systemApi.migrate();
      onDone();
      window.location.reload();
    } catch (caught) {
      setError(messageOf(caught));
      setBusy(false);
    }
  };

  return (
    <main className="flex min-h-screen items-center justify-center bg-slate-50 p-6">
      <div className="card max-w-md p-6 text-center">
        <h1 className="text-lg font-semibold">Güncelleme tamamlanmalı</h1>
        <p className="mt-2 text-sm text-slate-600">
          Yeni sürüm veritabanı güncellemesi bekliyor. Veri ekranları, güncelleme
          tamamlanana kadar güvenlik için kapalı tutulur — bu, verinizi yarım şemayla
          çalışmaktan korur.
        </p>
        {error ? <p className="mt-3 text-sm text-red-600">{error}</p> : null}
        <button type="button" className="btn-primary mt-4" disabled={busy} onClick={() => void run()}>
          {busy ? 'Güncelleniyor…' : 'Güncellemeyi tamamla'}
        </button>
      </div>
    </main>
  );
}
