# Website Design — bavarianrankengine.com

**Date:** 2026-02-21
**Author:** Michael Fuchs / Donau2Space
**Scope:** Static one-pager + 2 subpages (changelog, impressum)

---

## Overview

A static HTML/CSS/JS marketing website for the Bavarian Rank Engine WordPress plugin. Dark theme with a subtle Bavarian touch (Rauten pattern from the Bavarian flag). Text-only — no images or logos. Target audience: English-speaking bloggers. Tone: confident, slightly humorous, developer-honest.

---

## File Structure

```
website/
├── index.html
├── changelog.html
├── impressum.html
├── css/
│   └── style.css
├── js/
│   └── main.js
└── fonts/
    ├── playfair-display/
    │   ├── PlayfairDisplay-Bold.woff2
    │   ├── PlayfairDisplay-BoldItalic.woff2
    │   └── PlayfairDisplay-Regular.woff2
    ├── inter/
    │   ├── Inter-Regular.woff2
    │   ├── Inter-Medium.woff2
    │   └── Inter-SemiBold.woff2
    └── jetbrains-mono/
        ├── JetBrainsMono-Regular.woff2
        └── JetBrainsMono-Medium.woff2
```

---

## Design Tokens

| Token | Value |
|---|---|
| Background | `#0d0d0d` |
| Surface | `#111111` |
| Surface Deep | `#070707` |
| Border | `#1e1e1e` |
| Text Primary | `#f5f5f5` |
| Text Secondary | `#999999` |
| Accent Blue | `#0057a8` (Bavarian blue) |
| Accent Blue Light | `#4d9de0` |
| Font Display | Playfair Display (self-hosted) |
| Font Body | Inter (self-hosted) |
| Font Code | JetBrains Mono (self-hosted) |

---

## Typography

All fonts self-hosted as `.woff2` in `website/fonts/`. No external font requests (DSGVO compliance). `@font-face` declarations in `style.css`.

- **Headlines:** Playfair Display Bold / Bold Italic — punchy, premium feel
- **Body:** Inter Regular / Medium / SemiBold — clean, readable
- **Code blocks:** JetBrains Mono — for KeyVault and wp-config.php snippets

---

## Navigation

Sticky top nav, transparent → solid `#0d0d0d` on scroll (JS scroll listener).
Left: `</> Bavarian Rank Engine` (text logo, Playfair Display)
Right: `Features · Security · Get It` (anchor links)
Mobile: links collapse into a simple toggle menu (no hamburger icon library — pure CSS/JS).

---

## Page Sections (index.html)

### [1] Hero

Full-viewport-height section.

**Top:** Bavarian Rauten band — CSS-only repeating diamond pattern in `#0057a8`, 2px height, decorative.

**Headline (two lines):**
```
Not again another
WordPress SEO plugin.
```
Font: Playfair Display Bold, ~88px desktop / scaled mobile. Color: `#f5f5f5`.

**Subline (italic, accent blue):**
```
Well. Kind of.
```
Font: Playfair Display Bold Italic, same size, color `#4d9de0`.

**Separator line:** 1px `#0057a8`.

**Body copy:**
```
AI meta descriptions. Schema.org. llms.txt.
No subscription. No premium tier.
Pay your AI provider directly — not us.
```
Font: Inter Regular, 20px.

**CTAs:**
- Primary button: `↓ Download for WordPress` → links to WordPress.org plugin page (placeholder URL until published)
- Ghost button: `View on Git` → `https://git.donau2space.de`

**Bottom:** Second Rauten band.

---

### [2] The Pitch

Two-column layout (stacks on mobile).

**Left column** — personal note, smaller text, italic:
> "Made in Bavaria, vibe-coded for Donau2Space.de — my own AI blog. This plugin scratches my own itch."
> — Michael

**Right column** — sales copy:
> There are plenty of SEO plugins for WordPress. Most of them cost a monthly fee. Some cost a lot.
>
> Bavarian Rank Engine is free. Forever. The only cost: your AI API calls — billed directly by your provider. Typically less than any plugin subscription.
>
> It won't get your site to #1 overnight. It's a tool. A good one.

---

### [3] Features

Section heading: `What it does` (Playfair Display).

6-card grid: 3 columns desktop / 2 tablet / 1 mobile.
Cards: `background: #111111`, `border: 1px solid #1e1e1e`, `border-radius: 8px`, padding 24px.
Card accent: `◆` in `#0057a8` before each title.

| Card | Title | Description |
|---|---|---|
| 1 | AI Meta Generator | Auto-writes 150–160 char meta on publish. Custom prompt with placeholders. Fallback extraction without API key. |
| 2 | Bulk Generator | Batch your entire back-catalogue. Live progress log. Running cost estimate. Rate-limited, retry logic. |
| 3 | Schema.org (GEO) | JSON-LD for AI and search engines — Organization, Article, Author, Speakable, BreadcrumbList. |
| 4 | llms.txt | Machine-readable content index for AI systems. ETag caching, pagination, custom sections. |
| 5 | robots.txt Manager | Block 13 known AI training crawlers individually. GPTBot, ClaudeBot, Bytespider, and more. |
| 6 | Crawler Log | See which AI bots are visiting. SHA-256 hashed IPs, 90-day auto-purge. |

