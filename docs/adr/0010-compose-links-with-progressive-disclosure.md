# Compose links with progressive disclosure

The create-link drawer opens on just the two things every link needs — a destination URL (hero input with favicon feedback) and the short URL as a composed domain/slug segment control. Everything optional (activation, expiration, visit limit, password, folder, fallback, tags) starts as a chip and expands into an inline row only when added, so the common case stays a two-field form while power options remain one click away. This shape won a three-variant UI prototype (composer chips vs. rail-navigated sections vs. live-preview accordion) evaluated in place on the Links page.

Form controls that browsers render natively but inconsistently — date-times, tag lists, numeric limits, password reveal, toggles — are custom components under `Components/ui` (`DateTimeField`, `TagInput`, `StepperInput`, `PasswordInput`, `Switch`) styled with the app's dark token palette. They exchange the same wire formats the backend already accepts (`YYYY-MM-DDTHH:mm` local strings, comma-separated tags), so the crafted inputs are purely a presentation change.

The edit drawer follows the same progressive-disclosure grammar (shared `OptionRow` and `OptionChips` components): settings already present on the link open expanded, everything else waits as a chip, and removing a row clears the setting on save. Create and edit therefore read as the same surface.
