# Code Input: Extraction & Fix Format

> Référence du skill design-auditor, chargée à la demande depuis [SKILL.md](../SKILL.md). Contenu déplacé tel quel depuis SKILL.md.

## Step 1.6: Code Input Extraction (HTML / CSS / React / Vue)

When the input is code (not a Figma file), extract audit data using this parallel spec. This ensures the Type Scale Stack, component health, consistency checks, and microcopy analysis all work on code input — not just Figma.

### Code Audit Scope Selector

Before extracting values, check the size and nature of the input:

```
If input is a single component file (< 150 lines):
  → Proceed directly with full audit. No need to ask.

If input is a large file or multiple files (150+ lines or 3+ files):
  → Present scope widget before auditing:
    question: "This is a large codebase — what should I focus on?"
    type: multi_select
    options:
      "Full audit — everything / 전체 감사"
      "Accessibility only (Cat 6, 7) / 접근성"
      "Design tokens & consistency (Cat 5, 17) / 토큰 & 일관성"
      "Responsive & layout (Cat 3, 10) / 반응형 & 레이아웃"
      "Typography & color (Cat 1, 2) / 타이포그래피 & 색상"
      "Motion & states (Cat 8, 11) / 모션 & 상태"

If the user has already indicated focus in their message
(e.g. "check the accessibility", "is the contrast ok"):
  → Skip the widget, infer the scope, note it in the report header.
```

State the audit scope at the top of the report under the REPORT HEADER:
- English: `"Scope: [selected categories] — [N] files, ~[N] lines"`
- Korean: `"범위: [선택된 카테고리] — [N]개 파일, 약 [N]줄"`

### Framework Detection — Do This First

Before extracting any values, identify the framework. This changes how values are extracted and how fixes are written.

```
Signals to look for:

  HTML/CSS (vanilla)
    → <div>, <button>, <input> tags with class="" or style=""
    → Standalone .css or .html files
    → No import statements or JSX syntax

  React / JSX
    → import React / import { useState } / import { ... } from ...
    → JSX syntax: <Component />, className=, onClick=
    → .jsx or .tsx file extension
    → Possible: styled-components, CSS modules, inline styles

  Vue
    → <template>, <script setup>, <style scoped> blocks
    → v-bind, v-model, :class, @click directives
    → .vue file extension

  Tailwind CSS (any framework)
    → className / class values with utility prefixes:
      text-*, bg-*, p-*, m-*, gap-*, rounded-*, border-*, font-*, leading-*
    → Often combined with React or Vue

  CSS-in-JS (styled-components / emotion)
    → const Wrapper = styled.div`...`
    → css`...` template literals
    → Values are in JS template strings, not CSS files

  CSS custom properties / design tokens
    → var(--token-name) in CSS values
    → :root { --color-primary: #... } definitions

Declare the detected framework at the top of the audit:
  "Detected: React + Tailwind CSS"
  "Detected: Vue 3 (Composition API) + CSS Modules"
  "Detected: Vanilla HTML/CSS"

This declaration affects:
  - How values are extracted (see per-category specs below)
  - How fixes are written (Tailwind class swaps vs CSS property changes vs JSX prop changes)
  - Which categories get code-specific superpower checks (see Cat 6, 8, 9, 13, 17)
```

### Design System Detection (run after framework detection)

After identifying the framework, check for a known component library or design system. This changes how issues are interpreted — overriding a design system's defaults is often the root cause, not the symptom.

```
Detection signals:

  Material UI (MUI) / Joy UI
    → import { Button, TextField, ... } from '@mui/material' or '@mui/joy'
    → sx prop usage: sx={{ color: '...' }} or sx={{ p: 2 }}
    → theme.palette.*, theme.spacing(), ThemeProvider

  Chakra UI
    → import { Box, Stack, ... } from '@chakra-ui/react'
    → <Box p={4} color="gray.700"> — Chakra prop shorthand
    → ChakraProvider in root

  shadcn/ui
    → import { Button } from "@/components/ui/button"
    → cn() utility from "lib/utils" for class merging
    → Radix UI primitives as peer deps (package.json: @radix-ui/*)

  Ant Design
    → import { Button, Form, ... } from 'antd'
    → ConfigProvider in root

  Radix UI (headless)
    → import * as Dialog from '@radix-ui/react-dialog'
    → Direct Radix primitive imports

  Bootstrap / React-Bootstrap
    → className="btn btn-primary" or import { Button } from 'react-bootstrap'
    → bootstrap CSS imported

  None detected → treat as custom/vanilla (standard audit rules apply)

Declare at the top of the audit:
  "Detected: React + MUI v5"
  "Detected: Next.js + shadcn/ui + Tailwind CSS"
  "Detected: Vue 3 + Ant Design"
```

**Design system — system-specific issue types (add these to relevant categories when a DS is detected):**

