# Dark Mode Reference

> Référence du skill design-auditor, chargée à la demande depuis [SKILL.md](../SKILL.md). Contenu déplacé tel quel depuis SKILL.md.

### CATEGORY 9: Dark Mode (if applicable)

- [ ] **Not just inverted** — Dark mode requires redesigned colors, not flipped ones.
- [ ] **Background depth** — Lighter dark grays for elevated surfaces (cards, modals). Not pure black.
- [ ] **Saturation** — Reduce vivid brand colors in dark mode — they look garish on dark.
- [ ] **Shadow replacement** — Use lighter surface colors for elevation instead of shadows.
- [ ] **Icon & image legibility** — Icons/images still readable on dark backgrounds.

**📋 Code input: direct checks available (run these automatically)**
```
Detect if dark mode is implemented:
  → Search for @media (prefers-color-scheme: dark) in CSS
  → Search for [data-theme="dark"] or .dark selector patterns
  → Search for dark: utility prefix (Tailwind dark mode)
  → If any: audit dark mode implementation. If none: note as 🟢 Tip if product likely needs it.

If dark mode is found, check:

  Color swap pattern:
    → ✅ Good: CSS custom properties swapped in dark media query
        :root { --bg: #ffffff; --text: #111111; }
        @media (prefers-color-scheme: dark) { :root { --bg: #1a1a1a; --text: #f0f0f0; } }
    → 🔴 Bad: Separate hardcoded hex values in dark selectors (token system is broken)
        .dark .card { background: #1c1c1c; color: #ffffff; } ← hardcoded

  Pure black backgrounds:
    → background: #000000 or bg-black in dark mode → 🟡 Warning
    → Prefer #0f0f0f–#1e1e1e range for depth

  Contrast in dark mode:
    → Run WCAG check on dark mode color pairs too (not just light mode)
    → Dark mode often passes light mode checks but fails its own

  Tailwind dark mode:
    → Check if darkMode: 'class' or 'media' is configured
    → Inconsistent dark: prefix usage across components → 🟡

  color-scheme property:
    → <meta name="color-scheme" content="dark light"> or CSS `color-scheme: dark light`
      absent when dark mode IS implemented → 🟢 Tip
      (without this, browser chrome — scrollbars, form inputs — stays light even in dark mode)
    → color-scheme: dark (only) when the product has a light mode toggle → 🟡 Warning
      (locks browser chrome to dark regardless of user preference)
```

---
