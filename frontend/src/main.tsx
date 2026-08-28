import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './App';
import { temayiBaslat } from './lib/tema';
import { serviceWorkeriKaydet } from './lib/swKayit';
import './index.css';

// Tema İLK BOYAMADAN ÖNCE uygulanır: kayıtlı tercih koyuysa açık temanın bir
// kare boyunca görünüp kararması ("beyaz flaş") engellenir (İE#16 D1.1/D1.4).
temayiBaslat();
// V3-B E2: PWA kabuğu — yalnız üretimde, yalnız /panel/ kapsamında.
serviceWorkeriKaydet();

const container = document.getElementById('root');
if (!container) {
  throw new Error('#root bulunamadı.');
}

createRoot(container).render(
  <StrictMode>
    {/* Panel /panel/ altında sunulur; Slim tüm /panel* isteklerini index.html'e verir. */}
    <BrowserRouter basename="/panel">
      <App />
    </BrowserRouter>
  </StrictMode>,
);
