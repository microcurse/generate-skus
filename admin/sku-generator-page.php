<?php
if (!defined('ABSPATH')) {
    exit;
}

// Note: This template is loaded by SKU_Generator::render_sku_page()
// It expects the following variables to be in scope:
// $product, $headers, $template_data
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SKU Combinations for <?php echo esc_html($product->get_name()); ?></title>
    <?php wp_print_styles('wp-admin'); ?>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
        .wp-list-table { border: 1px solid #c3c4c7; }
    </style>
</head>
<body class="wp-admin wp-core-ui">
    <div id="wpcontent" style="padding: 20px;">
        <h1>SKU Combinations for <?php echo esc_html($product->get_name()); ?></h1>

        <form method="post" style="margin: 20px 0;">
            <?php wp_nonce_field('export_skus'); ?>
            <input type="hidden" name="product_id" value="<?php echo esc_attr($product->get_id()); ?>">
            <input type="submit" name="export_excel" value="Export to Excel" class="button button-primary">
        </form>

        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <?php foreach ($headers as $header) : ?>
                        <th scope="col"><?php echo esc_html($header); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($template_data as $row) : ?>
                    <tr>
                        <?php foreach ($row as $cell) : ?>
                            <td><?php echo esc_html($cell); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html> 