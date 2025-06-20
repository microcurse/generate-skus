<?php
if (!defined('ABSPATH')) {
    exit;
}

class SKU_Generator {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('admin_init', array($this, 'handle_sku_generation_request'));
    }

    /**
     * Handle the SKU generation request from the product meta box.
     */
    public function handle_sku_generation_request() {
        if (
            isset($_POST['generate_sku_list_action']) &&
            isset($_POST['product_id']) &&
            check_admin_referer('generate_sku_list_nonce')
        ) {
            $this->render_sku_page();
            exit;
        }

        if (isset($_POST['export_excel']) && check_admin_referer('export_skus')) {
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            if ($product_id) {
                $product = wc_get_product($product_id);
                if ($product) {
                    $this->export_skus_to_excel($product);
                    exit;
                }
            }
        }
    }

    /**
     * Match a variation to a given combination of attributes.
     * This method is public so it can be accessed from the template file.
     *
     * @param array $variation
     * @param array $combination
     * @param array $attribute_terms
     * @return boolean
     */
    public function match_variation_to_combination_public($variation, $combination, $attribute_terms) {
        foreach ((array) $variation['attributes'] as $taxonomy => $variation_value_slug) {
            $term = get_term_by('slug', (string) $variation_value_slug, $taxonomy);
            if (!$term) return false;
    
            $taxonomy_keys = array_keys($attribute_terms);
            $term_index = array_search($taxonomy, $taxonomy_keys);
            if ($term_index === false) return false;
    
            if (sanitize_title((string) $combination[$term_index]) !== (string) $variation_value_slug) {
                return false;
            }
        }
        return true;
    }

    /**
     * Render the SKU combinations page.
     */
    public function render_sku_page() {
        if (!isset($_POST['product_id'])) {
            wp_die('Product ID missing.');
        }
    
        $product_id = intval($_POST['product_id']);
        $product = wc_get_product($product_id);
    
        if (!$product || !$product->is_type('variable')) {
            wp_die('Invalid or non-variable product.');
        }
    
        // Get all variations first
        $variations = $product->get_children();
        $variation_data = [];
        foreach ($variations as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation || !$variation instanceof WC_Product_Variation) continue;
            
            $variation_data[] = [
                'id' => $variation_id,
                'sku' => $variation->get_sku(),
                'attributes' => $variation->get_variation_attributes()
            ];
        }
    
        // Get all attributes that have suffixes
        $attributes = $product->get_attributes();
        $attribute_terms = [];
        $attribute_slugs = [];
        $attribute_labels = [];
        $attribute_suffixes = [];
        
        foreach ($attributes as $attribute) {
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
                $current_terms = [];
                $current_slugs = [];
                $current_suffixes = [];
                foreach ($terms as $term) {
                    $current_terms[] = $term->name;
                    $current_slugs[] = $term->slug;
                    $suffix = get_term_meta($term->term_id, '_term_suffix', true);
                    $current_suffixes[] = $suffix ?: ''; // Use empty string if no suffix
                }
                
                $attribute_terms[$taxonomy] = $current_terms;
                $attribute_slugs[$taxonomy] = $current_slugs;
                $attribute_suffixes[$taxonomy] = $current_suffixes;
                $tax_object = get_taxonomy($taxonomy);
                $attribute_labels[$taxonomy] = $tax_object ? $tax_object->labels->singular_name : wc_attribute_label($taxonomy);
            }
        }
    
        if (empty($attribute_terms)) {
            wp_die('This product has no attributes configured for variations.');
        }
    
        // Prepare headers for the table
        $headers = ['Product Name'];
        foreach ($attribute_labels as $label) {
            $headers[] = $label;
        }
        $headers[] = 'Generated SKU';
    
        $combinations = self::generate_combinations($attribute_terms);
        $slug_combinations = self::generate_combinations($attribute_slugs);
        $suffix_combinations = self::generate_combinations($attribute_suffixes);
        $taxonomies = array_keys($attribute_terms);
    
        $template_data = [];
        foreach ($combinations as $index => $combination) {
            $current_row = [];
            $current_row['Product Name'] = $product->get_name();

            foreach ($combination as $term_name) {
                $current_row[] = $term_name;
            }

            $best_match = $this->find_best_variation_match($slug_combinations[$index], $variation_data, $attribute_slugs);

            $base_sku = (string) $product->get_sku();
            $suffixes_for_sku = $suffix_combinations[$index];
            $taxonomies_for_sku = array_keys($attribute_terms);

            if ($best_match) {
                $base_sku = $best_match['sku'];
                foreach ($taxonomies_for_sku as $i => $taxonomy) {
                    $prefixed_taxonomy = 'attribute_' . $taxonomy;
                    if (isset($best_match['attributes'][$prefixed_taxonomy]) && !empty($best_match['attributes'][$prefixed_taxonomy])) {
                        $suffixes_for_sku[$i] = null;
                    }
                }
            }

            $final_suffixes = array_filter($suffixes_for_sku, function($v) { return !empty($v); });
            $sku_part = implode('-', $final_suffixes);
            
            if (!empty($final_suffixes)) {
                $sku_part = $this->apply_laminate_sku_fix($sku_part, $suffix_combinations[$index], $taxonomies_for_sku);
            }

            $sku = empty($sku_part) ? strtoupper($base_sku) : strtoupper($base_sku . '-' . $sku_part);

            $current_row['Generated SKU'] = $sku;
            $template_data[] = $current_row;
        }
    
        // Render the page
        require_once SKU_GENERATOR_PATH . 'admin/sku-generator-page.php';
    }

    /**
     * Export SKU combinations to an Excel file.
     */
    public function export_skus_to_excel($product) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            require_once SKU_GENERATOR_PATH . 'vendor/autoload.php';
        }
    
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('SKU Combinations');
    
            $headerStyle = [
                'font' => ['bold' => true],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0E0E0']],
            ];
    
            $sheet->setCellValue('A1', 'Product Name');
            $sheet->setCellValue('B1', 'Generated SKU');
            $sheet->getStyle('A1:B1')->applyFromArray($headerStyle);
    
            // Data retrieval logic...
            $variations = $product->get_children();
            $variation_data = [];
            foreach ($variations as $variation_id) {
                $variation = wc_get_product($variation_id);
                if (!$variation || !$variation instanceof WC_Product_Variation) continue;
                $variation_data[] = ['id' => $variation_id, 'sku' => $variation->get_sku(), 'attributes' => $variation->get_variation_attributes()];
            }
    
            $attributes = $product->get_attributes();
            $attribute_terms = [];
            $attribute_slugs = [];
            $attribute_labels = [];
            $attribute_suffixes = [];
    
            foreach ($attributes as $attribute) {
                if (!$attribute->get_variation()) {
                    continue;
                }
    
                $taxonomy = $attribute->get_taxonomy();
                $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'object_ids' => $product->get_id()]);
    
                if (!is_wp_error($terms) && !empty($terms)) {
                    $current_terms = [];
                    $current_slugs = [];
                    $current_suffixes = [];
                    foreach ($terms as $term) {
                        $current_terms[] = $term->name;
                        $current_slugs[] = $term->slug;
                        $suffix = get_term_meta($term->term_id, '_term_suffix', true);
                        $current_suffixes[] = $suffix ?: ''; // Use empty string if no suffix
                    }
    
                    $attribute_terms[$taxonomy] = $current_terms;
                    $attribute_slugs[$taxonomy] = $current_slugs;
                    $attribute_suffixes[$taxonomy] = $current_suffixes;
                    $tax_object = get_taxonomy($taxonomy);
                    $attribute_labels[$taxonomy] = $tax_object ? $tax_object->labels->singular_name : wc_attribute_label($taxonomy);
                }
            }
    
            if (empty($attribute_terms)) {
                // This case should ideally not be hit if called from a valid product page, but as a safeguard:
                wp_die('This product has no attributes configured for variations.');
            }

            // Prepare headers for the Excel file
            $headers = ['Product Name'];
            foreach ($attribute_labels as $label) {
                $headers[] = $label;
            }
            $headers[] = 'Generated SKU';

            // Write headers to sheet and apply style
            $sheet->fromArray($headers, null, 'A1');
            $last_column = $sheet->getHighestColumn();
            $sheet->getStyle('A1:' . $last_column . '1')->applyFromArray($headerStyle);

            $combinations = self::generate_combinations($attribute_terms);
            $slug_combinations = self::generate_combinations($attribute_slugs);
            $suffix_combinations = self::generate_combinations($attribute_suffixes);
            $taxonomies = array_keys($attribute_terms);
    
            $row_num = 2;
            foreach ($combinations as $index => $combination) {
                $current_row = [];
                $current_row['Product Name'] = $product->get_name();

                foreach ($combination as $term_name) {
                    $current_row[] = $term_name;
                }
    
                $best_match = $this->find_best_variation_match($slug_combinations[$index], $variation_data, $attribute_slugs);

                $base_sku = (string) $product->get_sku();
                $suffixes_for_sku = $suffix_combinations[$index];
                $taxonomies_for_sku = array_keys($attribute_terms);

                if ($best_match) {
                    $base_sku = $best_match['sku'];
                    foreach ($taxonomies_for_sku as $i => $taxonomy) {
                        $prefixed_taxonomy = 'attribute_' . $taxonomy;
                        if (isset($best_match['attributes'][$prefixed_taxonomy]) && !empty($best_match['attributes'][$prefixed_taxonomy])) {
                            $suffixes_for_sku[$i] = null;
                        }
                    }
                }

                $final_suffixes = array_filter($suffixes_for_sku, function ($v) {
                    return !empty($v);
                });
                $sku_part = implode('-', $final_suffixes);

                if (!empty($final_suffixes)) {
                    $sku_part = $this->apply_laminate_sku_fix($sku_part, $suffix_combinations[$index], $taxonomies_for_sku);
                }

                $sku = empty($sku_part) ? strtoupper($base_sku) : strtoupper($base_sku . '-' . $sku_part);
                
                $current_row['Generated SKU'] = $sku;
                
                // Sanitize data for Excel
                $sanitized_row = array_map(function($value) {
                    return filter_var($value, FILTER_SANITIZE_STRING);
                }, array_values($current_row));

                $sheet->fromArray($sanitized_row, null, 'A' . $row_num);
                $row_num++;
            }
    
            // Auto size all columns
            foreach(range('A', $last_column) as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }
    
            $filename = 'sku-combinations-' . sanitize_title($product->get_name()) . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
    
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            die();
        } catch (Exception $e) {
            wp_die('Error generating Excel file: ' . esc_html($e->getMessage()));
        }
    }

    public function add_meta_box($post) {
        add_meta_box(
            'sku_generator_meta_box',           // ID
            'SKU Generator',                    // Title
            array($this, 'render_meta_box'),    // Callback
            'product',                          // Screen (product post type)
            'side',                             // Context (side panel)
            'default'                           // Priority
        );
    }

    public function render_meta_box($post) {
        $product = wc_get_product($post->ID);
        if (!$product || !$product->is_type('variable')) {
            echo '<p>SKU Generator is only available for variable products.</p>';
            return;
        }
        ?>
        <div class="sku-generator-box">
            <p>Generate a reference table of all possible SKU combinations based on the product's attributes used as variation. This applies the slug to the SKU.</p>
            <form id="sku_generator_form" method="post" action="" target="_blank">
                <?php wp_nonce_field('generate_sku_list_nonce'); ?>
                <input type="hidden" name="generate_sku_list_action" value="1" />
                <input type="hidden" name="product_id" value="<?php echo esc_attr($post->ID); ?>" />
                <button type="submit" class="button button-primary" style="width: 100%; text-align: center; margin-top: 5px;">
                    Generate SKU Combinations
                </button>
            </form>
            <script>
                document.getElementById('sku_generator_form').addEventListener('submit', function(e) {
                    this.target = '_blank';
                });
            </script>
        </div>
        <?php
    }

    public static function generate_combinations($arrays) {
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

    /**
     * Applies a specific SKU format for double-sided laminate products.
     * Replaces the hyphen with a period between laminate color and side B attributes.
     *
     * @param string $sku_part_string The imploded string of SKU suffixes.
     * @param array  $current_suffixes The array of suffixes for the current combination.
     * @param array  $taxonomies An ordered array of the attribute taxonomies.
     * @return string The potentially modified SKU part string.
     */
    private function apply_laminate_sku_fix($sku_part_string, $current_suffixes, $taxonomies) {
        $laminate_color_taxonomy = 'pa_laminate-color';
        $laminate_side_b_taxonomy = 'pa_laminate-side-b';

        $laminate_color_index = array_search($laminate_color_taxonomy, $taxonomies);
        $laminate_side_b_index = array_search($laminate_side_b_taxonomy, $taxonomies);

        if ($laminate_color_index !== false && $laminate_side_b_index !== false) {
            $laminate_color_suffix = $current_suffixes[$laminate_color_index];
            $laminate_side_b_suffix = $current_suffixes[$laminate_side_b_index];

            // The order of attributes can be changed, so we must check which comes first.
            $pattern = $laminate_color_suffix . '-' . $laminate_side_b_suffix;
            $replacement = $laminate_color_suffix . '.' . $laminate_side_b_suffix;

            // If side_b comes before color in the attribute ordering.
            if ($laminate_color_index > $laminate_side_b_index) {
                $pattern = $laminate_side_b_suffix . '-' . $laminate_color_suffix;
                $replacement = $laminate_side_b_suffix . '.' . $laminate_color_suffix;
            }

            return str_replace($pattern, $replacement, $sku_part_string);
        }

        return $sku_part_string;
    }

    /**
     * Finds the most specific variation that matches a given combination.
     *
     * @param array $combination The generated combination of attribute term names.
     * @param array $variation_data An array of all variation data for the product.
     * @param array $attribute_terms The product's attributes used for variations.
     * @return array|null The best matching variation data, or null if no match.
     */
    private function find_best_variation_match($combination, $variation_data, $attribute_terms) {
        $best_match = null;
        $max_specificity = -1;

        foreach ($variation_data as $variation) {
            if (empty($variation['sku'])) {
                continue; // We only care about variations with an explicit SKU.
            }

            $specificity = 0;
            $is_match = true;

            foreach ($variation['attributes'] as $prefixed_taxonomy => $value_slug) {
                if (empty($value_slug)) { // This is an "Any..." attribute.
                    continue;
                }

                $taxonomy = str_replace('attribute_', '', $prefixed_taxonomy);

                $taxonomies = array_keys($attribute_terms);
                $term_index = array_search($taxonomy, $taxonomies);

                if ($term_index === false || $combination[$term_index] !== $value_slug) {
                    $is_match = false;
                    break;
                }
                $specificity++;
            }

            if ($is_match && $specificity > $max_specificity) {
                $max_specificity = $specificity;
                $best_match = $variation;
            }
        }

        return $best_match;
    }
} 