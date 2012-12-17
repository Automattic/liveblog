In this file you'll find technical overview of how the liveblog works.

# Goal: a plugin, which allows you to quickly post entries, delivers them almost instantly to users, and scales.

# Glossary
* Entry – a single piece of text, which an author posts to the liveblog.
* Liveblog post – a WordPress post, which has the liveblog checbox checked, shows the liveblog entries in real time, and offers authorized users to insert new entries.
* Refresh interval – how often the client side checks for entries' updates.
* Nag – when there's a new update, we show the nag to the users, instead of loading the new entries directly. The nag contains a link to load the new entries.

# Code Organization

Pretty straight-forward:
* Most of the code is in `liveblog.php` with the intention of moving more and more to `classes/`.
* HTML templates are in `templates/`.
* CSS is in `css/`.
* JavaScript is in `js/`.
* Translations are in `languages/`.
* Tests are in `t/`.
