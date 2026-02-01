# block_ai_chat - Chat Frontend for local_ai_manager

This plugin provides a frontend to converse with defined Ai´s from local_ai_manager.
Features are different viewmodes, definition and management of personas and a chat history.

In the latest version the chat also provides an agent mode which assists filling out moodle forms.

# Settings

## Requirements

https://github.com/bycs-lp/moodle-local_ai_manager and https://github.com/bycs-lp/moodle-tiny_ai need to be installed.

## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/blocks/ai_chat

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## Special thanks ##

The *agent mode* has been initially started at the MoodleMOOT DACH 2025 in Lübeck. "Project 6: Moodle AI agent" won the third price in the dev camp!

Special thanks to all our team members! The ones who want to be named are (in random order :-)): 
- Peter Mayer
- Philipp Memmel
- Andreas Wagner
- Heikki Wilenius
- Alexander Karemaker
- Marcus Green

and Tobias Garske for reviewing and testing the complex frontend rework by Philipp Memmel as well as the final implementation of the agent mode in its final form.

## License ##

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
