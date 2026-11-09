=== Kriti AI ===
Contributors: lamarajan
Tags: ai, content generation, text to speech, image generation, video
Requires at least: 5.8
Tested up to: 7.1.4
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect multiple AI providers to generate content, images, audio and video in WordPress. Generated items stay as drafts until you publish them.

== Description ==

Kriti AI brings powerful AI capabilities directly into your WordPress dashboard.

Connect your preferred AI provider, create prompts, generate content, manage AI-generated results, and publish directly to WordPress — all from one place.

Whether you're creating blog posts, pages, products, or custom post types, Kriti AI helps you turn ideas into publish-ready WordPress content without leaving your site.

## Why Kriti AI?

Instead of switching between WordPress and separate AI tools, Kriti AI brings the AI workflow into WordPress itself.

Use your own AI provider and configure the models that work best for your needs. Kriti AI is designed to give WordPress site owners, publishers, agencies, and developers more control over how AI is used across their websites.

## Key Features

### 🤖 Connect AI to WordPress

Connect supported AI providers in the Integrations Hub and use AI directly from your WordPress dashboard.

Add an API key and the provider is ready to use. A "Test connection" button checks the key before you rely on it. Self-hosted AI (Ollama and any OpenAI-compatible "Local AI" server) is supported too.

### ✍️ Generate Content

Create AI-generated content from your own prompts.

Generate content for:

* Posts
* Pages
* Products
* Custom post types
* Other WordPress content types

### 🧠 Custom Prompts

Create and reuse prompts for your most common content workflows.

Build a prompt-driven workflow instead of repeatedly writing the same instructions.

### 🎯 Control AI Generation

Set defaults for every AI Studio parameter on the Settings page: default provider per type, temperature, max tokens, Top P, Top K, image size and style, voice and speed, video duration and quality. Change any of them for a single generation in AI Studio.

### 🖼️ AI-Generated Media

Store AI-generated media alongside your generated content and use it within your WordPress workflow.

### 📊 Generation History & Metrics

Keep track of generated content and useful generation information so you can understand how your AI workflow is being used.

### 🚀 Publish Directly to WordPress

Turn generated content into WordPress content without manually copying and pasting between different applications.

Choose the WordPress content type where your generated content should be published.

## Built for WordPress

Kriti AI is designed around the WordPress ecosystem rather than treating WordPress as an afterthought.

It works with the WordPress content model and is designed to support workflows involving posts, pages, products, and custom post types.

## Who is Kriti AI for?

Kriti AI can be useful for:

* Bloggers and publishers
* WordPress site owners
* Content teams
* Agencies
* WooCommerce store owners
* WordPress developers
* AI-powered publishing workflows

## A growing AI platform for WordPress

Kriti AI is being developed as a foundation for bringing more AI-powered workflows into WordPress.

Future capabilities may expand into AI-assisted writing, content optimization, media generation, WooCommerce workflows, automation, and other WordPress-specific AI tools.

## Privacy & AI Providers

Kriti AI connects to AI providers configured by the site administrator.

Depending on the provider and configuration you use, prompts and generated content may be sent to the selected third-party AI service for processing.

Please review the privacy policy and terms of your selected AI provider before using the service with sensitive or confidential information.

## Support

If you encounter a problem, have a feature request, or want to contribute, please use the support and development resources provided on the Kriti AI plugin page.

Kriti AI is designed to evolve with the WordPress community. Feedback and suggestions are welcome.


= Features =

