import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './App';
import './index.css';

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
