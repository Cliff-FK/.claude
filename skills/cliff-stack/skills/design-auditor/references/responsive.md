# Responsive & Adaptive Reference

> Référence du skill design-auditor, chargée à la demande depuis [SKILL.md](../SKILL.md). Contenu déplacé tel quel depuis SKILL.md.

### CATEGORY 10: Responsive & Adaptive

- [ ] **Breakpoints** — Mobile (320–480px), tablet (768px), desktop (1024px+) considered.
- [ ] **No overflow** — Long words or fixed-width containers don't break on small screens.
- [ ] **Mobile touch targets** — Bigger targets and more spacing than desktop.
- [ ] **Image scaling** — Images scale without awkward cropping or overflow.
- [ ] **Type scaling** — Large desktop headings (48px) scaled down to 28–32px on mobile.

**📋 Code input: direct checks available (run these automatically)**
```
Breakpoint coverage:
  → Collect all @media queries in CSS/styled-components/Tailwind
  → If only one breakpoint found (or none) → 🟡 Warning
  → If no mobile-first breakpoints (min-width) → 🟡 (desktop-first with max-width is harder to maintain)
  → Tailwind: check for sm:, md:, lg:, xl: prefix usage — missing sm: on any layout element → 🟡
  → Flag the specific elements that have no responsive variant

Fixed-width traps:
  → width: [value]px on containers (not icons or images) → 🟡 (use max-width or %)
  → Fixed pixel widths > 480px with no responsive override → 🔴 (will overflow on mobile)
  → min-width values that exceed mobile viewport (320px) → 🟡

Overflow risks:
  → overflow: hidden on a container without a max-width → 🟡 (clips content on small screens)
  → Long unbreakable strings: no word-break or overflow-wrap rule on text containers → 🟡
  → white-space: nowrap on text that could be long → 🟡

Image responsiveness:
  → <img> without max-width: 100% or w-full → 🟡 (overflows container on small screens)
  → <img> with fixed width/height attributes and no CSS override → 🟡
  → Missing srcset or sizes attributes on large hero images → 🟢 Tip (performance)
  → object-fit missing on images inside fixed-height containers → 🟡

Viewport meta tag (HTML only):
  → Missing <meta name="viewport" content="width=device-width, initial-scale=1"> → 🔴 Critical
    (without this, mobile browsers render at desktop width)

Font size on mobile:
  → body font-size < 16px with no responsive override → 🟡
    (iOS Safari auto-zooms on inputs with font-size < 16px)
  → Input font-size < 16px → 🟡 (triggers zoom on focus on iOS)

Tailwind responsive audit:
  → Elements using fixed Tailwind width classes (w-96, w-80) without sm:/md: override → 🟡
  → Text size classes without responsive scaling (text-5xl with no sm:text-3xl) → 🟡
  → hidden / flex / block classes without responsive context → note if suspicious

Viewport units:
  → height: 100vh on mobile without dvh fallback → 🟡
    (100vh includes browser chrome on mobile, causing content to be hidden)
  → Correct: height: 100dvh (dynamic viewport height) or min-height: 100svh
```

---
