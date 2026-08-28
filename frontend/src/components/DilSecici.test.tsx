import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import DilSecici, { dildekiMetin, dilSecenekleri } from './DilSecici';

/**
 * İE#20 C5 — dil seçici (ZH · TR · EN).
 *
 * Sınanan kararlar: üç dil KOŞULSUZ görünür (Ürün Sahibi kararı), metni olmayan
 * dil GİZLENMEZ ama işaretlenir, ZH bir çeviri değil KAYNAKTIR.
 */
describe('dil seçici', () => {
  const urun = {
    name: 'Çift Cidarlı Termos',
    name_original: '双层保温杯',
    ceviriler: { en: 'Double-wall thermos' },
  };

  it('UC DILI DE gosterir', () => {
    render(<DilSecici secili="tr" secenekler={dilSecenekleri(urun)} onSec={() => {}} />);

    expect(screen.getByRole('button', { name: /ZH/ })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /^TR/ })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /EN/ })).toBeInTheDocument();
  });

  it('EN cevirisi YOKSA secenek GIZLENMEZ, isaretlenir', () => {
    const secenekler = dilSecenekleri({ ...urun, ceviriler: {} });
    render(<DilSecici secili="tr" secenekler={secenekler} onSec={() => {}} />);

    const en = screen.getByRole('button', { name: /EN/ });
    expect(en).toBeInTheDocument();
    expect(en).toHaveTextContent('—');
    expect(en).toHaveAttribute('title', expect.stringContaining('henüz üretilmedi'));
  });

  it('secili dil aria-pressed tasir', () => {
    render(<DilSecici secili="zh" secenekler={dilSecenekleri(urun)} onSec={() => {}} />);

    expect(screen.getByRole('button', { name: /ZH/ })).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByRole('button', { name: /^TR/ })).toHaveAttribute('aria-pressed', 'false');
  });

  it('tiklayinca secilen dili bildirir', async () => {
    const kullanici = userEvent.setup();
    const onSec = vi.fn();
    render(<DilSecici secili="tr" secenekler={dilSecenekleri(urun)} onSec={onSec} />);

    await kullanici.click(screen.getByRole('button', { name: /EN/ }));

    expect(onSec).toHaveBeenCalledWith('en');
  });

  it('ZH KAYNAKTIR: orijinal basligi doner', () => {
    expect(dildekiMetin('zh', urun)).toBe('双层保温杯');
    expect(dildekiMetin('tr', urun)).toBe('Çift Cidarlı Termos');
    expect(dildekiMetin('en', urun)).toBe('Double-wall thermos');
  });

  it('metin yoksa NULL doner (uydurma yok)', () => {
    expect(dildekiMetin('en', { name: 'A', name_original: 'B', ceviriler: {} })).toBeNull();
    expect(dildekiMetin('zh', { name: 'A', name_original: '   ' })).toBeNull();
  });
});
