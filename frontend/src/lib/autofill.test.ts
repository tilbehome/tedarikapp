import { describe, expect, test } from 'vitest';
import { epostaGibiMi, otomatikDoldurmaKapali } from './autofill';

/**
 * D3 — tarayıcı otomatik doldurma kalkanı.
 *
 * Saha vakası: Chrome "Model" kutusuna kayıtlı e-postayı bastı, DeepSeek 400 döndü.
 * Testler kalkanın İKİ yarısını da tutar: alan adının tanınmaz hâle gelmesi ve
 * kaydetmeden önceki akıl kontrolü.
 */
describe('otomatikDoldurmaKapali', () => {
  test('alan adını tanınmaz hâle getirir — sezgi ada bakar', () => {
    const props = otomatikDoldurmaKapali('llm-model');

    expect(props.name).not.toBe('llm-model');
    expect(props.name.startsWith('llm-model-')).toBe(true);
  });

  test('aynı ad her çağrıda AYNI kalır — değişse React alanı yeniden kurar, imleç kaybolur', () => {
    expect(otomatikDoldurmaKapali('model').name).toBe(otomatikDoldurmaKapali('model').name);
  });

  test('parola yöneticisi kaçışlarını ve standart ipuçlarını taşır', () => {
    const props = otomatikDoldurmaKapali('anahtar');

    expect(props.autoComplete).toBe('off');
    expect(props.spellCheck).toBe(false);
    expect(props['data-lpignore']).toBe('true');
    expect(props['data-form-type']).toBe('other');
  });
});

describe('epostaGibiMi', () => {
  test('saha vakasını yakalar', () => {
    expect(epostaGibiMi('tilbehome@gmail.com')).toBe(true);
  });

  test('gerçek model adlarını yanlış alarma çevirmez', () => {
    for (const model of ['deepseek-v4-flash', 'claude-sonnet-4-6', 'gpt-5.6-terra', '']) {
      expect(epostaGibiMi(model)).toBe(false);
    }
  });

  test('boşluklu değerde de "@" görür', () => {
    expect(epostaGibiMi('  a@b.co  ')).toBe(true);
  });
});
