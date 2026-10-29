=== Agent Sidekick – AI Assistant & Chatbot for WordPress ===
Contributors: joelmorel, morelmedia
Donate link: https://morelmedia.org
Tags: chatbot, ai assistant, chat, woocommerce, customer support
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modern, standalone AI Agent & Assistant for WordPress. Connects directly to Google Gemini, OpenAI, Groq, or n8n without external servers.

== Description ==

**Agent Sidekick** is a fast, lightweight, standalone AI customer support agent for WordPress. It lives directly on your WordPress site and connects directly to top-tier AI providers—**no expensive monthly SaaS subscription, no external Vercel server, and no coding required.**

Whether you are running an agency, a portfolio, a blog, or an e-commerce store, Agent Sidekick greets your visitors, answers their questions 24/7, recommends products, tracks orders, captures leads, and escalates to human support when needed.

### 🌟 Key Features

* **Zero External Hosting Required**: Runs 100% on your WordPress server through the native WordPress REST API (`/wp-json/wp-ai-chatbot/v1/chat`).
* **Multi-Provider AI Engine**:
  * **Google Gemini** (Generous free tier directly from Google AI Studio, featuring Auto-Detect to automatically adapt to the best available Flash model).
  * **OpenAI** (Industry-standard GPT-4o and GPT-4o-mini).
  * **Groq** (Ultra-fast inference with open models such as Llama 3.3).
  * **n8n / Custom Webhooks** (Easily route inquiries into custom automation workflows or CRM pipelines).
* **Native Mini-RAG & Site Learning**: Automatically searches your published WordPress pages, blog posts, and WooCommerce products for keywords matching the visitor's question, passing relevant excerpts to the AI in real time.
* **WooCommerce & E-Commerce Integration**:
  * **Interactive Product Cards**: Displays product thumbnail, title, live price, stock status, and direct store link inside the chat bubble.
  * **1-Click AJAX "Add to Cart"**: Visitors can add recommended items to their WooCommerce cart without leaving the chat, instantly updating your theme's header cart counter.
  * **Visual WISMO Order Tracking Stepper**: Visual 4-stage tracking progress (`Placed ➔ Processing ➔ Shipped ➔ Delivered`) for order queries (`"Where is my order #1042?"`), showing order totals, dates, receipt links, and carrier tracking links.
  * **Intelligent Returns & Exchange Guidance**: Built-in policy guardrails for return windows, exchanges, and damaged goods.
* **Multi-Channel Human Support Handoff**:
  * Automatically triggers when the AI cannot resolve an inquiry or when the visitor asks to speak to a person.
  * Renders an interactive action button in the chat supporting **Calendly/Cal.com scheduler**, **Telegram**, **WhatsApp**, **SMS**, **Phone call**, **Custom Helpdesk (Gorgias, Help Scout, Zendesk)**, or **Direct Email**.
* **Instant Team Alerts (Slack & Discord)**: Sends immediate webhook notifications with visitor details, page context, and chat transcripts when human assistance is requested.
* **Agency Lead Capture Mode**: Detects when visitors ask for quotes or consultations, politely gathers their name, email, phone number, and project notes, and sends immediate alerts to the site owner while logging them in WP Admin.
* **Main WordPress Dashboard Widget**: At-a-glance status widget directly on your WordPress Admin Dashboard showing active provider, model, captured leads, and quick links.
* **Rate Limiting & Security**: Built-in IP rate limiting to prevent API abuse, with API keys safely stored on your server and never exposed to the browser.
* **Custom Styling**: Customizable theme color, launcher position (bottom-right or bottom-left), custom greetings, starter suggestion chips, and responsive mobile layout.

== Third-Party Services ==

This plugin connects to external third-party APIs to process conversational AI requests and team alerts. These services are only contacted when configured by the site administrator:

1. **Google Gemini API (Alphabet Inc. / Google LLC)**
   * **Purpose**: Generates conversational AI responses and discovers model availability.
   * **Data Transmitted**: User chat prompt, conversation history, site context excerpts, and your Google API key.
   * **Terms of Service**: https://policies.google.com/terms
   * **Privacy Policy**: https://policies.google.com/privacy

2. **OpenAI API (OpenAI, LLC)**
   * **Purpose**: Generates conversational AI responses using OpenAI models.
   * **Data Transmitted**: User chat prompt, conversation history, site context excerpts, and your OpenAI API key.
   * **Terms of Service**: https://openai.com/policies/terms-of-use/
   * **Privacy Policy**: https://openai.com/policies/privacy-policy/

