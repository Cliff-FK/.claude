# Figma MCP Workflow Reference

## Overview

The Figma MCP gives Claude direct read and write access to Figma files. This reference covers how to use it safely and effectively during design audits.

---

## Reading a Design

### Tool: `resolve_shortlink`
Use when the user shares a short Figma URL (e.g. `fig.com/abc123`).
```
input: { shortlink: "https://fig.com/abc123" }
output: { nodeId: "123:456", fileKey: "AbCdEfGh" }
```
Use the returned nodeId for all subsequent calls.

### Tool: `get_design_context`
The primary tool for reading design data. Call on any frame, component, or layer.

**What it returns:**
- Layer tree (names, types, parent-child relationships)
- Typography: fontFamily, fontSize, fontWeight, lineHeight, letterSpacing
- Colors: fills (hex + opacity), strokes, background colors
- Layout: width, height, x, y, padding (top/right/bottom/left), gap (auto-layout)
- Component info: which components are used, their names, any overrides

**Best practices:**
- Start with the top-level frame, not individual layers
- If the frame is very complex (100+ layers), request specific sections
- Component names are a signal of design system health — look for names like "Button/Primary/Default" (good) vs "Rectangle 42" (bad)

### Tool: `get_screenshot`
Always call this alongside `get_design_context`. The visual rendering catches issues that data alone misses:
- Visual density and crowding
- Color contrast as it actually appears
- Alignment issues
- Overall hierarchy and balance

```
input: { nodeId: "123:456" }
output: { imageUrl: "..." }
```

### Tool: `get_design_pages`
Call this on the file key **before** auditing any node. Returns the list of all pages in the file.

**Decision logic based on page count:**

```
1 page  → Proceed directly to get_design_context. No user prompt needed.

2–5 pages → Note page names at the top of the audit report.
            If user gave a specific node URL, audit that frame and note
            which page it belongs to. Offer to audit other pages afterward.

6+ pages → Present a page selection widget before auditing:
           "This file has [N] pages — which would you like to audit?"
           Options: [list of page names] + "Audit all pages"

           If "Audit all pages" selected:
             - Run get_design_context + get_screenshot on the top-level frame
               of each page sequentially
             - Score each page independently
             - Produce a ranked summary at the end (worst score first)

No node ID given (just file URL) → Always use get_design_pages first,
                                    then present page selection widget.
```

**Report header line** (include whenever 2+ pages exist):
- English: `"File: [N] pages — auditing '[page name]' (page [N] of [N])."`
- Korean: `"파일: [N]개 페이지 — '[페이지 이름]' 감사 중 ([N]/[N])."`

### Tool: `get_metadata`
Returns file-level info: title, last modified, owner. Use to confirm you're in the right file.

### Tool: `get_code_connect_suggestions`
Returns AI-suggested mappings between Figma components in the file and code components in the connected repository. Call after `get_design_context` on any Figma audit where a codebase is connected.

**What it returns:**
- Figma component name + node ID
- Suggested code component path or import name
- Confidence level per suggestion

**How to use:**
```
Call on the same nodeId as get_design_context.

For each suggestion returned:
  - Record: Figma component name → suggested code component
  - Use to enrich Cat 5 (naming consistency) and Cat 17 (token/component coverage)
  - Show in developer handoff report as a mapping table

If the call fails or returns empty:
  → Skip silently — Code Connect requires Dev Mode and a connected repo.
  → Never mention the failure in the report.
```

### Tool: `get_code_connect_map`
Returns confirmed, user-configured Figma→code component mappings (as opposed to suggestions). More reliable than suggestions when available.

**When to use:**
Call alongside `get_code_connect_suggestions`. If confirmed mappings exist, prefer them over suggestions for the handoff table. Note in the report header: "Code Connect: [N] confirmed mappings".

**Coverage gap detection:**
```
confirmed_components = set of Figma components with a code mapping
all_components = set of all named components in get_design_context layer tree

unmapped = all_components - confirmed_components
If unmapped is non-empty → flag as 🟡 Warning in Cat 5:
  "N Figma components have no code equivalent: [list]"
```

