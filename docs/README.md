# Liveblog developer documentation

Reference material for theme and plugin developers integrating with Liveblog.

## End users and editors

* [End user guide](end-user-guide.md) — creating, editing, archiving and styling liveblog posts.

## Developer reference

* [Customization](customization.md) — entry features (`#hashtags`, `/commands`, `@authors`, `:emoji:`), key event templates and formats, restricting shortcodes, overriding templates.
* [Extending the admin meta box](admin-meta-box.md) — adding custom fields and capturing their input.
* [Hooking into entries](entry-hooks.md) — filters for the entry lifecycle (insert, update, preview, edit, JSON output).
* [Auto-archiving](auto-archive.md) — configuring automatic archival of stale liveblogs.
* [WebSocket support](websockets.md) — real-time entry delivery via Socket.IO and Redis.

## Contributing

See [CONTRIBUTING.md](../CONTRIBUTING.md) in the repository root for development setup, code organization, design decisions and how to submit changes.
