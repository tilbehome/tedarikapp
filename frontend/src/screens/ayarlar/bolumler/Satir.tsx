/**
 * Etiket-değer satırı — Ayarlar sekmelerinin ortak parçası (V3-B C1).
 *
 * Eskiden SettingsScreen içinde `Line` adıyla duruyordu; sekmelere bölünürken
 * üç ayrı dosya aynı satırı çizecekti. Tek yerde durması, hizalamanın da tek
 * yerden değişmesini sağlar.
 */
export default function Satir({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-start justify-between gap-4">
      <dt className="text-ink-3">{label}</dt>
      <dd className="text-right font-medium">{value}</dd>
    </div>
  );
}
