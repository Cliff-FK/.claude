# Consistency Reference

> Référence du skill design-auditor, chargée à la demande depuis [SKILL.md](../SKILL.md). Contenu déplacé tel quel depuis SKILL.md.

### CATEGORY 5: Consistency
*Corner radius full rules → `references/corner-radius.md`*

- [ ] **Component reuse** — Buttons, inputs, cards identical throughout. No one-off styles.
- [ ] **Icon family** — All icons from the same set (same style, same stroke weight).
- [ ] **Corner radius scale** — Radii should come from a fixed set (e.g. 4, 8, 12, 16, 24px, full). Arbitrary values (7px, 11px) look accidental.
- [ ] **Nested radius rule** — When an element sits inside another, outer radius = inner radius + padding. If the inner element has 8px radius and 12px padding, the outer must be ~20px. Mismatched nesting makes corners look "poking out."
- [ ] **Size-proportional radius** — Larger elements need larger radii. A small badge with 12px radius looks right. A large modal with 4px radius looks barely rounded.
- [ ] **Pill shapes are intentional** — border-radius ≥ 50% of height creates a pill. Should be deliberate (tags, toggles, badges) not accidental.
- [ ] **Zero radius is a choice** — Sharp corners (0px) should be a design language decision, not a forgotten default.
- [ ] **Contextual radius** — Modals/sheets anchored to screen edges should have rounded top corners, square bottom. Floating elements fully rounded.
- [ ] **Interaction states** — Hover, active, disabled states all visually distinct.

**2-frame consistency compare mode:**
When the audit session has 2+ frames audited (e.g. file has multiple pages, or the user shares a second frame for comparison), automatically cross-check these values between frames:

```
Cross-frame checks (run silently, report only mismatches):
  - Button corner radius: same value on both frames?
  - Primary button fill color: same hex/token?
  - Body font size: same value?
  - Input field height: same?
  - Primary heading font weight: same?
  - Icon style: outline vs filled — consistent?

Report cross-frame inconsistencies as:
  🟡 Warning: "[Property] differs between frames: [Frame A] = [value], [Frame B] = [value]"
  Example: "Button corner radius: 8px on NTIR form, 4px on Dashboard screen — pick one."

Only run cross-frame checks when you have context data for 2+ frames in the session.
Single-frame audits skip this silently — do not mention it.
```

**📋 Code input: direct checks available (run these automatically)**
```
Multiple button implementations:
  → Collect all button-like elements: <button>, <a role="button">, elements with onClick
  → Group by visual role: primary (filled), secondary (outlined), ghost (text-only)
  → If primary buttons have more than 1 unique background-color value → 🔴 Critical
    (multiple primary button colors = broken visual language)
  → If buttons of the same role have different border-radius values → 🟡 Warning
  → If buttons of the same role have different font-size values → 🟡 Warning

Inconsistent border-radius:
  → Collect all border-radius values applied to cards, panels, modals, buttons, inputs
  → Group by element type (cards together, buttons together, inputs together)
  → More than 2 distinct radius values per element type → 🟡 Warning
    "Cards use 3 different radius values: 4px, 8px, 12px — pick one."
  → Arbitrary radius values (7px, 11px, 13px) not on a defined scale → 🟢 Tip

Colour consistency:
  → Collect all background-color / color values used for the same semantic role
    (e.g. all primary button backgrounds, all body text colors)
  → Same semantic role using 2+ different hex values (without token aliasing) → 🟡
    "Primary button uses #7c3aed in one place and #6d28d9 in another"

Duplicate style blocks:
  → Scan for CSS rule blocks with identical property sets applied to different selectors → 🟢 Tip
    (suggests a component opportunity — these should share a class)
  → React/Vue: identical JSX style props repeated across 3+ components → 🟢 Tip

Interaction state coverage:
  → For each interactive element type (button, link, input, card), check:
    → :hover defined? → ✅
    → :focus or :focus-visible defined? → ✅
    → :active defined? → 🟢 Tip if missing
    → .disabled or [disabled] styled? → 🟡 if missing
  → Inconsistency: some buttons have :hover, others don't → 🟡 Warning
```

---
