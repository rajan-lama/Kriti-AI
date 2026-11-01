# Kriti AI

Connect multiple AI providers to generate content, images, audio and video inside WordPress. Generated items stay as **drafts** until you publish them. Kriti AI is also the platform behind the [Kriti AI Writer](../kriti-ai-writer/README.md) add-on.

## Features

- **Providers** — enable any combination; each is ready as soon as its API key (or server URL) is saved:
  - Text: OpenAI, Anthropic Claude, Google Gemini, DeepSeek, Groq, OpenRouter, Ollama (local models)
  - Image: OpenAI (DALL-E), Google Gemini, Stability AI, Replicate, Local AI
  - Audio: OpenAI TTS, ElevenLabs, Google Gemini TTS, Local AI
  - Video: OpenAI Sora, Google Veo (async, auto-polled), Local AI
  - Mock — offered only when no real provider is set up for that type, to test the pipeline without keys
- **Integrations Hub** — API key per provider, **Test connection** (read-only check, nothing saved), server URL and model for Ollama / Local AI. Cloud providers use a default model; pick another per generation in AI Studio.
- **Local AI** — any OpenAI-compatible server (e.g. LocalAI) for image (`/v1/images/generations`), audio (`/v1/audio/speech`) and video (`/v1/videos`).
- **Settings** — defaults for every AI Studio parameter: default provider per type, temperature, max tokens, Top P, Top K, image size/aspect/style/quality, voice/speed/stability/similarity, video duration/resolution/aspect/quality, timeouts, uninstall cleanup.
- **AI Studio** — prompt builder with the same controls, starting from the Settings defaults.
- **Prompt library** — `kriti_ai_prompt` post type with reusable prompts and their AI settings (provider, model, temperature, max tokens, item type) and dynamic variables such as `{{site_name}}`, `{{post_title}}`, `{{product_name}}`.
- **Draft-first workflow** — generated articles are draft posts; media are `kriti_ai_media` drafts that can be published to the Media Library.
- **Background queue** — WP-Cron worker with a DB lock; async video jobs are polled until done.
- **Metrics dashboard** — requests, success/failure, tokens, estimated cost, duration, per-provider breakdown, draft vs published.
- **Writer platform** — provides text generation, the Prompt Library, generation options and chunked podcast audio to Kriti AI Writer.

## Installation

1. Copy the `kriti-ai` folder to `wp-content/plugins/` and activate it.
2. Open the **Integrations Hub** (*Kriti AI → MCP Server* in the admin menu), add API keys (or a server URL for Ollama / Local AI), click **Test connection**, then **Save Settings**.
3. Optionally set defaults on **Kriti AI → Settings**.
4. Open **Kriti AI → AI Studio**, write a prompt (or load one from the library), tune parameters and click **Generate**.
5. Track the job in **Queue** and publish finished items.

API keys can be defined in `wp-config.php` instead; constants always override stored values. The name is `KRITI_AI_KEY_` + the upper-cased provider slug:

```php
define( 'KRITI_AI_KEY_OPENAI', 'sk-...' );       // also _ANTHROPIC, _GEMINI, _DEEPSEEK, _GROQ, _OPENROUTER
define( 'KRITI_AI_KEY_OPENAI_IMAGE', 'sk-...' ); // also _GEMINI_IMAGE, _STABILITY, _REPLICATE
define( 'KRITI_AI_KEY_ELEVENLABS', '...' );      // also _OPENAI_AUDIO, _GEMINI_AUDIO, _OPENAI_VIDEO, _GEMINI_VIDEO
```

## Architecture

