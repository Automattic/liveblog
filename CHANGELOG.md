# Changelog

## [1.10.0] - 2026-01-06

### Added

* Replace Draft.js with Lexical editor for improved stability and maintenance by @GaryJones in https://github.com/Automattic/liveblog/pull/766
* Add image cropping and resizing to Lexical editor by @GaryJones in https://github.com/Automattic/liveblog/pull/782
* Add keyboard shortcut (Ctrl+Enter / Cmd+Enter) and auto-focus for entry publishing by @GaryJones in https://github.com/Automattic/liveblog/pull/792 (fixes #181)
* Add i18n support to React components by @GaryJones in https://github.com/Automattic/liveblog/pull/784
* Pre-load authors in dropdown for better discoverability by @GaryJones in https://github.com/Automattic/liveblog/pull/793
* Display total updates count in editor by @GaryJones in https://github.com/Automattic/liveblog/pull/777 (fixes #520)
* Add VIP edge cache purging when liveblog entries change by @GaryJones in https://github.com/Automattic/liveblog/pull/763
* Add `liveblog_facebook_app_id` filter for AMP sharing by @GaryJones in https://github.com/Automattic/liveblog/pull/756

### Fixed

* Fix accessibility: WCAG AA contrast for placeholders, 16px font size, aria-labels by @GaryJones in https://github.com/Automattic/liveblog/pull/793
* Fix author selection keyboard navigation for react-select v5 by @GaryJones in https://github.com/Automattic/liveblog/pull/793
* Prevent orphaned entry updates from breaking lazy-loading by @GaryJones in https://github.com/Automattic/liveblog/pull/787 (fixes #478)
* Use add_action for template_redirect hook by @GaryJones in https://github.com/Automattic/liveblog/pull/788 (fixes #620)
* Fix Facebook embed parsing for legacy XFBML format by @GaryJones in https://github.com/Automattic/liveblog/pull/761 (fixes #306)
* Fix crash when editing entries containing timestamp-like text by @GaryJones in https://github.com/Automattic/liveblog/pull/762
* Correct timezone handling in time ago display by @GaryJones in https://github.com/Automattic/liveblog/pull/770
* Use DST-aware timezone offset for entry timestamps by @GaryJones in https://github.com/Automattic/liveblog/pull/774
* Honour site locale in entry timestamps by @GaryJones in https://github.com/Automattic/liveblog/pull/775
* Resolve stale JSON data on initial cached page load by @GaryJones in https://github.com/Automattic/liveblog/pull/772
* Round both polling timestamps for cache efficiency by @GaryJones in https://github.com/Automattic/liveblog/pull/771
* Stop polling on archived liveblogs and enforce AMP refresh minimum by @GaryJones in https://github.com/Automattic/liveblog/pull/768
* Support drag-and-drop of multiple images by @GaryJones in https://github.com/Automattic/liveblog/pull/767
* Reset Preview tab when publishing a new entry by @GaryJones in https://github.com/Automattic/liveblog/pull/765
* Preserve image attributes with filterable render output by @GaryJones in https://github.com/Automattic/liveblog/pull/773
* Use add_action for template_redirect hook in AMP class by @GaryJones in https://github.com/Automattic/liveblog/pull/776
* Replace prohibited use of extract() by @psorensen in https://github.com/Automattic/liveblog/pull/760 (fixes #361)
* Check template part variable names against allow list by @psorensen in https://github.com/Automattic/liveblog/pull/764

### Maintenance

