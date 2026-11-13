---
name: The Message Pad
description: Dot.Notify. A company's message desk in ballpoint blue on duplicate-paper white, where the state that applies is the one box ticked.
colors:
  paper: "#f3f5fc"
  sheet: "#ffffff"
  sheet-2: "#e9edf9"
  ink: "#131a3a"
  ink-2: "#3c4569"
  ink-3: "#566086"
  rule: "#d3d9ec"
  rule-strong: "#a9b2d4"
  edge: "#7a84ae"
  pen: "#2438b8"
  pen-strong: "#1a2a94"
  on-pen: "#ffffff"
  pen-wash: "#e3e8fb"
  rail: "#17236d"
  rail-2: "#223087"
  rail-ink: "#f1f3fd"
  rail-ink-2: "#c3caee"
  mark: "#f2c832"
  on-mark: "#131a3a"
  ok: "#1c6a33"
  ok-wash: "#e2f1e3"
  bad: "#ad241c"
  bad-wash: "#fbe7e4"
  warn: "#7d5200"
  warn-wash: "#fbf0d2"
  night-paper: "#0d1130"
  night-sheet: "#141a40"
  night-sheet-2: "#1c2352"
  night-ink: "#edf0fc"
  night-ink-2: "#c4cbec"
  night-ink-3: "#9da7d2"
  night-rule: "#2a3268"
  night-pen: "#b1bcff"
  night-on-pen: "#0d1130"
  night-rail: "#090c25"
typography:
  title:
    fontFamily: "Atkinson Hyperlegible Next, Segoe UI, Helvetica Neue, Arial, sans-serif"
    fontSize: "2.25rem"
    fontWeight: 750
    lineHeight: 1.1
    letterSpacing: "-0.022em"
  heading:
    fontFamily: "Atkinson Hyperlegible Next, Segoe UI, Helvetica Neue, Arial, sans-serif"
    fontSize: "1.1875rem"
    fontWeight: 700
    lineHeight: 1.3
    letterSpacing: "-0.01em"
  lede:
    fontFamily: "Atkinson Hyperlegible Next, Segoe UI, Helvetica Neue, Arial, sans-serif"
    fontSize: "1.0625rem"
    fontWeight: 400
    lineHeight: 1.55
  body:
    fontFamily: "Atkinson Hyperlegible Next, Segoe UI, Helvetica Neue, Arial, sans-serif"
    fontSize: "0.9375rem"
    fontWeight: 400
    lineHeight: 1.5
  small:
    fontFamily: "Atkinson Hyperlegible Next, Segoe UI, Helvetica Neue, Arial, sans-serif"
    fontSize: "0.8125rem"
    fontWeight: 400
    lineHeight: 1.45
  title-narrow:
    fontFamily: "Atkinson Hyperlegible Next, Segoe UI, Helvetica Neue, Arial, sans-serif"
    fontSize: "1.75rem"
    fontWeight: 750
    lineHeight: 1.1
    letterSpacing: "-0.022em"
  figure:
    fontFamily: "Atkinson Hyperlegible Next, Segoe UI, Helvetica Neue, Arial, sans-serif"
    fontSize: "1.375rem"
    fontWeight: 700
    lineHeight: 1
  control:
    fontFamily: "Atkinson Hyperlegible Next, Segoe UI, Helvetica Neue, Arial, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.4
  label:
    fontFamily: "Atkinson Hyperlegible Next, Segoe UI, Helvetica Neue, Arial, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 600
    lineHeight: 1.4
  count:
    fontFamily: "Atkinson Hyperlegible Next, Segoe UI, Helvetica Neue, Arial, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 700
    lineHeight: 1
  code:
    fontFamily: "Atkinson Hyperlegible Mono, ui-monospace, SF Mono, Menlo, Consolas, monospace"
    fontSize: "0.875rem"
    fontWeight: 500
    lineHeight: 1.4
rounded:
  tick: "2px"
  control: "4px"
  sheet: "8px"
  switch: "11px"
spacing:
  row: "0.75rem"
  gutter: "2.5rem"
  section: "3rem"