```
kriti-ai/
├── kriti-ai.php                    Bootstrap, constants, Writer bridge filter
├── uninstall.php                   Data cleanup (opt-in via Settings)
├── includes/
│   ├── autoload.php                KritiAI\ autoloader (Foo_Bar → class-foo-bar.php)
│   ├── class-plugin.php            Wire-up
│   ├── class-install.php           DB tables, options, cron schedule
│   ├── class-post-types.php        kriti_ai_prompt / kriti_ai_media CPTs
│   ├── class-provider-manager.php  Registry, capability routing, enabling, default models, labels
│   ├── class-settings.php          Settings/provider option helpers + form handler
│   ├── class-queue.php             Jobs table + locking
│   ├── class-worker.php            WP-Cron worker (sync + async polling)
│   ├── class-generator.php         Prompt variables, draft posts, media finalization
│   ├── class-media.php             Upload pipeline, Media Library publishing
│   ├── class-metrics.php           Usage/cost/duration aggregation
│   ├── class-ajax.php              admin-ajax endpoints (AI Studio, dashboard, Test connection)
│   ├── class-admin.php             Menus and views
│   ├── class-assets.php            Enqueues CSS/JS (versioned by file modification time)
│   ├── class-writer-bridge.php     Kriti AI Writer PlatformContract implementation
│   ├── core/                       Base Provider + Text/Image/Audio/Video interfaces
│   └── providers/
│       ├── class-mock-provider.php
│       ├── class-local-ai.php      Shared Local AI helpers
│       ├── text/   OpenAI, Anthropic, Gemini, DeepSeek, Groq, OpenRouter, Ollama
│       ├── image/  OpenAI, Gemini, Stability, Replicate, Local AI
│       ├── audio/  OpenAI, ElevenLabs, Gemini, Local AI
│       └── video/  OpenAI (Sora), Gemini (Veo), Local AI
└── admin/
    ├── css/admin.css, css/tailwind.css (prebuilt)
    ├── js/admin.js                 Vanilla JS (no build step)
    └── views/                      dashboard, generate (AI Studio), queue, providers (Integrations Hub), settings
```

## Adding a provider

Extend `KritiAI\Core\Provider`, implement the capability interface, and register the slug in `Provider_Manager` (capability list, class map, model choices):

- `Text_Provider::generate_text()`
- `Image_Provider::generate_image()`
- `Audio_Provider::synthesize_audio()`
- `Video_Provider::start_video()` / `poll_video()`

Override `connection_request()` (URL + headers of a cheap, read-only GET) so **Test connection** works. Third-party plugins can use the `kriti_ai_provider_class`, `kriti_ai_providers_for_capability` and `kriti_ai_provider_models` filters instead of editing core files.

## Data model

- `wp_kriti_ai_jobs` — background jobs (`pending`, `processing`, `completed`, `failed`, `cancelled`)
- `wp_kriti_ai_metrics` — one row per finished job (tokens, cost, duration, provider)
- Options: `kriti_ai_settings`, `kriti_ai_providers` (`api_key`, `model`, `base_url`, `enabled` mirror), `KRITI_AI_DB_version`, `kriti_ai_queue_lock`
- Post meta: `_kriti_ai_generated`, `_kriti_ai_state` (`draft`/`published`), `_kriti_ai_item_type`, `_kriti_ai_job_id`, `_kriti_ai_provider`, `_kriti_ai_model`

## Hooks

| Hook | Type | Purpose |
| --- | --- | --- |
| `kriti_ai_provider_class` | filter | Map a provider slug to a class |
| `kriti_ai_providers_for_capability` | filter | Provider slugs offered per capability |
| `kriti_ai_provider_models` | filter | Suggested models per provider |
| `kriti_ai_prompt_variables` | filter | Prompt variable replacements |
| `kriti_ai_writer_bridge_max_tokens` | filter | Output budget per Writer operation |
| `kriti_ai_media_stored` / `kriti_ai_media_published` / `kriti_ai_content_published` | action | Generated item lifecycle |

## Local development

Requires WordPress 5.8+ and PHP 7.4+. With no keys configured, the Mock provider lets you run the whole pipeline.

```bash
# Lint PHP
find kriti-ai -name '*.php' -print0 | xargs -0 -n1 php -l

# Lint JS
node --check kriti-ai/admin/js/admin.js
```

Video endpoints (Sora, Veo, download URLs) follow current provider documentation and may change; keep the provider classes updated as APIs evolve.
