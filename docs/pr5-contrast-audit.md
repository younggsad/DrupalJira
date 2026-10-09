# PR5 theme contrast audit

Inspected all theme CSS foreground/background declarations, inherited body colors,
component surfaces, hover/selected states, and text sizes. Applied WCAG 2.2 SC
1.4.3: 4.5:1 for normal text, 3:1 for large text. All listed pairs satisfy the
stricter normal-text threshold after changes; small board labels are normal text.
Reference: https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum

Ratios calculated from sRGB channels: linearize c/255 using c <= 0.04045 ? c/12.92
: ((c + 0.055)/1.055)^2.4, then L = 0.2126R + 0.7152G + 0.0722B;
contrast = (lighter L + 0.05)/(darker L + 0.05). Values below are rounded only
for display; threshold decisions used unrounded values.

| Text / actual surface | Before | After |
| --- | ---: | ---: |
| Primary blue / primary-light #cee0fa (info messages, media selections, assigned cards) | 3.922 | 4.596 |
| Primary blue / secondary-light #e7e7eb (callout links) | 4.263 | 4.995 |
| Primary blue / white #ffffff | 5.257 | 6.160 |
| White / primary blue (buttons) | 5.257 | 6.160 |
| Primary blue / page #f5f5f5 | 4.822 | 5.650 |
| Primary blue / badge #e2f6ff | 4.721 | 5.532 |
| Primary blue / card hover #fcfdfe | 5.162 | 6.049 |
| Primary blue / assigned card hover #f1f7ff | 4.879 | 5.717 |
| Success message / #ecfdf3 | 3.539 | 5.132 |
| Error message / #fef2f2 | 4.417 | 4.779 |
| Secondary #5b5772 / white, page, primary-light, secondary-light | 6.883, 6.313, 5.135, 5.581 | unchanged |
| Muted #5f5f5f / white, page, primary-light, secondary-light | 6.385, 5.857, 4.764, 5.178 | unchanged |
| Board column title #433f5e / #d0cfd7 (10px) | 6.436 | unchanged |
| Board count #3f3f3f / page #f5f5f5 | 9.659 | unchanged |
| Warning #92400e / #fffbeb | 6.837 | unchanged |
| Code #f8fafc / #1e293b | 13.982 | unchanged |

Changed primary blue #0a65e4 to #095bcf and danger #d92d20 to #cf2b1e.
Only success message text changes to #027a48; the success accent token stays.
Primary hover #0852bc passes across the light surfaces (minimum 5.323 on
primary-light). Primary links in success, warning, and error messages pass at
5.841, 5.940, and 5.631 respectively. Body #202020 and heading #140f36 text
pass on all defined light surfaces (minimum 12.156 and 13.608 on primary-light).
The 2% blue table hover overlay on white is lighter than primary-light, so these
text colors also pass there. Badge and hover surfaces likewise pass for muted
and secondary text. Removed dragged-card opacity so its text keeps these ratios;
rotation and shadow remain the drag cues.

Static limitations: user-authored rich text/inline colors, text within uploaded
images, third-party editor/media/toolbar CSS, future externally supplied chart
colors, browser-native controls/autofill, and intermediate animated or overlaid
states require rendered checks against the actual content and cascade. Disabled
controls use opacity and are exempt under SC 1.4.3; this is not a site-wide
compliance claim. No rendered contrast scan was run.

Theme settings inspection found only core features.favicon and favicon settings,
no theme-settings form/hooks, custom defaults, or theme_get_setting calls. Those
keys are defined by core theme_settings; no custom settings schema was added.
