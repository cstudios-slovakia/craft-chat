# Craft Chat Plugin for Craft CMS 5

Craft Chat is a powerful, customizable AI chatbot plugin for your Craft CMS 5 website powered by OpenAI. It easily integrates an elegant chat widget into your frontend and allows you to seamlessly track conversations, tokens, and billing right from your Craft CMS Control Panel.

## Requirements

* Craft CMS 5.0.0 or later.
* PHP 8.2 or later.
* An active [OpenAI API Key](https://platform.openai.com/api-keys).

## Installation

You can easily install this plugin from the terminal using Composer.

1. Require the package via composer:
   ```bash
   composer require cstudios/craft-chat -w
   ```
2. In the Craft Control Panel, go to **Settings → Plugins** and click **Install** for "Craft Chat".

## Configuration

Once the plugin is installed, navigate to the Craft Chat **Settings** page in the Control Panel to customize your bot. 

* **OpenAI API Key:** Your secret key starting with `sk-...`. _(Required)_
* **OpenAI Model:** Select the foundational model (e.g., `gpt-4o`, `gpt-4-turbo`, `gpt-3.5-turbo`).
* **Initial Instructions:** The core system prompt instructing the bot on how it should behave, tone of voice, etc.
* **Search Context (Sections):** Select the Craft CMS entry sections you want the AI to be able to "search" and read from when visitors ask questions about your content.
* **Welcome Message:** The first line the bot says to greet website visitors.
* **Default Language:** The core localization setting for the chatbot dialogue.
* **Theme Color:** The primary brand hex color (e.g., `#10a37f`) for the frontend chat widget button and headers.
* **Bot Name:** The title displayed at the top of the frontend chat widget.

## Usage

### Frontend Chat Widget

To place the chat widget on your website's frontend (usually in your `_layout.twig` footer or base entry point), use the handy plugin hook:

```twig
{{ craft.app.view.invokeHook('chat') }}
```

This minimal snippet automatically handles injecting the chat UI and wiring up all Javascript requests to the plugin's internal backend endpoints securely.

### Monitoring & Auditing

Navigate to **Craft Chat -> Conversations** within the Control Panel sidebar. Here you can:
* View a list of all historical conversations visitors have had with your chatbot.
* View a short **AI-generated summary** of the exchange.
* Jump into individual conversations to review exactly what the user and bot said to each other.
* Audit **Tokens Used** per-conversation to strictly monitor OpenAI usage costs and optimize billing.
* Use the quick-link action banner to jump straight into your OpenAI Dashboard to check or fill your API Credit billing grants.
