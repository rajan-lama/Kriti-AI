# AGENTS.md — Kriti AI (WordPress plugin)

Guidance for AI coding agents working in this plugin. Read §6 (pitfalls) before changing providers, settings or anything that runs outside wp-admin.

## 1. What this plugin is

Kriti AI connects WordPress to AI providers (text, image, audio, video) using the site owner's own API keys or self-hosted servers, runs generation through a background queue, and keeps every result as a **draft** until a human publishes it.

It is also the **platform** for the Kriti AI Pro add-on (`../kriti-ai-pro`): Writer never calls providers itself; it calls Kriti AI through `includes/class-writer-bridge.php`.

```
Prompt (+ {{variables}})
  → AI Studio (generate.php) or Writer request
  → Queue (wp_kriti_ai_jobs, WP-Cron worker, kriti_ai_queue_lock)   [Writer: synchronous via the bridge]
  → Provider (Text | Image | Audio | Video)
  → Draft post / kriti_ai_media draft  → metrics → human publishes
```

## 2. Core rules

1. **Draft-first.** Generated articles are `draft` posts; generated media are `kriti_ai_media` drafts (`_kriti_ai_state = draft`) until published to the Media Library. Writer podcasts are the exception: they are saved as Media Library attachments attached to the post (still not shown until the editor enables them).
2. **Providers are enabled by configuration, not switches.** `Provider::is_enabled()`:
   - providers that need a key → a key exists (option or `KRITI_AI_KEY_<SLUG>` constant);
   - Ollama and Local AI → a `base_url` is set;
   - Mock → always, but `Provider_Manager::enabled_slugs()` drops Mock whenever any other provider of that capability is enabled.
   The stored `enabled` flag only mirrors this; never read it to decide anything.
3. **Default models.** `Provider_Manager::default_model()` — Ollama and Local AI use their saved `model` (`has_model_setting()`); all other providers use the first entry of `model_choices()`, ignoring any stored model. A request's `model` param always wins.
4. **Never send credentials to the browser** beyond the Integrations Hub key fields; never log them.
5. **Security on every endpoint:** `manage_options` + nonce (`kriti_ai_ajax` for AJAX, `kriti_ai_save_settings` for forms), sanitize input, escape output, `$wpdb->prepare()` for SQL.

## 3. File map

```
kriti-ai.php                    Constants (KRITI_AI_VERSION, KRITI_AI_DIR, KRITI_AI_URL), boot, kriti_ai_pro_platform filter
includes/
  autoload.php                  KritiAI\Foo\Bar_Baz → includes/foo/class-bar-baz.php; tries the plain lower-cased name first
                                (OpenAI_Text → class-openai-text.php), then a CamelCase-split name
  class-provider-manager.php    Capability → slugs, slug → class map, requires_key(), is_local_ai(), has_model_setting(),
                                default_model(), model_choices(), default_base_url(), label(), create() (uncached instance)
  class-settings.php            Settings::get() defaults, choices() (shared select options), providers()/provider(),
                                handle_save() for BOTH the Integrations Hub and the Settings page
  class-ajax.php                AI Studio queueing (queue_content/image/audio/video), dashboard, load_prompt, test_provider
  class-writer-bridge.php       Writer PlatformContract: createJob/getJob/listPrompts/getPrompt/getGenerationOptions,
                                operation prompts, podcast generation (chunk → synthesize → join → attachment)
  class-assets.php              Asset versions = KRITI_AI_VERSION . filemtime (browsers never keep stale CSS/JS)
  core/class-provider.php       HTTP helpers, get_api_key/get_model/get_base_url/get_top_k, is_enabled(),
                                test_connection() + connection_request(), write_tmp_file(), extract_error()
  providers/class-local-ai.php  Local AI base_url()/check_url()/check_config()
  providers/{text,image,audio,video}/class-*.php
admin/views/                    providers.php (Integrations Hub), settings.php, generate.php (AI Studio), dashboard.php,
                                queue.php, header.php, footer.php
admin/js/admin.js               Vanilla JS, no build step (initGenerate / initQueue / initDashboard / initProviders ...)
admin/css/admin.css             Plugin styles; tailwind.css is prebuilt (never load Tailwind from a CDN)
```

Admin pages: Dashboard (`kriti-ai`), AI Studio (`kriti-ai-generate`), Queue (`kriti-ai-queue`), Settings (`kriti-ai-settings`), Integrations Hub (`kriti-ai-providers`, menu label "MCP Server").

## 4. Providers

| Capability | Slugs |
| --- | --- |
| text | `openai`, `anthropic`, `gemini`, `deepseek`, `groq`, `openrouter`, `ollama`, `mock` |
| image | `openai_image`, `gemini_image`, `stability`, `replicate`, `local_ai_image`, `mock` |
| audio | `openai_audio`, `elevenlabs`, `gemini_audio`, `local_ai_audio`, `mock` |
| video | `openai_video`, `gemini_video`, `local_ai_video`, `mock` |

Provider-specific facts worth knowing:

