# Changelog

## [1.2.0](https://github.com/tatupesonen/Armaani/compare/v1.1.0...v1.2.0) (2026-03-08)


### Features

* vendor fonts, add OG image, refresh welcome page and branding ([3dba79b](https://github.com/tatupesonen/Armaani/commit/3dba79be4104172ac8ebae6069e96ef4c880746b))

## [1.1.0](https://github.com/tatupesonen/Armaani/compare/v1.0.0...v1.1.0) (2026-03-08)


### Features

* front page ([405f2b8](https://github.com/tatupesonen/Armaani/commit/405f2b86425d3778ae5d199c5454f8cf9cdfd63a))

## [1.0.0](https://github.com/tatupesonen/Armaani/compare/v0.3.3...v1.0.0) (2026-03-08)


### Features

* display app version in sidebar and auto-bump on release ([2f655e0](https://github.com/tatupesonen/Armaani/commit/2f655e0f3d8ba3b62136ee95f5d582d7968ce620))

## [0.3.3](https://github.com/tatupesonen/Armaani/compare/v0.3.2...v0.3.3) (2026-03-08)


### Bug Fixes

* define SIGTERM and SIGKILL fallbacks when pcntl extension is unavailable ([c3d4ce1](https://github.com/tatupesonen/Armaani/commit/c3d4ce1936e2185f0f2a57125e54934620213164))

## [0.3.2](https://github.com/tatupesonen/Armaani/compare/v0.3.1...v0.3.2) (2026-03-08)


### Bug Fixes

* remove dangling symlinks in copyBiKeys before creating new ones ([ef8c4ee](https://github.com/tatupesonen/Armaani/commit/ef8c4ee13af5f7533518c1dfc2c5f1adc6d7c41f))

## [0.3.1](https://github.com/tatupesonen/Armaani/compare/v0.3.0...v0.3.1) (2026-03-08)


### Bug Fixes

* recreate .env and APP_KEY in Docker test stage ([3b673c0](https://github.com/tatupesonen/Armaani/commit/3b673c071d8b3c3b7c5817ac4b902b2a5b4cbf78))
* use standard steamcmd base image and chown /home/steam for root ([5098529](https://github.com/tatupesonen/Armaani/commit/509852970519249d67044909d81e7abac5342317))

## [0.3.0](https://github.com/tatupesonen/Armaani/compare/v0.2.0...v0.3.0) (2026-03-08)


### Features

* test release pipeline ([f10e575](https://github.com/tatupesonen/Armaani/commit/f10e5753aa625fc0998f23c62dcd2e58f32e2f4b))

## [0.2.0](https://github.com/tatupesonen/Armaani/compare/v0.1.0...v0.2.0) (2026-03-08)


### Features

* add animations for server states, allow reboots during boot ([da3a703](https://github.com/tatupesonen/Armaani/commit/da3a70386e208785c10d33056f6906a6ea681de6))
* add dashboard view ([064213d](https://github.com/tatupesonen/Armaani/commit/064213d96891e192b69230d60c25696b2d61c5a9))
* add docker image ([f3161f9](https://github.com/tatupesonen/Armaani/commit/f3161f94f5cb18b652fe90988eccde3cd7dfc947))
* add persisted scenario info, discord webhooks ([1d3e45f](https://github.com/tatupesonen/Armaani/commit/1d3e45fac571c495d6ee2eb621cd7ea6bab38a87))
* add support for auto restart ([3c4ae45](https://github.com/tatupesonen/Armaani/commit/3c4ae4504db0a82cc59afcfacf8b40880358feb7))
* backups ([5208067](https://github.com/tatupesonen/Armaani/commit/5208067f4b3c39a95b38e7f9ae6775eee8d38172))
* crash detection and discord webhook notifications ([08d4d3e](https://github.com/tatupesonen/Armaani/commit/08d4d3ee94c8b8e900422194c81d60687933e62d))
* detect mod downloads from arma reforger version string ([0782f6f](https://github.com/tatupesonen/Armaani/commit/0782f6fbfdd74896e5efc56e647b2b57cae57c08))
* edit license, edit favicon ([a6ee70b](https://github.com/tatupesonen/Armaani/commit/a6ee70b5297acef08588ff018a1c02beebd62125))
* escape commands better ([decc8b9](https://github.com/tatupesonen/Armaani/commit/decc8b998da10b0d62e9f6172adce23106f83a8d))
* initial ([bc7d300](https://github.com/tatupesonen/Armaani/commit/bc7d3001fa32085bdb7c05bacecf43d11b5cffb1))
* move to using form requests ([66f2801](https://github.com/tatupesonen/Armaani/commit/66f28017923886bff7196cded124e3da99a1604d))
* network settings, UI work ([08454ae](https://github.com/tatupesonen/Armaani/commit/08454ae048300b3a4f78296f2fbb40660e813788))
* port to Inertia v2 + React 19 ([941f6b7](https://github.com/tatupesonen/Armaani/commit/941f6b7c5417b3e68ef5df5826878ce4ec5543a5))
* support multiple games ([53a3c0e](https://github.com/tatupesonen/Armaani/commit/53a3c0e033cdcf9823c440e0a99baaa1a3062581))
* support multiple strings for crash detection ([1d8d3df](https://github.com/tatupesonen/Armaani/commit/1d8d3df67d2a6f0770a2a92913c4f0fd9162b863))
* update boot detection strings ([4af8420](https://github.com/tatupesonen/Armaani/commit/4af8420e0394e9b0e5fccde38ddb2768f289be97))
* update project name ([83537fa](https://github.com/tatupesonen/Armaani/commit/83537fa0ff5db80f6763ed10566ffe49979c473b))
* update README.md ([148a563](https://github.com/tatupesonen/Armaani/commit/148a563a4be039f5d401f1213b8b19f43220785d))
* use websockets for broadcasting server status, use toasts ([c0725b4](https://github.com/tatupesonen/Armaani/commit/c0725b4c7fd33c2ed244b43c36b1265eed26541c))


### Bug Fixes

* add migration plan for LLM models for multi-game support ([77198e4](https://github.com/tatupesonen/Armaani/commit/77198e4a32730d290e928d462b8834121093570b))
* add some screenshots ([31e0969](https://github.com/tatupesonen/Armaani/commit/31e0969f71d1ae0e2ab4d6f8ca3e76f3d71683c8))
* CI test failures from proc_open and overly broad assertDontSee ([64ec702](https://github.com/tatupesonen/Armaani/commit/64ec70246b3390d1e9002dcee3d3004b935518db))
* code cleanup ([b9b9abc](https://github.com/tatupesonen/Armaani/commit/b9b9abceffd53514b3622faabc5fc35e1b90cd98))
* resolve eslint errors in project source files ([a19522f](https://github.com/tatupesonen/Armaani/commit/a19522fc71f9c989f7c33fd0c63b714620076c4f))
* steam login verification ([45943cf](https://github.com/tatupesonen/Armaani/commit/45943cf062b92155a2f6b1973ff02c7d59bda061))
* stuff ([4b77471](https://github.com/tatupesonen/Armaani/commit/4b774715517485b8688abada20bc41962a692d18))