### Tool: `create_design_system_rules`
Generates design system enforcement rules for the connected repository based on the Figma file's component structure, tokens, and naming conventions.

**When to offer:**
- Cat 17 score < 70 (significant hardcoding found)
- Component health < 50% (low coverage)
- User explicitly asks for design system setup or enforcement
- Code Connect mappings were found — rules can reference real component names

**Never call automatically** — always ask the user first. This writes to the codebase.

**Safety pattern:**
```
1. Audit completes and significant token/naming issues found
2. Offer: "Want me to generate design system rules for your repo?"
3. Wait for explicit "yes"
4. Call create_design_system_rules on the file key
5. Show the generated rules to the user before they are applied
6. If call fails: note "Requires Figma Dev Mode + connected repository" and skip
```

---

## Auditing from Context Data

When you have the `get_design_context` output, check these specific fields:

### Typography Checks
```
Look for in context data:
- fontFamily → Are there more than 2 distinct families?
- fontSize → Any below 12? Body text below 14?
- lineHeight → Calculate ratio: lineHeight / fontSize. Should be 1.4–1.6 for body.
- fontWeight → Does weight vary meaningfully across hierarchy levels?
- letterSpacing → Negative on body text? (bad)
```

### Color Checks
```
Look for:
- fills[].color → Convert to hex, check contrast against background
- opacity → Low opacity text is a common contrast failure
- Multiple fills → Complex blending may affect contrast
```

### Spacing Checks
```
Look for:
- paddingTop, paddingRight, paddingBottom, paddingLeft → Are they consistent? Multiples of 8?
- itemSpacing (gap) → Multiple of 8?
- x, y positions → Do sibling elements align to shared values?
```

### Component Health Checks
```
Look for:
- Layer names like "Frame 12", "Group 7", "Rectangle" → unnamed 🔴
- Layer names like "Button/Primary/Hover" → named component instance ✅
- Layer names like "Header", "Card Item" (frames, not components) → named frame ⚠️
- Nodes with no componentId → detached instance or raw layer 🟡

Tally all non-hidden layers in the frame:
  component_pct  = (named component instances / total) × 100
  unnamed_pct    = (unnamed layers / total) × 100
  detached_count = number of detached instances

Thresholds:
  component_pct ≥ 60% → ✅ Healthy
  component_pct 30–59% → 🟡 Partial
  component_pct < 30%  → 🔴 Low coverage

Always show in report header:
  "Component health: [N]% coverage · [N] detached · [N] unnamed layers"

Issue flags:
  unnamed_pct > 20%     → 🟡 Warning
  detached_count > 0    → 🟡 Warning
  component_pct < 30%   → 🔴 Critical
```

---

## Making Edits in Figma

### Tool: `perform_editing_operations`
Use this to fix issues directly. **Always follow the safety rules below.**

### Safety Rules for Editing

**Rule 1: Always confirm before editing**
Never apply edits without asking:
> "I can fix [specific issue] on the [element name] layer. Want me to go ahead?"

**Rule 2: Target specific node IDs**
Get node IDs from `get_design_context` output. Never guess or construct IDs.

**Rule 3: One change at a time for critical properties**
For risky changes (colors, layout, component swaps), do one edit and check the result before continuing.

**Rule 4: Never bulk-edit without confirmation**
Don't say "I'll fix all the spacing issues" and run 20 operations. List what you'll change, confirm, then execute.

**Rule 5: Take a screenshot after editing**
After each batch of edits, call `get_screenshot` to verify the result looks correct.

---

### Common Edit Patterns

#### Fix node width (snap to grid)
```
operation: "SET_WIDTH"
nodeId: "[frame or component node ID]"
value: 60  // snapped from 60.17px to nearest 8pt multiple
```

#### Fix node height (touch target)
```
operation: "SET_HEIGHT"
nodeId: "[interactive element node ID]"
value: 44  // WCAG minimum touch target
```