* Migrate assets from `assets/` to `build/` directory and automate builds in deploy workflow by @GaryJones in https://github.com/Automattic/liveblog/pull/781 (fixes #461)
* Remove redundant polyfills and add build CI step by @GaryJones in https://github.com/Automattic/liveblog/pull/752 (fixes #661)
* Resolve all PHPCS coding standards violations by @GaryJones in https://github.com/Automattic/liveblog/pull/778
* Enable PHPCS code style checks in CI by @GaryJones in https://github.com/Automattic/liveblog/pull/779
* Add concurrency controls to GitHub Actions workflows by @GaryJones in https://github.com/Automattic/liveblog/pull/790
* Modernise SCSS: @use/@forward syntax, CSS custom properties, and code consolidation by @GaryJones in https://github.com/Automattic/liveblog/pull/736
* Migrate PHPUnit test infrastructure to yoast/wp-test-utils by @GaryJones in https://github.com/Automattic/liveblog/pull/737
* Separate unit tests from integration tests by @GaryJones in https://github.com/Automattic/liveblog/pull/738
* Add JavaScript unit tests for React reducers and utilities by @GaryJones in https://github.com/Automattic/liveblog/pull/741
* Standardise workflows, harden security with SHA-pinned actions by @GaryJones in https://github.com/Automattic/liveblog/pull/747
* Add Dependabot configuration with CODEOWNERS for reviewers by @GaryJones in https://github.com/Automattic/liveblog/pull/720, https://github.com/Automattic/liveblog/pull/748
* Standardise test matrix and update readme by @GaryJones in https://github.com/Automattic/liveblog/pull/749
* Resolve webpack-dev-server security vulnerabilities by @GaryJones in https://github.com/Automattic/liveblog/pull/719
* Actions(deps): Bump softprops/action-gh-release in the actions group by @dependabot in https://github.com/Automattic/liveblog/pull/783
* npm(deps): Bump @babel/runtime and @wordpress/i18n by @dependabot in https://github.com/Automattic/liveblog/pull/786
* npm(deps-dev): Bump qs from 6.14.0 to 6.14.1 by @dependabot in https://github.com/Automattic/liveblog/pull/791

## [1.9.7] - 2024-06-07