```
MUI / Joy UI:
  Cat 6 — Accessibility:
    → button:focus { outline: none } overriding MUI's focus-visible → 🔴 Critical
      "Overriding MUI's focus-visible breaks keyboard accessibility — remove the override
       or use theme.components.MuiButton.styleOverrides.root with focus-visible targeting"
    → Calling .MuiButton-root CSS override without :focus-visible scope → 🟡
  Cat 17 — Tokens:
    → Hardcoded color values bypassing theme.palette → 🟡 "Use theme.palette.primary.main"
    → sx={{ fontSize: '14px' }} instead of sx={{ fontSize: 'body2.fontSize' }} → 🟢 Tip

Chakra UI:
  Cat 3 — Spacing:
    → <Box p="13px"> instead of <Box p={3}> (Chakra spacing scale: 3 = 12px) → 🟡
    → Arbitrary spacing string bypassing Chakra's scale → 🟡
  Cat 17 — Tokens:
    → Hardcoded color strings bypassing the Chakra color scheme ("gray.700" is correct; "#374151" is not) → 🟡

shadcn/ui:
  Cat 5 — Consistency:
    → Direct className overrides on shadcn components without using the cn() utility → 🟡
      (bypasses variant system — use variant prop or extend via cva())
    → Multiple custom button implementations alongside shadcn's Button → 🟡

Ant Design:
  Cat 5 — Consistency:
    → Direct style prop overrides on antd components instead of ConfigProvider/theme → 🟡
  Cat 6 — Accessibility:
    → antd Form.Item without name prop → 🟡 (label/input association breaks)

General rule for all design systems:
  → If a DS is detected AND a hardcoded value overrides its scale → always flag
    with the system-specific fix path, not just the generic CSS fix
  → Never flag system defaults as issues — only flag when the defaults are
    overridden in a way that breaks the design or accessibility
```

### Typography extraction from code
```
Collect all unique font-size values across the codebase/component:
  - CSS: font-size declarations (px, rem, em)
  - React/Vue: inline styles, className references to utility classes (e.g. text-sm, text-lg)
  - Convert rem to px (base 16px unless overridden): 1rem = 16px, 0.875rem = 14px

Map to roles by relative size and frequency (same logic as Figma):
  - Largest → heading, next → subheading, most-frequent → body, smallest → caption

Check against typography.md rules and flag issues.
Trigger Type Scale Stack widget with extracted sizes — same as Figma path.
```

### Color extraction from code
```
Collect all color values:
  - CSS: color, background-color, border-color (hex, rgb, hsl, var(--token))
  - Tailwind: color utility classes (text-gray-900, bg-white, border-blue-500)
  - CSS variables: resolve var(--color-x) to actual hex if defined in the file

For each foreground/background pair visible in context:
  - Run WCAG luminance contrast check (same algorithm as F3.5)
  - Trigger Contrast Checker widget if any pair fails

Hardcoded values (not var(--token)) → flag for Cat 17 (token coverage)
```

### Spacing extraction from code
```
Collect spacing values:
  - CSS: padding, margin, gap, width, height in px
  - Tailwind: spacing utility classes (p-4 = 16px, m-3 = 12px, gap-2 = 8px)

Tailwind spacing scale: 1 unit = 4px. So p-4 = 16px ✅, p-3 = 12px ✅, p-[13px] = off-grid 🟡.

Check for off-grid values (not multiples of 4). Trigger 8pt Grid Visualizer widget on first offender.
```

### Component health from code
```
Instead of layer tally, assess structural patterns:
  - Are UI elements defined as reusable components/functions? (React: <Button>, Vue: <BaseInput>) → ✅
  - Are there inline one-off HTML structures with no component wrapper? → 🟡
  - Count unique component definitions vs total render instances

If the input is a single component file (not an app):
  - Note "Single component input — cross-file component coverage cannot be assessed"
  - Audit the internal structure for prop hygiene, named slots, etc.
```

### Microcopy extraction from code
```
Collect all string literals that appear in the UI:
  - Button children: <button>Submit</button>, <Button>OK</Button>
  - Input placeholders: placeholder="Enter email"
  - Labels: <label>First name</label>
  - Error strings: "Invalid input", "Required"
  - Empty state text

Apply the same per-role checks as Cat 12. Cite line numbers instead of node IDs:
  🟡 placeholder="eg: 5" (line 47) — informal prefix. Use e.g. 5 or a unit hint.
```

### 2-frame comparison from code
```
If the user shares 2+ component files or code snippets in the same session:
  - Extract and compare button border-radius, primary color, body font-size across files
  - Flag cross-file inconsistencies the same way as the Figma 2-frame compare
```

---

## Step 1.7: Code Fix Output Format

When the input is code, fixes must be output as actual before/after code diffs — not descriptions. This is the code equivalent of F5 (Figma fix loop).

### Fix format rules

**Always output diffs in this format for every code fix:**

```
Issue: [issue description]
File: [filename or "component" if single file] · Line [N] (if known)

Before:
  [exact original code — 1–5 lines of context]

After:
  [corrected code with the fix applied]

Why: [one-sentence reason referencing the rule]
```

**Framework-aware fix output:**

```
Vanilla CSS fix:
  Before: padding: 13px;
  After:  padding: 12px;   /* snapped to 4pt grid */

Tailwind fix:
  Before: className="p-[13px]"
  After:  className="p-3"  /* 12px — nearest grid value */

React inline style fix:
  Before: style={{ padding: 13 }}
  After:  style={{ padding: 12 }}

CSS custom property fix (prefer this over hardcoded):
  Before: color: #8a8a8a;
  After:  color: var(--color-text-secondary);

Aria fix:
  Before: <img src="logo.png" />
  After:  <img src="logo.png" alt="Company logo" />

Focus style fix:
  Before: button:focus { outline: none; }
  After:  button:focus-visible { outline: 2px solid var(--color-focus); outline-offset: 2px; }
```

**Fix grouping:** When the same issue repeats across lines, show one representative diff and note the others:
```
  Fix shown for line 23. Apply the same pattern to lines 31, 47, 89.
```

**When to offer a fix loop:**
After completing the audit report, if there are 🔴 Critical issues, offer:
- English: *"Want me to output corrected code for all critical issues?"*
- Korean: *"모든 중요 문제에 대한 수정된 코드를 출력해 드릴까요?"*

If yes → output diffs for every critical in severity order, then warnings if requested.

---