* Text providers: OpenAI, Anthropic Claude, Google Gemini, DeepSeek, Groq, OpenRouter and Ollama (local models).
* Image providers: OpenAI (DALL-E), Google Gemini, Stability AI, Replicate and Local AI.
* Audio (text-to-speech) providers: OpenAI TTS, ElevenLabs, Google Gemini TTS and Local AI.
* Video providers: OpenAI Sora, Google Veo and Local AI.
* Local AI: connect your own OpenAI-compatible server (e.g. LocalAI) for image, audio and video with a server URL and an optional API key.
* Integrations Hub: a provider is enabled as soon as its API key is saved (or its server URL, for Ollama and Local AI); clear the field to disable it. "Test connection" verifies a key or server with a read-only request before you save.
* Cloud providers use a sensible default model; pick a different model per generation in AI Studio. Ollama and Local AI keep a model field for the models installed on your server.
* Settings page with defaults for every AI Studio parameter, including Top K (used by Gemini, Ollama and OpenRouter), request timeouts and default providers.
* Prompt library custom post type to store reusable prompts with dynamic variables (e.g. {{site_name}}, {{post_title}}, {{product_name}}).
* AI Studio with a prompt builder and fine-tuning controls: temperature, max tokens, Top P, Top K, voice, speed, stability, resolution, aspect ratio and quality.
* Generated articles, summaries, images, audio and video are saved as Kriti AI custom post types.
* Generated media files are stored in the uploads folder as kriti_ai_media drafts and can be published to the WordPress Media Library from the AI Media list.
* Background queue powered by WP-Cron. Long video jobs are polled automatically until the provider finishes.
* Metrics dashboard: requests, success/failure rates, token usage, estimated cost, average generation time, per-provider breakdown and draft vs published counts.
* Powers the Kriti AI Pro add-on: editor-side writing actions, AI review, summaries, FAQ generation, article and summary podcasts, and the Writer's prompt and provider choices all run on the providers configured here.
* A built-in Mock provider lets you test the full workflow without any API key.
* API keys can also be supplied via wp-config.php constants.

= Using the Mock provider =

The Mock provider simulates text, image, audio and video generation so you can verify the pipeline end to end before entering real API keys. It is offered automatically only when no other provider is set up for that type, and steps aside as soon as you add one.

= Using local or self-hosted AI =

