=== Custom Quantity Selector for WooCommerce ===
Contributors: marketingparadise
Tags: woocommerce, quantity, step, minimum quantity
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Set a global custom quantity step and minimum quantity (with up to two decimal places) for WooCommerce products and the cart.

== Description ==

**Custom quantity selector for WooCommerce** lets store administrators define a global quantity increment (step) and a minimum purchase quantity for WooCommerce products, supporting both whole numbers and decimal values with up to two decimal places.

For example, setting a **quantity step** of `0.5` and a **minimum quantity** of `1` prevents customers from purchasing `0.5` units while allowing `1`, `1.5`, `2`, `8.5`, and so on.

= Key Features =

* Single native settings section under **WooCommerce > Settings > Products > Quantity**.
* Supports numbers greater than `0` with up to 2 decimal places for both step and minimum quantity.
* Enables decimal quantities in WooCommerce product pages, variable products, shop loop "Add to cart" buttons, and the classic cart.
* Full server-side validation on add-to-cart, cart updates, and checkout checks to prevent client-side HTML manipulation.
* Respects products marked as **Sold individually** and native stock limits.
* Compatible with WooCommerce High-Performance Order Storage (HPOS).
* Clean uninstallation: removes stored settings when deleted from WordPress.

= Compatibility Note =

Decimal quantities require the classic WooCommerce Cart and Checkout shortcodes (`[woocommerce_cart]` and `[woocommerce_checkout]`). Because the WooCommerce Store API used by Cart and Checkout Blocks currently enforces integer quantities, this plugin declares incompatibility with `cart_checkout_blocks`.

== Installation ==

1. Ensure WooCommerce is installed and active.
2. Upload the `custom-quantity-selector-for-woocommerce` folder to `/wp-content/plugins/`, or install the plugin ZIP via **Plugins > Add Plugin > Upload Plugin**.
3. Activate the plugin through the **Plugins** screen in WordPress.
4. Go to **WooCommerce > Settings > Products > Quantity** (or click the **Settings** link on the Plugins screen).
5. Enter your desired **Quantity step** and **Minimum quantity** values and click **Save changes**.

== Frequently Asked Questions ==

= Where can I find the plugin settings? =

Navigate to **WooCommerce > Settings > Products > Quantity**, or click the **Settings** link below the plugin name on the **Plugins** screen.

= How do decimal steps and minimums work together? =

Valid quantities start at the configured **Minimum quantity** and increase by multiples of the **Quantity step**. For example, with a minimum of `1` and a step of `0.5`, valid values are `1`, `1.5`, `2`, `2.5`, etc.

= Does this affect products marked as "Sold individually"? =

No. Products configured with WooCommerce's **Sold individually** option keep their native single-unit behavior and are excluded from global step and minimum rules.

= Does it work with WooCommerce Cart and Checkout Blocks? =

WooCommerce's Store API enforces whole integer quantities in Cart and Checkout Blocks. For decimal quantities, use the classic `[woocommerce_cart]` and `[woocommerce_checkout]` shortcodes.

= Does the plugin delete its data when uninstalled? =

Yes. Deleting the plugin from the WordPress dashboard removes the `cqsfw_settings` option from the database.

== Changelog ==

= 1.0.0 =
* Initial release.
* Added global Quantity step and Minimum quantity settings under WooCommerce > Settings > Products > Quantity.
* Enabled decimal quantity support (up to 2 decimal places) and server-side cart/checkout validation.