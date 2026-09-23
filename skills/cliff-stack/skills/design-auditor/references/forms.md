# Forms & Inputs Reference

> Référence du skill design-auditor, chargée à la demande depuis [SKILL.md](../SKILL.md). Contenu déplacé tel quel depuis SKILL.md.

### CATEGORY 7: Forms & Inputs

- [ ] **Label placement** — Labels above inputs (not beside or inside). Fastest to scan.
- [ ] **Input sizing** — Wide enough to show typical content.
- [ ] **Required field marking** — Asterisk (*) with legend, or label optional fields instead.
- [ ] **Validation timing** — Validate on blur (leaving field), not only on submit.
- [ ] **Error placement** — Error messages directly below the relevant field.
- [ ] **Field grouping** — Related fields visually grouped (less space within, more between groups).
- [ ] **Submit button state** — Loading state while submitting. Disable after first click.

**📋 Code input: direct checks available (run these automatically)**
```
input type correctness:
  → <input type="text"> for email → 🟡 should be type="email"
  → <input type="text"> for password → 🔴 should be type="password"
  → <input type="text"> for phone → 🟡 should be type="tel"
  → <input type="text"> for numbers → 🟡 should be type="number" or inputMode="numeric"
  → <input type="text"> for URLs → 🟡 should be type="url"
  → <input type="submit"> instead of <button type="submit"> → 🟢 Tip (button is more styleable)

autocomplete attributes:
  → Name fields missing autocomplete="name" / autocomplete="given-name" → 🟡
  → Email fields missing autocomplete="email" → 🟡
  → Password fields missing autocomplete="current-password" or "new-password" → 🟡
  → Credit card fields missing autocomplete="cc-number" etc. → 🟡
  → Cite the field and the missing value

required + aria-required:
  → <input required> without aria-required="true" → 🟢 Tip (redundant but explicit for AT)
  → Required inputs with no visual indicator (* or "(required)" label) → 🟡

aria-describedby for error messages:
  → Error message element exists but not linked via aria-describedby on its input → 🟡
  → Correct: <input aria-describedby="email-error"> ... <span id="email-error">...</span>

fieldset + legend for grouped inputs:
  → Radio groups or checkbox groups without <fieldset><legend> → 🟡
  → Only one radio/checkbox option → skip this check

inputMode for mobile keyboards:
  → Numeric inputs missing inputMode="numeric" or inputMode="decimal" → 🟢 Tip
  → This triggers the correct soft keyboard on mobile

novalidate + custom validation:
  → <form> without novalidate when custom validation JS exists → 🟡 (browser + custom = double errors)
  → <form novalidate> with no custom validation JS visible → 🟡 (validation silently disabled)

disabled vs readonly:
  → <input disabled> when the intent is read-only display → 🟢 Tip
    (disabled excludes from form submission; readonly keeps the value but prevents editing)
```

---