#### Fix a text color for contrast
```
operation: "SET_FILL_COLOR"
nodeId: "[text layer node ID]"
color: { r: 0.2, g: 0.2, b: 0.2, a: 1.0 }  // #333333 — passes 4.5:1 on white
```

**Hex to 0–1 conversion:** divide each channel by 255. `#7C3AED` → `{r: 0.486, g: 0.227, b: 0.929, a: 1.0}`

#### Fix font size
```
operation: "SET_FONT_SIZE"
nodeId: "[text layer node ID]"
fontSize: 16
```

#### Fix padding on a frame
```
operation: "SET_PADDING"
nodeId: "[frame node ID]"
paddingTop: 16
paddingRight: 16
paddingBottom: 16
paddingLeft: 16
```

#### Fix spacing between auto-layout items
```
operation: "SET_ITEM_SPACING"
nodeId: "[auto-layout frame node ID]"
itemSpacing: 8
```

#### Fix auto-layout direction
```
operation: "SET_LAYOUT_MODE"
nodeId: "[auto-layout frame node ID]"
layoutMode: "HORIZONTAL"  // or "VERTICAL" or "NONE"
```

#### Fix auto-layout alignment
```
operation: "SET_PRIMARY_AXIS_ALIGN_ITEMS"
nodeId: "[auto-layout frame node ID]"
primaryAxisAlignItems: "CENTER"  // SPACE_BETWEEN, MIN, MAX, CENTER

operation: "SET_COUNTER_AXIS_ALIGN_ITEMS"
nodeId: "[auto-layout frame node ID]"
counterAxisAlignItems: "CENTER"  // MIN, MAX, CENTER, BASELINE
```

#### Fix auto-layout sizing (hug vs fixed vs fill)
```
operation: "SET_PRIMARY_AXIS_SIZE_TYPE"
nodeId: "[auto-layout frame node ID]"
primaryAxisSizeType: "AUTO"  // "AUTO" = hug contents, "FIXED" = fixed size

operation: "SET_COUNTER_AXIS_SIZE_TYPE"
nodeId: "[auto-layout frame node ID]"
counterAxisSizeType: "AUTO"
```

#### Rename a layer (for component hygiene)
```
operation: "RENAME_LAYER"
nodeId: "[layer node ID]"
name: "Button/Primary/Default"
```

---

### ⚠️ Component Instance Caveat

**You cannot directly edit layers inside a component instance.** If a node ID belongs to a layer inside an instance (not the main component), `perform_editing_operations` will fail or have no effect.

**How to detect this:** In `get_design_context`, instances show as `type: "INSTANCE"`. Any child layer of an instance is read-only from outside.

**What to do instead:**
1. Check if the parent frame is an instance — look for `componentId` or `mainComponent` in the context data
2. If it is an instance: inform the user that the fix must be applied to the **main component**, not the instance
3. Find the main component node ID (from `componentId` in the context) and apply the edit there — it will propagate to all instances
4. If the main component is in a different file (shared library), note that it cannot be edited via MCP and give design direction instead

**Example message to user:**
> "The node `68:27912` is inside a component instance. I'll apply this fix to the main component instead, which will update all instances automatically."

---

### Rule 6: Always verify with a screenshot after editing
After each `perform_editing_operations` call:
```
1. Call get_screenshot on the same nodeId
2. Show the screenshot to the user
3. If the visual doesn't match intent → call perform_editing_operations again with corrected values
4. Never assume the edit worked without visual confirmation
```

---

## Reading Component Structure

Well-structured Figma files use a naming convention like:
```
ComponentName/Variant/State
Button/Primary/Default
Button/Primary/Hover
Button/Secondary/Disabled
Icon/Arrow/Right
```

When auditing, note if components follow this pattern. If they don't, flag it as a 🟡 Warning — it means the design won't scale well and handoff to developers will be harder.

---

## Common Figma-Specific Issues to Flag

