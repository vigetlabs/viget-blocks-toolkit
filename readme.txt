=== Viget Blocks Toolkit ===
Contributors: viget, briandichiara, nathanschmidt
Tags: blocks,icons,components,editor,acf
Requires at least: 5.7
Tested up to: 6.9
Stable tag: 1.1.7
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Simplifying Block Registration and other block editor related features.

== Description ==

Enhancements to the block editor as well as simplifying custom block registration with Advanced Custom Fields Pro.

See full features and documentation in the [GitHub README file](https://github.com/vigetlabs/viget-blocks-toolkit).

== Frequently Asked Questions ==

= Is Advanced Custom Fields Pro required? =

No. There are several features of this plugin that can be used without Advanced Custom Fields Pro. However, to utilize the block registration feature, Advanced Custom Fields Pro is required at this time.

== Screenshots ==

1. Block Icons
2. Breakpoint Visibility Settings
3. Media Position Example

== Changelog ==

= 1.1.7 =

* Fixed `innerContainer` being ignored when declared under `supports` in block.json, which dropped the container div from blocks that had explicitly opted in.
* Added the `vgtbt_default_inner_container` filter to set the default globally for blocks that don't declare a value.
* Changed the unique html `id` attribute on blocks to be opt-in. Blocks no longer render an `id` unless they declare `supports.id` in block.json, an editor-set anchor is present, or an id is passed to `block_attrs()`. Added the `vgtbt_default_block_id` and `vgtbt_block_has_id` filters to control this globally or per block.
* Fixed `update-pot` excluding the `src` directory, which holds the plugin's PHP classes. 23 translatable strings were missing from the POT file, including everything in `includes/updater.php`.
* Updated build dependencies to resolve reported security advisories (86 to 50, all critical severity cleared). Build tooling only; no bundled runtime code changed.
* Regenerated `languages/viget-blocks-toolkit.pot`, which had been stale since 1.1.0.
* Rebuilt `build/` assets, which were stale relative to `src/editor.scss`.

= 1.1.6 =

* Added `blockPattern` support for ACF blocks with registered-pattern and local `patterns/` resolution.
* Added editor seeding from pattern markup (preserving real inner block content) and default lock propagation via `lock` attributes.
* Added `templateLock` passthrough support for ACF InnerBlocks usage.
* Added toolbar fallbacks for Icon and Responsive controls in content-only editing contexts.
* Added GitHub plugin updater.

= 1.1.5 =

* Added Block Icon support for `core/navigation-submenu` block.
* Added (bool) `hasInnerBlocks` to `$block` array.
* Added `vgtbt_block_data` filter on `$block` array.
* Fixed a race condition preventing using some filters.
* Fixed a bug that cascaded icons too deep while in the editor.

= 1.1.4 =

* Changes Responsive display styles to use `inherit` instead of `block`.

= 1.1.3 =

* Adds FAQPage Schema support to Core Accordion blocks.

= 1.1.2 =

* Fixes a bug when ACF passes stdClass into renderCallback.
* Adds Navigation Slug reference to core Navigation block.

= 1.1.1 =

* Bumps Tested up to version to 6.8.
* Fixes a bug where the `block.json` `tagName` was not supported correctly.
* Fixes a bug when the core function `wp_style_engine_get_styles` does not return the `css` array key index.

= 1.1.0 =

* Initial Release
