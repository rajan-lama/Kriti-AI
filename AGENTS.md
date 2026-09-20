# AGENTS.md

## Project: Kriti AI (WordPress Plugin)

### 1. Product Overview

**Kriti AI** is a comprehensive, WordPress-native AI orchestration, content generation, and media synthesis platform. It enables site owners, WooCommerce store managers, agencies, and publishers to connect multiple AI service providers (OpenAI, Anthropic Claude, Google Gemini, DeepSeek, Groq, OpenRouter, ElevenLabs, Stability AI, Replicate, Ollama) using their own API keys, manage custom prompts with dynamic variables, run asynchronous background generation jobs, and enforce a **Draft-First Workflow**.

#### Primary Value Proposition
1. **Multi-Provider & Capability Abstraction**: Mix and match Text, Image, Audio, and Video AI providers without platform lock-in.
2. **Draft-First Security & Control**: All generated content (posts, images, audio tracks, videos) lands as `draft` posts or draft Media Library attachments until explicitly reviewed and published by an administrator.
3. **WordPress & WooCommerce Ecosystem Integration**: Native WordPress storage using `$wpdb`, Custom Post Types (`kriti_ai_prompt`), WP-Cron, Action Scheduler compatibility, Media Library integration, dynamic prompt variables (`{{site_name}}`, `{{post_title}}`, `{{product_name}}`, `{{product_price}}`, `{{product_sku}}`, `{{brand_voice}}`, `{{tone}}`), and REST/AJAX APIs.
4. **Background Queue Engine**: Asynchronous job handling for heavy AI requests (including pollable long-running video models like OpenAI Sora and Google Veo) backed by database locking (`kriti_ai_queue_lock`).
5. **Metrics & Cost Analytics**: Detailed tracking of API calls, token counts, estimated costs, execution durations, and per-provider success/failure rates.
6. **Mock Testing Capabilities**: Built-in zero-config `Mock_Provider` for offline development, automated testing, and pipeline validation without API keys.

#### Core Lifecycle
```
PROMPT TEMPLATE / USER PROMPT WITH DYNAMIC VARIABLES {{product_name}}, {{site_name}}
          │
          ▼
   GENERATOR STUDIO (Modes: Text, Image, Audio, Video | Params: Temp, Tokens, Voice, Speed, Resolution, Aspect Ratio)
          │
          ▼
   BACKGROUND QUEUE (`wp_kriti_ai_jobs` Table / Cron Worker / Lock Guard)
          │
          ▼
 ┌────────┴────────┬─────────────────┬────────────────┐
 │                 │                 │                │
▼                 ▼                 ▼                ▼
TEXT GENERATION   IMAGE GENERATION  AUDIO SYNTHESIS  VIDEO GENERATION
(Draft Post)      (Draft Media)     (Draft Media)    (Async Polled Draft)
 │                 │                 │                │
 └────────┬────────┴─────────────────┴────────────────┘
          │
          ▼
 METRICS RECORDED (`wp_kriti_ai_metrics` Table) & DRAFT QUEUE UPDATED
          │
          ▼
   MANUAL REVIEW → PUBLISH / UNPUBLISH TOGGLE
```

---

## 2. Core Architectural Principles

### 2.1 WordPress-Native First
All core functionalities run natively inside WordPress without external SaaS intermediate servers or Node.js microservices.
- **Data Storage**: WordPress options (`kriti_ai_providers`, `kriti_ai_settings`), custom tables (`wp_kriti_ai_jobs`, `wp_kriti_ai_metrics`), and post meta (`_kriti_ai_*`).
- **Background Processing**: WP-Cron (`kriti_ai_process_queue`) with single-minute schedules and atomic lock guards (`kriti_ai_queue_lock`).
- **Frontend**: Vanilla JavaScript (ES6+) with modern CSS3 styling and native WordPress Admin UI components.
- **APIs & Security**: WordPress REST API / `admin-ajax.php` endpoints secured with Nonces (`wp_create_nonce('kriti_ai_ajax')`) and Capability Checks (`manage_options`).

