# Viget Blocks Toolkit

This toolkit was made to simplify the process of registering custom blocks with ACF Pro. It also adds several additional features to the block editor such as Block Icons and Breakpoint Visibility.

## Creating Custom Blocks in your Theme

To create a block in your theme, simply create a `blocks` folder in the root of your theme directory. Each block should have its own folder and a `block.json` file. The `block.json` file should contain the [block configuration](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/). You can then use a `render.php` file (or `render.twig` file if Timber/Twig is supported) to render the block. By default, the blocks that support `jsx` will automatically render using the plugins `jsx.php` file.

### Customizations

#### `block.json`

##### tagName

Useful when using the built-in render file for `jsx` supported blocks, `tagName` can be set in `block.json` to specify the outer tag for the block. (By default it uses `section`).

##### innerContainer
You can disable the inner container wrapper by setting the value of `innerContainer` in `supports` to `false`.

##### blockId

Assign each block a unique, persistent `blockId` simply by adding the attribute to the `block.json` file.

##### mediaPosition

Adding the `mediaPosition` attribute will enable the Media Position toggle buttons in the block toolbar and apply transforms based on the `mediaPosition: transformations` array in the `supports` object. The transformations rules will apply:

* Root level transformations will apply to all child blocks.
* `reverse` will reverse the order of the child blocks.
* `attributes` will modify the block's attributes.
  * Each value will be determined based on the value of `mediaPosition`. See example below. 
* Nested `innerBlocks` will apply transformations only to child blocks when present within the parent block.

In the following example scenario:
* All columns will be reversed.
* All buttons will have their layout justified based on the `mediaPosition` value. (`mediaPosition` is on the left, the attribute value is on the right)
* Any images within a column block will have their alignment switched based on the `mediaPosition` value.

```json
{
  "supports": {
    "mediaPosition": {
      "transformations": [
        {
          "core/columns": {
            "reverse": true
          }
        },
        {
          "core/buttons": {
            "attributes": {
              "layout": {
                "justifyContent": {
                  "left": "right",
                  "right": "left"
                }
              }
            }
          }
        },
        {
          "core/column": {
            "innerBlocks": [
              {
                "core/image": {
                  "attributes": {
                    "align": {
                      "left": "right",
                      "right": "left"
                    }
                  }
                }
              }
            ]
          }
        }
      ]
    }
  }
}
```

This is an example of a `block.json` file with all the supported customizations.

```json
{
  "tagName": "article",
  "attributes": {
    "blockId": {
      "type": "string",
      "default": ""
    },
    "mediaPosition": {
      "type": "string",
      "default": "left"
    }
  },
  "supports": {
    "innerContainer": false,
    "mediaPosition": {
      "transformations": [
        {
          "core/columns": {
            "reverse": true
          }
        }
      ]
    }
  }
}
```

#### `template.json`

If there is a `template.json` file present, the contents of `template` will be used as the `innerBlocks` template. Here's an example that will start with a heading and paragraph block:

```json
{
  "template": [
      [ "core/heading" ],
      [ "core/paragraph" ]
    ]
}
```

#### `block.php`

If there a `block.php` file present, it will automatically be loaded during block registration.

#### `render.php`

There are several variables available in the `render.php` file:

```php
/**
 * @global array     $block      The block data.
 * @global array     $context    The block context data.
 * @global bool      $is_preview Whether the block is being previewed.
 * @global int       $post_id    The current post ID. (Be careful, you may want to use $context['postId'] when in a loop)
 * @global \WP_Block $wp_block   The WP_Block object.
 */
```

The `$block` variable also has some additional values:

```php
$block['url'] // The URL to the block folder.
$block['path'] // The path to the block folder.
$block['template'] // The template.json file contents.
$block['slug'] // The slug of the block, without the `acf/` prefix.
```

## Breakpoint Visibility

This block settings panel is available on any supported block in the Full Site Editor and in post block editor. It allows you to set visibility for each block at different breakpoints, so blocks can be hidden or shown based on the screen size. There is a setting to also specify a breakpoint and whether the block should be hidden or shown at that breakpoint.

## Main Helper Functions

### `vgtbt()`

Returns an instance of the Viget Blocks Toolkit `Core` class.

### `block_attrs()`

Outputs the block attributes as a string. Supported arguments:

* `$block` array - The block data.
* `$custom_class` string - Additional classes to add to the block. 
* `$attrs` array - Additional attributes to add to the block.

### `inner_blocks()`

Outputs the `<innerBlocks />` element of a block. Supported arguments:

* `$props` array - The properties of `innerBlocks`
  * `template` array - The template for the inner blocks. This value is automatically `json_encode`d.
  * `templateLock` string - The lock value for the inner blocks.
  * `allowedBlocks` array - The allowed blocks for the inner blocks. This value is automatically `json_encode`d.

## Hooks

### `vgtbt_block_locations` (Filter)

Filter the block locations. This allows you to add or change where custom blocks can be found.

```php
<?php
add_filter(
	'vgtbt_block_locations',
	function ( array $locations ): array {
		$locations[] = get_template_directory() . '/other-blocks';
		return $locations;
	}
);
```

### `vgtbt_button_icons` (Filter)

Filter the button icons.

```php
<?php
add_filter(
	'vgtbt_button_icons',
	function ( array $icons ): array {
		$icons['my-custom-icon'] = [ // The key is the unique icon slug.
			'label'       => __( 'My Custom Icon', 'my-text-domain' ),
			'icon'        => '<svg ... ></svg>',
			'defaultLeft' => false, // Optional, defaults icon to align left.
		];
		
		return $icons;
	}
);
```

### `vgtbt_supported_icon_blocks` (Filter)

Filter the supported icon blocks. Note: the frontend and editor CSS may need to be manually added for additional blocks.

```php
<?php
add_filter(
	'vgtbt_supported_icon_blocks',
	function ( array $blocks ): array {
		$blocks[] = 'core/heading';
		return $blocks;
	}
);
```

### `vgtbt_button_icons_editor_css` (Filter)

Filter the editor CSS for the button icons. This is useful when some icons do not use outline fill the fill property causes issues. Or can also be used to specify icon dimensions using `max-height`.

```php
add_filter(
	'vgtbt_button_icons_editor_css',
	function ( string $css ): string {
		return $css . '.components-button.button-icon-picker__icon-my-custom-icon svg { fill:none; }';
	}
);
```

### `vgtbt_unregister_block_styles` (Filter)

Unregister block styles from core blocks.

```php
add_filter(
	'vgtbt_unregister_block_styles',
	function ( array $styles ): array {
		$styles[] = [
			'core/separator',
			'dots',
		];

		return $styles;
	}
);

```
### `vgtbt_unregister_block_variations` (Filter)

Unregister block variations from core blocks.

```php
add_filter(
	'vgtbt_unregister_block_variations',
	function ( array $variations ): array {
		$variations[] = [
			'core/social-link',
			'bluesky',
		];
		return $variations;
	}
);
```
