<?php
if (!defined('ABSPATH')) {
    exit;
}

function sku_generator_page_callback() {
    if (!isset($_GET['product_id'])) {
        wp_die('Product ID missing.');
    }

    $product_id = intval($_GET['product_id']);
    $product = wc_get_product($product_id);

    if (!$product || !$product->is_type('variable')) {
        wp_die('Invalid or non-variable product.');
    }

    // Handle Excel export
    if (isset($_POST['export_excel']) && check_admin_referer('export_skus')) {
        export_skus_to_excel($product);
        exit;
    }

    echo '<h1>SKU Combinations for Product: ' . esc_html($product->get_name()) . '</h1>';

    // Generate the list of attribute combinations
    $variations = [];
    $attributes = $product->get_attributes();

    // Prepare the attributes for combination
    $attribute_terms = [];
    $attribute_labels = [];
    $attribute_slugs = [];
    
    foreach ($attributes as $attribute_name => $attribute) {
        if (!$attribute->get_variation()) {
            continue;
        }

        $taxonomy = $attribute->get_taxonomy();
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'object_ids' => $product_id
        ]);

        if (!is_wp_error($terms) && !empty($terms)) {
            $attribute_terms[$taxonomy] = [];
            $attribute_slugs[$taxonomy] = [];
            
            foreach ($terms as $term) {
                $attribute_terms[$taxonomy][] = $term->name;
                $attribute_slugs[$taxonomy][] = sanitize_title($term->slug);
            }
            
            $tax_object = get_taxonomy($taxonomy);
            $attribute_labels[$taxonomy] = $tax_object ? $tax_object->labels->singular_name : wc_attribute_label($taxonomy);
        }
    }

    if (empty($attribute_terms)) {
        echo '<p>No attributes are set for variations. Please edit the product and check "Used for variations" for the attributes you want to generate SKUs for.</p>';
        return;
    }

    // Generate combinations
    $combinations = SKU_Generator::generate_combinations($attribute_terms);
    $slug_combinations = SKU_Generator::generate_combinations($attribute_slugs);

    // Add export form
    ?>
    <form method="post" style="margin: 20px 0;">
        <?php wp_nonce_field('export_skus'); ?>
        <input type="submit" name="export_excel" value="Export to Excel" class="button button-primary">
    </form>
    <?php

    // Display combinations table
    echo '<table border="1" cellpadding="8" style="border-collapse: collapse;">';
    echo '<tr><th>Attribute Combination</th><th>Generated SKU</th></tr>';
    
    foreach ($combinations as $index => $combination) {
        $readable_combination = [];
        $i = 0;
        foreach ($attribute_terms as $taxonomy => $terms) {
            $readable_combination[] = '<strong>' . $attribute_labels[$taxonomy] . '</strong>: ' . $combination[$i];
            $i++;
        }
        
        $sku = strtoupper($product->get_sku() . '-' . implode('-', $slug_combinations[$index]));
        
        echo '<tr><td>' . implode(' | ', $readable_combination) . '</td><td>' . esc_html($sku) . '</td></tr>';
    }
    echo '</table>';
}

function export_skus_to_excel($product) {
    if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        require_once SKU_GENERATOR_PATH . 'vendor/autoload.php';
    }

    try {
        // Create new spreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('SKU Combinations');

        // Style the header row
        $headerStyle = [
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => 'E0E0E0',
                ],
            ],
        ];

        // Set headers
        $sheet->setCellValue('A1', 'Attribute Combination');
        $sheet->setCellValue('B1', 'Generated SKU');
        $sheet->getStyle('A1:B1')->applyFromArray($headerStyle);

        // Get the data
        $attributes = $product->get_attributes();
        $attribute_terms = [];
        $attribute_labels = [];
        $attribute_slugs = [];
        
        foreach ($attributes as $attribute_name => $attribute) {
            if (!$attribute->get_variation()) {
                continue;
            }

            $taxonomy = $attribute->get_taxonomy();
            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
                'object_ids' => $product->get_id()
            ]);

            if (!is_wp_error($terms) && !empty($terms)) {
                $attribute_terms[$taxonomy] = [];
                $attribute_slugs[$taxonomy] = [];
                
                foreach ($terms as $term) {
                    $attribute_terms[$taxonomy][] = $term->name;
                    $attribute_slugs[$taxonomy][] = sanitize_title($term->slug);
                }
                
                $tax_object = get_taxonomy($taxonomy);
                $attribute_labels[$taxonomy] = $tax_object ? $tax_object->labels->singular_name : wc_attribute_label($taxonomy);
            }
        }

        $combinations = SKU_Generator::generate_combinations($attribute_terms);
        $slug_combinations = SKU_Generator::generate_combinations($attribute_slugs);

        // Add data
        $row = 2;
        foreach ($combinations as $index => $combination) {
            $readable_combination = [];
            $i = 0;
            foreach ($attribute_terms as $taxonomy => $terms) {
                $readable_combination[] = $attribute_labels[$taxonomy] . ': ' . $combination[$i];
                $i++;
            }
            
            $sku = strtoupper($product->get_sku() . '-' . implode('-', $slug_combinations[$index]));
            
            $sheet->setCellValue('A' . $row, implode(' | ', $readable_combination));
            $sheet->setCellValue('B' . $row, $sku);
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'B') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Add borders to all cells
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle('A1:B' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(
            \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
        );

        // Create the writer
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        // Clean output buffer
        if (ob_get_length()) {
            ob_end_clean();
        }

        // Set headers for download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($product->get_name() . '-SKUs.xlsx') . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        // Save file to browser
        $writer->save('php://output');
        exit;
    } catch (\Exception $e) {
        wp_die('Error generating Excel file: ' . $e->getMessage());
    }
}

// Helper function to generate combinations
function sku_generator_generate_combinations($arrays) {
    $result = [[]];
    foreach ($arrays as $property => $values) {
        $temp = [];
        foreach ($result as $result_item) {
            foreach ($values as $value) {
                $temp[] = array_merge($result_item, [$value]);
            }
        }
        $result = $temp;
    }
    return $result;
} 