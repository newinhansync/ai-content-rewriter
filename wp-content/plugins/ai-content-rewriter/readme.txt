=== AI Content Rewriter ===
Contributors: hansync
Tags: ai, content, rewriter, chatgpt, gemini, rss, blog, seo
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Transform URLs, RSS feeds, or text into SEO-optimized blog posts using AI (ChatGPT, Gemini).

== Description ==

AI Content Rewriter is a powerful WordPress plugin that transforms URLs, RSS feeds, or plain text into SEO-optimized blog posts using advanced AI models like ChatGPT and Google Gemini.

= Key Features =

* **AI-Powered Content Generation**: Transform any content into unique, SEO-friendly blog posts
* **Multiple AI Support**: Works with ChatGPT (GPT-4o, GPT-4, GPT-3.5) and Google Gemini
* **RSS Feed Reader**: Import and rewrite content from RSS feeds automatically
* **Auto Category Detection**: AI automatically suggests or creates appropriate categories
* **Extended Content**: Generate content 1.5x longer than the original with rich details
* **Async Processing**: Background processing with progress tracking - no timeout issues
* **SEO Optimization**: Auto-generates meta titles, descriptions, keywords, and tags
* **Automation Dashboard**: Monitor cron status, view execution logs, configure external cron
* **Multi-language Support**: Translate and rewrite content in any language
* **Prompt Management**: Customize the AI prompt directly in Settings for full control over content generation

= RSS Feed Reader Features =

* Add unlimited RSS feeds
* Auto-fetch new articles at scheduled intervals
* Preview original content before rewriting
* Bulk rewrite multiple articles
* Track processing status with visual progress indicators

= Content Generation Features =

* Maintains original content length or expands by 1.5x
* Proper HTML structure with H2/H3 headings
* Automatic SEO meta data generation
* Custom category assignment or AI-suggested categories
* Tag auto-generation

== Installation ==

= Automatic Installation =

1. Go to Plugins > Add New in your WordPress admin
2. Search for "AI Content Rewriter"
3. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin ZIP file
2. Go to Plugins > Add New > Upload Plugin
3. Choose the ZIP file and click "Install Now"
4. Activate the plugin

= After Activation =

1. Go to AI Rewriter > Settings
2. Enter your AI API keys (ChatGPT and/or Gemini)
3. Configure default options
4. Start adding RSS feeds or rewriting content!

== Frequently Asked Questions ==

= What AI services are supported? =

Currently, the plugin supports:
* OpenAI ChatGPT (GPT-4o, GPT-4o-mini, GPT-4-turbo, GPT-4, GPT-3.5-turbo, o1, o1-mini)
* Google Gemini (Gemini Pro, Gemini 1.5 Pro, Gemini 1.5 Flash)

= Do I need API keys? =

Yes, you need API keys from OpenAI and/or Google to use this plugin. You can get them from:
* OpenAI: https://platform.openai.com/api-keys
* Google AI: https://makersuite.google.com/app/apikey

= Is the generated content unique? =

Yes, the AI completely rewrites the content in its own words while preserving the original meaning and information.

= Does it support Korean and other languages? =

Yes! The plugin supports multiple languages including Korean, English, Japanese, Chinese, and more.

= What happens if the process times out? =

The plugin uses asynchronous processing. Long content generation runs in the background with progress tracking, so timeout issues are avoided.

= Will my data be deleted when I deactivate the plugin? =

No, deactivation only stops the plugin. Your data is preserved. If you want to delete all data when uninstalling, enable "Delete data on uninstall" in Settings before deleting the plugin.

== Screenshots ==

1. RSS Feed Reader - View and manage imported articles
2. Content Rewriting - Progress tracking during AI processing
3. Settings Page - Configure AI providers and options
4. Feed Management - Add and manage RSS feeds

== Changelog ==

= 1.2.0 =
* Added Automation tab in Settings for complete cron management
* External Cron support for reliable scheduling on shared hosting
* Cron execution logging with database storage
* Real-time status monitoring dashboard
* Manual execution buttons for each scheduled task
* Setup guides for cPanel, EasyCron, Cron-Job.org
* Token-based security for external cron endpoint
* Recommendations for optimal cron configuration

= 1.1.0 =
* Major refactoring: Removed unused template system
* Added Prompt Management tab in Settings
* Prompt is now directly editable via Settings > Prompt Management
* Prompt stored in wp_options for simplicity
* Users can customize or reset prompt to default
* Cleaned up AjaxHandler, AdminMenu, and related files
* Improved PromptManager class architecture

= 1.0.6 =
* Added async processing for long content generation
* Added AI auto-category selection/creation
* Expanded content generation to 1.5x original length
* Fixed JSON parsing for AI responses
* Improved progress UI with step indicators
* Increased timeout limits for API calls

= 1.0.5 =
* Added RSS feed reader functionality
* Improved prompt templates
* Added SEO meta field generation

= 1.0.0 =
* Initial release
* ChatGPT and Gemini integration
* URL content extraction
* Basic content rewriting

== Upgrade Notice ==

= 1.2.0 =
New automation features! Configure external cron services for reliable RSS feed processing on shared hosting. Full monitoring dashboard with execution logs.

= 1.1.0 =
Major update: Template system removed and replaced with simplified Prompt Management. Customize your AI prompt directly in Settings.

= 1.0.6 =
Major update with async processing, auto-category, and extended content generation. Recommended for all users.

== Requirements ==

* WordPress 6.0 or higher
* PHP 8.0 or higher
* OpenAI API key and/or Google Gemini API key
* cURL PHP extension
* JSON PHP extension

== Privacy Policy ==

This plugin sends content to third-party AI services (OpenAI, Google) for processing. Please review their privacy policies:
* OpenAI: https://openai.com/privacy
* Google: https://policies.google.com/privacy

No personal data is collected by this plugin itself. Content is sent to AI services only when you initiate a rewrite action.