### 2.2 Draft-First Workflow
To protect store reputation and avoid publishing auto-generated hallucinated content:
- Text generations configured in `post` mode land as draft posts (`post_status = draft`).
- Generated images (`.png`, `.webp`), audio (`.mp3`, `.wav`), and video (`.mp4`, `.gif`) are downloaded to `wp-content/uploads/` and registered in the Media Library with custom draft state post meta (`_kriti_ai_state = draft`).
- Publishing and unpublishing are controlled explicitly via the plugin's Queue / Gallery screens or standard WordPress editors.

### 2.3 Provider Abstraction & Capability Routing
AI services are cleanly decoupled into capability contracts:
- `Text_Provider`: Handles prompt completion and JSON formatting.
- `Image_Provider`: Handles text-to-image generation (PNG/WebP/SVG).
- `Audio_Provider`: Handles text-to-speech synthesis into binary streams.
- `Video_Provider`: Handles asynchronous job creation (`start_video`) and interval polling (`poll_video`).
- Extensible via WordPress filters (`kriti_ai_provider_class`, `kriti_ai_providers_for_capability`, `kriti_ai_provider_models`).

### 2.4 Prompt Variable Engine
Built-in substitution engine replaces dynamic tags in prompts before sending them to providers:
- `{{site_name}}`, `{{site_url}}`, `{{author}}`, `{{current_date}}`
- `{{post_title}}`, `{{post_content}}`, `{{excerpt}}`
- `{{product_name}}`, `{{product_price}}`, `{{product_sku}}`, `{{product_description}}`, `{{stock_status}}` (WooCommerce)
- `{{brand_voice}}`, `{{tone}}`, `{{keywords}}`

---

## 3. Technology Stack & Coding Standards