| Issue | Figma Signal | Severity |
|---|---|---|
| No components used | All layers are "Frame", "Rectangle", "Group" | 🟡 |
| Detached components | Layer shows "⚠ Detached" | 🟡 |
| Inconsistent text styles | No shared text styles defined | 🟡 |
| Inconsistent color styles | No shared color styles defined | 🟡 |
| Missing auto-layout | Fixed-position elements that should flex | 🟢 |
| No grids defined | Layout grid not applied to frames | 🟢 |
| Unlabeled frames | Frames named "Frame 1", "Frame 2" | 🟢 |
| Missing variants | Component has no hover/disabled states | 🟡 |
| Images not masked | Raw image fills without mask shapes | 🟢 |

---

## When Figma MCP Isn't Available

If Figma MCP tools aren't connected, ask the user to:
1. Export a screenshot (PNG) and share it
2. Or share specific values they see in Figma's right panel (colors, font sizes, spacing)
3. Or copy-paste the CSS Figma generates (right panel → Inspect → CSS)

The CSS export from Figma is particularly useful — it contains exact font sizes, colors, and spacing values that can be checked programmatically.

---

> Section déplacée telle quelle depuis [SKILL.md](../SKILL.md).

## Figma MCP Workflow

When a Figma file or URL is involved, follow these steps. Read `references/figma-mcp.md` for full details and safe editing patterns.

### F0: Check MCP Availability First
Before attempting any Figma tool call, check if Figma MCP is active by attempting `get_design_context`. If it fails or is unavailable, respond in the user's detected language:
- English: *"I can see you've shared a Figma link, but I don't have Figma MCP access in this session. Could you export a screenshot or paste the relevant CSS/component code? I can still run a full audit — I'll just note it as 🟡 Medium confidence since I won't have exact layer data."*
- Korean: *"Figma 링크를 공유해 주셨지만, 이 세션에서는 Figma MCP 접근 권한이 없습니다. 스크린샷을 내보내거나 관련 CSS/컴포넌트 코드를 붙여넣어 주시겠어요? 전체 감사는 진행할 수 있지만, 정확한 레이어 데이터가 없으므로 🟡 중간 신뢰도로 표시됩니다."*

Never attempt to audit a Figma URL without MCP access — do not guess or hallucinate layer values.

### F1: Resolve the Link
If given a Figma URL or shortlink → call `resolve_shortlink` first to get the node ID.

### F1.5: Get File Structure
Before diving into any node, call `get_design_pages` on the file key to understand the full file structure.

**What to do with the result:**

```
If the file has 1 page:
  → Proceed directly to F2 with the provided node ID. No need to ask.

If the file has 2–5 pages:
  → State the page names at the top of the report.
  → If the user gave a specific node URL, audit that frame and note which page it's on.
  → Offer to audit other pages after the current audit completes.

If the file has 6+ pages:
  → Present a widget before auditing:
    question: "This file has [N] pages — which would you like to audit?"
    type: single_select
    options: [list of page names] + "Audit all pages / 모든 페이지 감사"
  → If "Audit all pages": run sequential audits per page, aggregate scores, surface a
    ranked summary at the end (highest issue count first).

If the user gave no specific node ID (just a file URL):
  → Use get_design_pages to list pages, present the widget above, then proceed.
```

**File structure line in report header** (always include when 2+ pages exist):
- English: *"File: [N] pages — auditing '[page name]' (page [N] of [N])."*
- Korean: *"파일: [N]개 페이지 — '[페이지 이름]' 감사 중 ([N]/[N])."*

### F2: Get Design Context
Call `get_design_context` on the node. Returns: layer structure, component names, typography (font, size, weight, line-height), colors (fills, strokes, opacity), spacing (padding, gap, auto-layout), and component/style references.

**Component health scan (run automatically on every Figma audit):**
While reading the layer tree from `get_design_context`, tally the following:

