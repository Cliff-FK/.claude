# Accessibility (A11y / WCAG) Reference

> Référence du skill design-auditor, chargée à la demande depuis [SKILL.md](../SKILL.md). Contenu déplacé tel quel depuis SKILL.md.

### CATEGORY 6: Accessibility (A11y / WCAG)

- [ ] **Touch targets** — Interactive elements ≥ 44×44px (iOS) or 48×48dp (Material).
- [ ] **Focus states** — Visible focus ring on every keyboard-navigable element.
- [ ] **Alt text readiness** — Meaningful images need alt text. Decorative = `aria-hidden`.
- [ ] **Form labels** — Visible label on every input. Placeholder alone is not a label.
- [ ] **Error messages** — Text description of errors, not just red border/color change.
- [ ] **Reading order** — Visual order matches logical/DOM order for screen readers.
- [ ] **Motion sensitivity** — Animations respect `prefers-reduced-motion`.
- [ ] **Link clarity** — Links distinguishable from text by more than color alone.

**📋 Code input: deeper checks available (run these automatically)**
```
When auditing HTML/React/Vue code, check directly:

  aria-label / aria-labelledby
    → Every <button> or <a> without visible text must have aria-label
    → Icon buttons: <button aria-label="Close"><Icon /></button> ✅
    → Missing: <button><Icon /></button> → 🔴 Critical

  alt attributes on <img>
    → <img src="..." /> (no alt) → 🔴 Critical
    → <img src="..." alt="" /> (decorative, intentional empty) → ✅
    → <img src="logo.png" alt="Company logo" /> → ✅

  focus styles
    → button:focus { outline: none } or *:focus { outline: none } → 🔴 Critical
    → :focus-visible with visible outline → ✅
    → Search for outline: none / outline: 0 across all CSS

  role attributes
    → Custom interactive elements (<div onClick=...>) missing role="button" → 🟡
    → Landmark roles present: role="main", role="nav", role="complementary" → ✅

  tabIndex misuse
    → tabIndex > 0 on any element → 🟡 (breaks natural tab order)
    → tabIndex="-1" on programmatically focused elements → ✅

  input label association
    → <input id="email" /> must have <label for="email"> or aria-label → 🔴 if missing
    → Placeholder-only inputs → 🔴

  Color contrast (code path)
    → Extract foreground/background pairs from CSS, run WCAG check programmatically
    → More precise than visual estimate — cite exact ratio

  SVG accessibility:
    → Inline <svg> used decoratively (icon, illustration) without aria-hidden="true" → 🔴 Critical
      Correct: <svg aria-hidden="true" focusable="false">...</svg>
      Why: Screen readers announce SVG contents as text if not hidden, creating noise
    → Inline <svg> used as a meaningful image (logo, chart, diagram) without role="img"
      AND a <title> element → 🔴 Critical
      Correct: <svg role="img" aria-labelledby="icon-title"><title id="icon-title">Company logo</title>...</svg>
    → <svg> with focusable="true" (IE default) in an icon-only button → 🟡 Warning
      (creates double tab stop — icon receives focus separately from its button wrapper)
    → <use href="..."> or <use xlink:href="..."> referencing a <symbol> — check the <symbol> has a <title> → 🟡
```

---