### Backend
- **PHP**: 7.4+ (compatible up to PHP 8.3+)
- **Namespace**: `KritiAI\` (PSR-4 autoloading via `includes/autoload.php`)
- **Database**: MySQL 5.7+ / MariaDB 10.3+ via `$wpdb` and `dbDelta()`
- **HTTP Client**: `wp_remote_post()`, `wp_remote_get()`, and `wp_remote_request()` in `KritiAI\Core\Provider`

### Frontend & Styling
- **JS**: Vanilla JavaScript (ES6+), modern DOM manipulation, Fetch API wrappers for WP AJAX.
- **CSS**: Pure Vanilla CSS (`admin/css/admin.css`) structured with custom properties / CSS variables (`--kriti-ai-primary`), glassmorphism cards, status pills, and responsive grid layouts.

### Quality & Linting
- **PHP Linting**: `find . -name '*.php' -exec php -l {} \;`
- **JS Linting**: `node --check admin/js/admin.js`
- **Security & Standards**: Strict Nonces, Capability Checks (`manage_options`), Sanitization (`sanitize_text_field`, `sanitize_key`), and Escaping (`esc_html`, `esc_attr`, `esc_url`).

---

## 4. Product Architecture & File Map

```
kriti-ai/
├── kriti-ai.php               Main plugin file, constants (KRITI_AI_VERSION, KRITI_AI_DIR), bootstrap
├── uninstall.php                   Data cleanup routine (respects `delete_uninstall` setting)
├── README.md                       Product summary & installation guide
├── AGENTS.md                       Developer & AI Agent reference documentation
├── includes/
│   ├── autoload.php                PSR-4 class autoloader for KritiAI namespace
│   ├── class-plugin.php            Plugin singleton initializer & registry hookup
│   ├── class-install.php           Activator, dbDelta table schemas, default options, cron schedule
│   ├── class-post-types.php        CPT `kriti_ai_prompt` & metabox parameter handlers
│   ├── class-provider-manager.php  Provider registry, capability mapping, model choices & key checking
│   ├── class-queue.php             Queue manager, job inserter, DB locking & query helpers
│   ├── class-worker.php            Cron execution loop (Text, Image, Audio, Video, polling)
│   ├── class-generator.php         Prompt variable engine, title/content parser, draft creation
│   ├── class-media.php             File uploader (`wp_upload_bits`), attachment metadata & draft state
│   ├── class-metrics.php           Token, cost, duration, and status statistics recorder
│   ├── class-settings.php          Settings API handler & option getters/setters
│   ├── class-ajax.php              Endpoints for AJAX actions (generate, poll, publish, settings, prompts)
│   ├── class-admin.php             Submenu pages, view loading & enqueue triggers
│   ├── class-assets.php            Styles & JavaScript enqueuer with localization
│   ├── core/
│   │   ├── class-provider.php              Base abstract provider with HTTP & response helpers
│   │   ├── interface-text-provider.php     Text provider contract (`generate_text`)
│   │   ├── interface-image-provider.php    Image provider contract (`generate_image`)
│   │   ├── interface-audio-provider.php    Audio provider contract (`synthesize_audio`)
│   │   └── interface-video-provider.php    Video provider contract (`start_video`, `poll_video`)
│   └── providers/
│       ├── class-mock-provider.php         Offline mock provider for text, image, audio & video testing
│       ├── text/                           Text Providers
│       │   ├── class-openai-text.php       OpenAI GPT models (`gpt-4o`, `gpt-4o-mini`, etc.)
│       │   ├── class-anthropic-text.php    Anthropic Claude (`claude-3-5-sonnet`, etc.)
│       │   ├── class-gemini-text.php       Google Gemini text (`gemini-2.0-flash`, etc.)
│       │   ├── class-deepseek-text.php     DeepSeek (`deepseek-chat`, `deepseek-reasoner`)
│       │   ├── class-groq-text.php         Groq high-speed Llama models
│       │   ├── class-openrouter-text.php   OpenRouter API proxy
│       │   └── class-ollama-text.php       Local Ollama models (`llama3.1`, `mistral`, etc.)
│       ├── image/                          Image Providers
│       │   ├── class-openai-image.php      OpenAI DALL-E 3 / DALL-E 2
│       │   ├── class-gemini-image.php      Google Gemini Imagen 3
│       │   ├── class-stability-image.php   Stability AI (SD3 / Ultra)
│       │   └── class-replicate-image.php   Replicate (Flux / SDXL)
│       ├── audio/                          Audio Providers
│       │   ├── class-openai-audio.php      OpenAI TTS (`tts-1`, `tts-1-hd`, `gpt-4o-mini-tts`)
│       │   ├── class-elevenlabs-audio.php  ElevenLabs Multilingual speech synthesis
│       │   └── class-gemini-audio.php      Google Cloud TTS / Gemini Audio
│       └── video/                          Video Providers
│           ├── class-openai-video.php      OpenAI Sora (`sora-2`, `sora-2-pro`) async video
│           └── class-gemini-video.php      Google Veo (`veo-2.0-generate-001`, `veo-3.0`) async video
└── admin/
    ├── css/
    │   └── admin.css               Dashboard layouts, metrics cards, pill badges, variable tags
    ├── js/
    │   └── admin.js                Vanilla JS AJAX handlers, prompt loader, variable inserter, poller
    └── views/
        ├── dashboard.php           Metrics overview, KPI cards, velocity charts, provider table
        ├── generate.php            Studio workspace, prompt library, variable pills, fine-tuning
        ├── queue.php               Queue status table, progress bars, retry/cancel triggers
        └── settings.php            Provider enable/disable toggles, API key fields, model settings
