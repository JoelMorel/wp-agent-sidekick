# Changelog

All notable changes to the **WP AI Chatbot** plugin will be documented in this file using [Semantic Versioning](https://semver.org/):
- **MAJOR** (`X.0.0`): Incompatible API changes, major architectural overhauls.
- **MINOR** (`0.X.0`): New functionality, features, or integrations added in a backwards-compatible manner.
- **PATCH** (`0.0.X`): Backwards-compatible bug fixes, model updates, or performance improvements.

## [1.6.0] - 2026-10-23

### Added
- **DigitalGenius-Inspired E-Commerce Rich Cards**:
  - **Interactive Product Cards**: Displays visual product cards inside the chat with thumbnail images, live pricing, stock availability badges, and star ratings.
  - **1-Click Native AJAX "Add to Cart"**: Shoppers can add items to their WooCommerce cart directly from the chat conversation with instant confirmation and automatic site-wide mini-cart updates.
  - **Visual WISMO Order Tracking Stepper**: Replaces plain text status with a visual 4-stage delivery pipeline (`Placed ➔ Processing ➔ Shipped ➔ Delivered`), total cost, and direct carrier tracking links (FedEx, USPS, UPS, DHL).
  - **Self-Service Returns & Exchanges Intent Engine**: Integrated DigitalGenius-style conversational flows for returns, size exchanges, and damaged items.
  - **New WooCommerce Settings**: Added toggles for Interactive Product Cards and Visual Order Steppers under **Agent Sidekick ➔ Settings ➔ Section 4**.
  - **Dedicated Add-to-Cart REST Route**: Added `/wp-json/wp-ai-chatbot/v1/add-to-cart` ensuring fast and reliable cart session handling across all themes.

---

## [1.5.0] - 2026-10-01

### Changed
- **Rebranded to WP Agent Sidekick**: Updated plugin title, description, headers, and default bot title to **WP Agent Sidekick**.

### Added
- **Top-Level WordPress Admin Menu**: Promoted settings from under `Settings` to a primary top-level sidebar menu item (`Agent Sidekick`) with dedicated submenus for **Settings** and **Captured Leads**.
- **Main WordPress Admin Dashboard Widget**: Added an interactive glance widget on the main WordPress Dashboard (`wp-admin/index.php`) displaying live Agent status (Active/Inactive), connected AI engine and model, total captured leads count, and quick setup action buttons.
- **Modern UI Redesign**:
  - **Floating Launcher**: Enhanced launcher button with a smooth animated pulse halo, modern gradient elevation, and AI Sidekick icon.
  - **Glassmorphic Header**: Modern header styling with sidekick avatar, glowing active status indicator, title, and instant **Reset Conversation** button.
  - **Message Rows & Avatars**: Bot messages now feature an assistant avatar beside clean, modern cards with subtle shadows and responsive formatting.
  - **Micro-Interactions**: Suggestion pills now feature lift animations (`transform: translateY(-1px)`), and the input area uses a modern pill enclosure with gradient send button.
  - **Subtle Branding**: Added clean "Powered by WP Agent Sidekick" footer note.

---

## [1.4.0] - 2026-10-01

### Added
- **Multi-Channel Human Support Handoff**:
  - Automatically triggers when the AI detects it cannot answer or when a visitor explicitly asks to speak with a human/team member.
  - Interactive clickable action button rendered directly inside the chat window (`Talk to Our Team`).
  - Supports 7 instant handoff channels configured via WordPress Admin:
    1. **Meeting Scheduler** (Calendly, Cal.com, Acuity Scheduling)
    2. **Telegram** (`https://t.me/username`)
    3. **WhatsApp** (`https://wa.me/number?text=...`)
    4. **SMS / Text Message** (`sms:+1...`)
    5. **Phone Call** (`tel:+1...`)
    6. **Custom Helpdesk** (Gorgias, Help Scout, Zendesk, LiveChat direct URLs)
    7. **Email Support Ticket** (`mailto:support@...`)
- **Instant Team Webhook Alerts (Slack & Discord)**:
  - Dispatches immediate incoming webhook alerts to your company's Slack or Discord channel whenever a human handoff is requested.
  - Formatted notifications include Visitor Name/Email (if captured), Current Page URL, and full conversation transcript so the team has full context immediately.

### Changed
- Standardized terminology across the entire plugin from "Print on Demand" to **E-Commerce**.

---

## [1.3.0] - 2026-10-01

### Added
- **Current Page Context (Universal)**: Automatically sends the visitor's current page title and URL with every chat message, allowing the bot to provide context-aware answers (e.g. "How much is this service?" or "What are the specs on this product?").
- **Agency Lead Capture Mode**:
  - Automatically identifies when a visitor requests a price quote, consultation, or custom project.
  - Politely collects visitor Name, Email, Phone number, and project notes.
  - Sends immediate email notification to the site administrator with lead details and chat transcript.
  - Stored in a new **Captured Leads Log** in the WordPress Admin with instant review and clear options.
- **WooCommerce Store & E-Commerce Integration**:
  - **Live Product Search**: Queries WooCommerce products to provide real-time pricing, stock status, SKU, and direct purchase links.
  - **Automated Order Tracking**: Allows shoppers to ask "Where is my order #1234?" and retrieves live fulfillment status, tracking notes, items, and delivery guidelines.

---

## [1.2.1] - 2026-10-01

### Fixed
- **Resolved "Developer instruction is not enabled for api version v1"**: Prioritized the `v1beta` endpoint which natively supports `system_instruction` (developer instructions) alongside modern flash models (`gemini-2.5-flash`).
- **Graceful v1 Fallback Adaptation**: Added automatic payload restructuring for `v1` calls (embedding instructions into user content) if `v1` is ever used as a fallback.

---

## [1.2.0] - 2026-10-01

### Added
- **Future-Proof "Auto-Detect Best Model"**: Dynamically queries Google's `ListModels` API for the user's specific key, automatically ranking and selecting the latest compatible flash model (cached in WordPress for 7 days).
- **Self-Healing Fallback Engine**: If any model is deprecated or disabled by Google in the future, the plugin automatically busts cache, discovers the newest active model on the account, and retries the request seamlessly with zero user intervention.
- Added model selection options for `gemini-2.5-flash`, `gemini-2.5-flash-lite`, and `gemini-2.5-pro`.

---

## [1.1.0] - 2026-10-01

### Added
- **Site Learning (Native Mini-RAG)**: Automatically searches published WordPress Pages, Blog Posts, and WooCommerce Products matching visitor keywords and passes relevant excerpts into the AI prompt.
- **Business Knowledge & FAQs Field**: Dedicated admin text area to train the bot on store hours, refund policies, contact info, and business rules.
- **Google Gemini 2.5 Series Support**: Added `gemini-2.5-flash` (recommended default), `gemini-2.5-flash-lite`, and `gemini-2.5-pro`.
- **Automatic Model Resolution & Discovery**: When an AI model is deprecated or unavailable, the plugin automatically queries the Google account's active models and falls back seamlessly without failing.
- **Version Tracking**: Added version indicator badge on the WordPress Admin settings screen.

### Changed
- Promoted Gemini API calls to use the stable `v1` endpoint.
- Removed obsolete external Vercel proxy backend (`backend/` folder) in favor of the 100% self-contained WordPress architecture.

### Fixed
- Resolved `404 Not Found` and `is no longer available` errors when connecting to the Gemini API.

---

## [1.0.0] - 2026-10-01

### Added
- Initial release of the standalone WordPress AI Chatbot plugin.
- Direct multi-provider support: Google Gemini, OpenAI, Groq, and n8n Webhook.
- Responsive floating chat UI with custom theme colors, welcome greeting, and quick suggestions.
- Built-in visitor rate limiting to protect API quotas.
- Interactive "Test AI Connection Now" button in WordPress Admin.
- Shortcode `[wp_ai_chat]` for inline embeds.
