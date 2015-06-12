
# Liveblog

* Contributors: [automattic](http://profiles.wordpress.org/automattic), [nbachiyski](http://profiles.wordpress.org/nbachiyski), [batmoo](http://profiles.wordpress.org/batmoo), [johnjamesjacoby](http://profiles.wordpress.org/johnjamesjacoby), [philipjohn](http://profiles.wordpress.org/philipjohn)
* Tags: liveblog
* Requires at least: 3.5
* Tested up to: 4.2.2
* Stable tag: 1.4.1
* License: GPLv2 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.html

Quick and simple blogging for following fast-paced events.

## Description

[wpvideo tWpw6nCt]

Your readers want your updates as quickly as possible, and we think we provide the easiest and the most flexible publishing environment to make that happen. Sometimes though, that’s just not enough.

When you’re covering a fast-paced event — the latest Apple unveiling, an F1 Grand Prix, or the Super Bowl — a full blog post for each individual update is a poor experience for your authors and your audience.

The [WordPress.com VIP Liveblog Add-On](http://vip.wordpress.com/liveblog-add-on/) was purpose-built to address these issues specifically.

Here’s what makes it special:

 * Post updates right from the front-end of your site (no need to use the `/wp-admin` dashboard)
 * Viewers of your Liveblog get new entries served to them instantly and automatically, without needing to refresh their browser.
 * Your authors can drag-and-drop photos right into the Liveblog area, without needing to navigate to separate browser tabs or windows.
 * There’s no need for a separate site dedicated to liveblogging: *every* post can be a liveblog, even existing ones.

[Check out our in-depth documentation.](http://vip.wordpress.com/documentation/using-the-liveblog-plugin/)

If you'd like to check out the code and contribute, [join us on github](https://github.com/Automattic/liveblog), pull requests are more than welcome.

## Installation

1. Upload the `liveblog` folder to your plugins directory (e.g. `/wp-content/plugins/`)
2. Activate the plugin through the 'Plugins' menu in WordPress
3. You can enable the liveblog on any post's edit page

## Screenshots

![The entry form is the simplest possible](https://raw.github.com/Automattic/liveblog/master/screenshot-1.png)
![Writers can preview before posting](https://raw.github.com/Automattic/liveblog/master/screenshot-2.png)
![New posts are highlighted](https://raw.github.com/Automattic/liveblog/master/screenshot-4.png)
![Adding images is a matter of just drag-and-drop](https://raw.github.com/Automattic/liveblog/master/screenshot-5.png)
![Dragged photos are automatically inserted](https://raw.github.com/Automattic/liveblog/master/screenshot-6.png)
![Typical liveblog view](https://raw.github.com/Automattic/liveblog/master/screenshot-8.png)

## Changelog

### 1.4.1

* Bump tested tag to 4.2.2.
* Added Composer support!

### 1.4

* Rich-text editing!
* Archived liveblogs now display in chronological order (live ones show reverse chron)
* New and udpated translations
* Bump to fix SVN sync issues (thanks @kevinlisota)

### 1.3.1

* Fixed a bug where liveblog would show up in secondary loops

### 1.3

**The liveblog plugin now requires WordPress 3.5.**

New functionality:

* Liveblog archiving
* Shows automatically new entries, with a slick notification bar if we have scrolled out of view. With the help of [@borkweb](https://github.com/borkweb) and [@zbtirrell](https://github.com/zbtirrell)
* Front-end editing
* Pasting an image URL embeds the image

Translations:

* German by [@cfoellmann](https://github.com/cfoellmann)
* Spanish by [@elarequi](http://profiles.wordpress.org/elarequi)

Also a lot of internal improvements and bug fixes. See the [full list of
closed issues](https://github.com/Automattic/liveblog/issues?milestone=3&state=closed).

### 1.2

New functionality:

* Introduce many new hooks and filters, which help customization without changing the plugin code.
* Allow shortcodes and OEmbed in liveblog entries
* Translations:
	- Spanish by [@elarequi](http://profiles.wordpress.org/elarequi)
	- Dutch by [@defries](https://github.com/defries)
	- Catalan by [@gasparappa](https://github.com/gasparappa)
	- German by [@cfoellmann](https://github.com/cfoellmann)
* Add github-friendly version of `readme.txt`
* Optimize PNG files

Fixed problems:

* Fix JavaScript errors on IE8, props [@pippercameron](https://github.com/pippercameron)
* Fix preview tab
* Compatibility with plupload 1.5.4, props [@borkweb](https://github.com/borkweb)

### 1.1

* Backwards compatibility for 3.4
* Support for non-pretty permalinks
* Support for permalinks without trailing slashes
* Fix preview tab

### 1.0

* Initial release