```
For every layer in the tree, classify it:
  - Named component instance (e.g. "Button/Primary/Default", "⚡ Input") → component ✅
  - Raw frame/group with a meaningful name (e.g. "Header", "Card Item") → named frame ⚠️
  - Raw frame/group with a generic name ("Frame 12", "Group 7", "Rectangle") → unnamed 🔴
  - Detached instance (shows no componentId) → detached 🟡

Compute:
  total_layers = all non-hidden layers
  component_pct = (named component instances / total_layers) × 100
  unnamed_pct = (unnamed layers / total_layers) × 100

Thresholds:
  component_pct ≥ 60% → ✅ Healthy component usage
  component_pct 30–59% → 🟡 Partial — some components, many one-offs
  component_pct < 30% → 🔴 Low — mostly raw layers, not using a component system

Show the Component Health line in the report header (always, on Figma audits):
  "Component health: 68% component coverage · 4 detached instances · 12 unnamed layers"

Flag as issues:
  unnamed_pct > 20% → 🟡 "High proportion of unnamed layers ([N]) — slows handoff and makes edits harder"
  detached_instances > 0 → 🟡 "N detached component instances — updates to the main component won't propagate"
  component_pct < 30% → 🔴 "Low component coverage ([N]%) — most elements are raw frames, not reusable components"
```

**Auto-Layout compliance scan (run automatically on every Figma audit):**
While reading the layer tree from `get_design_context`, check for absolute-position frames that should be auto-layout:

```
For every Frame or Group in the tree:
  - If it contains 2+ child layers arranged in a clear row or column pattern
    AND it has no layoutMode (i.e. not using Auto Layout) → flag as 🟡 Warning
    "Frame '[name]' contains [N] stacked/row children but uses manual positioning —
     convert to Auto Layout for responsive behaviour and easier spacing control"

  - If a Frame has children with hardcoded x/y absolute positions AND the frame
    has a fixed width → 🟡 Warning
    (absolute children won't reflow when content changes)

  - If layoutMode = HORIZONTAL or VERTICAL but itemSpacing or padding values
    are not on the 8pt grid → 🟡 (same as Cat 3 spacing check)

Positive signals to acknowledge:
  → Frames using Auto Layout (layoutMode = HORIZONTAL or VERTICAL) → ✅
  → Consistent itemSpacing across sibling Auto Layout frames → ✅
  → "Min W" / "Fill" constraints used instead of fixed widths → ✅

Show in report header on Figma audits when manual-position frames are found:
  "Auto Layout: [N]% of frames using Auto Layout · [N] frames with manual positioning"
```

### F3: Get a Screenshot
Call `get_screenshot` on the same node. Essential — context data alone misses visual issues like crowding, poor contrast, or bad hierarchy.

### F3.5: Get Variable Definitions + Contrast Analysis
Call `get_variable_defs` on the same node. Returns the actual token/variable data bound to the design (e.g. `color/primary: #7c3aed`, `spacing/md: 16px`).

**Use for Category 17 (Design Tokens):**
- If a value in `get_design_context` matches a variable in `get_variable_defs` → it is tokenized ✅
- If a value in `get_design_context` has no matching variable → it is hardcoded 🔴
- If `get_variable_defs` returns empty or fails → note "No variables found — token coverage cannot be verified" and audit Cat 17 from context data only
- Declare token coverage % in the Cat 17 section: e.g. "4 of 7 color values tokenized (57%)"

**Use for Category 2 (Color & Contrast) — no screenshot required:**
When color tokens are available from `get_variable_defs`, compute WCAG contrast ratios programmatically using this algorithm:

```
1. Extract all color token pairs where one is clearly a foreground (text, icon, border)
   and the other is a background (surface, fill, container).
   Look for naming patterns like:
   - color/text/* paired with color/background/* or color/surface/*
   - color/on-* paired with color/*
   - color/foreground paired with color/canvas

2. For each hex color, compute relative luminance:
   - Normalize: R = hex_r/255, G = hex_g/255, B = hex_b/255
   - Linearize: channel < 0.04045 ? channel/12.92 : ((channel+0.0539)/1.055)^2.4
   - L = 0.2126*R_lin + 0.7152*G_lin + 0.0722*B_lin

3. Compute contrast ratio:
   - ratio = (lighter_L + 0.05) / (darker_L + 0.05)

4. Compare against WCAG thresholds (from inferred or selected level):
   - AA normal text: ≥ 4.5:1
   - AA large text / UI components: ≥ 3:1
   - AAA normal text: ≥ 7:1
   - AAA large text: ≥ 4.5:1

5. Flag any pair that fails as a Cat 2 issue. Pre-populate the Contrast Checker widget
   with the exact failing hex pair and the computed ratio.

6. Also flag if no text/background token pairs can be identified — this means contrast
   cannot be verified from tokens alone.
```

