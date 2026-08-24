import { describe, expect, test, vi } from 'vitest';
import { renderHook } from '@testing-library/react';
import { gorunurSecim, useSuzgecSecimi } from './secim';

/**
 * SEÇİM KURALLARI (E2E-PNL-23).
 *
 * Söz: kullanıcı hangi süzgeçte seçtiyse o süzgeçte işlem yapar. Süzgeç değişti
 * mi seçim düşer; veri tazelendi mi hayalet kimlik kalmaz.
 */

describe('useSuzgecSecimi', () => {
  test('imza aynı kaldıkça seçim korunur', () => {
    const temizle = vi.fn();
    const { rerender } = renderHook(({ imza }) => useSuzgecSecimi(imza, 3, temizle), {
      initialProps: { imza: 'a|b|' },
    });

    rerender({ imza: 'a|b|' });

    expect(temizle).not.toHaveBeenCalled();
  });

  test('imza değişince seçim TEMİZLENİR', () => {
    const temizle = vi.fn();
    const { rerender } = renderHook(({ imza }) => useSuzgecSecimi(imza, 3, temizle), {
      initialProps: { imza: 'a|b|' },
    });

    rerender({ imza: 'a|received|' });

    expect(temizle).toHaveBeenCalledTimes(1);
  });

  test('seçim boşsa gereksiz temizleme çağrılmaz', () => {
    const temizle = vi.fn();
    const { rerender } = renderHook(({ imza }) => useSuzgecSecimi(imza, 0, temizle), {
      initialProps: { imza: 'a||' },
    });

    rerender({ imza: 'b||' });

    expect(temizle).not.toHaveBeenCalled();
  });

  test('uyarı çipi süzgeci de imzanın parçasıdır', () => {
    const temizle = vi.fn();
    const { rerender } = renderHook(({ imza }) => useSuzgecSecimi(imza, 2, temizle), {
      initialProps: { imza: '||' },
    });

    rerender({ imza: '||category_id' });

    expect(temizle).toHaveBeenCalledTimes(1);
  });
});

describe('gorunurSecim', () => {
  test('görünmeyen kimlikler seçimden düşer', () => {
    expect(gorunurSecim([1, 2, 3], [2, 3, 9])).toEqual([2, 3]);
  });

  test('hiçbiri görünmüyorsa seçim boşalır', () => {
    expect(gorunurSecim([1, 2], [7])).toEqual([]);
  });

  test('sıra korunur — kullanıcı seçim sırasını görür', () => {
    expect(gorunurSecim([5, 1, 3], [1, 3, 5])).toEqual([5, 1, 3]);
  });
});