Two additional smaller feature pills below the grid (inline badges):
- `Post Editor Meta Box` — inline AI regeneration, character counter
- `SEO Analysis Widget` — live word count, heading structure, link warnings
- `Link Analysis Dashboard` — pillar pages, internal link gaps
- `Multi-Provider` — OpenAI · Anthropic · Gemini · Grok

---

### [4] API Key Security

Full-width section, background `#070707` (slightly darker to distinguish).

Heading: `Your keys. Your control.`

Two-column: explanation left, code block right.

**Left:**
> API keys are obfuscated before database storage using XOR encryption with a key derived from your WordPress authentication salts. They never appear in plain text in database dumps or export files.
>
> For maximum security: define keys in `wp-config.php`. The plugin uses them automatically — nothing stored in the database.

**Right (code block, JetBrains Mono, `#111` bg, `#0057a8` border-left):**
```
// Stored format
bre1:<base64(xor(key, sha256(AUTH_KEY + SALT)))>

// Admin UI always shows masked:
••••••Ab3c9

// wp-config.php alternative:
define('BRE_OPENAI_KEY',    'sk-...');
define('BRE_ANTHROPIC_KEY', 'sk-ant-...');
define('BRE_GEMINI_KEY',    'AI...');
define('BRE_GROK_KEY',      'xai-...');
```

---

### [5] Pricing / Cost Section

Centered, large type.

**Headline:** `Free. Forever.` (Playfair Display, large)

**Subline:**
> The plugin costs nothing. No subscription, no premium tier, no license key.
>
> AI features use your own API key, billed directly by your provider.
> A meta description costs roughly 1,500 tokens. With GPT-4o mini: ~$0.001 per post.
> 1,000 posts ≈ $0.50–$1.00 — less than most monthly plugin subscriptions.

**Supported providers (4 inline pills):**
`OpenAI` · `Anthropic` · `Google Gemini` · `xAI Grok`

---

### [6] Get It

Centered CTA section.

**Heading:** `Ready to try it?`

Three buttons:
1. `↓ Download on WordPress.org` — primary, blue fill (placeholder href)
2. `git.donau2space.de` — ghost button
3. `missioncontrol.donau2space.de` — ghost button, labeled "Support Forum"

**Requirements line:**
`WordPress 6.0+  ·  PHP 8.0+  ·  GPL-2.0-or-later`

---

### [7] Footer

Simple dark footer.

Left: `Bavarian Rank Engine — by Donau2Space.de`
Right: `Changelog · Impressum & Datenschutz`

---

## Subpages

### changelog.html

- Standard page layout (nav + footer matching index)
- Full changelog from readme.txt, styled as timeline or simple version blocks
- Indexable

### impressum.html

- `<meta name="robots" content="noindex, nofollow">` in `<head>`
- Contains: Impressum (§ 5 TMG), Datenschutzerklärung (Matomo cookieless)
- Matomo opt-out widget embedded
- No nav links back to main (footer still present)

---

## Analytics

Matomo tracking code (self-hosted at `data.donau2space.de`, Site ID 2) in `<head>` of `index.html` and `changelog.html`. **Not** on `impressum.html`.

Matomo opt-out script embedded in the Datenschutz section of `impressum.html`.

---

## SEO

- `index.html`: full meta title + description, Open Graph tags
- `changelog.html`: own title/description
- `impressum.html`: `noindex, nofollow`
- No sitemap needed (3 pages)

---

## JavaScript (main.js)

- Nav scroll behavior: add `.scrolled` class on `window.scroll > 50`
- Mobile nav toggle
- Smooth scroll for anchor links
- No frameworks, no dependencies — vanilla JS only

---

## Fonts (Self-Hosted, DSGVO)

Download sources:
- Playfair Display: https://fonts.google.com/specimen/Playfair+Display (download ZIP)
- Inter: https://rsms.me/inter/ (download ZIP)
- JetBrains Mono: https://www.jetbrains.com/legalforms/fonts/ (open source)

Only the weights actually used are included in the repo to keep size minimal:
- Playfair Display: 700, 700 Italic
- Inter: 400, 500, 600
- JetBrains Mono: 400

---

## Impressum Data

**Angaben gemäß § 5 TMG:**
Michael Fuchs
Vornholzstraße 121
94036 Passau
Deutschland

**Kontakt:**
Telefon: 0851 20092730
E-Mail: kontakt@donau2space.de

**Datenschutz:** Matomo cookieless, self-hosted at `stats.donau2space.de` / `data.donau2space.de`, Site ID 2, 90-day data retention, DNT respected, no third-party data transfer.
