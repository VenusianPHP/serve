# Venusian Serve

[![Tests](https://github.com/VenusianPHP/serve/actions/workflows/tests.yml/badge.svg)](https://github.com/VenusianPHP/serve/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/venusian/serve.svg)](https://packagist.org/packages/venusian/serve)
[![Total Downloads](https://img.shields.io/packagist/dt/venusian/serve.svg)](https://packagist.org/packages/venusian/serve)
[![License](https://img.shields.io/packagist/l/venusian/serve.svg)](LICENSE)

## About Venusian Serve

> **Note:** This repository contains the web stack for the Venusian framework. If you want to build an application using Venusian, visit the main [Venusian skeleton repository](https://github.com/VenusianPHP/venusian).

Venusian Serve brings the web to your Venusian application: routing, requests and responses, sessions, cookies, authentication, validation, views, mail and notifications, all in the Laravel-like syntax you already know.

Built a robot arm and want a dashboard for it? Got a weather station on a Raspberry Pi and want to check it from your phone? Serve your app over HTTP from the same app that's driving the hardware. No second app, no second language.

Serve ships its own HTTP server, so `php computer serve` is all it takes to get going. Want it behind nginx and PHP-FPM instead? That works too. A running sketch can even serve its own routes on its own loop, right next to its windows and its sensors.

And when a request has to wait on a slow query or an outside API, serve can hand that work off and keep answering everyone else in the meantime. It's opt-in, one route at a time.

> **Built with AI assistance.** This package was developed LLM-assisted — not vibe-coded. Generated code was deliberately prompted and reviewed by a professional software engineer. See [AI-assisted development](#ai-assisted-development).

## Learning Venusian Serve

Venusian Serve has documentation and guides on the [Venusian website](https://venusian.projectsaturnstudios.com/docs/serve/installation), from [installing it](https://venusian.projectsaturnstudios.com/docs/serve/installation) and [serving your application](https://venusian.projectsaturnstudios.com/docs/serve/serving) to [deployment](https://venusian.projectsaturnstudios.com/docs/serve/deployment) and [serving from a sketch](https://venusian.projectsaturnstudios.com/docs/serve/sketches).

## AI-assisted development

This codebase was built with LLM assistance under human direction. Prompts, reviews, and architectural decisions were owned by a professional software engineer — the model accelerates drafting and exploration; it does not replace judgment on APIs, dependency direction, or what ships.

## Contributing

Thank you for considering contributing to Venusian Serve! The contribution guide can be found in the [Venusian documentation](https://venusian.projectsaturnstudios.com/docs/contributions).

## Code of Conduct

In order to ensure that the Venusian community is welcoming to all, please review and abide by the [Code of Conduct](https://venusian.projectsaturnstudios.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

Please review [our security policy](https://github.com/VenusianPHP/serve/security/policy) on how to report security vulnerabilities.

## License

Venusian Serve is open-sourced software licensed under the [MIT license](LICENSE).
