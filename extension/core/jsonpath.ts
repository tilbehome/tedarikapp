/**
 * Nokta-yollu erişim + fastjson $ref çözücü (docs/arastirma/1688-parser-raporu §A.0).
 *
 * 1688'in gömülü JSON'u fastjson ile üretilir ve tekrarlanan düğümler
 * `{"$ref": "$.result.data...."}` göstergeleriyle gelir — çözülmezse fiyat/kargo
 * alanları obje yerine gösterge okur. Saf fonksiyonlar: DOM/ağ yok, test edilebilir.
 */

export function getPath(root: unknown, path: string): unknown {
  const parts = path.replace(/\[(\d+)\]/g, '.$1').split('.').filter(Boolean);
  let current: unknown = root;
  for (const part of parts) {
    if (current === null || typeof current !== 'object') return undefined;
    current = (current as Record<string, unknown>)[part];
  }
  return current;
}

/** Verilen yollardan İLK tanımlı değeri döndürür (katmanlı fallback — K53 seçici verisi). */
export function firstPath(root: unknown, paths: string[]): unknown {
  for (const path of paths) {
    const value = getPath(root, path);
    if (value !== undefined && value !== null) return value;
  }
  return undefined;
}

/** $ref göstergelerini kök üzerinden yerinde çözer (döngü korumalı, en çok 2 geçiş). */
export function resolveRefs<T>(root: T): T {
  const walk = (node: unknown, depth: number): unknown => {
    if (depth > 12 || node === null || typeof node !== 'object') return node;
    if (Array.isArray(node)) return node.map((item) => walk(item, depth + 1));

    const record = node as Record<string, unknown>;
    const ref = record.$ref;
    if (typeof ref === 'string' && ref.startsWith('$')) {
      const resolved = getPath(root, ref.replace(/^\$\.?/, ''));
      // Çözülemeyen gösterge olduğu gibi bırakılır — veri kaybı yerine ham hâl (RAW ilkesi).
      return resolved === undefined ? node : resolved;
    }
    const out: Record<string, unknown> = {};
    for (const [key, value] of Object.entries(record)) {
      out[key] = walk(value, depth + 1);
    }
    return out;
  };

  return walk(walk(root, 0), 0) as T;
}

/** HTML kaynağından window.context JSON'unu çıkarır (ISOLATED world / fixture yolu). */
export function extractContext(html: string, regexSource: string): unknown {
  const match = new RegExp(regexSource).exec(html);
  if (!match?.[1]) return undefined;
  try {
    return JSON.parse(match[1]) as unknown;
  } catch {
    return undefined;
  }
}
