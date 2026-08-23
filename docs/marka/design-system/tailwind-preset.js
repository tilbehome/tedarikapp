/** Tedarik App - Tailwind-compatible preset. */
module.exports = {
  theme: {
    extend: {
      colors: {
        tedarik: {
          ink: "#1F2530",
          ink2: "#2D323C",
          orange: "#FF6B00",
          orangeDark: "#E85F00",
          amber: "#FFB000",
          coral: "#FF4D3D",
          surface: "#FFFDF8",
          canvas: "#FFF8F1",
          border: "#DCE5EF",
          text: "#152A44",
          muted: "#526982"
        },
        success: "#0E9F6E",
        info: "#1479C9",
        warning: "#D97706",
        danger: "#D92D20"
      },
      fontFamily: {
        sans: ["Inter", "Noto Sans", "Noto Sans SC", "system-ui", "sans-serif"]
      },
      borderRadius: {
        ta: "10px",
        "ta-lg": "14px",
        "ta-xl": "20px"
      },
      boxShadow: {
        ta: "0 1px 2px rgb(31 37 48 / 8%), 0 1px 4px rgb(31 37 48 / 5%)",
        "ta-lg": "0 8px 24px rgb(31 37 48 / 10%)"
      }
    }
  }
};
