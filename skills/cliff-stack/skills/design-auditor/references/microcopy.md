# Microcopy Reference

Microcopy is the small functional text throughout a UI — button labels, error messages, tooltips, placeholders, confirmations, and empty states. It's often written by developers as an afterthought, but it has an outsized impact on usability and user trust.

---

## Button Labels

### The Verb Rule
Buttons should always be a **verb** (or verb phrase) that describes the action:

| ❌ Vague | ✅ Specific |
|---|---|
| OK | Got it / Save / Confirm |
| Submit | Send Message / Create Account / Place Order |
| Yes | Delete / Remove / Yes, cancel my plan |
| Cancel | Keep editing / Go back |
| Upload | Upload photo / Add file |
| Continue | Next: Shipping / Continue to payment |

### Rules
- **Be specific about what happens.** "Save Changes" is better than "Save" when the user might not know what they're saving.
- **Match the button to the outcome.** If clicking a button sends an email, say "Send Email" — not "Confirm."
- **Destructive buttons should name the thing being destroyed.** "Delete Project" not just "Delete." "Cancel Subscription" not just "Cancel."
- **Primary CTA should be 2–4 words max.** Longer labels don't scan well in buttons.
- **Sentence case, not Title Case.** "Create account" not "Create Account" (unless your brand style is Title Case throughout).

### Confirmation Dialogs
A good destructive confirmation dialog has:
```
Title:   Delete "Project Alpha"?
Body:    This will permanently delete the project and all its tasks.
         This action cannot be undone.
Buttons: [Cancel]  [Delete project]  ← specific, not just "Delete"
```

Never:
```
Title:   Are you sure?
Body:    This action cannot be undone.
Buttons: [No]  [Yes]
```

---

## Error Messages

### The Formula
A good error message answers three questions:
1. **What went wrong?**
2. **Why did it go wrong?** (if not obvious)
3. **How do I fix it?**

| ❌ Bad | ✅ Good |
|---|---|
| Invalid input | Please enter a valid email address |
| Error | We couldn't save your changes — check your connection and try again |
| Password incorrect | That password doesn't match — try again or reset your password |
| Required field | Please add a project name to continue |
| 500 Internal Server Error | Something went wrong on our end. We're looking into it — try again in a moment |
| File too large | This file is too large. Maximum size is 10MB |

### Rules
- **Don't blame the user.** Passive voice helps: "That email wasn't recognized" vs "You entered an invalid email."
- **Be specific.** "Something went wrong" is a last resort, not a default.
- **Tell them what to do next.** End with an action: "try again", "reset your password", "check your connection."
- **Match your brand's tone.** A playful app can say "Oops! That didn't work." An enterprise tool should stay neutral and professional.
- **Never expose technical details** to end users. No stack traces, no HTTP status codes, no "null pointer exception."
- **Keep error messages short.** 1–2 sentences max. If it needs more explanation, link to a help article.

### Validation Timing
- **Validate on blur** (when leaving a field) for format errors (email, URL, phone).
- **Validate on change** for things like password strength (give live feedback).
- **Validate on submit** for required field checks — don't interrupt typing.
- **Never validate on every keystroke** for format errors — showing "Invalid email" while the user is still typing is annoying.

---

## Placeholder Text

Placeholders appear inside empty inputs and disappear when the user starts typing. They are **not labels**.

### What Placeholders Are Good For
- Showing the expected format: `name@example.com`, `(555) 000-0000`, `YYYY-MM-DD`
- Giving a short hint: `Search by name or email`
- Example values: `e.g. Project Alpha`, `e.g. San Francisco`

### What Placeholders Are NOT Good For
- Replacing labels (they disappear, users forget what goes there)
- Long instructions (they get cut off)
- Required information (users skip it)

### Rules
- **Always have a visible label** in addition to placeholder text.
- **Keep placeholders short** — one phrase, max.
- **Prefix style** — When showing an example value, write it as `e.g. Project Alpha` (with a space after the period). Avoid `eg:`, `eg.`, or `E.g:` — these are informal and inconsistent. Flag as 🟡 Warning.
- **Don't duplicate the label** — A placeholder that says exactly the same thing as its label adds no value. Use the placeholder for format hints or examples only.
- **Placeholder contrast** should be noticeably lighter than real input text — but still meet 3:1 contrast for readability.

### Citing Placeholder Issues in Audit Reports
When flagging a placeholder issue, always cite:
- The exact placeholder text (quoted)
- The node ID if available from `get_design_context`
- The specific rule it violates

Example: 🟡 Placeholder `"eg: 5"` (node 68:27994) — uses informal `eg:` prefix. Use `e.g. 5` or just the unit hint.

---

## Tooltips & Helper Text

### When to Use Tooltips
- To explain an icon button with no label
- To clarify a non-obvious field ("API keys are case-sensitive")
- To explain why something is disabled ("You need admin access")