**Confidence upgrade:** If `get_variable_defs` returns usable color pairs, the Cat 2 audit upgrades from 🟡 Medium to 🟢 High confidence even if no screenshot is available. State this explicitly:
- English: *"Color contrast audited from design tokens (no screenshot required) — 🟢 High confidence."*
- Korean: *"색상 대비는 디자인 토큰에서 감사되었습니다 (스크린샷 불필요) — 🟢 높은 신뢰도."*

If `get_variable_defs` fails or returns no color pairs, fall back to screenshot-based visual assessment and 🟡 Medium confidence for Cat 2.

### F3.6: Code Connect — Design-to-Code Mapping (if available)

After variable definitions, attempt `get_code_connect_suggestions` on the audited node. This returns AI-suggested mappings between Figma components and real code components in the connected codebase.

**What to do with the result:**

```
If get_code_connect_suggestions returns mappings:
  → For each suggested mapping (Figma component → code component):
    - Note the component name, suggested code path, and confidence level
    - Use this to enrich Cat 5 (Consistency) and Cat 17 (Tokens):
      "Button/Primary/Default" → maps to <Button variant="primary"> in codebase
    - Flag mismatches: Figma component name vs code component name divergence → 🟢 Tip
    - Flag missing mappings: Figma components with no suggested code equivalent → 🟡
      (component exists in design but not in codebase — handoff gap)

  Also attempt get_code_connect_map to retrieve confirmed existing mappings:
  → Confirmed mappings (user has already set up Code Connect) → show in report header
  → No confirmed mappings → note "Code Connect not configured — suggestions only"

  Add a Code Connect line to the REPORT HEADER when data is available:
    "Code Connect: [N] components mapped · [N] unmapped · [N] suggestions available"

If get_code_connect_suggestions fails or returns empty:
  → Skip silently. Do not mention it in the report.
  → Code Connect requires the Figma Dev Mode and a connected codebase — not always available.
```

**Use for Cat 5 cross-check:**
```
When a component has a confirmed or suggested code mapping:
  → Check if the Figma component name matches the code component name
  → "Button/Primary" in Figma → <PrimaryButton> in code → 🟢 Tip (minor naming drift)
  → "Button/Primary" in Figma → <Btn> in code → 🟡 Warning (naming too divergent)
  → Figma has 12 button variants, code has 3 → 🟡 Warning (variant coverage gap)
```

**Use for developer handoff report:**
When generating the Developer Handoff Report and Code Connect data is available, include a mapping table:

```
| Figma Component | Code Component | Status | Notes |
|---|---|---|---|
| Button/Primary/Default | <Button variant="primary"> | ✅ Mapped | — |
| Card/Product | <ProductCard> | ✅ Mapped | — |
| Modal/Confirmation | — | 🟡 Unmapped | No code equivalent found |
```

### F4: Run the Audit
With context data, variable definitions, screenshot, and code connect data in hand, run the full audit below.

### F5: Fix Directly in Figma (if requested)
When the user selects "Fix all Critical" or "Fix a specific issue" and the original input was a Figma file (not a screenshot or code), apply fixes using `perform_editing_operations`. Always follow the safety rules in `references/figma-mcp.md`.

**Fix loop for Figma input:**
For each confirmed fix (user selected "Yes, apply it"):
1. Look up the node ID from the audit (should have been captured during F2)
2. **Pre-flight check:** Before calling `perform_editing_operations`, verify:
   - The node ID exists in the context data captured during F2
   - The node is not inside a component instance (see component instance caveat in `references/figma-mcp.md`)
   - The operation type matches the node type (e.g. `SET_FONT_SIZE` requires a text node)
