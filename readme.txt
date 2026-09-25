=== Kriti AI ===
Contributors: lamarajan
Tags: ai, content generation, text to speech, image generation, video
Requires at least: 5.8
Tested up to: 7.1.2
Requires PHP: 7.4
Stable tag: 1.0.2
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

Connect supported AI providers and use AI directly from your WordPress dashboard.

Configure your provider, model, and generation settings according to your workflow.

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

Configure AI generation settings such as the model and temperature to control how your content is generated.

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

* Provider abstraction with Local LLMs, OpenAI, Google Gemini, DeepSeek, Ollama (text), OpenAI DALL-E, Gemini Imagen 3, OpenAI TTS, Google Cloud TTS (audio) and OpenAI Sora / Gemini Veo (video).
* Provider management from the dashboard: enable/disable, API keys, model and endpoint per provider.
* Prompt library custom post type to store reusable prompts with dynamic variables (e.g. {{site_name}}, {{post_title}}, {{product_name}}).
* Generator screen with a prompt builder and fine-tuning controls: temperature, max tokens, voice, speed, stability, resolution, aspect ratio and quality.
* Generated articles, summaries, images, audio and video are saved as Kriti AI custom post types.
* Generated media files are stored in the uploads folder as kriti_ai_media drafts and can be published to the WordPress Media Library from the AI Media list.
* Background queue powered by WP-Cron. Long video jobs are polled automatically until the provider finishes.
* Metrics dashboard: requests, success/failure rates, token usage, estimated cost, average generation time, per-provider breakdown and draft vs published counts.
* A built-in Mock provider lets you test the full workflow without any API key.
* API keys can also be supplied via wp-config.php constants.
* Local LLM supports.

= Using the Mock provider =

The Mock provider is enabled by default and simulates text, image, audio and video generation so you can verify the pipeline end to end before entering real API keys.

= Security notes =

* API keys are stored in wp_options and are only shown in the settings screen. For extra safety, define `KRITI_AI_KEY_OPENAI`, `KRITI_AI_KEY_GEMINI`, `KRITI_AI_KEY_DEEPSEEK`, in wp-config.php; constants always override the stored values.
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

== Installation ==

1. Upload the `kriti-ai` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen.
3. Open the new "Kriti AI" menu and visit Settings to enable providers and add API keys.
4. Head to Generate, write a prompt or load a saved prompt, tune the parameters and click Generate.
5. Watch the job in the Queue screen. Publish finished items from the queue, AI Media list or the post editor.

== Frequently Asked Questions ==

= How are generated items kept as drafts? =

Content generation creates a standard `post` in `draft` status. Image, audio and video files are uploaded to wp-content/uploads and registered as Media Library attachments carrying a `draft`/`published` state. Nothing becomes publicly visible until you publish it.

= Which providers are supported? =

Text: Local LLMs, OpenAI, Google Gemini, DeepSeek, Ollama and Mock. Image: Local LLMs, OpenAI DALL-E, Gemini Imagen 3, and Mock. Audio: Local LLMs, OpenAI TTS, Google Cloud TTS and Mock. Video: Local LLMs, OpenAI Sora, Google Veo and Mock. The dashboard lets the site owner enable and configure any combination.

= Why does my video job stay in "processing"? =

Video providers run asynchronously. The plugin polls them on every WP-Cron tick using the configured poll interval. If your site has no external cron, make sure WP-Cron is enabled (default) or set up a real cron job hitting wp-cron.php.

== Changelog ==

= 1.0.2 =
* Fixed Found Bug.

= 1.0.1 =
* Fixed Bug on AI Studio.
* Added header and footer.

= 1.0.0 =
* Initial release.