* Ollama: enter your Ollama server URL (e.g. http://localhost:11434) and the full model name (e.g. qwen3.5:latest).
* Local AI (image, audio, video): enter the base URL of an OpenAI-compatible server and the model name it serves. Requests use the same /v1 endpoints as OpenAI; an API key is only needed if your server requires one.

Nothing is sent to a third party when you use your own server.

= Security notes =

* API keys are stored in wp_options and are only shown in the Integrations Hub. For extra safety, define a constant named `KRITI_AI_KEY_` followed by the provider slug in wp-config.php, e.g. `KRITI_AI_KEY_OPENAI`, `KRITI_AI_KEY_ANTHROPIC`, `KRITI_AI_KEY_GEMINI`, `KRITI_AI_KEY_ELEVENLABS` or `KRITI_AI_KEY_OPENAI_IMAGE`; constants always override the stored values.
* "Test connection" only reads from the provider (e.g. its model list or account info). It never generates content, costs credits or saves the key.
* All admin actions require the `manage_options` capability and a nonce.

== Third-Party Services ==

Kriti AI integrates directly with external AI API services to perform AI generation requests when configured by the site administrator:

* **OpenAI (GPT, DALL-E, TTS, Sora)**
  - Web: https://openai.com
  - Terms of Service: https://openai.com/policies/terms-of-use/
  - Privacy Policy: https://openai.com/policies/privacy-policy/
  - Data sent: User prompts, model choices, temperature settings, and audio/video options.

* **Google Cloud & Gemini (Gemini Flash/Pro, Imagen, Veo, Cloud TTS)**
  - Web: https://ai.google.dev
  - Terms of Service: https://ai.google.dev/terms
  - Privacy Policy: https://policies.google.com/privacy
  - Data sent: User prompts, script text, generation attributes.

* **DeepSeek API**
  - Web: https://www.deepseek.com
  - Terms of Service: https://cdn.deepseek.com/policies/en-US/deepseek-terms-of-use.html
  - Privacy Policy: https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html
  - Data sent: Prompt text and model parameters.

* **Anthropic (Claude)**
  - Web: https://www.anthropic.com
  - Terms of Service: https://www.anthropic.com/legal/commercial-terms
  - Privacy Policy: https://www.anthropic.com/legal/privacy
  - Data sent: Prompt text and model parameters.

* **Groq**
  - Web: https://groq.com
  - Terms of Service: https://groq.com/terms-of-use/
  - Privacy Policy: https://groq.com/privacy-policy/
  - Data sent: Prompt text and model parameters.

* **OpenRouter**
  - Web: https://openrouter.ai
  - Terms of Service: https://openrouter.ai/terms
  - Privacy Policy: https://openrouter.ai/privacy
  - Data sent: Prompt text and model parameters. OpenRouter forwards requests to the model provider you choose.

* **Stability AI**
  - Web: https://stability.ai
  - Terms of Service: https://stability.ai/terms-of-use
  - Privacy Policy: https://stability.ai/privacy-policy
  - Data sent: Image prompts, model and aspect ratio.

* **Replicate**
  - Web: https://replicate.com
  - Terms of Service: https://replicate.com/terms
  - Privacy Policy: https://replicate.com/privacy
  - Data sent: Image prompts, model and aspect ratio.

* **ElevenLabs**
  - Web: https://elevenlabs.io
  - Terms of Service: https://elevenlabs.io/terms-of-use
  - Privacy Policy: https://elevenlabs.io/privacy-policy
  - Data sent: Text to be spoken and voice settings.

* **Ollama and Local AI (self-hosted)**
  - Requests go only to the server URL you enter (for example your own computer or network). No data is sent to Kriti AI or any other third party unless that server is itself a third-party service.

When the Kriti AI Pro add-on is active, the content it sends for writing actions, reviews, summaries, FAQs and podcasts is processed by the provider you configured above.

== Installation ==

1. Upload the `kriti-ai` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen.
3. Open Kriti AI → Integrations Hub and add an API key, or a server URL for Ollama / Local AI. Click "Test connection" to check it, then save.
4. Optionally set your defaults on Kriti AI → Settings.
5. Head to AI Studio, write a prompt or load a saved prompt, tune the parameters and click Generate.
6. Watch the job in the Queue screen. Publish finished items from the queue, AI Media list or the post editor.

== Frequently Asked Questions ==

= How are generated items kept as drafts? =

Articles are saved as drafts in Kriti AI → Content Library. When you publish one, Kriti AI creates a draft copy in the post type you choose under "Publish Target" (Posts by default), so you publish the final post yourself. Image, audio and video files are saved in wp-content/uploads as Kriti AI → Media Library drafts and are added to the WordPress Media Library only when you publish them. Nothing becomes publicly visible until you publish it.

= Which providers are supported? =

Text: OpenAI, Anthropic Claude, Google Gemini, DeepSeek, Groq, OpenRouter, Ollama and Mock. Image: OpenAI (DALL-E), Google Gemini, Stability AI, Replicate, Local AI and Mock. Audio: OpenAI TTS, ElevenLabs, Google Gemini TTS, Local AI and Mock. Video: OpenAI Sora, Google Veo, Local AI and Mock. Configure any combination in the Integrations Hub.

= How do I turn a provider on or off? =

Save its API key in the Integrations Hub (or its server URL for Ollama and Local AI) and it is enabled. Clear the field and save to disable it. There is no separate on/off switch.

= How do I check that an API key works? =

Click "Test connection" on the provider's card. A green "Connected" tick means the key or server works; otherwise the provider's own error message is shown. The test does not save anything, so save your settings afterwards.

= Where do I choose the model? =

Cloud providers use their recommended default model. Choose another model for a single generation in AI Studio. Ollama and Local AI have a Model field in the Integrations Hub because their models depend on what you have installed.

= Why does my video job stay in "processing"? =

Video providers run asynchronously. The plugin polls them on every WP-Cron tick using the configured poll interval. If your site has no external cron, make sure WP-Cron is enabled (default) or set up a real cron job hitting wp-cron.php.

== Changelog ==

= 1.1.0 =
* Added providers: Anthropic Claude, Groq and OpenRouter (text), Stability AI and Replicate (image), ElevenLabs (audio), and Local AI for image, audio and video.
* Added "Test connection" to every provider in the Integrations Hub.
* Changed: providers are enabled automatically when an API key (or server URL for Ollama / Local AI) is saved; the on/off switches were removed.
* Changed: cloud providers use a default model; the Model field remains only for Ollama and Local AI.
* Added a Settings page with defaults for every AI Studio parameter, plus Top K in AI Studio.
* Changed: the Mock provider is offered only when no other provider is set up for that type.
* Kriti AI Pro: Prompt Library and per-user provider/model choices, an output budget sized for each writing task, longer-lived job results, and dedicated prompts for writing actions, AI review, summaries and FAQs.
* Kriti AI Pro: article and summary podcasts (long text is split, voiced and joined into one Media Library audio file).
* Fixed: the OpenAI providers (text, image, audio and video) and DeepSeek could not be enabled because their classes never loaded (autoloader bug).
* Fixed: media providers failed outside wp-admin (REST and WP-Cron) because a WordPress helper was not loaded.
* Fixed: admin CSS and JS are versioned by file date so browsers pick up updates.
* Updated default model lists (e.g. Claude Opus 5.5, Sonnet 5.5 and Haiku 5.5).

= 1.0.2 =
* Fixed Found Bug.

= 1.0.1 =
* Fixed Bug on AI Studio.
* Added header and footer.

= 1.0.0 =
* Initial release.
