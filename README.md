# block_ai_chat - Global and course chatbot

This plugin provides a frontend to converse with defined AI's from local_ai_manager.
Features are different viewmodes, definition and management of personas and a chat history.

In the latest version the chat also provides an agent mode which assists filling out moodle forms.

## Features

- **Chat mode**: Converse with an AI chatbot using configurable personas.
- **Agent mode**: An AI-powered assistant that helps filling out Moodle forms by suggesting field values.
- **Personas**: Define and manage personas (system templates and user personas) that control the AI's behaviour and system prompt.
- **Chat history**: Conversations are stored and can be revisited.
- **Multiple view modes**: Floating button, docked to the right, full width or chat window.
- **AI context management**: Admins can define additional context that is sent along with AI requests.
- **Different instances**: Use the global chatbot block instance on specific page types or create new block instances within a course with different configurations.
- **Integrated AI tools (tiny_ai)**: The chat input area includes an AI button that provides access to the tools from `tiny_ai` (e.g. text generation, translation, text-to-speech, image generation). This allows users to use AI-powered tools directly within the chatbot without having to open a TinyMCE editor.

## Relationship to mod_aichat

The activity plugin **mod_aichat** wraps an instance of `block_ai_chat` into a regular Moodle course activity. This means:

- `block_ai_chat` provides the **core chat functionality, UI components via AMD modules** and **persona management**.
- `mod_aichat` depends on `block_ai_chat` and embeds it as an activity that can be added to the course content area.

Use `mod_aichat` when you want the chatbot to appear as a dedicated activity inside a course (e.g. visible in the course content with completion tracking possibilities). Use `block_ai_chat` when you want the chatbot to appear as a floating overlay on arbitrary pages or as a sidebar within a course.

## Configuration

### Instance types

There are two fundamentally different ways to make the AI chatbot available to users:

#### 1. Course block instance

A teacher (or admin) can add the AI chatbot block to a specific course. In this case an instance of the block is created within the course context. The block will be visible on the course pages and the AI configuration (personas, context, purposes) is governed by the course context and the `local_ai_manager` settings for that course.

To add the block to a course, use the course settings form (checkbox *"Add an AI Chat to this course"*).

#### 2. Global floating chatbot (via admin setting `showonpagetypes`)

An administrator can configure the chatbot to appear as a floating button on specific page types across the platform — **without** teachers manually adding a block instance. This is controlled by the admin setting:

> _Site administration > Plugins > Blocks > AI Chat > Pagetypes on which the chat bot floating button should be shown_

In this text area you enter one page type per line. You can also use `*` to show the chatbot on all pages.

### Example: Show the chatbot on the Dashboard

To make the AI chatbot available on every user's Dashboard (Moodle "My" page), add the following value to the `showonpagetypes` setting:

```
my-index
```

You can specify multiple page types by entering one per line. For example, to show the chatbot on both the Dashboard and the Front page:

```
my-index
site-index
```

This will display the floating chatbot button on the configured pages for all users who have the required capability (`block/ai_chat:view`).

### Other useful page type examples

| Page type string | Page |
|---|---|
| `my-index` | Dashboard |
| `site-index` | Front page |
| `course-view-*` | All course pages (any course format) |
| `mod-assign-view` | Assignment view page |
| `*` | All pages |

### Capabilities

The following capabilities control access to the different features of the AI chatbot:

| Capability | Description | Default roles |
|---|---|---|
| `block/ai_chat:view` | Allows a user to see and use the AI chatbot. This is the basic access capability. Also used by `mod_aichat`. | editingteacher |
| `block/ai_chat:edit` | Allows configuring the AI Chat block (e.g. selecting personas, setting options). Also used by `mod_aichat`. | editingteacher, manager |
| `block/ai_chat:addinstance` | Allows adding a new AI Chat block instance manually. By default prevented for all roles (see note below). | — (prevented) |
| `block/ai_chat:myaddinstance` | Allows adding the block to the user's Dashboard. By default prevented for all roles (see note below). | — (prevented) |
| `block/ai_chat:useagentmode` | Allows using the agent mode (AI-assisted form filling). | editingteacher, manager |
| `block/ai_chat:managepersonatemplates` | Allows managing global persona templates that are available across all AI chats site-wide. | manager |
| `block/ai_chat:manageaicontext` | Allows managing the additional AI context entries that are sent along with AI requests. | manager |

> **Note:** `block/ai_chat:view` and `block/ai_chat:edit` use `CONTEXT_MODULE` as context level so they can also be checked by `mod_aichat` in the activity module context.

> **Why are `addinstance` and `myaddinstance` prevented?**
> Users should never manually add the AI Chat block via the block drawer. Instead, block instances are created through controlled mechanisms:
> - **In a course:** The block is added automatically when a teacher enables the *"Add an AI Chat to this course"* checkbox in the course settings form.
> - **Globally (floating button):** The admin configures the `showonpagetypes` setting — no manual block instance is needed on those pages.
>
> This ensures consistent configuration, avoids duplicate instances (the block only allows one instance per page), and prevents misconfiguration by end users.

## Settings

| Setting | Name | Description |
|---|-----------------------------------------------|---|
| **Pagetypes for floating button** | `block_ai_chat/showonpagetypes`               | Page types on which the floating chatbot button should be shown (one per line, `*` for all). |
| **Agent mode unavailable page types** | `block_ai_chat/agentmodeunavailablepagetypes` | Page types (by body id) where agent mode should be disabled (e.g. `page-mod-hvp-mod`). |
| **Replace help button** | `block_ai_chat/replacehelp`                   | Replaces the Moodle help button with the AI Chat button. |
| **Persona info link** | `block_ai_chat/personalink`                   | URL to an information page explaining what personas are. |
| **Manage AI context** | `block_ai_chat/manageaicontext`               | Link to the management page for additional AI context entries. |

## Requirements

This plugin requires the following plugins to be installed:

| Plugin | Repository | Purpose |
|---|---|---|
| **local_ai_manager** | https://github.com/bycs-lp/moodle-local_ai_manager | Provides the AI backend integration layer. Manages API connections to AI services (e.g. OpenAI, etc.), handles request routing, usage tracking, rate limiting and the purpose-based configuration of AI tools. `block_ai_chat` uses it for all AI communication. |
| **tiny_ai** | https://github.com/bycs-lp/moodle-tiny_ai | TinyMCE editor plugin that provides AI-powered tools (text generation, translation, text-to-speech, image generation). `block_ai_chat` integrates these tools via the AI button in the chat input area, making them accessible outside of the TinyMCE editor context. |

## Installing via uploaded ZIP file

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/blocks/ai_chat

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## Special thanks

The *agent mode* has been initially started at the MoodleMOOT DACH 2025 in Lübeck. "Project 6: Moodle AI agent" won the third price in the dev camp!

Special thanks to all our team members! The ones who want to be named are (in random order :-)): 
- Peter Mayer
- Philipp Memmel
- Andreas Wagner
- Heikki Wilenius
- Alexander Karemaker
- Marcus Green

and Tobias Garske for reviewing and testing the complex frontend rework by Philipp Memmel as well as the final implementation of the agent mode in its final form.

## License

2024, ISB Bayern

Lead developer: Tobias Garske <tobias.garske@isb.bayern.de>

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <https://www.gnu.org/licenses/>.
