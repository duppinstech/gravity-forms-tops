# Project Intent

## Purpose

GF TowX Integration is a WordPress plugin that sends selected Gravity Forms submission data to TOPS by TowXchange (TOPSLink) for tow dispatch, and can populate vehicle choices from TowX data. Duppins Technology is not affiliated with Rocketgenius or TowXchange.

## Intended users

WordPress site administrators who use Gravity Forms and have authorized TowX credentials. The plugin is distributed and maintained by Duppins Technology.

## Product boundaries

- Configure the integration and field mapping per form, with global API environment settings.
- Submit Create Call XML and support the JSON calls needed for vehicle data and connection testing.
- Protect credentials, validate permissions and requests, and avoid exposing sensitive information in logs or UI.
- Remain a WordPress plugin; do not turn this repository into a hosted service or a general-purpose TowX client.

## Non-goals

- Replacing Gravity Forms, TowX, or WordPress administration.
- Guaranteeing TowX availability or changing behavior of external services.
- Storing customer submission data beyond the plugin's documented dispatch and logging behavior.
- Introducing a dependency, framework, or compatibility-floor change without a separately reviewed need.

## Decision principles

Prefer backward-compatible, narrowly scoped changes; use established WordPress APIs; keep credentials and customer data private; document operational behavior that administrators need to know.
