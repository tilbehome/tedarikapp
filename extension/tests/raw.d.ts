/**
 * `?raw` içe aktarımı için tip bildirimi.
 *
 * Store beyanı çapraz kontrolü (A8) belgeyi METİN olarak okur. Node tipleri
 * (`@types/node`) eklemek yerine Vite'ın `?raw` yeteneği kullanılır: eklenti
 * paketine yeni bir bağımlılık girmez (K19).
 */
declare module '*?raw' {
  const icerik: string;
  export default icerik;
}
