# WP Agent Sidekick

A lightweight, modern, standalone AI Agent & Assistant for WordPress.
Works out of the box with **Google Gemini (free tier)**, **OpenAI (ChatGPT)**, **Groq**, or your own **n8n Webhook workflow**.

---

## 🚀 Quick Install (Under 2 Minutes)

1. Download [`wp-ai-chatbot.zip`].
2. Go to your WordPress Admin dashboard: **Plugins → Add New Plugin → Upload Plugin**.
3. Select `wp-ai-chatbot.zip` and click **Install Now**, then **Activate**.
4. Click **Agent Sidekick** in your WordPress Admin sidebar (or view the glance widget on your main Dashboard).
5. Paste your API Key (e.g., Google Gemini or OpenAI) or your n8n Webhook URL.
6. Click **Save Settings**.

---

## ⚡ Supported Providers

### 1. Google Gemini (Recommended for non-technical users)
- **Why**: Google AI Studio provides a free API tier with fast response times.
- **Model**: `gemini-1.5-flash` (or `gemini-2.0-flash`).
- **Setup**: Get a free API key at [aistudio.google.com/app/apikey](https://aistudio.google.com/app/apikey), paste it in the settings, and save.

### 2. OpenAI
- **Why**: Industry standard GPT models.
- **Model**: `gpt-4o-mini` (cost-efficient) or `gpt-4o`.
- **Setup**: Get your API key from [platform.openai.com](https://platform.openai.com).

### 3. Groq
- **Why**: Ultra-fast inference with open models (Llama 3.3).
- **Setup**: Get your API key from [console.groq.com](https://console.groq.com).

### 4. n8n / Custom Webhook
- **Why**: For automation workflows, CRM lookups, Lead capture, or custom agent chains.
- **How it works**:
  - The plugin sends a POST request with `{ "message": "...", "history": [...], "site_name": "...", "site_url": "..." }`.
  - Your n8n workflow processes the message and returns `{ "output": "..." }` or `{ "reply": "..." }` or plain text.

---

## 🛠 Features

- **No Third-Party Hosting**: Runs entirely on your WordPress server via the native WordPress REST API (`/wp-json/wp-ai-chatbot/v1/chat`).
- **Main WordPress Admin Dashboard Widget**: Interactive glance widget directly on your WordPress Admin Dashboard (`wp-admin/index.php`) showing live Agent status, AI engine/model, total leads count, and quick setup shortcuts.
- **Top-Level Sidebar Menu**: Dedicated `Agent Sidekick` menu in the WordPress sidebar with direct submenus for **Settings** and **Captured Leads**.
- **Modern UI & Micro-Interactions**:
  - Floating launcher with glowing pulse halo and agent icon.
  - Glassmorphic header with avatar badge, live pulsing status dot, and instant **Reset Conversation** button.
  - Refined message bubbles with assistant avatars, modern shadows, and responsive layout.
  - Interactive pill suggestion chips with hover lift effects.
  - Enclosed pill input bar with gradient send button and subtle branding footer.
- **Multi-Channel Human Support Handoff**:
  - Automatically triggers when the AI cannot resolve an inquiry or when the visitor requests a human representative.
  - Interactive clickable action button rendered directly in the chat widget (`Talk to Our Team`).
  - Supports 7 instant handoff channels: **Calendly / Cal.com meeting scheduler**, **Telegram**, **WhatsApp**, **SMS text message**, **Phone call**, **Custom Helpdesk (Gorgias, Help Scout, Zendesk)**, or **Direct Email ticket**.
- **Instant Team Alerts (Slack & Discord)**: Sends immediate webhook notifications to your team's Slack or Discord channel with visitor details and full chat transcripts when human intervention is requested.
- **Current Page Context**: The bot automatically knows which page the visitor is currently browsing, enabling intelligent answers about specific services, products, or articles.
- **Agency Lead Capture Mode**: Automatically detects when a visitor asks for a quote or consultation, politely collects their Name, Email, Phone number, and project notes, and sends an immediate email alert to the site owner (with a captured leads table in WP Admin).
- **WooCommerce & E-Commerce Store Support**:
  - Live product search with real-time prices, stock status, SKU, and direct store links.
  - Automated order status lookup (`"Where is my order #1234?"`) with fulfillment status, tracking notes, and delivery guidelines.
- **Site Learning (Native Mini-RAG)**: Automatically searches published WordPress Pages, Blog Posts, and WooCommerce Products for keywords matching the visitor's question, feeding relevant excerpts to the AI in real time.
- **Business Knowledge & FAQs**: Train the bot on your exact business hours, return policies, store policies, or agency services.
- **API Key Security**: The API key is stored securely in WordPress and is never exposed to visitors or client-side scripts.
- **Abuse Prevention**: Built-in rate limiting (customizable max messages per visitor IP).
- **Session Continuity**: Conversation memory persists in `sessionStorage` as visitors browse different pages on your site.
- **Brand Customization**: Custom title, greeting message, theme color picker, and positioning (bottom-right or bottom-left).
- **Shortcode Support**: Use `[wp_ai_chat]` to place a chat box anywhere in a post or page.

---

## 📦 Project Structure

```
WP-Plugin_Chat/
├── wp-ai-chatbot.zip                 # Ready-to-upload WordPress plugin file (v1.5.0)
└── wp-ai-chatbot/                    # Plugin source files
    ├── wp-ai-chatbot.php             # Main plugin entry & asset loader
    ├── includes/
    │   ├── class-llm-service.php     # Direct API calls (Gemini, OpenAI, Groq, n8n) & Context Engine
    │   ├── class-api-handler.php     # REST API endpoint, Human Handoff & Webhook Notifier
    │   └── class-admin-settings.php  # Top-level Admin Menu, Dashboard Widget & Leads Log
    └── assets/
        ├── css/chatbot.css           # Modern glassmorphism UI & responsive styles
        └── js/chatbot.js             # Modern client chat widget, avatars & reset action
```
