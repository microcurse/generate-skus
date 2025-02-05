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
    }

    public function add_meta_box() {
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
            <a href="<?php echo admin_url('admin.php?page=generate_sku_list&product_id=' . $post->ID); ?>" 
               class="button button-primary" 
               target="_blank"
               style="width: 100%; text-align: center; margin-top: 5px;">
                Generate SKU Combinations
            </a>
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
} 