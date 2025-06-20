# SKU Generator for WooCommerce Variations

A simple but powerful plugin to generate a reference table and Excel export of all possible SKU combinations for a WooCommerce variable product.

## Features

-   Generates a complete list of all possible product variation combinations.
-   Correctly uses explicit SKUs set on specific variations (e.g., a variation for "Size") as a base for generating further combinations.
-   Allows setting specific SKU "suffixes" on attribute terms for fine-grained control over the final SKU parts.
-   Handles special formatting rules, such as replacing hyphens with periods for specific attribute pairings.
-   Provides a clean, on-screen table of the generated combinations.
-   Exports the complete list to a cleanly formatted Excel (`.xlsx`) file.
-   Output table and Excel file dynamically generate columns for the Product Name and each attribute for easy reading and filtering.

## How to Use

1.  Navigate to the edit screen for a **Variable Product**.
2.  In the right-hand sidebar, you will see a new meta box titled "SKU Generator".
3.  Click the "Generate SKU Combinations" button.
4.  A new tab will open displaying a table of all possible SKU combinations.
5.  From this new page, you can use the "Export to Excel" button to download the list.

### Adding SKU Suffixes

To control the parts of the generated SKU, you can add a suffix to any attribute term.

1.  Go to **Products > Attributes**.
2.  Select an attribute (e.g., "Laminate Color") and click "Configure terms".
3.  Edit an existing term (e.g., "Chalk White").
4.  You will see a "Term Suffix" field. Enter the value you want to be appended to the SKU for this term (e.g., "CW").
5.  Click "Update".

Now, when you generate SKUs, the plugin will use "CW" in the generated SKU string whenever the "Chalk White" term is used.

## Description

This plugin adds a SKU Generator tool to variable products in WooCommerce. It helps store managers:
- Generate consistent SKUs for all possible product variations using the attribute term's slug
- Export variation SKUs to Excel for inventory management

## Requirements

- WordPress 5.0 or higher
- WooCommerce 7.0 or higher
- PHP 8.1 or higher
- PHP extensions: zip, fileinfo

## Installation

1. Download the plugin zip file
2. Go to WordPress Admin > Plugins > Add New
3. Click "Upload Plugin" and select the downloaded zip file
4. Click "Install Now" and then "Activate"

## Support

If you encounter any issues or have questions, please create an issue in the GitHub repository.

## License

GPL v2 or later