* Adds a capability check to the preview, authors and hashtag endpoint to prevent unauthenticated calls (#685)

## [1.9.6] - 2021-09-29

* Revert #597, restoring `get_fields_for_render()` that is being used in some implementations (#639)
* Harden check when rendering media library (#652)
* Clean comment cache after direct SQL queries (#658)
* REST API routes require a permission_callback (#669)
* Load CPT support later to avoid fatals with early use of WP_Query (#672)

props [anigeluk], [david-binda], [GaryJones], [jeffersonrabb], [mslinnea], [philipjohn], [rebeccahum]

## [1.9.5] - 2019-01-23

* Fix PHP 7.3 continue switch warning (#617)
* Remove unused get_fields_for_render() (#597)

## [1.9.4] - 2018-12-07

* Send the correct data to get_liveblog_metadata() (#558)
* Render AMP css safe without using esc_html to pass AMP validation (#586)
* Don't run WPCOM_Liveblog_AMP::setup() on non-live posts. (#593)

## [1.9.3] - 2018-11-09

* Improve caching by setting a short TTL on future timestamps (#542)

## [1.9.2] - 2018-10-26

* Fix for overzealous API requests in author list (#417)
* Prevent empty entries (#475)
* Restored customisable Key Events widget title (#372)
* Restore deletion confirmation for entries (#482)
* Performance improvements to the build (#495)

props [cain], [GaryJones], [jasonagnew], [kevinlisota], [lidocaine], [maxhartshorn], [no-sws], [paulschreiber], [philipjohn], [sathyapulse], [scottblackburn], [sboisvert], [tomjn]

## [1.9.1] - 2018-10-17

* Multiple coding standards fixes
* Removes unused debug code
* Fix rendering embeds upon entry updates
* Avoid null dereference in `get_user_data_for_json()`
* Improvements to code review process

props, [cain], [GaryJones], [maevelander], [paulschreiber], [philipjohn], [rgllm], [rogertheriault]

## [1.9] - 2018-09-27

* Round out polling timestamp for improved performance (#496)
* Use the new core jshint rules, instead of ours (#120)
* Add LiveBlogPosting Schema (#337)
* AMP Support (#450)
* Move is_robot function to javascript (#266)
* JS api actions can now be configured to send cross domain requests (#463)
* Fix deprecated notice about non-static function (#484)
* Remove deprecated lazyload JS (#498)
* Readme updates (#512)

props [david-binda], [GaryJones], [jacklenox], [jasonagnew], [joshbetz], [justnorris], [jvpcode], [lovestulip], [maevelander], [maxhartshorn], [mjangda], [mikeselander], [nb], [paulschreiber], [philipjohn], [rogertheriault], [sboisvert], [scottblackburn], [tessaneedham]

## [1.8.2] - 2018-05-11

* Fix issue with time format (#424)
* Adds check around the jetpack is_mobile flag (#428)
* Restore current user back in to localised scripts (#430)
* Cast liveblog rewrite version before checks (#439)
* Document the minimum PHP version (#447)
* Fix bug where pagination did not update (#433)
* Fix GMT offsets in entry times (#432)

props [justnorris], [mjangda], [paulschreiber], [philipjohn], [scottblackburn]

## [1.8.1] - 2018-05-01

* Fix bug with changing contributors
* Fix multiple PHP Coding Standards issues

props [paulschreiber]

## [1.8] - 2018-04-22

* New: Allow multiple authors for each Liveblog entry
* New: Entries no longer have to have an author
* New: Share entries with entry-specific permalinks
* New: Media library integration in the entry editor
* New: Edit entry HTML within the editor
* Fixed: Bug with some installs using the correct REST API base URL
* Fixed: Various coding standards issues

props [jasonagnew], [liam-defty], [paulschreiber], [philipjohn], [sboisvert]

## [1.7.1] - 2018-02-02

* Fix bug with REST endpoints in Multisite
* Fix for some failing unit tests due to core changes
* Fix for bug where shortcodes would be removed completely
* Fixed some pagination issues in relatively unique circumstances
* Fixed a bug that failed to correctly handle avatars
* Made sure we handle timezones in entries properly

props [jasonagnew], [justnorris], [liam-defty]

## [1.7] - 2018-01-10

* New: Mobile-friendly React-based frontend UI for a better editing experience across devices.
* Various UI bugfixes thanks to the new frontend.
* Fix for incorrect use of `defined()`

props [jasonagnew], [jrmd], [kevinfodness], [liam-defty]

## [1.6.1] - 2017-10-26

* Remove support for Flash + Silverlight which are no longer supported in WP 4.9, see https://core.trac.wordpress.org/ticket/41755#no0
* Bugfix for WPCOM: Don't force an AJAX URL if we're using the REST API.
* Bugfix WPCOM: Retain SA access for A12s

## [1.6] - 2017-09-26

* REST API support
* Performance improvements to lazy loading
* Auto-archiving of Liveblogs
* Removed copied core functions
* Improved test coverage
* Bugfix for edited comments appearing on archived Liveblogs
* Bugfix for multiple edits issue
* Bugfix for deleted key events appearing after edits
* Bugfix for shortcodes within key events
* Bugfix to allow editing entries more than once

Thanks to [mjangda], [jasonagnew], Max Katz, Olly Warren, [rebeccahum], [travisw]

## [1.5] - 2015-12-06

* New "Key Events" feature
* New "Lazyloading" feature
* Improved escaping

People who helped make this happen: [jasonagnew], [joshbetz], [sarahblackstock], [sboisvert], [iandunn], [scottsweb], [tfrommen], [markgoodyear], [ChrisHardie], [philipjohn], [pkevan], Connor Parks

## [1.4.1] - 2015-06-12

* Bump tested tag to 4.2.2.
* Added Composer support!

## [1.4] - 2015-04-22

* Rich-text editing!
* Archived liveblogs now display in chronological order (live ones show reverse chron)
* New and udpated translations
* Bump to fix SVN sync issues (thanks [kevinlisota])

## [1.3.1] - 2015-03-01

* Fixed a bug where liveblog would show up in secondary loops

## [1.3] - 2013-01-23

**The liveblog plugin now requires WordPress 3.5.**

New functionality:

* Liveblog archiving
* Shows automatically new entries, with a slick notification bar if we have scrolled out of view. With the help of [borkweb] and [zbtirrell]
* Front-end editing
* Pasting an image URL embeds the image

Translations:

* German by [cfoellmann]
* Spanish by [elarequi]

Also a lot of internal improvements and bug fixes. See the [full list of
closed issues](https://github.com/Automattic/liveblog/issues?milestone=3&state=closed).

## [1.2] - 2012-12-13

New functionality:

* Introduce many new hooks and filters, which help customization without changing the plugin code.
* Allow shortcodes and OEmbed in liveblog entries
* Translations:
	- Spanish by [elarequi]
	- Dutch by [defries]
	- Catalan by [gasparappa]
	- German by [cfoellmann]
* Add github-friendly version of `readme.txt`
* Optimize PNG files

Fixed problems:

* Fix JavaScript errors on IE8, props [pippercameron]
* Fix preview tab
* Compatibility with plupload 1.5.4, props [borkweb]

## [1.1] - 2012-09-04

* Backwards compatibility for 3.4
* Support for non-pretty permalinks
* Support for permalinks without trailing slashes
* Fix preview tab

## [1.0] - 2012-09-04

* Initial release


[1.10.0]: https://github.com/Automattic/liveblog/compare/1.9.7...1.10.0
[1.9.7]: https://github.com/Automattic/liveblog/compare/1.9.6...1.9.7
[1.9.6]: https://github.com/Automattic/liveblog/compare/1.9.5...1.9.6
[1.9.5]: https://github.com/Automattic/liveblog/compare/1.9.4...1.9.5
[1.9.4]: https://github.com/Automattic/liveblog/compare/1.9.3...1.9.4
[1.9.3]: https://github.com/Automattic/liveblog/compare/1.9.2...1.9.3
[1.9.2]: https://github.com/Automattic/liveblog/compare/1.9.1...1.9.2
[1.9.1]: https://github.com/Automattic/liveblog/compare/1.9...1.9.1
[1.9]: https://github.com/Automattic/liveblog/compare/1.8.2...1.9
[1.8.2]: https://github.com/Automattic/liveblog/compare/1.8.1...1.8.2
[1.8.1]: https://github.com/Automattic/liveblog/compare/1.8.0...1.8.1
[1.8]: https://github.com/Automattic/liveblog/compare/1.7.1...1.8.0
[1.7.1]: https://github.com/Automattic/liveblog/compare/1.7...1.7.1
[1.7]: https://github.com/Automattic/liveblog/compare/1.6.1...1.7
[1.6.1]: https://github.com/Automattic/liveblog/compare/1.6...1.6.1
[1.6]: https://github.com/Automattic/liveblog/compare/1.5...1.6
[1.5]: https://github.com/Automattic/liveblog/compare/1.4.1...1.5
[1.4.1]: https://github.com/Automattic/liveblog/compare/v1.4.0...1.4.1
[1.4]: https://github.com/Automattic/liveblog/compare/1.3.1...v1.4.0
[1.3.1]: https://github.com/Automattic/liveblog/compare/v1.3.0...1.3.1
[1.3]: https://github.com/Automattic/liveblog/compare/v1.2.0...v1.3.0
[1.2]: https://github.com/Automattic/liveblog/compare/v1.1.0...v1.2.0
[1.1]: https://github.com/Automattic/liveblog/compare/v1.0.0...v1.1.0
[1.0]: https://github.com/Automattic/liveblog/releases/tag/v1.0.0

[anigeluk]: https://github.com/anigeluk
[borkweb]: https://github.com/borkweb
[cain]: https://github.com/cain
[cfoellmann]: https://github.com/cfoellmann
[ChrisHardie]: https://github.com/ChrisHardie
[david-binda]: https://github.com/david-binda
[defries]: https://github.com/defries
[elarequi]: http://profiles.wordpress.org/elarequi
[GaryJones]: https://github.com/GaryJones
[gasparappa]: https://github.com/gasparappa
[iandunn]: https://github.com/iandunn
[jacklenox]: https://github.com/jacklenox
[jasonagnew]: https://github.com/jasonagnew
[jeffersonrabb]: https://github.com/jeffersonrabb
[joshbetz]: https://github.com/joshbetz
[jrmd]: https://github.com/jrmd
[justnorris]: https://github.com/justnorris
[jvpcode]: https://github.com/jvpcode
[kevinfodness]: https://github.com/kevinfodness
[kevinlisota]: https://github.com/kevinlisota
[liam-defty]: https://github.com/liam-defty
[lidocaine]: https://github.com/lidocaine
[lovestulip]: https://github.com/lovestulip
[maevelander]: https://github.com/maevelander
[markgoodyear]: https://github.com/markgoodyear
[maxhartshorn]: https://github.com/maxhartshorn
[mikeselander]: https://github.com/mikeselander
[mjangda]: https://github.com/mjangda
[mslinnea]: https://github.com/mslinnea
[nb]: https://github.com/nb
[no-sws]: https://github.com/no-sws
[paulschreiber]: https://github.com/paulschreiber
[philipjohn]: https://github.com/philipjohn
[pippercameron]: https://github.com/pippercameron
[pkevan]: https://github.com/pkevan
[psorensen]: https://github.com/psorensen
[rebeccahum]: https://github.com/rebeccahum
[rgllm]: https://github.com/rgllm
[rogertheriault]: https://github.com/rogertheriault
[sarahblackstock]: https://github.com/sarahblackstock
[sathyapulse]: https://github.com/sathyapulse
[sboisvert]: https://github.com/sboisvert
[scottblackburn]: https://github.com/scottblackburn
[scottsweb]: https://github.com/scottsweb
[tessaneedham]: https://github.com/tessaneedham
[tfrommen]: https://github.com/tfrommen
[tomjn]: https://github.com/tomjn
[travisw]: https://github.com/travisw
[zbtirrell]: https://github.com/zbtirrell
