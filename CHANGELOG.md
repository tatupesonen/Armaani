# Changelog

## [1.8.1](https://github.com/tatupesonen/Armaani/compare/v1.8.0...v1.8.1) (2026-03-11)


### Bug Fixes

* add query_port to Reforger and Project Zomboid settings schema ([b657e44](https://github.com/tatupesonen/Armaani/commit/b657e44301b939ec3039da441b286d676e00a45e))
* add query_port to Reforger and Project Zomboid settings schema ([af27c01](https://github.com/tatupesonen/Armaani/commit/af27c01cc85275651b2078c54db73a13b10b2acf))

## [1.8.0](https://github.com/tatupesonen/Armaani/compare/v1.7.1...v1.8.0) (2026-03-11)


### Features

* add paratest, general improvements ([a32f95d](https://github.com/tatupesonen/Armaani/commit/a32f95d2ad21b7aedfa0b006fa944dfb447ac9f0))
* fix crash handling ([504c001](https://github.com/tatupesonen/Armaani/commit/504c0018f5c2e5b5858be64bb8e8059acdc5145b))
* improvements ([396abdc](https://github.com/tatupesonen/Armaani/commit/396abdc4922362054c11d40ac42c2a9690804dc4))
* move game-specific fields from servers table to game settings tables ([7eab204](https://github.com/tatupesonen/Armaani/commit/7eab20470c7e7f4c0fa60da02c5aacff5e509560))
* remove AUDIT_PLAN.md left over from LLMs in previous commit ([8fdb0fc](https://github.com/tatupesonen/Armaani/commit/8fdb0fc0c3b33b059644ae22e17c897ba2c0cc91))
* rewrite tests, add SteamWorkshopMods contract ([44a4075](https://github.com/tatupesonen/Armaani/commit/44a40751afb1e524e55975e844aeef6345072d86))


### Bug Fixes

* add missing tests/Unit directory for ParaTest ([5f42f66](https://github.com/tatupesonen/Armaani/commit/5f42f664dc7734f2b854f85a56e7b1da8177d751))
* clear cached routes before parallel tests to prevent race condition ([a40726b](https://github.com/tatupesonen/Armaani/commit/a40726bc6da3152b46f971a8016459bc0b664906))
* remove paratest to resolve parallel test race conditions on CI ([54a8f6e](https://github.com/tatupesonen/Armaani/commit/54a8f6ef64d6da67bc015b0fca77c76b1ecbf1ae))

## [1.7.1](https://github.com/tatupesonen/Armaani/compare/v1.7.0...v1.7.1) (2026-03-11)


### Bug Fixes

* update release script, check for app version on boot ([177f842](https://github.com/tatupesonen/Armaani/commit/177f842969eb0130bcc6674815a843e180a64b64))

## [1.7.0](https://github.com/tatupesonen/Armaani/compare/v1.6.2...v1.7.0) (2026-03-11)


### Features

* allow tailing multiple log files ([a544fec](https://github.com/tatupesonen/Armaani/commit/a544fec4b23f8e3d0dae1a14078d387c63c53b92))
* allow tailing multiple log files ([2eed107](https://github.com/tatupesonen/Armaani/commit/2eed107c2ba869e8fade4735006b75d8fe8b70dc))
* logging fixes ([4dff2c9](https://github.com/tatupesonen/Armaani/commit/4dff2c959a06ba2d919398b4666c571bdd1ba50f))

## [1.6.2](https://github.com/tatupesonen/Armaani/compare/v1.6.1...v1.6.2) (2026-03-11)


### Bug Fixes

* allow nullable scenario_id for Reforger servers ([#23](https://github.com/tatupesonen/Armaani/issues/23)) ([266f475](https://github.com/tatupesonen/Armaani/commit/266f475cb1a3192f446c32194a14e485759cca05))
* allow nullable scenario_id for Reforger servers ([#23](https://github.com/tatupesonen/Armaani/issues/23)) ([a6fcb39](https://github.com/tatupesonen/Armaani/commit/a6fcb398c4c08005d60270d75643eb0fb0d6b005))

## [1.6.1](https://github.com/tatupesonen/Armaani/compare/v1.6.0...v1.6.1) (2026-03-10)


### Bug Fixes

* enable PHP-FPM worker log capture and reduce Caddy log noise in Docker ([1848cee](https://github.com/tatupesonen/Armaani/commit/1848cee31191118d733db86aa09f40fb3117dd90))

## [1.6.0](https://github.com/tatupesonen/Armaani/compare/v1.5.0...v1.6.0) (2026-03-10)


### Features

* add Factorio game handler and HTTP download installer infrastru… ([75caeb2](https://github.com/tatupesonen/Armaani/commit/75caeb280e5839f9d0f8ce337c776ff202e82ad3))
* add Factorio game handler and HTTP download installer infrastructure ([563f0fd](https://github.com/tatupesonen/Armaani/commit/563f0fd201eb8265f6b62d2edf767f1441e9a134))

## [1.5.0](https://github.com/tatupesonen/Armaani/compare/v1.4.0...v1.5.0) (2026-03-10)


### Features

* add Project Zomboid game handler with full server management ([cfe281b](https://github.com/tatupesonen/Armaani/commit/cfe281b7c3e142ae653b075639e705e471e36ae3))
* add Project Zomboid game handler with full server management ([0075a53](https://github.com/tatupesonen/Armaani/commit/0075a53e794bc92bafa010f0ea0096cca6d19cb8))

## [1.4.0](https://github.com/tatupesonen/Armaani/compare/v1.3.2...v1.4.0) (2026-03-10)


### Features

* add skill for LLMs to generate gamehandlers ([2baf934](https://github.com/tatupesonen/Armaani/commit/2baf934aca6d3e2d81220c05d16150001affab2e))
* automatic game handler generation ([c1ce131](https://github.com/tatupesonen/Armaani/commit/c1ce1313c4567c890eac918623d1185cf285858c))
* gameserviceprovider cache, generic frontend over games ([5e78352](https://github.com/tatupesonen/Armaani/commit/5e783521905037d37dad5ee1985f5802aa81fc80))
* wip ([a99baa5](https://github.com/tatupesonen/Armaani/commit/a99baa57255d591255f5433faecb079470d68450))


### Bug Fixes

* wip, add query port ([e915333](https://github.com/tatupesonen/Armaani/commit/e91533358a08052e5c4a916f70d2b27e5cfd1b34))

## [1.3.2](https://github.com/tatupesonen/Armaani/compare/v1.3.1...v1.3.2) (2026-03-10)


### Bug Fixes

* resolve Larastan error, add Larastan to CI, and cover test gaps ([57f5e9b](https://github.com/tatupesonen/Armaani/commit/57f5e9bbee13b4a5adaa9628b792b27d80ef77e1))
* set explicit target-branch for release-please ([afbc2ac](https://github.com/tatupesonen/Armaani/commit/afbc2acfd3f798f832468cc0011518452e3af45f))

## [1.3.1](https://github.com/tatupesonen/Armaani/compare/v1.3.0...v1.3.1) (2026-03-09)


### Bug Fixes

* update GitHub URL and remove log box from welcome demo ([0d23668](https://github.com/tatupesonen/Armaani/commit/0d23668e7a0ef87b5410f23037055404883eb380))

## [1.3.0](https://github.com/tatupesonen/Armaani/compare/v1.2.0...v1.3.0) (2026-03-09)


### Features

* add server lifecycle demo to welcome page and complete SEO meta tags ([fcf1852](https://github.com/tatupesonen/Armaani/commit/fcf1852c3033212702f72b92b77754a84b5511a7))

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
