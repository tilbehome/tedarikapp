import { useEffect } from 'react';
import { Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom';
import { onUnauthorized } from './api/client';
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

  if (!checked) {
    return <Spinner label="Oturum kontrol ediliyor…" />;
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