3. **Groq API (Groq, Inc.)**
   * **Purpose**: Ultra-fast LLM inference using open models (e.g. Llama 3).
   * **Data Transmitted**: User chat prompt, conversation history, site context excerpts, and your Groq API key.
   * **Terms of Service**: https://groq.com/terms-of-use/
   * **Privacy Policy**: https://groq.com/privacy-policy/

4. **Custom n8n Webhook (User-configured)**
   * **Purpose**: Sends chat messages to a self-hosted or cloud-hosted n8n instance for custom workflows.
   * **Data Transmitted**: User chat message, conversation history, site name, and current page URL.
   * **Terms & Privacy**: Governed by the administrator's own n8n server or hosting provider.

5. **Slack & Discord Webhooks (User-configured)**
   * **Purpose**: Sends optional team notifications when a visitor triggers human support handoff.
   * **Data Transmitted**: Chat excerpt, page context, and timestamp.
   * **Slack Terms & Privacy**: https://slack.com/terms-of-service and https://slack.com/privacy-policy
   * **Discord Terms & Privacy**: https://discord.com/terms and https://discord.com/privacy

== Installation ==

1. Upload the `wp-ai-chatbot` folder to the `/wp-content/plugins/` directory, or upload the `.zip` archive via **Plugins → Add New Plugin → Upload Plugin**.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **Agent Sidekick** in your WordPress Admin sidebar.
4. Select your preferred AI provider (e.g., Google Gemini for a free API key) and paste your API key.
5. Save settings and test the connection using the **Test Connection** button.
6. The floating chat widget will now appear on your site!

== Frequently Asked Questions ==

= Do I need to pay for an external server or SaaS subscription? =
No. The plugin runs entirely on your WordPress hosting environment. If you use Google AI Studio (Gemini), Google provides a generous free tier for API access.

= How does the AI learn about my website content? =
When a visitor asks a question, the plugin performs an automatic search across your published WordPress pages, blog posts, and WooCommerce products, passing the most relevant content excerpts to the AI as context.

= Can visitors add WooCommerce products directly to their cart from the chat? =
Yes. When a product is recommended, visitors see an interactive product card with a 1-click "Add to Cart" button. It adds the item via AJAX and updates your site's cart counter immediately without a page refresh.

= Can I disable the floating widget and use a shortcode instead? =
Yes. Uncheck "Enable floating chat widget on all site pages" under Appearance settings, and place the `[wp_ai_chat]` shortcode on any post or page.

= Where are captured leads stored? =
Captured leads are logged under **Agent Sidekick → Captured Leads** in the WordPress Admin sidebar and can also be emailed immediately to the site administrator.

== Screenshots ==

1. Floating chat widget with modern glassmorphic header, avatar badge, and starter suggestions.
2. Interactive WooCommerce product cards with 1-click Add to Cart inside the chat.
3. Visual 4-step WISMO order tracking progress stepper.
4. Multi-channel human support handoff action button.
5. Admin settings panel for configuring AI providers, knowledge base, and appearance.
6. WordPress Admin Dashboard glance widget with live status.

== Changelog ==

= 1.6.0 =
* Added Interactive WooCommerce Product Cards with live price, stock badges, and 1-click in-chat Add to Cart.
* Added Visual 4-step WISMO (Where Is My Order?) progress stepper (`Placed ➔ Processing ➔ Shipped ➔ Delivered`).
* Added DigitalGenius-inspired intelligent return and exchange policy handling.
* Added dedicated `/wp-json/wp-ai-chatbot/v1/add-to-cart` REST endpoint.
* Added WordPress.org directory compliance and third-party service disclosures.

= 1.5.0 =
* Redesigned user interface with glassmorphism, responsive chat bubble layouts, and pulsing status indicator.
* Added WordPress Admin Dashboard glance widget.
* Added top-level "Agent Sidekick" sidebar menu and dedicated Captured Leads page.
* Added Multi-Channel Human Support Handoff with 7 channels (Calendly, Telegram, WhatsApp, SMS, Phone, Custom Helpdesk, Email).
* Added Slack and Discord real-time webhook team notifications.

= 1.0.0 =
* Initial public release with Google Gemini, OpenAI, Groq, and n8n webhook support.

== Upgrade Notice ==

= 1.6.0 =
Version 1.6.0 introduces interactive WooCommerce product cards with 1-click cart addition and visual order tracking steppers.