components:
  button-primary:
    backgroundColor: "{colors.pen}"
    textColor: "{colors.on-pen}"
    rounded: "{rounded.control}"
    padding: "0 14px"
    height: "36px"
  button-primary-hover:
    backgroundColor: "{colors.pen-strong}"
  button-next-step:
    backgroundColor: "{colors.pen}"
    textColor: "{colors.on-pen}"
    rounded: "{rounded.control}"
    padding: "0 22px"
    height: "48px"
  button-ghost:
    backgroundColor: "{colors.sheet}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    padding: "0 14px"
    height: "36px"
  input:
    backgroundColor: "{colors.sheet}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    padding: "8px 12px"
    height: "40px"
  slip:
    backgroundColor: "{colors.sheet}"
    textColor: "{colors.ink}"
    rounded: "{rounded.sheet}"
    padding: "1.25rem 1.375rem"
  status-sent:
    backgroundColor: "{colors.ok-wash}"
    textColor: "{colors.ok}"
    rounded: "{rounded.control}"
  status-failed:
    backgroundColor: "{colors.bad-wash}"
    textColor: "{colors.bad}"
    rounded: "{rounded.control}"
  nav-item-active:
    backgroundColor: "{colors.rail-ink}"
    textColor: "{colors.rail}"
    rounded: "{rounded.control}"
---

# Design System: Dot.Notify, The Message Pad

Written 2026-11-14 from the built home page and shell (`resources/css/notify.css`, the single source of every
value here). The direction was chosen without the owner in the room, from the owner's brief ("clear and
dependable", its own look, light and dark, no borrowed control-room darkness). It is the owner's to change.

## Overview

The office message pad: someone takes a message, writes who it is for and what it is about, ticks one box for
what happened, and the copy stays in the book. Dot.Notify does that job for a company's Dot apps, so the pad
lends the product four things and nothing else.

- **Palette.** Ballpoint blue on cool duplicate-paper white. The rail is the pad's deep blue cover in both
  schemes. Highlighter yellow, the yellow of the logo, appears in exactly one place: the box that is ticked.
- **Type.** Atkinson Hyperlegible Next, a face drawn so that no letter can be mistaken for another, for a
  product whose whole job is that the message is read. Its Mono sets event names (`task.assigned`) and other
  code only.
- **Density.** Ruled rows, not cards. A section is a heading over hairline-ruled rows on the page itself.
- **Signature move: the tick row.** The latest message is shown as a slip (To, About, Event, Through) over a
  row of six boxes, one for each status the log can really hold, with only the one that applies ticked.
  Elsewhere the same idea is a small mark and a word: tick for sent or active, cross for failed, clock for
  waiting or retrying, dash for off.

The scene is a desk in daylight, a few times a week, often because someone says they never got the email.
Light is the default. Dark is the same pad under a lamp: deep blue-black paper, never neutral black, with the
pen lightened and the primary button inverted.

The layout, navigation and controls are the ordinary ones of a working web app (side rail, top bar, buttons,
selects, inputs). The pad never becomes a costume: no torn edges, no handwriting, no paper texture.

## Colors

Restrained on the page, committed in the shell.

### Primary
- **Pen** `#2438b8` (dark scheme `#b1bcff`): the primary button, links on hover, the slip's head rule, the
  switched-on state of a switch. `pen-strong` is its hover.

### Secondary
- **Rail** `#17236d` (dark `#090c25`): the sidebar only. Its active item is the inverse (rail ink ground, rail
  text); there is no coloured side bar on an item.

### Tertiary
- **Mark** `#f2c832`: the fill of a ticked box, text selection, and the focus ring in the dark scheme. Never a
  button, a background or a decoration.

### Neutral
- **Paper** `#f3f5fc` is the page; **sheet** `#ffffff` is anything written on (the slip, a form, an input);
  **sheet-2** is a quiet inset. **Ink**, **ink-2**, **ink-3** are text in falling importance; ink-3 is the
  floor and still clears 4.5:1 on paper and sheet. **Rule** and **rule-strong** are the hairlines; **edge** is the
  outline of a control (input, ghost button, tick box) and clears 3:1.
- **Ok / bad / warn**, each with a wash, are for states and are always accompanied by a mark and a word.

