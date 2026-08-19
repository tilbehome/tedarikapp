// K45: /public/... altinda statik acilan sayfa kendini dogru adrese tasir.
// Harici dosya: CSP default-src 'self' ile uyumlu (IE#10.5 PM rotusu).
if (location.pathname.indexOf('/public/') === 0) {
  location.replace(location.pathname.slice('/public'.length) + location.search + location.hash);
}