- **Anthropic**: current Claude models reject `temperature`/`top_p`/`top_k`; none are sent. Opus/Sonnet/Fable 5.x requests add the `server-side-fallback-2026-07-01` beta with `fallbacks: "default"`. `stop_reason` `refusal` / `max_tokens` become clear errors.
- **Top K** is sent only by Gemini (`topK`), Ollama (`options.top_k`) and OpenRouter (`top_k`), and only when > 0.
- **Stability** posts `multipart/form-data` (SD3.5 via `/sd3` with `model`; `core` / `ultra` endpoints). **Replicate** uses `/v1/models/{owner}/{name}/predictions` (or `version` for `owner/name:version`) and polls `urls.get`.
- **Local AI** classes extend the OpenAI ones and override `get_base_url()` so they can never fall back to api.openai.com; URL and model are required.
- **Test connection** (`connection_request()`): `/v1/models` for OpenAI-style APIs, Anthropic and Gemini; `/v1/key` (OpenRouter), `/v1/user/account` (Stability), `/v1/account` (Replicate), `/v1/user` (ElevenLabs), `/api/tags` (Ollama). Read-only, nothing saved.

To add one: extend `Core\Provider`, implement the interface, add the slug to `$capabilities`, the class map and `model_choices()`, implement `connection_request()`, and add it to `readme.txt` (features + Third-Party Services).

## 5. Data

- Tables: `wp_kriti_ai_jobs` (`job_type`, `provider`, `model`, `params` JSON, `status`, `progress`, `result` JSON, `error`, `attempts`, `poll_after`, `scheduled_at`, timestamps) and `wp_kriti_ai_metrics` (per finished job: tokens, cost, duration, status).
- `kriti_ai_providers[slug]`: `api_key`, `model` (only meaningful for Ollama/Local AI), `base_url` (Ollama/Local AI), `enabled` (mirror of §2.2).
- `kriti_ai_settings` (see `Settings::get()`): `default_{text,image,audio,video}_provider` (`''` = first available), `temperature`, `max_tokens`, `top_p`, `top_k`, `image_resolution`, `image_aspect_ratio`, `image_style`, `image_quality`, `audio_voice`, `audio_speed`, `audio_stability`, `audio_similarity_boost`, `video_duration`, `video_resolution`, `video_aspect_ratio`, `video_quality`, `text_timeout`, `media_timeout`, `video_poll_interval`, `delete_uninstall`.
- Other options: `KRITI_AI_DB_version`, `kriti_ai_queue_lock` (`{marker}:{expiry}`, claimed with atomic `add_option`).
- Cron: `kriti_ai_process_queue` on the `kriti_ai_every_minute` schedule.
- Writer bridge job results: transients `kriti_ai_pro_job_<md5>` for 1 hour (`JOB_TTL`).

## 6. Pitfalls (learned the hard way)

1. **wp-admin functions are not loaded on REST, WP-Cron or front-end requests.** `wp_tempnam()`, `wp_generate_attachment_metadata()`, `is_plugin_active()` etc. need `require_once ABSPATH . 'wp-admin/includes/{file,image,media,plugin}.php'` first. `Provider::write_tmp_file()` already does this; media providers crashed without it.
2. **Autoloader naming.** Class `OpenAI_Text` must live in `class-openai-text.php`. Do not rely on CamelCase splitting (that bug once stopped every OpenAI and DeepSeek provider from loading).
3. **`Settings::handle_save()` serves two forms.** Only fields present in the request change; provider configs are only touched when `kriti_ai_providers` is posted; `kriti_ai_return_page` decides the redirect. Unchecked checkboxes are not posted — pair them with a hidden `0` input.
4. **A blank API key field means "remove the key"** (the field shows the saved key); a field that is not posted keeps the key.
5. **Writer payload values override Settings** in the bridge (`setting()`), and `max_tokens()` raises them to a per-operation floor (`MIN_TOKENS`). Writer sends its user's provider/model/sampling choices with every job.
6. **Bridge prompts are per operation** (`build_prompt()` / `editing_instruction()`): outline, section, `ai_review` (JSON report), `action_summarize`, `action_generate_faq` (JSON `faqs`), and rewrite/improve/expand/change_tone. Block-toolbar requests (`section_id` starting with `block:`) must return exactly three `Option N:` lines; whole-page requests a single result.
7. **Podcasts** (`generate_podcast`): use the default **audio** provider (ignore the payload's text provider), split text at sentence boundaries into ≤ 4000-character chunks, and join parts (MP3 frames with ID3 stripped; WAV by merging `data` chunks). Other formats cannot be joined — fail clearly.
8. **Cache busting** is by file modification time; do not hard-code asset versions.

## 7. Verification

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l   # after PHP edits
node --check admin/js/admin.js                        # after JS edits
```

There is no PHPUnit suite in this plugin. For behaviour changes, write a throwaway script outside the plugin that loads `wp-load.php`, calls the code (e.g. `Provider_Manager::create($slug, $config)->test_connection()`, `rest_do_request()`, or the bridge's `createJob()` with the Mock provider) and prints results; create temporary posts and delete them (and their job rows/attachments) in a `finally` block. Never leave test data in the site, and do not call paid providers without asking.

## 8. Docs to keep in sync

When behaviour changes, update `readme.txt` (WordPress.org: features, Third-Party Services with terms/privacy links, FAQ, changelog), `README.md`, and this file. Bump `Version:` (plugin header), `KRITI_AI_VERSION` and `Stable tag` together, only when releasing.
