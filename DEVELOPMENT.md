# Development Guidelines - Politeia Learning Plugin

## Google Material Symbols (Icons)

### Prevent Flash of Unstyled Text (FOUT)
When using Google Material Symbols with ligatures (e.g., `<span class="material-symbols-outlined">person_add</span>`), the browser may display the literal text `person_add` for a microsecond before the icon font loads.

To prevent this, **always use `display=block`** in the Google Fonts URL. This instructs the browser to hide the text for a short period until the font is ready.

**Correct URL Example:**
`https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block`

**Avoid:**
Using `&display=swap` for icon fonts, as it explicitly tells the browser to show the fallback text immediately.

### Prevent Layout Shift (FOUT)
Even with `display=block`, a long ligature name (like `person_add`) can occupy more horizontal space than the final icon, causing the layout to "jump" or "shrink" once the icon loads.

To prevent this, **always set a fixed width** on the icon container:

```css
.material-symbols-outlined {
    display: inline-flex;
    width: 1em;
    height: 1em;
    overflow: hidden;
}
```

This ensures the icon always occupies exactly `1em` of space, clipping the wide ligature text while the font is still loading.

---

*This rule is enforced across the entire plugin codebase.*
