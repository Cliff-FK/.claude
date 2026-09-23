# Visual Hierarchy & Focus Reference

> Référence du skill design-auditor, chargée à la demande depuis [SKILL.md](../SKILL.md). Contenu déplacé tel quel depuis SKILL.md.

### CATEGORY 4: Visual Hierarchy & Focus

- [ ] **One primary action per screen** — One thing should be obviously most important.
- [ ] **Reading patterns** — Users scan in F or Z patterns. Key info along those paths.
- [ ] **Size = importance** — Bigger = more important. Check it maps correctly.
- [ ] **Contrast = importance** — High contrast = foreground. Check it maps correctly.

**📋 Code input: direct checks available (run these automatically)**
```
Font-size prominence mapping:
  → Collect all font-size values across the file (same as Cat 1 extraction)
  → Map: largest = most prominent, most frequent = body, smallest = least prominent
  → If the largest font-size element is not the primary CTA or heading → 🟡 Warning
    (visual hierarchy may be inverted — the biggest thing should matter most)
  → If body copy and a CTA/button share the same font-size → 🟡 Warning
    (no size signal to distinguish the action from surrounding text)

z-index stacking ladder:
  → Collect all z-index values across the file
  → Map relative stacking: base content (0–1) < sticky headers (10–20) < dropdowns (100–200)
    < modals (300–400) < tooltips (500+) < dev overlays (9999)
  → z-index values not following a logical step ladder → 🟡 Warning
    (e.g. modal at z-index 5 when a sticky header is at z-index 10 = modal renders behind header)
  → Multiple elements at the same z-index in potentially overlapping contexts → 🟡
  → z-index: 9999 / 99999 on non-overlay elements → 🟡 (escalation smell)

Element weight ratios:
  → Identify the primary CTA (largest button, most prominent button by class/type)
  → Compare its font-weight and font-size against surrounding body text
  → Primary CTA font-weight ≤ body font-weight → 🟡 Warning (CTA doesn't feel more important)
  → Primary CTA font-size = body font-size with no weight or color differentiation → 🟡

Overchoice check:
  → Collect all interactive sibling elements (buttons, links, cards) within the same container
  → If 4+ siblings share identical: font-size, font-weight, and background-color (or all lack background)
    AND none is visually differentiated as primary → 🟡 Warning
    "4+ equal-weight options with no primary action — users experience decision paralysis.
     Establish one clear primary action per section."
  → If 6+ sibling options share identical visual treatment → 🔴 Critical
    (Overchoice Paradox: too many undifferentiated choices measurably reduce decision quality and
     completion rates. Demote, group, or hide lower-priority options.)
  → Exception: lists (ul/ol), nav menus, and data tables are exempt — overchoice applies to
    action-oriented UI (CTAs, product cards, feature choices), not content lists.
  → Korean: "4개 이상 동일한 시각적 비중의 선택지 — 결정 마비 위험. 주요 액션을 명확히 구분하세요."

Only run this category if:
  → Font-size data was successfully extracted (Cat 1 extraction already done)
  Otherwise: note "Visual hierarchy inferred from screenshot — see Cat 1 for typography data"
```

---
