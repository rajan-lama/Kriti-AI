/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: "class",

  content: ["./**/*.php", "./**/*.js", "./**/*.jsx", "./**/*.ts", "./**/*.tsx"],

  theme: {
    extend: {
      colors: {
        "kriti-ai-surface-container": "#e7eeff",
        "kriti-ai-tertiary": "#904900",
        "kriti-ai-on-tertiary-container": "#fffbff",
        "kriti-ai-on-secondary-fixed-variant": "#0032c4",
        "kriti-ai-inverse-on-surface": "#ecf1ff",
        "kriti-ai-surface-container-low": "#f0f3ff",
        "kriti-ai-primary": "#4648d4",
        "kriti-ai-tertiary-fixed": "#ffdcc5",
        "kriti-ai-secondary": "#284bdd",
        "kriti-ai-on-primary-fixed": "#07006c",
        "kriti-ai-surface-bright": "#f9f9ff",
        "kriti-ai-surface-variant": "#d8e3fb",
        "kriti-ai-surface-container-highest": "#d8e3fb",
        "kriti-ai-background": "#F8FAFC",
        "kriti-ai-secondary-fixed": "#dee1ff",
        "kriti-ai-surface-container-high": "#dee8ff",
        "kriti-ai-inverse-surface": "#263143",
        "kriti-ai-on-background": "#111c2d",
        "kriti-ai-primary-fixed": "#e1e0ff",
        "kriti-ai-on-secondary-fixed": "#001159",
        "kriti-ai-wp-admin-dark": "#1E1E1E",
        "kriti-ai-on-primary-container": "#fffbff",
        "kriti-ai-on-secondary": "#ffffff",
        "kriti-ai-indigo-wash": "#EEF2FF",
        "kriti-ai-on-primary": "#ffffff",
        "kriti-ai-secondary-fixed-dim": "#bac3ff",
        "kriti-ai-tertiary-fixed-dim": "#ffb783",
        "kriti-ai-slate-gray": "#64748B",
        "kriti-ai-on-surface": "#111c2d",
        "kriti-ai-inverse-primary": "#c0c1ff",
        "kriti-ai-on-tertiary-fixed-variant": "#703700",
        "kriti-ai-outline-variant": "#c7c4d7",
        "kriti-ai-on-secondary-container": "#fffbff",
        "kriti-ai-surface": "#FFFFFF",
        "kriti-ai-primary-container": "#6063ee",
        "kriti-ai-error": "#ba1a1a",
        "kriti-ai-primary-fixed-dim": "#c0c1ff",
        "kriti-ai-surface-tint": "#494bd6",
        "kriti-ai-outline": "#767586",
        "kriti-ai-tertiary-container": "#b55d00",
        "kriti-ai-on-tertiary-fixed": "#301400",
        "kriti-ai-surface-container-lowest": "#ffffff",
        "kriti-ai-on-surface-variant": "#464554",
        "kriti-ai-on-error-container": "#93000a",
        "kriti-ai-on-primary-fixed-variant": "#2f2ebe",
        "kriti-ai-error-container": "#ffdad6",
        "kriti-ai-secondary-container": "#4866f7",
        "kriti-ai-on-error": "#ffffff",
        "kriti-ai-surface-dim": "#cfdaf2",
        "kriti-ai-on-tertiary": "#ffffff",
      },

      borderRadius: {
        DEFAULT: "0.25rem",
        lg: "0.5rem",
        xl: "0.75rem",
        full: "9999px",
      },

      spacing: {
        "kriti-ai-stack-sm": "8px",
        "kriti-ai-margin-mobile": "16px",
        "kriti-ai-gutter": "24px",
        "kriti-ai-margin-page": "32px",
        "kriti-ai-stack-md": "16px",
        "kriti-ai-stack-lg": "32px",
        "kriti-ai-unit": "4px",
      },

      fontFamily: {
        "kriti-ai-label-md": ["Inter"],
        "kriti-ai-body-md": ["Inter"],
        "kriti-ai-body-lg": ["Inter"],
        "kriti-ai-display-lg": ["Inter"],
        "kriti-ai-headline-lg-mobile": ["Inter"],
        "kriti-ai-title-md": ["Inter"],
        "kriti-ai-headline-md": ["Inter"],
        "kriti-ai-headline-lg": ["Inter"],
        "kriti-ai-code": ["JetBrains Mono"],
      },

      fontSize: {
        "kriti-ai-label-md": [
          "12px",
          {
            lineHeight: "16px",
            letterSpacing: "0.05em",
            fontWeight: "600",
          },
        ],

        "kriti-ai-body-md": [
          "14px",
          {
            lineHeight: "20px",
            fontWeight: "400",
          },
        ],

        "kriti-ai-body-lg": [
          "16px",
          {
            lineHeight: "24px",
            fontWeight: "400",
          },
        ],

        "kriti-ai-display-lg": [
          "48px",
          {
            lineHeight: "56px",
            letterSpacing: "-0.02em",
            fontWeight: "700",
          },
        ],

        "kriti-ai-headline-lg-mobile": [
          "24px",
          {
            lineHeight: "32px",
            fontWeight: "600",
          },
        ],

        "kriti-ai-title-md": [
          "18px",
          {
            lineHeight: "28px",
            fontWeight: "500",
          },
        ],

        "kriti-ai-headline-md": [
          "24px",
          {
            lineHeight: "32px",
            fontWeight: "600",
          },
        ],

        "kriti-ai-headline-lg": [
          "32px",
          {
            lineHeight: "40px",
            letterSpacing: "-0.01em",
            fontWeight: "600",
          },
        ],

        "kriti-ai-code": [
          "13px",
          {
            lineHeight: "20px",
            fontWeight: "400",
          },
        ],
      },
    },
  },

  plugins: [
    require("@tailwindcss/forms"),
    require("@tailwindcss/container-queries"),
  ],
};
