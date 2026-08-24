# Feature Document Creation Rules

## Purpose

These documents are **sales enablement materials** — written for potential clients, not developers. Every document must make the reader feel excited, impressed, and convinced that this application will make their work dramatically easier.

## Golden Rule

> **Don't describe what the feature does. Describe what the feature does FOR THE READER.**

Wrong: "The system queries LDAP and HRMS databases simultaneously and returns JSON which is rendered as tabbed cards."
Right: "Type an ID. See everything. In under a second."

---

## Document Structure (Template)

Every feature document MUST follow this structure:

### 1. Title
A short, powerful headline that states the core benefit.
- Format: `# [Feature Name] — [Emotional Benefit]`
- Example: `# Get User Info — Your Instant Window Into Any User`
- Must be under 15 words

### 2. Opening Hook ("Imagine This")
2-4 lines that paint a relatable pain-point scenario. Use everyday language. Never use technical terms here.

### 3. "What If..." Section
A rhetorical question that builds curiosity, followed by the core value proposition. Include a **visual preview** (formatted as plaintext/code block showing the output in an appealing way).

### 4. Why This Changes Everything
Compare "before" and "after" using this feature. Use a table with concrete time/effort comparisons.

### 5. The "Wow" Features (2-4 subsections)
Each subsection highlights one powerful capability. Use headings like:
- "Find Anyone, Even When..."
- "Handle Entire Teams at Once"
- "Spot Issues Before They Become Problems"

Each one must:
- Be framed as a benefit to the reader
- Include a short, vivid example
- Use **bold** for emotional triggers

### 6. Real Stories / Testimonials (Optional but Powerful)
2-3 short quotes from fictional/persona-based users showing real value. Use format:
> *"Quote describing a real situation and how the feature saved time/solved a problem."*
> — Persona, Organization Type

### 7. "What's In It For You" Table
A clean, scannable table mapping benefits to outcomes. Use short phrases, not sentences.

### 8. Closing Summary
A short, punchy paragraph that restates the emotional value. End with a memorable one-liner.

### 9. Call to Action (Optional)
"How to get started" — simple instructions to try the feature immediately.

---

## Writing Style Rules

| Rule | Example |
|------|---------|
| **No technical jargon** | Say "finds users" not "queries LDAP with fuzzy matching" |
| **No architecture details** | Never mention APIs, JSON, LDAP, databases, etc. |
| **No code or commands** | Never show PHP, JS, SQL, or shell commands |
| **Short sentences** | Max 20 words per sentence. Break long thoughts. |
| **Active voice** | "The system shows you" not "The information is displayed to you" |
| **You-focused** | "You type an ID" not "The user enters an identifier" |
| **Emotional language** | "Amazing", "instant", "powerful", "effortless", "beautiful" |
| **Concrete numbers** | "In under 1 second" not "very fast" |
| **Before/after contrasts** | "Before: 30 seconds across 3 tools. After: 1 click." |
| **No feature lists** | Don't say "Features: A, B, C". Instead say "With this, you can A, B, and C — instantly." |

### Words to NEVER use

❌ backend, frontend, API, endpoint, JSON, LDAP, AD, PowerShell, script, query, database, server, protocol, framework, library, function, method, parameter, config, deployment, integration, module, REST, CRUD, schema, cache, thread, process, daemon, cron

### Words to ALWAYS use

✅ instant, one-click, simple, powerful, beautiful, smart, automatic, intelligent, effortless, seamless, complete, unified, real-time, fast, intuitive, easy, amazing, impressive, professional

---

## Formatting Rules

### Headings
- `# Title` — only one per document (the feature name)
- `## Section` — for major sections (Opening, Comparison, Benefits, etc.)
- `### Subsection` — for specific capability highlights

### Tables
- Keep tables simple: max 3 columns
- First column: short label
- Second column: benefit description
- No merged cells, no complex formatting

### Lists
- Use `-` not `*`
- Max 7 items per list
- Each item should be a complete thought

### Emphasis
- **Bold** for keywords and emotional triggers only
- Never use italics for emphasis (reserved for quotes)
- Never use ALL CAPS except in comparison tables

### Quotes
```
> *"Quote text here"*
> — Person Name, Title
```

### Visual Previews
When showing output, use a **plaintext code block** with a clean, formatted view. Never show actual code.

---

## How to Write the Opening Hook

The opening hook is the most important part. It must make the reader think "Yes, I have this problem."

Formula:
1. Set a relatable scene (2-3 short sentences)
2. Name the pain (what's hard/frustrating/slow)
3. Promise the solution (what this feature changes)

Example:
> You're at your desk. An employee calls — they can't log in. You don't open AD Users & Computers. You don't call HR for their records. You don't switch between three different tools.
>
> **You type one ID. Press one button. And everything appears.**

---

## Directory & Naming

- All feature documents go in: `/docs/client/features/`
- Filename format: `FEATURE_NAME_IN_CAPS.md`
- Example: `GET_USER_INFO.md`, `BULK_IMPORT.md`, `PASSWORD_RESET.md`
- Use underscores, not spaces or hyphens
- All caps for consistency with existing docs

---

## Review Checklist

Before marking a document complete, verify:

- [ ] Would this excite a non-technical business decision-maker?
- [ ] Does the opening hook create an emotional connection?
- [ ] Are there ZERO technical implementation details?
- [ ] Is every sentence written from the reader's perspective?
- [ ] Are there concrete numbers and comparisons?
- [ ] Does it pass the "so what?" test on every paragraph?
- [ ] Would someone reading this feel confident showing it to their boss?
- [ ] Does it end with a clear, memorable takeaway?
- [ ] No jargon, no code, no architecture
- [ ] Follows the template structure (hook → what-if → comparison → wow features → benefits → close)

---

## Example Transformation

**Technical version (DON'T write this):**
> The Info endpoint calls ldap_user_repository_find() which returns a JSON payload containing SamAccountName, DisplayName, MemberOf, and UAC status. The frontend then renders buildTabbedCard() with two tabs.

**Sales version (DO write this):**
> Type an ID. See their AD account, groups, status, and HR record — all at once. One click. Complete picture. No tool switching.

---

*Last updated: June 2026*
