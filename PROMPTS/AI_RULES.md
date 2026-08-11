# Purpose
This file contains mandatory development rules for AI coding agents working on the LogTrail WordPress plugin.
Always read this file before making any code changes.

# Core Principles
- Follow existing project architecture before introducing new patterns.
- Minimize changes and avoid unnecessary refactoring.
- Preserve backward compatibility whenever possible.
- Generate production-quality code only.
- Never generate placeholder or demo code.
- Keep the plugin WordPress.org compliant.

# PHP
- Follow WordPress Coding Standards (PHPCS).
- Add PHPDoc comments to all classes and methods.
- Sanitize every input.
- Validate every input.
- Escape every output.
- Use capability checks.
- Use nonce verification for privileged actions.
- Use prepared SQL statements.
- Never suppress errors.

# Architecture
- PSR-4 autoloading.
- Reuse existing models and helper classes.
- Reuse existing services.
- Reuse existing utilities before creating new ones.
- Prefer modular classes over large files.

# React
- Use existing reusable components.
- Use @wordpress/api-fetch.
- Do not introduce Bootstrap.
- Do not introduce Tailwind.
- Keep UI consistent with existing admin pages.

# CSS
- Prefix every class with wptl.
- Prefix every ID with wptl.
- Avoid global selectors.

# Database
- Optimize for millions of log records.
- Avoid unnecessary joins.
- Use indexes where appropriate.
- Keep queries performant.

# Security
- Assume all user input is malicious.
- Escape all output.
- Check permissions before executing actions.
- Follow WordPress security best practices.

# Performance
- Avoid unnecessary database queries.
- Cache expensive operations when appropriate.
- Use AJAX or REST instead of full page reloads where possible.

# Extensibility
- Prefer hooks and filters over hardcoded logic.
- Keep APIs stable.
- Keep architecture developer-friendly.

# Before Writing New Code
Always inspect the project first.
Ask yourself:
- Does similar code already exist?
- Can an existing helper be reused?
- Can an existing component be reused?
- Can an existing service be extended?
- Is this consistent with neighboring files?
Only create new abstractions when necessary.

# Goal
Every change should look like it was written by the original author of LogTrail and should maintain consistency, performance, security, and maintainability.
