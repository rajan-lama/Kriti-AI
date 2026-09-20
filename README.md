# Kriti AI

Connect multiple AI providers to generate content, audio and video inside WordPress. Generated items stay as **drafts** until you publish them.

## Features

- **Provider abstraction** — the site owner enables and configures any combination of providers from the dashboard:
  - Text: OpenAI (`gpt-4o`, `gpt-4o-mini`, ...), Google Gemini, Ollama (local models)
  - Audio: OpenAI TTS, Google Cloud TTS
  - Video: OpenAI Sora, Google Veo (async, auto-polled)
  - Mock provider (enabled by default) to test the whole pipeline without API keys
- **Prompt library** — a `kriti_ai_prompt` post type stores reusable prompts together with their AI settings (provider, model, temperature, max tokens, item type)
- **Generator screen** — prompt builder with fine-tuning controls (temperature, max tokens, voice, speed, stability, video duration, resolution, aspect ratio, quality)
- **Draft-first workflow** — generated articles, summaries, images, audio and video are stored as Kriti AI custom post types. Media files are tracked as `kriti_ai_media` drafts and can be published to the WordPress Media Library from the AI Media list.
- **Background queue** — jobs run through WP-Cron; async video jobs are polled on an interval until completion
- **Metrics dashboard** — requests, success/failure rates, tokens, estimated cost, average generation time, per-provider breakdown, and draft vs published counts

## Installation

1. Copy the `kriti-ai` folder to `wp-content/plugins/`
2. Activate the plugin
3. Open **Kriti AI → Settings** to enable providers and enter API keys
4. Open **Kriti AI → Generate**, write a prompt (or load one from the library), tune parameters, and click **Generate**
5. Track the job in **Queue** and publish finished items

> API keys can be supplied via wp-config.php constants instead of the settings screen. They always override stored values:
> `KRITI_AI_KEY_OPENAI`, `KRITI_AI_KEY_GEMINI`, `KRITI_AI_KEY_OPENAI_AUDIO`, `KRITI_AI_KEY_GEMINI_AUDIO`, `KRITI_AI_KEY_OPENAI_VIDEO`, `KRITI_AI_KEY_GEMINI_VIDEO`.

## Architecture

```
kriti-ai/
├── kriti-ai.php          Bootstrap, constants, hooks
├── uninstall.php              Data cleanup (opt-in via settings)
├── includes/
│   ├── autoload.php           PSR-4 style autoloader (KritiAI\ namespace)
│   ├── class-plugin.php       Wire-up
│   ├── class-install.php      DB tables, options, cron schedule
│   ├── class-post-types.php   kriti_ai_prompt CPT + parameter meta box
│   ├── class-provider-manager.php  Provider registry & capability routing
│   ├── class-queue.php        Jobs table + locking
│   ├── class-worker.php       WP-Cron worker (sync + async polling)
│   ├── class-generator.php    Draft post creation, media finalization, metrics
│   ├── class-media.php        Upload pipeline, media attachment publishing, deletion state
│   ├── class-metrics.php      Usage/cost/duration aggregation
│   ├── class-settings.php     Options helpers + settings form handler
│   ├── class-ajax.php         admin-ajax endpoints (vanilla JS frontend)
│   ├── class-admin.php        Menus and views
│   ├── class-assets.php       Enqueues CSS/JS
│   ├── core/
│   │   ├── class-provider.php         Base HTTP/config helpers
│   │   ├── interface-text-provider.php
│   │   ├── interface-audio-provider.php
│   │   └── interface-video-provider.php
│   └── providers/
│       ├── class-mock-provider.php
│       ├── text/    (OpenAI, Gemini, Ollama)
│       ├── audio/   (OpenAI TTS, Google Cloud TTS)
│       └── video/   (OpenAI Sora, Gemini Veo)
└── admin/
    ├── css/admin.css
    ├── js/admin.js            Vanilla JS (no build step)
    └── views/                 dashboard, generate, queue, media, settings
```

## Adding a provider

Implement the relevant interface and register it in `Provider_Manager`:

- `KritiAI\Core\Text_Provider::generate_text()`
- `KritiAI\Core\Audio_Provider::synthesize_audio()`
- `KritiAI\Core\Video_Provider::start_video()` / `poll_video()`

Use the `kriti_ai_provider_class`, `kriti_ai_providers_for_capability` and `kriti_ai_provider_models` filters to register third-party providers without touching core files.

## Data model

- `wp_kriti_ai_jobs` — background generation jobs (`pending`, `processing`, `completed`, `failed`, `cancelled`)
- `wp_kriti_ai_metrics` — one row per finished job (tokens, cost, duration, provider)
- Options: `kriti_ai_settings`, `kriti_ai_providers`, `KRITI_AI_DB_version`
- Post meta: `_kriti_ai_generated`, `_kriti_ai_state` (`draft`/`published`), `_kriti_ai_item_type`, `_kriti_ai_job_id`, `_kriti_ai_provider`

## Local development

Requires WordPress 5.8+, PHP 7.4+. The Mock provider lets you run the full pipeline with zero configuration.

```bash
# Lint PHP
find kriti-ai -name '*.php' -print0 | xargs -0 -n1 php -l

# Lint JS
node --check kriti-ai/admin/js/admin.js
```

> Video endpoints (Sora, Veo, download URLs) are implemented per current provider documentation and may change; keep the provider classes updated as APIs evolve. The Mock provider is unaffected.
