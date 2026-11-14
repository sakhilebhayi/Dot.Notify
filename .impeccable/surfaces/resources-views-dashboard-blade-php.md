---
version: 1
slug: "resources-views-dashboard-blade-php"
primary_target: "resources/views/dashboard.blade.php"
related_targets: []
---

# Home (/dashboard)

Scope: the signed-in home page of Dot.Notify and the shell tokens it shares. Mode: Operate.
Audience and job: a company owner or administrator checking that people are being told, or setting that up for
the first time; read-only members checking the record.
One action per state: no channel, add a channel; channels but no rule on, switch on events (Connected apps);
sending, read the log (failed first when something failed today). Read-only members never get a write action.
Content: real records only. Constraint: the four Livewire managers keep their behaviour.

## Direction contract

THESIS: the page is a message desk that always knows the next step and shows the last message handed over as
proof. It refuses the admin page of five equal metric tiles over stacked dark cards.

OWN-WORLD: The Message Pad. Ballpoint blue on cool duplicate-paper white; a deep blue rail in both themes;
highlighter yellow used only to tick the state that applies; hairline-ruled rows instead of cards; Atkinson
Hyperlegible Next for everything, its Mono for event names only.

STORY: the visitor sees their company named, reads what the product does in one sentence, sees the one thing
to do next, and can check the newest message and today's record without scrolling.

FIRST VIEWPORT: real logo top left, InfoDot and Platforms top right. Left two thirds: h1, one paragraph with
the welcome, the finding sentence, the single filled button. Right third: the latest message as a slip with a
six-box tick row, one box ticked. Under both: today's record as one ruled line of figures.

FORM: office telephone message pad (candidate 6 of 7; seed a53be1c7). Signature move: the tick row.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict,
DESIGN.md, and every shipping raster carrying its provenance
