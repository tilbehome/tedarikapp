import '@testing-library/jest-dom/vitest';
import { afterEach } from 'vitest';
import { cleanup } from '@testing-library/react';

/**
 * Test ortamının ortak zemini (İE#20 C11).
 *
 * `cleanup` her testten sonra DOM'u boşaltır: aksi hâlde bir testin bıraktığı
 * düğme, sonraki testin `getByRole` sorgusunu ikircikli yapar ve testler
 * sıraya bağlı hâle gelir — yani "yeşil" bir süit yanlış şeyi kanıtlar.
 */
afterEach(() => {
  cleanup();
});