3. Call `perform_editing_operations` with the appropriate operation
4. After each operation, call `get_screenshot` on the affected node to verify the change
5. Show the screenshot and confirm ✅ with the before/after values
6. If the operation fails → see **Failure recovery** below

**Failure recovery — partial failure handling:**
If `perform_editing_operations` throws an error or the screenshot shows the change did not apply:

```
Step 1: Identify the failure type
  - "Node not found" → node ID is stale or incorrect. Re-call get_design_context to refresh.
  - "Cannot edit instance" → node is inside a component instance. Find main component ID and retry there.
  - "Invalid operation" → operation type doesn't match node type. Check node type in context data.
  - "Permission denied" → file is view-only or in a shared library. Cannot edit via MCP.
  - Unknown error → report to user and skip to next fix.

Step 2: Report clearly to the user in their detected language
  - English: "⚠️ Fix [N] failed: [reason]. Skipping to the next issue — I'll note this one so you can apply it manually."
  - Korean: "⚠️ 수정 [N] 실패: [이유]. 다음 문제로 넘어갑니다 — 수동으로 적용할 수 있도록 기록해 두겠습니다."

Step 3: Log the failed fix
  Track all failed fixes in a list. After the loop completes, show a summary:
  - English: "N fixes applied ✅. N fixes need manual attention:"
  - Korean: "N개 수정 완료 ✅. N개는 수동 적용이 필요합니다:"
  Then list each failed fix with the exact Figma right-panel value to enter manually.

Step 4: Continue the loop
  Never stop the entire fix loop because one fix failed. Skip the failed fix and continue.
```

**Operation type mapping — common audit fixes:**

| Issue Type | Operation | Key Parameters |
|---|---|---|
| Off-grid width/height | `SET_WIDTH` / `SET_HEIGHT` | nodeId, value (snapped to 8pt) |
| Off-grid padding | `SET_PADDING` | nodeId, paddingTop/Right/Bottom/Left |
| Off-grid gap | `SET_ITEM_SPACING` | nodeId, itemSpacing |
| Auto-layout direction | `SET_LAYOUT_MODE` | nodeId, layoutMode |
| Auto-layout alignment | `SET_PRIMARY_AXIS_ALIGN_ITEMS` | nodeId, primaryAxisAlignItems |
| Text color contrast fail | `SET_FILL_COLOR` | nodeId, color: {r,g,b,a} in 0–1 range |
| Font size too small | `SET_FONT_SIZE` | nodeId, fontSize |
| Rename unlabelled layer | `RENAME_LAYER` | nodeId, name |
| Touch target too small | `SET_WIDTH` + `SET_HEIGHT` | nodeId, 44 (minimum) |

**If `perform_editing_operations` is not available:** Fall back to design direction mode for all fixes — describe the change spatially and provide the exact Figma right-panel values to enter manually. Never silently skip without informing the user.

### F5.5: Generate Design System Rules (optional, post-audit)

After the audit and fix loop complete, if the user asks "can you generate design system rules?" or "set up design system enforcement" — or if the audit found significant token/naming issues — offer to call `create_design_system_rules`.

```
When to offer:
  → Cat 17 (Tokens) score < 70 — significant hardcoding found
  → Component health < 50% — low component coverage
  → User explicitly asks for design system setup or enforcement
  → Code Connect mappings were found (F3.6) — rules can reference real components

What it does:
  → Generates design system rules for the connected repository based on the
    Figma file's component structure, token definitions, and naming conventions
  → Rules can enforce: component naming, token usage, spacing scale, radius scale

How to offer (after fix loop):
  English: "I found significant design system gaps. Want me to generate design
            system enforcement rules for your codebase based on this Figma file?"
  Korean: "디자인 시스템 격차가 발견되었습니다. 이 Figma 파일을 기반으로 코드베이스에
           대한 디자인 시스템 적용 규칙을 생성할까요?"

If yes → call create_design_system_rules on the file key
If the call fails or is unavailable → note "Design system rule generation requires
  Figma Dev Mode and a connected repository" and skip.
```

---
