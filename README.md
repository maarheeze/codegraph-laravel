# maarheeze/codegraph-laravel

**Automated code indexing and navigation for Laravel applications.**

This package extends [maarheeze/codegraph](https://github.com/maarheeze/codegraph) with Laravel-specific extractors, automatically building a complete, queryable graph of your codebase. Understand relationships, dependencies, and call chains instantly—without manual mapping or slow IDE searches.

## Why Use It?

**Stop guessing about your codebase.**

- **Instant Navigation** — Find where a service is bound, where a model relation is defined, where a route leads—in milliseconds
- **Impact Analysis** — Know exactly what breaks when you change a class or service
- **Automated Relationship Mapping** — Extract routes, model relations, service bindings automatically from your code
- **IDE-Ready Data** — Structured, queryable data for tooling and IDE integration
- **Fast, Offline** — Index once, query offline. No running application needed
- **AST-Based Accuracy** — Parse the actual code structure, not regex or heuristics

**Result:**
- ⚡ **Faster** — Queries in milliseconds instead of reading files
- 💰 **Cheaper** — Use fewer tokens, no need to pass file contents  
- 🔍 **Accurate** — Structured data about code relationships, not guesses

## What It Extracts

This package extends CodeGraph with Laravel-aware extraction:

- **Routes** — All route definitions with HTTP methods and controller handlers
- **Eloquent Relations** — Model relationships (hasMany, belongsTo, hasOne, hasManyThrough, belongsToMany, morphMany, morphTo)
- **Service Bindings** — Service container bindings (bind, singleton, scoped, instance, factory)
- **Call Graph** — Complete call graphs across your codebase
- **Inheritance & Traits** — Class hierarchies and trait usage

## Installation

```bash
composer require maarheeze/codegraph-laravel
```

The package auto-registers via Laravel's service provider system. No additional configuration needed.

## Usage

### Index Your Application

```bash
php artisan codegraph:index
```

Scans your `app/` and `routes/` directories and extracts all Laravel patterns into `.codegraph/index.sqlite`.

### View Indexing Status

```bash
php artisan codegraph:status
```

Shows what was extracted: routes, relations, service bindings, and other patterns.

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

## Requirements

- PHP 8.3+
- Laravel 13.0+
- `ext-sqlite3` extension

## License

MIT
