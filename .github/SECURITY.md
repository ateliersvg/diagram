# Security

## Reporting security issues

If you believe you have found a security vulnerability in `atelier/diagram`,
please report it through coordinated disclosure.

> [!IMPORTANT]
> **Do not report security vulnerabilities through public GitHub issues.**
>
> Report by email at [smnandre@gmail.com](mailto:smnandre@gmail.com).
> You should receive an acknowledgment within 48 hours, followed by a security
> patch as quickly as possible.

Include what you have:

- a description of the vulnerability;
- steps to reproduce, with the Mermaid source or builder calls involved;
- the PHP version and operating system used.

Parser input is untrusted by design. Reports about `Parser/` handling of hostile
Mermaid source -- unbounded memory, unbounded time, or output that escapes the
SVG text encoding -- are in scope.

## Supported versions

Only the latest minor release receives security fixes.

## Preferred languages

English or French.

## Security policy

This project follows
[Coordinated Vulnerability Disclosure](https://en.wikipedia.org/wiki/Coordinated_vulnerability_disclosure).
