import { describe, expect, test, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import SatirEylemMenusu, { useSatirEylemMenusu } from './SatirEylemMenusu';

/**
 * K105 §2.1 — `⋯` ve SAĞ TIK AYNI menüyü açar (tek tanım); sağ tık tarayıcı
 * menüsünü bastırır; ↑/↓ + Enter seçer; Esc kapatır; tehlikeli öğe onay sormaz.
 */
function Satir({ onAc, onSil }: { onAc: () => void; onSil: () => void }) {
  const menu = useSatirEylemMenusu();
  return (
    <table>
      <tbody>
        <tr data-testid="satir" onContextMenu={menu.sagTik}>
          <td>Termos</td>
          <td>
            <SatirEylemMenusu
              menu={menu}
              etiket="Eylemler: Termos"
              ogeler={[
                { etiket: 'Aç', onClick: onAc },
                { etiket: 'Çöpe at', onClick: onSil, tehlikeli: true, ayrac: true },
              ]}
            />
          </td>
        </tr>
      </tbody>
    </table>
  );
}

describe('SatirEylemMenusu', () => {
  test('⋯ düğmesi ve sağ tık aynı menüyü açar; sağ tık varsayılan menüyü bastırır', async () => {
    const kullanici = userEvent.setup();
    const onAc = vi.fn();
    const onSil = vi.fn();
    render(<Satir onAc={onAc} onSil={onSil} />);

    await kullanici.click(screen.getByRole('button', { name: 'Eylemler: Termos' }));
    expect(screen.getByRole('menu', { name: 'Eylemler: Termos' })).toBeInTheDocument();
    await kullanici.click(screen.getByRole('menuitem', { name: 'Aç' }));
    expect(onAc).toHaveBeenCalledTimes(1);
    expect(screen.queryByRole('menu')).not.toBeInTheDocument();

    const olay = new MouseEvent('contextmenu', { bubbles: true, cancelable: true, clientX: 40, clientY: 50 });
    screen.getByTestId('satir').dispatchEvent(olay);
    expect(olay.defaultPrevented).toBe(true);
    expect(await screen.findByRole('menu', { name: 'Eylemler: Termos' })).toBeInTheDocument();
    await kullanici.click(screen.getByRole('menuitem', { name: 'Çöpe at' }));
    expect(onSil).toHaveBeenCalledTimes(1);
  });

  test('klavye: ↓ ile ilerler, Enter seçer, Esc kapatır', async () => {
    const kullanici = userEvent.setup();
    const onAc = vi.fn();
    const onSil = vi.fn();
    render(<Satir onAc={onAc} onSil={onSil} />);

    await kullanici.click(screen.getByRole('button', { name: 'Eylemler: Termos' }));
    await kullanici.keyboard('{ArrowDown}{Enter}');
    expect(onSil).toHaveBeenCalledTimes(1);
    expect(onAc).not.toHaveBeenCalled();

    await kullanici.click(screen.getByRole('button', { name: 'Eylemler: Termos' }));
    await kullanici.keyboard('{Escape}');
    expect(screen.queryByRole('menu')).not.toBeInTheDocument();
  });
});
