/**
 * PLAYWRIGHT KÖPRÜSÜ — arayüz katmanını sayfaya taşır.
 *
 * Senaryolar 1688'e ÇIKMAZ (dış ağ yok): sınanan şey bizim çizimimizin gerçek
 * tarayıcıdaki DÜZEN davranışıdır. Bu dosya yalnız test kurulumudur; eklenti
 * bundle'ına girmez (`extension/entrypoints` dışındadır).
 */
import { cekmeceKur } from '../extension/ui/v2/cekmece';
import { montajNobeti, montajYap } from '../extension/ui/v2/montaj';

(window as unknown as { TDK: unknown }).TDK = { cekmeceKur, montajYap, montajNobeti };
