# maarheeze/codegraph-laravel

**Instant code understanding for Laravel applications.**

This package extends [maarheeze/codegraph](https://github.com/maarheeze/codegraph) to automatically extract and index Laravel patterns—routes, models, services, and dependencies. Understand your codebase instantly without guessing, grepping, or slow IDE searches.

## Why Use It?

**Answer questions about your Laravel app in milliseconds.**

- **Where is X bound?** — Find service bindings instantly
- **What breaks if I change this?** — See all callers, relations, and dependents
- **How does this route work?** — Trace the full call chain from route → controller → models → queries
- **Where is this model used?** — Find all relations and eager-loading patterns
- **What models relate to this one?** — See all hasMany, belongsTo, morphs, and polymorphic relations at a glance

**How it helps:**

- ⚡ **Faster refactoring** — Know exactly what breaks before you change it
- 💰 **Cheaper AI assistance** — Feed Claude accurate, structured data instead of raw file contents
- 🔍 **Offline navigation** — No IDE plugin or running app needed; index once, query forever
- ✨ **Automatic extraction** — No manual mapping; parses your actual code structure (AST-based, not regex)
- 🤖 **Works with Claude Code, Cursor, and any AI** — MCP protocol integration for context-aware code assistance

## What It Extracts

This package extends CodeGraph with Laravel-aware extraction:

- **Routes** — All route definitions with HTTP methods and controller handlers
- **Eloquent Relations** — Model relationships (hasMany, belongsTo, hasOne, hasManyThrough, belongsToMany, morphMany, morphTo)
- **Service Bindings** — Service container bindings (bind, singleton, scoped, instance, factory)
- **Call Graph** — Complete call graphs across your codebase
- **Inheritance & Traits** — Class hierarchies and trait usage

## Installation

```bash
composer require maarheeze/codegraph-laravel --dev
```

The package auto-registers via Laravel's service provider system. No additional configuration needed.

## Usage

### Initialize and Index Your Application

First, initialize CodeGraph:

```bash
php artisan codegraph:init
```

Then, index your application:

```bash
php artisan codegraph:index
```

By default, `init` sets up the CLI integration — the cheapest path for AI assistants.
The `init` command:
- Sets up the `.codegraph/index.sqlite` database
- Adds a CodeGraph section to `CLAUDE.md` pointing at the CLI commands

CLI mode does **not** write `.mcp.json`, publish `routes/ai.php`, or touch Claude Code
settings. AI assistants discover the CLI once via `CLAUDE.md` and only spend tokens when they
actually run a command — unlike MCP, whose tool definitions are loaded into context on every
session whether they are used or not.

The `index` command scans your `app/` and `routes/` directories and extracts all Laravel patterns into the database. This directory is auto-generated and should be added to `.gitignore`.

### Querying the Index (CLI)

The CLI ships with the core `maarheeze/codegraph` dependency and queries the same
`.codegraph/index.sqlite` database that the Laravel indexer fills:

```bash
php vendor/bin/codegraph search <name>
php vendor/bin/codegraph callers <fqn>
php vendor/bin/codegraph callees <fqn>
php vendor/bin/codegraph blast-radius <fqn> --depth=3
php vendor/bin/codegraph search-chunks '<query>'
```

Each command prints JSON to stdout.

### MCP Integration (opt-in)

If you prefer the MCP integration, pass the `--mcp` flag:

```bash
php artisan codegraph:init --mcp
```

This restores the full MCP setup:
- Auto-detects your environment (Sail, Docker, or plain PHP)
- Generates `.mcp.json` with the correct startup command
- Publishes `routes/ai.php` to register the Laravel-native MCP server
- Registers the CodeGraph MCP server in Claude Code settings
- Adds an MCP-flavored CodeGraph section to `CLAUDE.md`

You can override environment detection with `--mcp-config=auto|sail|docker|php`.

### View Indexing Status

```bash
php artisan codegraph:status
```

Shows what was extracted: routes, relations, service bindings, and other patterns.

## Using Your Index

Once indexed, your code graph is ready to use:

- **With the CLI (default)** — `CLAUDE.md` instructs Claude Code to query the graph via `php vendor/bin/codegraph …`. No always-on context cost.
- **With MCP (opt-in)** — After `codegraph:init --mcp`, Claude Code discovers the MCP server via `.mcp.json` and Laravel MCP and proactively offers the 3 Laravel-specific tools below.
- **Programmatically** — Query the SQLite index directly in `.codegraph/index.sqlite` for custom tools and workflows

### MCP Tools for Claude Code

After running `codegraph:init --mcp` and `codegraph:index`, Claude Code has access to **3 Laravel-specific MCP tools** powered by Laravel MCP:

#### `codegraph_find_route`
Find Laravel routes by URL pattern or name.
```
Pattern: "users" → Returns all routes matching "users"
Pattern: "user.show" → Returns the named route "user.show"
```
**Returns:** Route path, HTTP method, controller method, file location

#### `codegraph_find_model`
Find Eloquent models and their relations.
```
Name: "User" → Returns the User model with all its relations
```
**Returns:** Model FQN, file path, all hasMany/belongsTo/morphs relations with related models

#### `codegraph_find_service`
Find service container bindings.
```
Name: "auth" → Returns all services bound to "auth"
Name: "UserRepository" → Returns where UserRepository is bound
```
**Returns:** Binding key, concrete class, service provider, file location

These tools work alongside CodeGraph's 5 core tools (search, callers, callees, blast_radius, search_chunks) to give Claude Code complete understanding of your Laravel application. The MCP server is automatically started using Laravel's native MCP framework based on your environment (Sail, Docker, or plain PHP) as detected during `codegraph:init --mcp`.

### Migrating from MCP to the CLI

MCP tool definitions are loaded into the AI assistant's context on every session, whether or
not they are used. The CLI is discovered once via `CLAUDE.md` and only costs tokens when a
command actually runs. To switch an existing MCP setup over to the CLI:

1. Remove the `<!-- codegraph -->` … `<!-- /codegraph -->` block from `CLAUDE.md`.
2. Delete the `codegraph` entry from `.mcp.json`.
3. Remove `codegraph` from `enabledMcpjsonServers` in `.claude/settings.local.json`.
4. Remove (or guard) the published `routes/ai.php` MCP registration.
5. Re-run `php artisan codegraph:init`.

### Automatic Indexing on Install

To automatically initialize and index your codebase after dependencies are installed or updated, add the following to your `composer.json`:

```json
{
  "scripts": {
    "post-autoload-dump": [
      "@php artisan codegraph:init || true",
      "@php artisan codegraph:index || true"
    ]
  }
}
```

This ensures your code graph is initialized and stays in sync whenever dependencies change or the codebase is freshly checked out.

## Architecture

The package implements the CodeGraph plugin system:

- **LaravelPlugin** — Registers Laravel-specific extractors
- **RouteExtractor** — Parses route definitions
- **EloquentRelationExtractor** — Detects model relationships
- **ServiceProviderBindingExtractor** — Captures service bindings
- **LaravelIndexingService** — Wraps core IndexingService with Laravel defaults
- **LaravelStatusService** — Provides query interface for extracted data

All extractors extend `BaseAstVisitor` and work with the AST (Abstract Syntax Tree) to ensure accuracy.

## How It Works

1. **Register plugin** — LaravelPlugin registers all extractors with CodeGraph
2. **Scan files** — Discovers and parses PHP files in `app/`, `routes/`, and configurable directories
3. **Extract patterns** — Each extractor identifies Laravel-specific patterns in the AST (Abstract Syntax Tree)
4. **Build graph** — Creates nodes for routes, models, services, and edges for dependencies
5. **Persist index** — Stores the complete code graph in SQLite for fast, offline querying
6. **Resolve references** — CodeGraph resolves all cross-file references automatically

The index is **incremental** — subsequent runs only re-scan changed files, keeping indexing fast even on large codebases.

## Use Cases

- **Impact Analysis** — Answer "what breaks if I change this?" before you refactor
- **Dependency Discovery** — Understand the full dependency graph of a feature
- **Refactoring Safety** — See all callers, relations, and bindings before making changes
- **Onboarding** — New developers understand the codebase structure instantly
- **Documentation** — Auto-generate architecture diagrams from actual code
- **IDE & Tools** — Integrate with Claude Code, custom scripts, or build tools for context-aware assistance
- **Code Review** — Verify that refactors are complete and consistent

## License

MIT
