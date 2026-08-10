# Changelog

All notable changes to `laravel-rulebook` will be documented in this file.

## v0.1.4 - 2026-08-10

Trust and polish release.

- Document the deterministic, side-effect-free rule authoring contract, stable keys, exception behavior, and the limits of historical reproduction against mutable code and data.
- Correct the ambiguity logging example to use Laravel's logger with structured context.
- Remove the unnecessary `spatie/laravel-package-tools` runtime dependency while retaining the existing service provider for compatibility.
- Run PHPStan for pull requests and when Composer metadata changes.

## v0.1.3 - 2026-08-09

Documentation and discovery release.

- Add the project page and versioned-business-rules architecture guide as distinct package resources.
- Link the runnable Austrian electric-vehicle pricing application with its 2025, 2026, and 2027 policies.
- Use the project page as the canonical Composer and GitHub homepage.

## v0.1.2 - 2026-08-09

Distribution and documentation release.

- Lead the documentation with the historical reproducibility problem, a concise `resolveAt()` example, practical usage triggers, and intentional scope.
- Improve Composer and GitHub discovery metadata with focused keywords, support links, badges, repository topics, and a Packagist homepage.
- Add a Mathias Onea CI-styled social preview and a concrete feedback invitation for effective-date business logic.

## v0.1.1 - 2026-07-20

Documentation release.

- Expand the Austrian electric-vehicle example with yearly policy rules, changing parameters, historical resolution, and decision inspection.
- Reframe future feature ideas as a roadmap and invite issues, discussions, and pull requests.

## v0.1.0 - 2026-07-17

Initial release of Laravel Rulebook.

- Add application-defined, container-injected rulebooks and rules.
- Add typed subjects, optional contexts, and outcomes with PHPStan generics.
- Add half-open validity periods and deterministic priority resolution.
- Add complete evaluation inspection, shadowed matches, and mandatory reasons.
- Add explicit errors for missing matches, ambiguous winners, duplicate keys, invalid periods, and unexpected input types.
