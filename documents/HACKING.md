In this file you'll find technical overview of how the liveblog works.

# Goal: a plugin, which allows you to quickly post entries, delivers them almost instantly to users, and scales.

# Glossary

* Entry – a single piece of text, which an author posts to the liveblog.
* Liveblog post – a WordPress post, which has the liveblog checkbox checked, shows the liveblog entries in real time, and offers authorized users to insert new entries.
* Refresh interval – how often the client side checks for entries' updates.
* Nag – when there's a new update, we show the nag to the users, instead of loading the new entries directly. The nag contains a link to load the new entries.
* Modifying Entry – an entry, which updates or deletes (replaces) an existing entry.

# Major Design Decisions

* **Each entry is a comment** – adding a lot of posts quickly leads to too much cache invalidations. Comments don't have cache entry per comment, so it's much easier to create a scalable liveblog.
* **The front-end polls for new comments** – even though long-polling or pushing data to the browser is better and faster it requires much different infrastructure, which few people have or are ready to invest in.
* **The URLs of the polling endpoints are in the form `/liveblog/<start-timestamp>/<end-timestamp>/`** – it gives you all entries between those two timestamps. By having both timestamps, instead of just the start we can cache the result indefinitely and don't bother with cache invalidations.
* **Each entry change is a new entry** – because of the previous decision, we can't allow changing an entry. Instead, we insert a new entry and mark it that it replaces the older entry.

# Code Organization

Pretty straight-forward:

* Most of the code is in `liveblog.php` with the intention of moving more and more to `classes/`.
* HTML templates are in `templates/`.
* CSS is in `css/`.
* JavaScript is in `js/`.
* Translations are in `languages/`.
* Tests are in `t/`.

# Backend

* The god class is `WPCOM_Liveblog`. Its members are used only as static. It's responsible for almost the whole backend. We should slowly split out parts of it.
* `<permalink>/liveblog` URLs are handled by `handle_ajax_request()`.
* The methods responsible for responding to AJAX requests are prefixed with `ajax_`. There's most of the action.
* `WPCOM_Liveblog_Entry_Query` is responsible for searching for entries by different criteria. Its methods usually return arrays of instances of `WPCOM_Liveblog_Entry`.
* `WPCOM_Liveblog_Entry` represents a liveblog entry. It contains all the comment data and some functionality, mostly around rendering it in different contexts.