### When NOT to Use Tooltips
- For essential information — if it's critical, put it on the screen, not hidden in a hover
- For mobile (tooltips don't work on touch — use inline helper text or a modal instead)
- For long explanations — tooltips should be 1–2 sentences max

### Helper Text (Below Input)
Use static helper text below inputs for:
- Format hints: "Use 8+ characters with at least one number"
- Consequences: "This will be visible to everyone on your team"
- Counts: "0/280 characters"

Position: directly below the input, above where the error message will appear.

---

## Tone Guidelines

### Pick One Tone and Stick to It

| Tone | Best For | Example |
|---|---|---|
| **Friendly & casual** | Consumer apps, social, creative tools | "Looks great! Your changes are saved ✓" |
| **Warm & professional** | SaaS, productivity, HR tools | "Your changes have been saved." |
| **Neutral & clinical** | Finance, legal, healthcare, enterprise | "Transaction completed successfully." |
| **Playful** | Games, kids apps, lifestyle | "Woohoo! You crushed it 🎉" |

### Consistency Rules
- If you use contractions ("you're", "we'll") in one place, use them everywhere.
- If you use first person ("Your account") vs second person ("My account") — pick one.
- If you address users casually in empty states, don't suddenly switch to formal language in error messages.
- If your brand is friendly, never let technical/legal copy sneak in with a completely different voice.

---

## Terminology Consistency

One of the most common microcopy mistakes is calling the same concept different things in different parts of the app.

### How to Audit Terminology
Search for synonyms being used for the same thing. Common traps:

| Concept | Don't Mix These |
|---|---|
| The user's workspace | "workspace" / "project" / "team" / "organization" |
| Content items | "task" / "item" / "card" / "ticket" / "to-do" |
| Saving | "save" / "update" / "apply" / "confirm" |
| Removing | "delete" / "remove" / "archive" / "clear" |
| Members | "members" / "users" / "collaborators" / "teammates" |

### Rule: Pick One Word Per Concept
Create a short glossary and stick to it. Every button, label, confirmation, and error message should use the same word for the same thing.

---

## Numbers, Dates & Formatting

- **Dates**: Use unambiguous formats. "Jan 15, 2025" or "15 January 2025" beats "01/15/25" (is that Jan 15 or the 1st of the 15th month?).
- **Relative time**: "2 hours ago" is friendlier than a timestamp for recent events. But always show the full date on hover/focus.
- **Large numbers**: Use commas: 1,000,000 not 1000000. Or abbreviate for dashboards: 1.2M, 45K.
- **Percentages**: "You're 75% done" not "75% complete" — the possessive makes it feel personal.
- **File sizes**: Show in the most readable unit. 1.4 MB not 1,400,000 bytes.

---

## Confirmation & Success Messages

| Action | ✅ Good Confirmation |
|---|---|
| Saved | "Changes saved" or "Project saved" |
| Sent | "Message sent to Jamie" |
| Deleted | "Task deleted" + Undo option |
| Copied | "Link copied to clipboard" |
| Uploaded | "Photo uploaded successfully" |
| Invited | "Invitation sent to alex@example.com" |

### Rules
- **Be specific** — name the thing that was acted on when possible.
- **Offer undo** for destructive or hard-to-reverse actions.
- **Don't celebrate too hard** — "🎉 Amazing! You saved a file!" is patronizing. Save enthusiasm for genuinely exciting moments (first project created, subscription upgraded).

---

## Required Field Legend

If asterisks (*) are used to mark required fields, a visible legend must appear in the form — typically at the top or bottom:

| ✅ Good | ❌ Bad |
|---|---|
| "* Required fields" near the form title | Asterisks with no explanation |
| "Fields marked * are required" | Assuming all users know the convention |

**Rule:** The `*` convention is widely understood but not universal. A legend is always required for accessibility. Absence of a legend is a 🟢 Tip — not critical, but worth flagging at handoff.

---

## Per-Role Text Audit Guide

When auditing text content in a Figma frame or codebase, classify each text node by role before checking it:

| Role | How to Identify | Key Rule |
|---|---|---|
| **CTA button** | Text inside button/interactive component | Must be a verb. "Save", "Submit", "Send Message" |
| **Label** | Text above or beside an input field | Must be visible, not placeholder. Required = * with legend |
| **Placeholder** | Greyed text inside empty input | Format hint only. Never a label replacement |
| **Error message** | Red/warning text near field or at form top | Answers what/why/how. No blame, no tech jargon |
| **Empty state** | Text shown when list/content area is empty | Explains the state + gives a next action |
| **Section header** | Largest text in a content block | Should describe the content, not be decorative |
| **Confirmation/toast** | Success/feedback text after an action | Names the thing acted on. Offers undo if destructive |

**Audit citation format:** Always quote the exact text and include the node ID when available.
`🟡 Button label "Submit" (node 68:27811) — vague verb. Use "Save Changes" or "Send Report" instead.`

---

## Checklist d'audit (déplacée depuis SKILL.md)

> Section déplacée telle quelle depuis [SKILL.md](../SKILL.md).

### CATEGORY 12: Content & Microcopy
*The words inside a UI are part of the design. Read `references/microcopy.md` for full guidance.*

**On Figma/code input: read every text node.** `get_design_context` returns all text content. Extract and check each one — do not guess. Group them by role:

```
Text content extraction (Figma + code):
  1. Collect all text node values from get_design_context
  2. Classify each by role:
     - CTA buttons: text nodes inside button components
     - Labels: text nodes associated with inputs (above/beside)
     - Placeholders: text nodes with placeholder-style content ("Search", "Enter...", "eg:")
     - Error messages: text nodes near error states or with error styling
     - Empty state messages: text nodes in empty/zero-state frames
     - Section headers / titles: largest text nodes in a section
  3. Apply checks per role (see below)
  4. Cite the exact text content and node ID in each issue
     e.g. 🟡 "Placeholder 'eg: 5' (node 68:27994) — informal prefix. Use '0' or unit hint."
```

Per-role checks:
- [ ] **Button labels are verbs** — "Save Changes", "Send Message" not "OK", "Submit", "Yes"
- [ ] **Error messages are human** — "Invalid input" → 🔴. "Please enter a valid email" → ✅
- [ ] **Placeholder ≠ label** — Placeholders hint at format (e.g. "name@example.com"), never replace a label. Flag any placeholder that duplicates its label exactly.
- [ ] **Placeholder prefix style** — "eg:", "e.g." → 🟡 informal. Use the example value directly or a unit label.
- [ ] **Destructive actions are explicit** — "Delete" dialogs should name what's being deleted. "Are you sure?" alone → 🟡
- [ ] **Consistent terminology** — Flag if the same concept uses different words across text nodes (e.g. "workspace" and "project" used interchangeably)
- [ ] **Tone consistency** — Formal in one section, casual in another → 🟡
- [ ] **No lorem ipsum** — Any "lorem ipsum" or "placeholder text" string → 🔴 Critical at Dev handoff or later
- [ ] **Empty states have direction** — "No results found" alone → 🟡. Should include a next action.
- [ ] **Required field legend** — If * is used for required fields, check for a "* Required fields" legend somewhere in the frame. Missing → 🟢 Tip.

**📋 Code input: direct checks available (run these automatically)**
```
Button label audit:
  → Collect all <button>, <a role="button">, and submit-type elements and extract their text content
  → Labels that are not verbs or verb phrases → 🟡 Warning
    ❌ "OK", "Yes", "Submit", "Click here", "More"
    ✅ "Save changes", "Send message", "Get started", "Delete account"
  → Icon-only buttons with no aria-label → 🔴 Critical (also caught by Cat 6, flag once)
  → Button text identical to page heading (no specificity) → 🟡
    e.g. two "Submit" buttons on the same page with no differentiating context

Error message audit:
  → Scan all string literals used in error/validation contexts (near catch blocks, validation fns, error state renders)
  → Strings that are purely technical → 🟡 Warning
    ❌ "Invalid input", "Error 422", "Request failed", "null", "undefined"
    ✅ "Please enter a valid email address", "Something went wrong — try again"
  → Error strings with no guidance on how to fix → 🟡
  → aria-describedby linked error messages (cross-check with Cat 7) → ✅

Placeholder audit:
  → Collect all placeholder="..." attribute values
  → Placeholder that exactly duplicates its label text → 🟡
    e.g. label="Email" + placeholder="Email" — redundant, adds no value
  → Placeholder starting with "eg:", "e.g.", "ex:" → 🟡 informal prefix
    Prefer: use the example directly — placeholder="name@example.com"
  → Placeholder used as sole label (no visible <label>) → 🔴 (also Cat 7)

Lorem ipsum / filler content:
  → Any string containing "lorem ipsum", "placeholder text", "TBD", "TODO", "FIXME" in UI-facing strings → 🔴 Critical at Dev handoff stage
  → Flag line number and surrounding component

Terminology consistency:
  → Collect all nouns used for the same concept across the file
  → If 2+ different terms are used for the same entity → 🟡 Warning
    e.g. "workspace" in one component, "project" in another, "team" in a third — all meaning the same thing

Destructive action copy:
  → Alert/confirm dialogs or delete modal text that reads only "Are you sure?" → 🟡
    Must name the specific thing being deleted: "Delete 'Project Alpha'? This cannot be undone."

Korean report labels for this category:
  → 버튼 라벨 동사 누락 / 오류 메시지 비인간적 / 플레이스홀더 = 라벨 중복 /
     비공식 플레이스홀더 접두사 / 로렘 입숨 발견 / 용어 불일치 / 파괴적 동작 문구 부재
```