### Named Rules
- **The One Tick Rule.** Yellow means "this is the state that applies" and nothing else.
- **The Tinted Neutral Rule.** Every neutral leans blue. No pure grey, no pure black.
- **The Token Rule.** No colour or face is written outside the token blocks of `notify.css`.

## Typography

One family. Fixed rem sizes, no fluid type.

### Hierarchy
- **Title** 2.25rem / 750 (1.75rem on phones): the home page's one headline.
- **Heading** 1.1875rem / 700: each section.
- **Lede** 1.0625rem: the welcome paragraph and the finding sentence (the finding is 650).
- **Body** 0.9375rem; **small** 0.8125rem for row detail and help. Nothing is set below 0.75rem.
- **Figure** 1.375rem / 700 tabular: the record line. **Label** 0.875rem / 600: field labels and buttons.
  **Control** 1rem: inputs on phones, so iOS does not zoom. **Count** 0.75rem: the unread count only.
- **Code** 0.875rem Mono: event names, JSON conditions.

### Named Rules
- **Sentence case, untracked.** No uppercase tracked labels, no label above a heading.
- **Figures are tabular** wherever numbers line up or change.

## Layout

A fixed 248px rail and a 56px top bar; content in a centred column capped at 80rem with 2.5rem gutters.
Below 860px the rail becomes a drawer; below 1100px two-column regions stack. Sections are 3rem apart and
separated by space and a rule, never by a box. Touch targets are 44px on narrow screens; inputs are 16px
there so iOS does not zoom.

The home page reads in this order: logo and the way back to InfoDot; headline, welcome, the finding and the
one next step beside the latest-message slip; the record so far as one ruled line of figures; then the log,
bulk sends, channels and templates side by side, and rules.

## Elevation & Depth

Flat. Surfaces are separated by a tone step or a 1px rule. One shadow exists (`--lift`), for the notification
panel that really floats over the page.

## Shapes

4px on controls and status marks, 8px on sheets (the slip, a form, a floating panel). Tick boxes are 2px.
The one rounded track is a switch (11px, half its height). No pill buttons or pill badges. Icons are drawn inline on a 24px grid at one 1.7 stroke.

## Components

### Buttons
- **Primary** (`.dot-btn-primary`): pen fill, 36px (40px with `.btn-tall`, 44px on phones). One per form.
- **Next step** (`.nf-primary`, carries `data-primary-action`): the same fill at 48px. Exactly one per page,
  only on the home page.
- **Ghost** (`.dot-btn-ghost`): sheet fill, strong rule. Row actions use `.dot-btn-small`; a destructive row
  action is underlined red text (`.row-action`).

### Inputs / Fields
`.dot-input` with a visible `<label class="nf-label">`; errors in `.nf-error` under the field with
`aria-invalid` on it. Focus is a 2px ring, shown at once.

### Navigation
The rail lists pages; the top bar holds the page title, the company, notifications, the theme toggle and the
profile link. The home page adds a plain "On this page" list of links.

### The slip
`.nf-slip`: a sheet with a 2px pen rule under its head, dotted rules between fields, then `.nf-ticks`. With no
message it keeps its frame and says so in a sentence.

### Status
`<x-status kind label>`: mark and word on a wash. Kinds: `sent`, `on`, `failed`, `retrying`, `waiting`, `off`.

### Rows
`.dot-row` inside `.nf-rows`: title, one line of detail in words, state and actions at the right. Empty
lists are one sentence in `.nf-empty`, between the same rules a list would have.

## Do's and Don'ts

### Do:
- Say what the record shows, in a sentence: "3 sent, the last 2 hours ago", "Nothing sent through it yet".
- Offer one next step, chosen from the company's real state, and only to someone allowed to take it.
- Keep every state as a mark and a word.
- Check both schemes; the logo on the page is `logo.png` in light and `logo-light.png` in dark, unaltered.

### Don't:
- Don't build metric tiles, cards in a grid, a coloured side stripe, gradient text, glass, or a label above a
  heading.
- Don't use yellow for anything but the tick, selection and the dark focus ring.
- Don't show a number, time or health claim that is not read from a record. Dot.Notify never says
  "delivered" or "opened".
- Don't load a font, icon font or stylesheet from another host.
- Don't borrow another Dot platform's identity or the dark control-room look of the internal tools.
