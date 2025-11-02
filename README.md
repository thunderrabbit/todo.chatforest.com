# Todo.chatforest.com

A personal todo list management website built on a minimalist PHP framework. Manage your todos in simple Markdown files with a responsive web interface.

---

## 📋 Overview

This is a lightweight todo list system designed for personal use. Each user gets their own todo projects, stored as Markdown files in a protected directory that's not web-accessible. The interface is clean, simple, and only visible to logged-in users.

## 📂 File Structure

Todo files are stored in `todos/(username)/YYYY/` adjacent to `wwwroot`, ensuring they remain private and not accessible via web browsers.

Each year gets its own directory, with a `main.md` file and other project files at the same level. For example:
- `todos/rob/2025/main.md`
- `todos/rob/2025/create_website_sublists.md`
- `todos/rob/2025/book_travel.md`
- `todos/rob/2025/learn_piano.md`

## ✨ Todo Format

Each todo follows this simple format:

**Unfinished todo:**
```markdown
- [ ] 02-nov-2025 Create TODO website
```

**Finished todo:**
```markdown
- [x] 02-nov-2025 Create TODO website 05-nov-2025
```

- **Date at the start**: When the todo was created
- **Checkbox**: `[ ]` for unfinished, `[x]` for finished
- **Todo text**: Description of the task
- **Date at the end**: When the todo was completed (only appears for finished todos)

### Linked Todos (Sub-lists)

You can link to sub-lists in separate markdown files using wiki-style double brackets:

**Unfinished linked todo:**
```markdown
- [ ] 02-nov-2025 [[Create website sublists]]
```

**Finished linked todo:**
```markdown
- [x] 02-nov-2025 [[Create website sublists]] 05-nov-2025
```

**How it works:**
- Double brackets `[[...]]` create a link to another markdown file in the same year directory
- Filename is automatically derived from the link text (lowercase, spaces converted to underscores)
- The checkbox marks the parent todo complete and does **not** affect items in the sub-list
- Web interface renders both a checkbox and a separate clickable link
- This creates a hierarchical structure similar to Notion pages, where you can organize complex projects into manageable sub-lists

## 🎯 Features

- **User Authentication**: Login required to access your todos
- **Markdown-Based**: Simple, readable file format
- **Private Storage**: Todos stored outside web-accessible directories
- **Responsive Web Interface**: Edit todos via browser on any device
- **Manual Editing**: Files can also be edited directly with any text editor
- **Per-User Projects**: Each user has their own todo directory
- **Project Organization**: Separate files for different projects/categories

## 🔧 Technology Stack

Built on a minimalist PHP framework:
- Lightweight custom templating system (no Twig, Blade, or Smarty)
- Session-based authentication with database-backed cookies
- Simple, clean UI with light blue aesthetic
- Easy deployment to DreamHost or similar hosting

## 🚀 Getting Started

1. Visit the site and log in with your credentials
2. Access your personal todo dashboard
3. Create new project files or open existing ones
4. Add todos with dates, mark them complete when done

The system automatically tracks when todos are created and finished, making it easy to see your productivity over time.

---

## 💡 Based on

Originally created during work on the **MarbleTrack3** stop-motion animation archive (June 2025). Designed for fun and minimal overhead.