```

---

## 5. Database Schema & Data Model

### 5.1 Custom Tables

#### 1. `wp_kriti_ai_jobs` (Background Queue Table)
Stores generation requests and execution states.
- `id` (`BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`)
- `job_type` (`VARCHAR(20)`): `text` | `image` | `audio` | `video`
- `provider` (`VARCHAR(40)`): e.g. `openai`, `anthropic`, `gemini`, `deepseek`, `groq`, `openrouter`, `openai_image`, `gemini_image`, `stability`, `replicate`, `mock`
- `model` (`VARCHAR(120)`): Model identifier (e.g., `gpt-4o-mini`, `dall-e-3`, `deepseek-chat`)
- `params` (`LONGTEXT`): JSON payload (prompt, temperature, tokens, voice, resolution, aspect_ratio)
- `status` (`VARCHAR(20)`): `pending` | `processing` | `completed` | `failed` | `cancelled`
- `progress` (`TINYINT UNSIGNED`): Percentage (0 to 100)
- `result` (`LONGTEXT`): JSON payload with post IDs, attachment URLs, titles, or body content
- `error` (`TEXT`): Failure message when status is `failed`
- `attempts` (`TINYINT UNSIGNED`): Retry counter
- `poll_after` (`DATETIME`): Next poll time for async video jobs
- `scheduled_at` (`DATETIME`): Initial scheduled execution timestamp
- `created_at` (`DATETIME`), `updated_at` (`DATETIME`)

#### 2. `wp_kriti_ai_metrics` (Analytics & Usage Table)
Logs performance metrics for finished requests.
- `id` (`BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`)
- `job_id` (`BIGINT UNSIGNED`): Foreign key matching `wp_kriti_ai_jobs.id`
- `item_type` (`VARCHAR(20)`): `text` | `image` | `audio` | `video`
- `provider` (`VARCHAR(40)`): Provider slug
- `model` (`VARCHAR(120)`): Model slug
- `status` (`VARCHAR(20)`): `success` | `failed`
- `tokens_in` (`BIGINT UNSIGNED`): Prompt/input tokens
- `tokens_out` (`BIGINT UNSIGNED`): Output/completion tokens
- `cost` (`DECIMAL(12,6)`): Calculated cost in USD
- `duration_ms` (`INT UNSIGNED`): Execution time in milliseconds
- `created_at` (`DATETIME`)

### 5.2 Options & Config Keys
- `KRITI_AI_DB_version`: DB migration version string (`1.0.0`).
- `kriti_ai_providers`: Provider configurations (`enabled`, `api_key`, `model`, `base_url`).
- `kriti_ai_settings`: General settings:
  - `default_text_provider`, `default_image_provider`, `default_audio_provider`, `default_video_provider`
  - `temperature` (default `0.7`), `max_tokens` (default `1024`)
  - `text_timeout` (90s), `media_timeout` (180s), `video_poll_interval` (15s)
  - `delete_uninstall` (`0` or `1`)
- `kriti_ai_queue_lock`: Atomic lock string formatted as `{uuid}:{expiration_timestamp}`.

---

## 6. Security, Validation & Escaping Requirements

1. **Permission Checks**: All AJAX and admin actions MUST verify user capabilities with `current_user_can('manage_options')`.
2. **Nonce Verification**: All AJAX calls MUST send `nonce` and verify via `check_ajax_referer('kriti_ai_ajax', 'nonce')`.
3. **Data Sanitization**:
   - Keys & Slugs: `sanitize_key()`
   - Text inputs & prompts: `sanitize_text_field()`, `sanitize_textarea_field()`
   - URLs & Base Endpoints: `esc_url_raw()`
   - JSON Payloads: `wp_json_encode()`, `json_decode()`
4. **Output Escaping**:
   - HTML views: `esc_html()`, `esc_attr()`, `esc_url()`
   - Rich post content: `wp_kses_post()`
5. **Database Safety**: Always use `$wpdb->prepare()` for dynamic parameter binding.

---

## 7. Developer & AI Agent Guidelines

1. **Inspect Before Modifying**: View existing provider files in `includes/providers/` before creating or editing provider adapters.
2. **Strict Capability Typing**: Ensure text providers implement `Text_Provider`, image providers implement `Image_Provider`, audio providers implement `Audio_Provider`, and video providers implement `Video_Provider`.
3. **Draft-First Guarantee**: Always route generated outputs to draft posts or draft media attachments (`_kriti_ai_state = 'draft'`).
4. **Verification Protocol**:
   - Run `find . -name '*.php' -exec php -l {} \;` after PHP edits.
   - Run `node --check admin/js/admin.js` after JS edits.
5. **Zero SaaS Overhead**: Maintain native execution with zero required node build step or external microservice reliance.
