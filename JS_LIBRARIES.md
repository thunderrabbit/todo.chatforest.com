# JavaScript libraries

The site has one third-party library, loaded from a CDN. It has no build
step, no npm, and no import map or `import` statements.

## Third-party

### SortableJS

- **Version**: 1.15.0 (from the URL path)
- **Loaded at**: `templates/layout/admin_base.tpl.php:10`, as
  `src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"`
  with `integrity="sha384-eeLEhtwdMwD3X9y+8P3Cn7Idl/M+w8H4uZqkgD/2eJVkWIN1yKzEj6XegJ9dL3q0"` and `crossorigin="anonymous"`
  (admin pages only; `templates/layout/base.tpl.php` does not load it)
- **How**: CDN (cdnjs)
- **Needs**: nothing
- **Needed by**: `wwwroot/js/todo-form.js`, which uses `new Sortable(...)`
  for drag and drop (guarded by `typeof Sortable !== "undefined"`).
  `wwwroot/css/styles.css` styles its `sortable-ghost` and
  `sortable-drag-over` classes.
- **Latest**: 1.15.7 on npm (`https://registry.npmjs.org/sortablejs/latest`)
  and on cdnjs (`https://api.cdnjs.com/libraries/Sortable`), looked up
  2026-11-24. It needs no other library, so the newest release is also the
  newest that fits.

## The site's own scripts

- `wwwroot/js/timezone.js`: shows the browser's timezone name in the
  `.timezone-display` element. Loaded as `src="/js/timezone.js"` at
  `templates/layout/base.tpl.php:25` and
  `templates/layout/admin_base.tpl.php:28`.
- `wwwroot/js/todo-form.js`: behaviour of the todo form (including the
  SortableJS drag and drop). PHP writes the tag, at
  `classes/Todo/TodoRenderer.php:171`, as
  `'<script src="/js/todo-form.js"></script>'`.
- Inline `<script>` block in `templates/admin/migrate_tables.tpl.php:2`:
  `applyMigration()`, which posts to `/admin/apply_migration.php`.

## Notes for later steps

- SortableJS is pinned to an exact version on a CDN path that still serves
  newer releases (1.15.7 is on cdnjs); no path change is needed to move up.
- The CDN script carries an `integrity` sha384 hash and `crossorigin="anonymous"`; a version change needs a new hash.
- Nothing depends on jQuery; no build step exists.
