# WP-CLI Commands

The Liveblog plugin provides a comprehensive set of WP-CLI commands for managing liveblogs from the command line.

## Quick Reference

| Command | Description |
|---------|-------------|
| `wp liveblog list` | List all liveblogs |
| `wp liveblog status <id>` | Show detailed status of a liveblog |
| `wp liveblog stats` | Show overall statistics |
| `wp liveblog enable <id>` | Enable liveblog on a post |
| `wp liveblog archive <id>` | Archive a liveblog |
| `wp liveblog unarchive <id>` | Unarchive a liveblog |
| `wp liveblog disable <id>` | Disable liveblog on a post |
| `wp liveblog entries <id>` | List entries for a liveblog |
| `wp liveblog add <id> <content>` | Add a new entry |
| `wp liveblog archive-old` | Bulk archive inactive liveblogs |
| `wp liveblog fix-archive` | Repair archive data inconsistencies |

---

## Listing & Status Commands

### `wp liveblog list`

List all liveblog posts with their current state.

**Options:**

| Option | Description | Default |
|--------|-------------|---------|
| `--state=<state>` | Filter by state: `enabled`, `archived`, or `all` | `all` |
| `--format=<format>` | Output format: `table`, `json`, `csv`, `ids` | `table` |

**Examples:**

```bash
# List all liveblogs
wp liveblog list

# List only enabled liveblogs
wp liveblog list --state=enabled

# Get just the IDs for scripting
wp liveblog list --format=ids

# Export as JSON
wp liveblog list --format=json
```

**Output columns:** ID, title, state, entries, last_updated

---

### `wp liveblog status <post_id>`

Show detailed information about a specific liveblog.

**Arguments:**

| Argument | Description | Required |
|----------|-------------|----------|
| `<post_id>` | The post ID of the liveblog | Yes |

**Options:**

| Option | Description | Default |
|--------|-------------|---------|
| `--format=<format>` | Output format: `table`, `json`, `yaml` | `table` |

**Examples:**

```bash
# Show status of liveblog 123
wp liveblog status 123

# Output as JSON for parsing
wp liveblog status 123 --format=json
```

**Information displayed:**
- Post ID and title
- Current state (enabled/archived)
- URL
- Total entry count
- Key event count
- Unique author count
- First and last entry dates
- Auto-archive expiry date
- Created and last modified dates

---

### `wp liveblog stats`

Show overall statistics for all liveblogs on the site.

**Options:**

| Option | Description | Default |
|--------|-------------|---------|
| `--format=<format>` | Output format: `table`, `json`, `yaml` | `table` |

**Examples:**

```bash
# Show overall stats
wp liveblog stats

# Output as JSON
wp liveblog stats --format=json
```

**Metrics displayed:**
- Total liveblogs (enabled + archived)
- Enabled count
- Archived count
- Total entries
- Key events
- Unique authors
- Average entries per liveblog
- Most active liveblog
- Most recent entry date

---

## State Management Commands

### `wp liveblog enable <post_id>`

Enable liveblog functionality on a post. This allows new entries to be added and displays the liveblog interface on the post.

**Arguments:**

| Argument | Description | Required |
|----------|-------------|----------|
| `<post_id>` | The post ID to enable liveblog on | Yes |

**Examples:**

```bash
# Enable liveblog on post 123
wp liveblog enable 123
```

---

### `wp liveblog archive <post_id>`

Archive a liveblog, making it read-only. Entries are still displayed but new entries cannot be added.

**Arguments:**

| Argument | Description | Required |
|----------|-------------|----------|
| `<post_id>` | The post ID of the liveblog to archive | Yes |

**Examples:**

```bash
# Archive liveblog 123
wp liveblog archive 123
```

---

### `wp liveblog unarchive <post_id>`

Unarchive a liveblog, re-enabling it for new entries. This is the opposite of archiving.

**Arguments:**

| Argument | Description | Required |
|----------|-------------|----------|
| `<post_id>` | The post ID of the liveblog to unarchive | Yes |

**Examples:**

```bash
# Unarchive liveblog 123
wp liveblog unarchive 123
```

---

### `wp liveblog disable <post_id>`

Completely disable liveblog functionality on a post. Unlike archiving, this removes the liveblog display from the post entirely. Existing entries are preserved and can be restored by re-enabling.

**Arguments:**

| Argument | Description | Required |
|----------|-------------|----------|
| `<post_id>` | The post ID to disable liveblog on | Yes |

**Options:**

| Option | Description |
|--------|-------------|
| `--yes` | Skip confirmation prompt |

**Examples:**

```bash
# Disable liveblog on post 123 (will prompt for confirmation)
wp liveblog disable 123

# Disable without confirmation
wp liveblog disable 123 --yes
```

---

## Entry Management Commands

### `wp liveblog entries <post_id>`

