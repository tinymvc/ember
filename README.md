# TinyMVC Ember

Ember is an AI-powered development companion for **TinyMVC / Spark** that equips AI coding agents (such as Cursor, Claude Code, GitHub Copilot, and Antigravity) with framework-specific knowledge, skills, conventions, and workflows.

Inspired by `laravel/boost`, Ember ensures AI agents generate accurate, idiomatically correct Spark code rather than hallucinating Laravel-specific APIs.

## Installation

Install Ember as a development dependency in your TinyMVC application:

```bash
composer require tinymvc/ember --dev
php spark ember:install
```

## Features

- **Tailored AGENTS.md**: Automatically inspects your application stack (TinyCore version, PHP version, database driver, cache, queue, Vite/assets) and injects customized AI instructions.
- **AI Coding Skills**: Automatically registers framework skills under `.agents/skills/tinymvc-ember/` with detailed reference guides for routing, ORM, FormRequest validation, Blade components, queues, and testing.
- **Spark API Differences**: Explicitly guides AI agents on where TinyMVC / Spark differs from Laravel habits (e.g. `FormRequest`, `Input`, `Validator::make($rules, $data)`, `Model::remove()`, `get()` vs `all()`, `Spark\Paginator`, Blade anonymous components, and named slots).
- **API Lookup Tool**: Run `php spark ember:lookup <term>` to quickly inspect declarations and signatures across installed TinyCore directly from the CLI. Use `php spark ember:lookup 'isJs()' --vendor=tinymvc/fireline-php` for another installed package's `src/` directory, or `--source=/path/to/src` for other layouts. Queries with parentheses must be quoted; `--file=substring` filters paths and `--limit=1..200` caps results (default 25).

## License

MIT