List entries for a liveblog with optional filtering.

**Arguments:**

| Argument | Description | Required |
|----------|-------------|----------|
| `<post_id>` | The post ID of the liveblog | Yes |

**Options:**

| Option | Description | Default |
|--------|-------------|---------|
| `--key-events` | Only show key events | false |
| `--limit=<number>` | Limit number of entries (0 for all) | 20 |
| `--format=<format>` | Output format: `table`, `json`, `csv`, `ids` | `table` |

**Examples:**

```bash
# List recent entries for liveblog 123
wp liveblog entries 123

# List only key events
wp liveblog entries 123 --key-events

# Export all entries as JSON
wp liveblog entries 123 --limit=0 --format=json

# Get entry IDs for scripting
wp liveblog entries 123 --format=ids

# Show last 50 entries
wp liveblog entries 123 --limit=50
```

**Output columns:** ID, author, date, key_event (unless --key-events), content (truncated)

---

### `wp liveblog add <post_id> <content>`

Add a new entry to a liveblog. Supports author assignment, contributors, and key event marking.

**Arguments:**

| Argument | Description | Required |
|----------|-------------|----------|
| `<post_id>` | The post ID of the liveblog | Yes |
| `<content>` | The entry content (use quotes for multi-word) | Yes |

**Options:**

| Option | Description |
|--------|-------------|
| `--author=<user_id>` | User ID for the entry author (defaults to current user or first admin) |
| `--contributors=<user_ids>` | Comma-separated list of contributor user IDs |
| `--hide-authors` | Hide the author name on this entry |
| `--key-event` | Mark this entry as a key event |
| `--porcelain` | Output only the new entry ID (for scripting) |

**Examples:**

```bash
# Add a simple entry
wp liveblog add 123 "Breaking news: something happened!"

# Add entry with specific author
wp liveblog add 123 "Update from the field" --author=5

# Add entry with multiple contributors
wp liveblog add 123 "Team report" --author=5 --contributors=6,7,8

# Add anonymous key event
wp liveblog add 123 "Major development!" --hide-authors --key-event

# Get just the entry ID for scripting
ENTRY_ID=$(wp liveblog add 123 "New entry" --porcelain)
```

---

## Bulk Operations

### `wp liveblog archive-old`

Archive liveblogs that have been inactive for a specified period. Useful for automated maintenance.

**Options:**

| Option | Description | Required |
|--------|-------------|----------|
| `--days=<number>` | Archive liveblogs with no entries in the last N days | Yes |
| `--dry-run` | Show what would be archived without making changes | No |
| `--yes` | Skip confirmation prompt | No |

**Examples:**

```bash
# Preview liveblogs that would be archived (inactive > 30 days)
wp liveblog archive-old --days=30 --dry-run

# Archive all liveblogs inactive for 60+ days
wp liveblog archive-old --days=60 --yes

# Interactive archive (will show preview and ask for confirmation)
wp liveblog archive-old --days=90
```

---

## Maintenance Commands

### `wp liveblog fix-archive`

Repairs data inconsistencies that can occur when liveblog entries are edited while archived. This command corrects `liveblog_replaces` meta values and restores proper comment content.

**Options:**

| Option | Description |
|--------|-------------|
| `--dry-run` | Run without making changes to see what would be modified |

**Examples:**

```bash
# Preview what would be fixed
wp liveblog fix-archive --dry-run

# Actually fix the archives
wp liveblog fix-archive
```

---

## Scripting Examples

### Bulk enable liveblogs from a list

```bash
# Enable liveblogs from a file of post IDs
while read post_id; do
    wp liveblog enable "$post_id"
done < post_ids.txt
```

### Export all entries from all liveblogs

```bash
# Export entries from all liveblogs to separate JSON files
for id in $(wp liveblog list --format=ids); do
    wp liveblog entries "$id" --limit=0 --format=json > "liveblog-${id}-entries.json"
done
```

### Automated archiving via cron

```bash
# Add to crontab: archive liveblogs inactive for 30+ days, daily at 2am
0 2 * * * cd /path/to/wordpress && wp liveblog archive-old --days=30 --yes
```

### Create a liveblog with initial entries

```bash
# Enable liveblog and add initial entries
wp liveblog enable 123
wp liveblog add 123 "Welcome to our live coverage!" --key-event
wp liveblog add 123 "We'll be posting updates throughout the day."
```

---

## Exit Codes

All commands follow standard WP-CLI exit code conventions:

| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | Error (invalid arguments, post not found, etc.) |

---

## Related Documentation

- [HACKING.md](../HACKING.md) - Technical overview of how the liveblog works
- [CONTRIBUTING.md](../CONTRIBUTING.md) - How to contribute to the plugin
- [TESTING_CHECKLIST.md](../TESTING_CHECKLIST.md) - Manual testing checklist
